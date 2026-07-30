# Arquitectura

## Principio de partida

El proyecto replica el patrón del sistema de inventario que ya mantiene el mismo equipo.
No es capricho: significa que quien conoce uno conoce el otro, y que el núcleo que aquí
se usa ya lleva tiempo funcionando en producción.

De ahí vienen, sin cambios de fondo:

- PHP orientado a objetos sin framework, con `require_once` explícito y sin autoload.
- Front controller único con enrutado por query string.
- PDO en singleton, sentencias preparadas con marcadores nombrados y
  `ATTR_EMULATE_PREPARES` desactivado.
- Clase `Model` base con paginación y transacciones anidadas por contador.
- Sesiones con cookie `httponly` y `samesite=Strict`, regeneración de identificador al
  entrar, mensajes flash y token CSRF.
- Permisos por rol con notación `modulo.accion` y comodín `*`.
- Subida de imágenes con validación de MIME real mediante `finfo`.
- Bootstrap 5.3.3 por CDN, sin jQuery y sin DataTables.
- Todo nombrado en español; borrado lógico; SQL InnoDB con `utf8mb4`.

## Lo que sí cambia, y por qué

El sistema de inventario es un panel interno: todos sus visitantes están autenticados.
Este proyecto tiene dos caras. Tres piezas del núcleo original no se pueden copiar tal
cual.

### 1. El guard de autenticación baja al área de administración

En el original, el router redirige a la pantalla de login a cualquiera que llegue sin
sesión. Copiado tal cual, el sitio público no existiría: un visitante que entra a leer
los horarios de misa terminaría en un formulario de contraseña.

El guard se aplica ahora solo cuando el área solicitada es la de administración.

### 2. El sitio público se puede cachear

`Controller::render()` emite `Cache-Control: no-store` en cada respuesta. Esas cabeceras
tienen sentido para formularios del panel —evitan que el navegador muestre datos viejos
al volver atrás—, pero en el sitio público desperdician ancho de banda y tiempo de carga.

`ControllerPublico` las sustituye por `Cache-Control: public, max-age=300` cuando el
visitante es anónimo y no hay ningún mensaje flash pendiente.

### 3. La sesión arranca solo cuando hace falta

El original abre sesión en cada petición. Aquí eso pondría una cookie `PHPSESSID` a todo
visitante anónimo, lo que además de innecesario anula cualquier caché intermedia.

`Session::iniciarSiNecesario()` arranca la sesión únicamente si se cumple alguna de estas
condiciones:

- ya existe la cookie de sesión;
- el área solicitada es la de administración;
- la petición es un POST;
- el controlador público declara `$requiereSesion = true`, que es el caso de las páginas
  con formulario, porque necesitan token CSRF.

Quien solo lee avisos no recibe cookie.

## Enrutado

### El parámetro `area`

`Router` mantiene dos tablas estáticas de rutas y elige según `$_GET['area']`, con una
whitelist de dos valores y `publico` por defecto.

```
área pública   inicio · nosotros · horarios · sacramentos · pastorales · cursos
               avisos · eventos · contacto · pagina · sitemap

área admin     auth · panel · configuracion · bloques · paginas · personas · centros
               organigrama · horarios · sacramentos · pastorales
               cursos · inscripciones · avisos · eventos · galeria · carrusel
               mensajes · usuarios · auditoria · respaldos
```

Flujo de `dispatch()`:

1. Leer y validar `area`; si no es `publico` ni `admin`, se trata como `publico`.
2. Elegir la tabla correspondiente. Módulo por defecto: `inicio` en público, `panel` en
   administración.
3. Aplicar el guard de sesión **solo** si el área es `admin` y el módulo no es `auth`.
4. Convertir la acción a método con la misma regla de siempre: `crear_entrada` pasa a
   `crearEntrada`.
5. Si llega un `slug` pero no una acción, la acción es `ver`.

**Por qué un parámetro y no prefijar los nombres de módulo.** Con `area`, el módulo
`avisos` puede existir en los dos mundos con controladores distintos —`AvisoController`
para el panel, `AvisoPublicoController` para el sitio— y **un solo modelo compartido**.
La alternativa, nombrarlos `admin_avisos` y `avisos`, ensucia la tabla de rutas y obliga a
duplicar convenciones. El costo es un parámetro más en cada URL interna, y lo absorben los
helpers `url_publica()` y `url_admin()`; ningún código debe construir URLs a mano.

### URLs amigables

`.htaccess` traduce las rutas legibles a la forma canónica:

```
/                                → area=publico&modulo=inicio
/quienes-somos                   → area=publico&modulo=nosotros
/pastorales                      → area=publico&modulo=pastorales
/pastorales/coro                 → area=publico&modulo=pastorales&slug=coro&accion=ver
/avisos?pagina=2                 → area=publico&modulo=avisos
/admin/inscripciones/ver?id=12   → area=admin&modulo=inscripciones&accion=ver
```

Orden de las reglas: primero dejar pasar los archivos y directorios que existen (assets y
uploads), luego el prefijo `/admin/`, luego `sitemap.xml`, y al final la regla genérica de
hasta tres segmentos. Una tabla de alias en el Router mapea la URL legible al módulo
interno cuando no coinciden, como `quienes-somos` a `nosotros`.

Dos reglas de uso:

- **Los formularios POST siempre apuntan a la URL canónica con query string**, nunca a la
  legible. Se construye con `url_post()`. Evita ambigüedades de reescritura y problemas al
  volver con `redirectBack()`.
- **La constante `URLS_AMIGABLES` en `config/app.php` es el interruptor.** Si el hosting
  no coopera con `mod_rewrite`, se pone en `false` y los helpers emiten query strings. Sin
  esa indirección desde el primer día, migrar significaría reescribir cada vista.

Las direcciones se construyen siempre con los helpers de `core/helpers.php`:
`url_publica()`, `url_admin()`, `url_post()` y `url_activo()` para los assets. Ninguna
vista escribe una URL a mano; es lo que hace que el interruptor anterior baste.

### `APP_URL` se deduce sola

El sitio vive en `/WebParroquia` durante el desarrollo y en la raíz del dominio en
producción. Olvidar ese cambio al desplegar rompe todos los enlaces a la vez, así que
`config/app.php` lo calcula a partir de `dirname($_SERVER['SCRIPT_NAME'])`, que es
precisamente lo que cambia entre un entorno y otro. Se puede fijar a mano sustituyendo el
bloque por un `define()` si algún hosting se comportara de forma extraña.

### Para desarrollar sin Apache

`router.php` reproduce las reglas del `.htaccess` para el servidor integrado de PHP
(`php -S localhost:8080 router.php`), incluido el bloqueo de las carpetas de código y de
cualquier script dentro de `uploads/`. Así el entorno de desarrollo se comporta como el
servidor real. En Apache y en cPanel no interviene.

## Los dos layouts

`layout_admin.php` es el layout del sistema de inventario portado: navbar fija, sidebar
colapsable filtrado por permisos, zona de mensajes flash e inyección de la vista del
módulo mediante `$vistaPath`.

`layout_publico.php` es nuevo: navbar del sitio, hero opcional, y footer con dirección,
teléfono, redes sociales y enlace al aviso de privacidad. Recibe `$config` —los datos
globales de la parroquia— y las variables de SEO `$metaTitulo`, `$metaDescripcion`,
`$ogImagen` y `$urlCanonica`.

Lo que comparten —marca, iconografía, scripts de CDN— vive en `shared/views/parciales/`,
para que los dos layouts no se desincronicen con el tiempo.

En código: `Controller` gana una propiedad `protected string $layout = 'layout_admin'`, y
`ControllerPublico` la sobrescribe, precarga `$config` y ajusta las cabeceras de caché. Es
la intervención mínima sobre la clase base.

## Organización de los módulos

Un directorio por sección, con administración y público juntos:

```
modules/avisos/
├── AvisoController.php          Panel: alta, edición, publicación
├── AvisoPublicoController.php   Sitio: listado y detalle
├── AvisoModel.php               Compartido
└── views/
    ├── lista.php                Panel
    ├── form.php                 Panel
    └── publico/
        ├── index.php
        └── detalle.php
```

Un solo modelo por sección, usado en lectura por el controlador público y en escritura por
el de administración. Mantener juntas las dos caras de una misma entidad evita que una
cambie y la otra se quede atrás.

## Contenido editable: tres mecanismos, no uno

El requisito era que todo fuera modificable. La tentación es hacer un constructor de
páginas genérico, pero eso permite que alguien borre la portada o rompa el menú un martes
por la tarde. La solución son tres mecanismos con propósitos distintos.

| Mecanismo | Para qué sirve | Quién crea las claves |
|---|---|---|
| `configuracion` | Datos atómicos globales: teléfono, dirección, mapa, redes, logo | Sembradas en `install.sql`; el administrador solo edita el valor |
| `bloques_contenido` | Textos largos anclados a zonas fijas: bienvenida del párroco, historia, misión | Sembradas; el administrador edita título, HTML e imagen, pero no puede crear ni borrar |
| `paginas` | Páginas nuevas con slug propio | El administrador crea las que quiera |

El resultado: portada, quiénes somos, horarios y contacto son estructuralmente
inmutables —siempre se ven bien— con su texto e imágenes 100 % editables. Y `paginas`
cubre lo imprevisto sin abrir la puerta a romper lo esencial.

Las semillas de `bloques_contenido` usan `INSERT IGNORE` en `install.sql`, igual que el
resto de las tablas del proyecto — a diferencia del patrón `ensureTable()` de
`EmpresaModel` en `inventario`, que sí reinserta en tiempo de ejecución, `BloqueModel` no
lo hace. Mientras el sitio no esté en producción esto no importa (`install.sql` se
reimporta entero al cerrar cada etapa, ver "Actualizaciones posteriores" en
`docs/DESPLIEGUE.md`); una clave nueva que se agregue después de desplegar sí necesitaría
su propio `INSERT IGNORE` aplicado a mano, como cualquier otro cambio de esquema en
producción.

Casi todos los bloques se siembran con `contenido` vacío, para que el administrador lo
llene. `ligas_interes` es la excepción: trae ya un enlace a la arquidiócesis porque tiene
sentido desde el primer día, no hay que esperar a que alguien lo capture.

**Lectura**: `core/Config.php` carga la tabla `configuracion` una sola vez por petición y
la deja en memoria. El pie del sitio necesita el teléfono, la dirección y las redes en
cada página, y no tiene sentido consultarlos varias veces. Si la base de datos aún no
está lista, devuelve valores vacíos en lugar de romper la página. La edición de esos
valores vive en el módulo de administración `configuracion`; `Config` solo los lee.

## Editor de texto y sanitizado

**El editor** es `assets/js/editor.js`: JavaScript vanilla sobre un `contenteditable`, con
barra de negrita, cursiva, subrayado, títulos, listas y enlaces, sincronizado con un
`<textarea>`. Se descartaron TinyMCE y CKEditor porque son dependencia externa —contra la
regla del proyecto— y porque amplían la superficie de XSS. También se descartó un textarea
plano a secas: los coordinadores no van a escribir HTML.

Tres detalles del editor:

- Usa `document.execCommand`, marcado como obsoleto pero todavía funcional en todos los
  navegadores y sin sustituto nativo. **Si algún día deja de responder, el textarea sigue
  ahí**: se muestra cuando el JavaScript no se carga y se puede escribir directamente.
- Al pegar conserva solo el texto. Arrastrar el formato de Word o de otra página no sirve
  de nada, porque el servidor lo descartaría igual.
- Sincroniza también en el `submit` del formulario, por si se guarda sin que el área
  editable pierda el foco.

**Las imágenes se quitan con una casilla**, no con un botón aparte. Un formulario por
imagen no puede anidarse dentro del formulario principal, y resolverlo con el atributo
`form` obligaba a colocar los formularios fuera del contenido. La casilla
`<campo>_quitar` lo resuelve sin JavaScript y con un solo envío.

**La configuración se guarda por secciones**, con un formulario independiente por pestaña.
Si algo falla al validar el mapa, no se pierde lo escrito en las demás. Además, cada
sección solo escribe las claves que vienen en el envío: un formulario completo las manda
todas, pero un envío incompleto no borra datos que nadie pidió borrar.

**El sanitizado es obligatorio, con editor o sin él.** `core/SanitizadorHtml.php` usa
`DOMDocument`, que es nativo de PHP, y aplica:

- whitelist de etiquetas: `p`, `br`, `strong`, `em`, `u`, `ul`, `ol`, `li`, `a`, `h2`,
  `h3`, `h4`, `blockquote`, `img`, `figure`, `figcaption`;
- whitelist de atributos por etiqueta;
- `href` limitado a `http`, `https` y `mailto`;
- eliminación de todo atributo `on*` y de cualquier `javascript:`.

Todo campo de contenido pasa por el sanitizador **al guardar**, no al mostrar. Esos
campos —y solo esos— se imprimen con `echo` en lugar de `e()`. Es la única excepción a la
regla de escapar en cada eco, y debe ir marcada con un comentario en el código donde
ocurra.

Comportamiento verificado con HTML hostil:

| Entrada | Resultado |
|---|---|
| `<script>alert(1)</script>` | Se elimina con su contenido |
| `<p onclick="alert(1)">Hola</p>` | `<p>Hola</p>` — se cae el atributo, no el párrafo |
| `<a href="javascript:alert(1)">x</a>` | `<a>x</a>` — se cae el `href` |
| `<a href="https://…" target="_blank">` | Se conserva y se le añade `rel="noopener noreferrer"` |
| `<div style="color:red">texto</div>` | `texto` — se desenvuelve y se conserva lo escrito |
| `<iframe src="…">` | Se elimina con su contenido |

### El mapa se guarda como dirección, no como HTML

El campo del mapa acepta que se pegue el fragmento completo que entrega Google Maps, pero
solo conserva la dirección del `iframe`, comprobando que apunte de verdad a
`google.com/maps/embed`. La vista construye el `iframe` a partir de ella.

Guardar el fragmento tal cual obligaría a imprimir HTML de terceros en el sitio, y sería
la única vía por la que un `iframe` podría entrar al contenido después de todo el trabajo
del sanitizador.

### Entrada normalizada a UTF-8

`postStr()`, `postHtml()` y `getStr()` comprueban que el texto recibido sea UTF-8 válido y
lo reinterpretan si no lo es. Un navegador que abre una página con `<meta charset="UTF-8">`
siempre envía UTF-8, así que en el uso normal no hace nada; pero si llegara texto en otra
codificación, MySQL sustituye cada acento por un signo de interrogación y el dato se pierde
sin aviso. Los formularios además declaran `accept-charset="UTF-8"`.

## Decisiones de modelado

### Las misas recurrentes no son eventos

Modelar "misa dominical de 12:00" como filas en la tabla de eventos generaría miles de
registros y un mantenimiento imposible. Por eso hay dos tablas con semánticas distintas:

- `horarios` es **recurrencia semanal**: día de la semana más hora. Las columnas
  `vigente_desde` y `vigente_hasta` cubren los horarios de temporada, como Cuaresma o
  verano, sin duplicar la tabla.
- `eventos` es **fecha concreta**.

El costo aceptado es que una celebración especial que además es misa se captura dos veces.
Ocurre pocas veces al año.

### Horarios: agrupado público por tipo, con filtro de sede/centro (issue #3)

`horarios` gana `centro_id` (FK a `centros`, `ON DELETE SET NULL`, NULL para un horario
sin sede/centro asignado). La página pública pasó por dos diseños antes de este: primero
agrupaba en tarjetas por `tipo` (misa, confesión…); luego, cuando se agregó `centro_id`,
pasó a agrupar por sede/centro y el tipo bajó a subtítulo. En el uso real eso resultó
menos útil que agrupar por tipo con un **filtro** de sede/centro aparte: un visitante
normalmente ya sabe qué tipo de horario busca (misa, confesión…) y quiere ver ese tipo
para su sede o para todas a la vez, no navegar centro por centro para encontrarlo.

`HorarioModel::vigentesPorTipo(?int $centroId = null)` es el método vigente:

- Agrupa por tipo, en el orden `ORDEN_PUBLICO` —misa arriba, confesión al final—, no el
  orden de `TIPOS` que usa el admin (ahí misa también va primero, pero por ser lo más
  frecuente de dar de alta). El array devuelto es tipo ⇒ horarios, y su orden de claves
  ya llega misa-primero desde la consulta SQL: la vista solo debe recorrerlo con
  `foreach` —nunca iterar `HorarioModel::TIPOS` en su lugar para decidir el orden de las
  tarjetas—, o el orden dependería de una coincidencia entre dos constantes en vez de
  ser explícito.
- Dentro de cada tipo, los horarios quedan ordenados de lunes a domingo —no domingo
  primero, que es como queda `dia_semana` tal cual, con `MOD(dia_semana + 6, 7)` en el
  `ORDER BY`, el mismo truco que ya usa el calendario de turnos MESC— y de la mañana a
  la noche. La vista muestra el centro de cada horario como etiqueta (`badge`), pero
  solo cuando el filtro está en "Todos": con un centro ya elegido, repetir su nombre en
  cada fila sería ruido.
- `$centroId` es el filtro: `null` = todos los centros mezclados dentro de cada tipo; un
  id concreto acota la consulta a esa sola sede/centro (`AND h.centro_id = :centro`).
  `HorarioPublicoController` lee `?centro=` de la URL y lo valida contra
  `CentroModel::activos()` —un id que no exista cae de vuelta a "Todos" en silencio, no
  a un error—, y arma el `<select>` del filtro con esas mismas opciones. El filtro es un
  formulario `GET` sin JavaScript: funciona igual con o sin JS, y el resultado es una URL
  compartible (`/horarios?centro=3`).

El listado de administración (`HorarioModel::todos()`) no cambia: sigue ordenado por
`tipo` en el orden de `TIPOS` (misa primero) y sin agrupar ni filtrar por centro, porque
ahí es más útil editar en bloque (todas las misas juntas, todas las confesiones juntas)
que separar por sede.

### Campos de sacramento configurables (eliminado en el issue #3)

Existió en la fase 1 original: columnas fijas + una columna `datos_extra` JSON para lo
variable, con `sacramento_campos` definiendo qué campo pedía cada sacramento en su
formulario de solicitud. Se eliminó por completo junto con el formulario que lo usaba —
ver "Sacramentos: catálogo puramente informativo", más abajo—. Se deja esta nota porque el
trade-off en sí (columnas fijas para lo que se filtra/ordena/audita, JSON solo para lo
variable) puede volver a ser relevante si otro módulo necesita algo parecido.

### Un usuario puede coordinar varias pastorales

Se usa la tabla pivote `usuarios_pastorales` en vez de una columna `pastoral_id` en
`usuarios`. En la práctica una misma persona suele coordinar catequesis y liturgia a la
vez. La tabla cuesta cuatro líneas de SQL ahora y evita una migración con datos reales
después.

### El organigrama son datos, no una imagen

`organigrama_nodos` es un árbol autorreferenciado de hasta `OrganigramaModel::NIVEL_MAXIMO`
(4) niveles. Se renderiza como una lista indentada con líneas de guía —`<ul>` anidados,
CSS puro, sin JavaScript— en vez de un diagrama de cajas y conectores: un organigrama real
de caja-y-línea en CSS puro es notoriamente frágil, y una lista jerárquica es accesible,
indexable por buscadores, editable sin abrir Photoshop, y se lee igual de bien en escritorio
que en un celular sin necesitar dos diseños distintos.

El HTML lo genera un único partial recursivo (`shared/views/parciales/organigrama_arbol.php`,
con la función `organigrama_render_nodo()`), compartido entre el panel y el sitio público;
solo cambian los colores según la hoja de estilos que cargue la página. Un nodo puede tener
un título sin persona asignada (un puesto vacante sigue apareciendo, solo que sin nombre), y
si la persona asignada está inactiva, el sitio público muestra el título pero no el nombre.

**Prevención de ciclos.** Al editar un nodo, el selector de "depende de" excluye al propio
nodo y a todos sus descendientes (`OrganigramaModel::idsConDescendientes()`), y el servidor
vuelve a validar esa misma regla al guardar — el filtro del formulario es una ayuda, no el
control real. El nivel de cada nodo se calcula solo a partir del de su padre; nunca lo
escribe la persona que edita.

Válvula de escape: si la clave `configuracion.organigrama_imagen` tiene valor, la vista
muestra esa imagen en lugar del árbol, y ni siquiera se consulta la base de datos del
organigrama. La parroquia decide sin que nadie toque código.

### Calendario propio

Mejora progresiva de verdad, no solo de palabra: `EventoPublicoController::index()`
calcula la cuadrícula del mes solicitado **en el servidor** (`construirCalendario()`) y la
sirve como HTML normal — funciona sin JavaScript, incluida la navegación de mes anterior
y siguiente, porque esos enlaces son URLs comunes (`?anio=&mes=`) que el propio
controlador sabe responder. `assets/js/calendario.js` solo intercepta esos clics para
traer el mes por `fetch` contra `?accion=datos` (un endpoint JSON) y reconstruir la tabla
sin recargar; si el `fetch` falla por lo que sea, cae al enlace normal sin más. Sin
FullCalendar ni ninguna otra librería. Debajo del calendario, una lista de "próximos
eventos" en HTML plano cubre a quien tiene JavaScript desactivado.

La acción JSON se llama `datos`, no `json`: `Controller` ya tiene un método `json()` para
emitir la respuesta, y una acción de ruta con ese mismo nombre lo taparía.

Simplificación deliberada en `EventoModel::delMes()`: un evento se ubica en el día en que
**empieza**, no en cada día que dura. La inmensa mayoría de los eventos de una parroquia
son de un solo día; uno de varios días solo aparece en su fecha de inicio.

### Publicación con moderación, ya preparada

`avisos.publicado` y `eventos.publicado` arrancan en 0. Los controladores comprueban el
permiso `*.publicar` por separado de `*.crear`/`*.editar` — hoy tanto `admin` como
`editor` lo tienen, así que en la práctica todo el contenido que crean se publica según
ellos decidan. La separación existe para la etapa 6: el rol coordinador tendrá
`*.crear`/`*.editar` pero no `*.publicar`, así que lo que escriba entrará como borrador
para que un editor lo revise, sin tocar un solo controlador de esta etapa. Se aplicó el
mismo patrón a `galeria.publicar`, independiente de `galeria.editar`.

### Vigencia de avisos (issue #3)

`publicado` es binario y manual: alguien tiene que volver a apagarlo. `avisos.vigente_hasta`
(DATE NULL) añade una ventana de tiempo sobre ese flag, para boletines y comunicados con
fecha de caducidad natural ("hasta el domingo de posadas") que nadie quiere estar
recordando despublicar a mano. `AvisoModel::VIGENTE` es la condición SQL compartida por
toda consulta pública (`publicados()`, `porSlugPublicado()`, `recientes()`,
`paraSitemap()`): `publicado = 1 AND fecha_publicacion <= CURDATE() AND (vigente_hasta IS
NULL OR vigente_hasta >= CURDATE())`. `fecha_publicacion` ya era el "visible desde";
`vigente_hasta` es el "visible hasta". El listado del panel (`AvisoModel::listar()`)
deliberadamente **no** usa esta condición: un aviso vencido sigue editable — el panel le
agrega el badge "Vencido" para que quede claro por qué ya no se ve en el sitio, sin que eso
le impida a un editor reabrirlo extendiendo la fecha.

No se replicó el mismo campo en `eventos`: un evento ya tiene su propio ciclo de vida
(`fecha_inicio`/`fecha_fin`), y ocultar automáticamente los que ya pasaron trabajaría contra
el interés de conservar un registro histórico de lo organizado.

## Roles y permisos

```
ROL_ADMIN        Todo, incluidos usuarios, configuración y auditoría.
ROL_EDITOR       Todo el contenido del sitio; publica y modera.
                 Sin acceso a usuarios ni configuración.
ROL_COORDINADOR  Contenido de su o sus pastorales. No puede publicar.
ROL_SECRETARIA   Solicitudes, inscripciones y mensajes. No edita el sitio.
```

La matriz `PERMISOS` vive en `config/app.php`, con notación `modulo.accion` y comodín
`'*'`, exactamente igual que en inventario.

**Por qué existe el rol de secretaría.** No es una distinción organizativa sino legal:
separa a quien ve datos personales de menores de quien edita la web. Un coordinador de
pastoral juvenil no necesita ver las actas de nacimiento de los niños de catequesis, y
dárselas "porque es de confianza" es exactamente el descuido que la ley busca evitar.

### El alcance por pastoral es ortogonal a la matriz

La matriz de permisos responde *"¿qué acción puede hacer?"*. Una segunda comprobación
responde *"¿sobre qué registro?"*. No se mezclan; si se mezclaran, la matriz crecería con
una entrada por pastoral y sería inmanejable.

En `Auth`, calcado del filtro por sucursal del sistema de inventario:

```php
Auth::pastoralesPermitidas(): array      // IDs, cacheados en sesión al entrar
Auth::tieneAlcanceGlobal(): bool         // administrador o editor
Auth::puedeSobrePastoral(?int $id): bool
```

En `Controller`:

```php
requireAlcancePastoral(?int $pastoralId): void
filtroPastoralSql(): ?array
```

Reglas sin excepción:

- Toda escritura sobre `avisos`, `eventos`, `galeria_imagenes` y `pastoral_actividades`
  llama a `requireAlcancePastoral()` con el `pastoral_id` **leído de la base de datos**
  cuando se edita o borra, nunca el que venga en el POST.
- Todo listado del panel pasa `pastoralesPermitidas()` al modelo, que añade
  `AND pastoral_id IN (…)`.
- Al crear, el coordinador no elige pastoral en un select abierto: si tiene una sola, va
  en un campo oculto; si tiene varias, el select se construye solo con las suyas. En
  ambos casos se revalida en el servidor.
- `pastoral_id NULL` significa contenido parroquial global. Un coordinador nunca lo toca.

### Administrador y Consulta por pastoral (revisión de módulos)

`ROL_COORDINADOR` es genérico: sirve para cualquier pastoral, sin importar cuál. Pero las
pastorales con un módulo propio y dedicado —MESC, Catequesis, Lector, calcadas unas de
otras— ganaron además un par de roles con nombre explícito cada una:
`ROL_ADMIN_MESC`/`ROL_CONSULTA_MESC`, `ROL_ADMIN_CATEQUESIS`/`ROL_CONSULTA_CATEQUESIS`,
`ROL_ADMIN_LECTOR`/`ROL_CONSULTA_LECTOR`.

- **Administrador de X** tiene el mismo alcance de contenido que Coordinador (avisos,
  eventos, galería, `pastoral_actividades`/`pastoral_documentos` de su pastoral) más
  control total (`ver`/`crear`/`editar`/`eliminar`) del módulo específico de esa
  pastoral. Es, en la práctica, "Coordinador con nombre puesto": mismo mecanismo de
  alcance, para que crear la cuenta dé de una vez claridad sobre qué administra, en vez
  de un rol abstracto más una asignación de pastoral aparte.
- **Consulta de X** es de solo lectura: únicamente el permiso `X.ver` (además de
  `panel.ver`). Pensado para que un ministro, catequista o lector de a pie entre al
  panel solo a ver su propio calendario o los documentos de su pastoral, sin poder
  editar nada.

Los seis roles reutilizan exactamente el mismo mecanismo de alcance que Coordinador
(`usuarios_pastorales`/`usuarios_centros`, cacheado en sesión por `Auth::cargarPastorales()`
al iniciar sesión, sin distinción de rol): al crear la cuenta hay que asignarle la
pastoral correspondiente, igual que a un coordinador. La constante
`ROLES_CON_ALCANCE_PASTORAL` en `config/app.php` agrupa los siete roles que necesitan
este checklist (Coordinador más los seis nuevos), para que el formulario de usuarios
(`modules/usuarios/views/form.php`) y su guardado (`UsuarioController::guardar()`) no
repitan `=== ROL_COORDINADOR` en cada punto — un error fácil de cometer si un rol nuevo
se agrega en un solo lugar y se olvida el otro.

### Un botón sin permiso no se muestra, no se muestra deshabilitado

Antes de los roles de Consulta, cualquiera que pudiera *ver* un módulo (coordinador,
editor, admin) también podía *editarlo* — nunca hizo falta que una vista distinguiera
entre ambos. Consulta rompió ese supuesto (solo tiene `X.ver`) y expuso un hueco real en
las vistas de MESC (construidas antes de que existiera ese rol) y, por copiarlas tal
cual, también en Catequesis y Lector: botones de Nuevo/Editar/Eliminar sin ningún
`Auth::tienePermiso()`, y —el más importante— el calendario de turnos enlazaba cada
evento directo a `turno_editar`, así que un usuario de Consulta que le diera clic caía
en `requirePermiso('mesc.editar')`, era redirigido a `/admin/panel` con un error de
permisos, y no entendía por qué. `requirePermiso()` y `requireAlcancePastoral()`
comparten ese mismo destino (`Controller.php`), así que cualquier acción sin permiso —no
solo un botón, un enlace directo como el del calendario— termina ahí.

La regla, ya aplicada en MESC, Catequesis y Lector: si `Auth::tienePermiso()` es falso
para la acción, el botón o enlace **no se dibuja**, no se muestra gris ni deshabilitado.
En el calendario de turnos, el evento sigue mostrándose (`<span>` en vez de `<a>`,
mismo color y título) para que Consulta vea su turno, solo que no es clickeable. Esto
es además de la comprobación real en el controlador (`requirePermiso()` sigue ahí):
ocultar el botón es una cortesía de UX, no el límite de seguridad.

### Alcance por centro/sede (issue #3)

Cada pastoral ahora está ligada a un `centro_id` (FK a `centros`, `ON DELETE SET NULL`,
NULL en las que ya existían antes de este campo). El issue pidió, además de "usuarios
administradores de la pastoral" (ya cubierto por `usuarios_pastorales`), "usuarios por
centro/sede": alguien que administra San Pío de Pietrelcina completo no debería tener que
marcar, una por una, cada pastoral que ese centro tenga hoy o llegue a tener mañana.

`usuarios_centros` es la tabla pivote análoga a `usuarios_pastorales`.
`Auth::pastoralesPermitidas()` calcula la **unión** de ambas fuentes con un solo `UNION`
SQL — pastorales asignadas directo, más las de cualquier centro que el usuario administre
completo — y cachea el resultado ya unido en sesión, exactamente igual que antes. Ningún
otro método de `Auth` ni de `Controller` cambió: `puedeSobrePastoral()`,
`requireAlcancePastoral()` y `filtroPastoralSql()` siguen leyendo `pastoralesPermitidas()`
sin saber que ahora tiene dos orígenes. `Auth::centrosPermitidos()` expone aparte los
centros asignados directo, para el formulario de usuarios y para mostrar qué centro
administra alguien; no se usa para autorizar nada por sí solo.

### Contenido propio por pastoral (issue #3)

La ficha pública de una pastoral (`pastorales/publico/detalle.php`) reúne, todo filtrado
por su propio `pastoral_id`: sus avisos vigentes (`AvisoModel::publicadosPorPastoral()`,
reutiliza la misma condición `VIGENTE` de la sección de avisos), sus próximos eventos
(`EventoModel::proximos($limite, $pastoralId)`) con un enlace a su propio calendario
mensual completo en `/eventos?pastoral=slug`, y sus documentos descargables
(`pastoral_documentos`, solo agregar/quitar — para cambiar uno se sube uno nuevo, no hay
edición de archivo). El centro/sede al que pertenece se muestra en la tarjeta de
información.

**Deliberadamente no hay un "organigrama de esta pastoral" aparte.**
`organigrama_nodos.pastoral_id` ya existe desde antes de este issue: cada nodo del
organigrama general puede ligarse a una pastoral. Duplicar esa misma información
filtrada dentro de la ficha de cada pastoral sería mostrar dos veces lo mismo con dos
caminos de código distintos, sin que nadie lo haya pedido.

**`/eventos?pastoral=slug`** reutiliza el calendario general en vez de duplicarlo:
`EventoModel::delMes()`/`proximos()` reciben un `$pastoralId` opcional,
`EventoPublicoController` resuelve el slug con `pastoralSolicitada()` (si no resuelve a
una pastoral activa, se ignora el filtro — la página cae en el calendario completo en vez
de mostrar uno vacío), y `calendario.js` propaga el filtro leyendo `data-pastoral` del
contenedor tanto en el fetch AJAX como al reescribir los enlaces de mes anterior/siguiente.

### MESC: visitas a enfermos y rutas (issue #3)

`modules/mesc/` es, a propósito, el único módulo del sitio **sin ningún controlador
público**: `MescModel` no tiene una sola consulta que no pase por
`requirePermiso('mesc.*')` + `requireAlcancePastoral()`. La razón está en
[`PRIVACIDAD.md`](PRIVACIDAD.md): el solo hecho de aparecer en `mesc_visitas` revela un
estado de salud, el primer dato sensible en sentido estricto de la LFPDPPP que maneja el
sistema. `pastoral_id` en `mesc_visitas`/`mesc_rutas` es **obligatorio**, a diferencia de
avisos o eventos: esta actividad nunca es "contenido parroquial general", siempre
pertenece a la pastoral de MESC. `MescController::pastoralIdMescValidado()` es una
variante de `Controller::pastoralIdValidado()` que nunca acepta `null`, ni siquiera para
un administrador.

**Mapa: Leaflet + OpenStreetMap, sin llave de API.** El formulario de una visita
(`mesc/views/form.php`) incluye un mapa (`assets/js/mapa_mesc.js`) donde marcar el pin es
enteramente opcional — el campo obligatorio sigue siendo la dirección de texto. Se eligió
Leaflet/OSM en vez de Google Maps por la misma razón que ya evitó reCAPTCHA (ver más
abajo): enviar la ubicación de un enfermo a un servicio de terceros de pago no es
aceptable cuando hay una alternativa gratuita igual de funcional. `.htaccess` amplía la
CSP (`img-src`) para permitir los tiles de `*.tile.openstreetmap.org` y los iconos del
marcador servidos desde `cdn.jsdelivr.net`; no se tocó `Permissions-Policy` — la
geolocalización del navegador sigue bloqueada en todo el sitio, así que el pin siempre se
coloca a mano, nunca por GPS.

**"Ruta óptima" es una aproximación geométrica, no una ruta real.**
`MescModel::ordenSugerido()` aplica una heurística de **vecino más cercano** (greedy)
sobre distancia **Haversine** en línea recta, partiendo de `configuracion.latitud`/
`longitud` si están configuradas. Las visitas sin pin en el mapa no tienen con qué
calcular cercanía, así que se agregan al final en el orden en que se registraron. Esto es
deliberadamente simple: una ruta por calles reales exigiría un servicio externo de
enrutamiento (con costo, límites de uso o ambos), y el propio issue permite que el
resultado sea "modificable después de generado" — `mesc_ruta_visitas.orden` es editable a
mano en `mesc/ruta_editar` antes de exportar.

**El "archivo" es un CSV**, no un PDF: el proyecto no depende de ninguna librería de
generación de PDF (cero dependencias, ver la introducción de este documento), y un CSV se
genera en PHP puro con `fputcsv()` y se abre en cualquier hoja de cálculo. `rutaExportar()`
antepone un BOM UTF-8 para que Excel no destroce los acentos.

### MESC: calendario de turnos (issue #3)

A diferencia de `mesc_visitas`, los turnos (`mesc_turnos`/`mesc_turno_ministros`) **no**
son un dato sensible: es la lista pública-para-el-equipo de quién sirve en qué misa, no de
quién recibe una visita. Aun así vive enteramente dentro del panel, sin controlador
público, porque nadie pidió exponerlo fuera del equipo pastoral.

**`mesc_ministros` es una entidad aparte de `personas`.** `personas` alimenta el equipo
pastoral público de "Quiénes somos" (foto, cargo, semblanza); un ministro MESC es un
voluntario interno que no necesariamente pertenece a esa vitrina, y forzar que lo fuera
habría acoplado dos cosas que cambian por separado.

**Un turno no tiene FK a `horarios` ni a `eventos`.** `horarios` es recurrencia semanal
sin fecha concreta ("domingos a las 12:00"), mientras que un turno cubre una **ocurrencia**
concreta de esa misa ("el domingo 3 de agosto"). Atarlo a `horarios` no resolvería la
fecha; atarlo a `eventos` obligaría a crear un evento formal para cada misa dominical, que
es justo la duplicación que `horarios` existe para evitar. `mesc_turnos.descripcion` es
texto libre ("Misa de 12:00", "Velorio familia González") — más simple y no depende de que
ese horario o evento exista formalmente en el sistema.

**Revalidación de ministros activos, igual que el resto del sistema nunca confía en el
POST.** `MescController::turnoGuardar()` cruza los IDs recibidos contra
`MescModel::ministrosActivos()` de la pastoral (`array_intersect`) antes de guardar: un
ministro dado de baja no puede colarse en un turno nuevo por más que se manipule el
formulario, sin necesidad de una validación aparte en el modelo.

**El calendario es una cuadrícula renderizada 100% en servidor**, sin AJAX: a diferencia
del calendario público de eventos (`calendario.js`, con fetch y sin recargar), aquí cada
clic en "mes anterior/siguiente" es un enlace normal que recarga la página. El panel admin
no tiene el mismo volumen de tráfico que justifique la complejidad de un endpoint JSON
aparte; `MescController::construirCalendarioTurnos()` es una cuadrícula de semanas
análoga a `EventoPublicoController::construirCalendario()`, deliberadamente duplicada en
vez de compartida —cruzar la frontera pública/admin por una función de 20 líneas no vale
el acoplamiento—. Los estilos (`.calendario-tabla`, `.numero-dia`, `.evento-punto`) se
copiaron de `assets/css/publico.css` a `assets/css/app.css` porque el panel carga una
hoja de estilos distinta a la del sitio público.

**Colores litúrgicos, como catálogo de mantenimiento, no como constante en PHP.**
`mesc_colores_liturgicos` (blanco, verde, morado, rojo, rosa, con su significado) es
editable desde el panel en `mesc/colores` en vez de vivir hardcodeado en el código: el
propio equipo pastoral puede ajustar el texto o el tono exacto sin tocar una línea de PHP.
Cada turno referencia opcionalmente un color (`color_liturgico_id`, `ON DELETE SET NULL`
— borrar un color no rompe los turnos que ya lo tenían, solo los deja sin etiqueta), y el
calendario usa ese `color_hex` como fondo de la casilla. `mesc_texto_legible()` en
`turnos.php` calcula la luminancia percibida del color (fórmula estándar
`0.299R + 0.587G + 0.114B`) para decidir si el texto va en blanco o en negro — el blanco
litúrgico (`#f4f1ea`) necesita texto oscuro encima, el resto necesita texto claro, y
hardcodear un solo color de texto para todos habría vuelto ilegible alguno de los dos.

**La nota de "consiga un cambio de turno entre compañeros"** viene del calendario que la
parroquia ya distribuía en papel/imagen; se muestra como una alerta fija arriba de la
cuadrícula en vez de guardarse como dato de turno, porque es una instrucción para todos
los turnos, no de uno en particular.

### Catequesis y Lector: MESC como plantilla para módulos dedicados (revisión de módulos)

Dos módulos nuevos, `modules/catequesis/` y `modules/lector/`, replican deliberadamente el
patrón de MESC —módulo propio y separado para una pastoral específica, sin controlador
público— en vez de ampliar el sistema genérico de "contenido propio por pastoral"
(`pastoral_actividades`/`pastoral_documentos`). La razón es la misma que hizo a MESC un
módulo aparte: cada uno necesita columnas y pantallas que ese sistema genérico no tiene
y que no tendría sentido forzar sobre *todas* las pastorales.

**Catequesis va un paso más allá que MESC: no solo `pastoral_id` es obligatorio, la
pastoral está fija.** MESC sí muestra un selector si el usuario administra más de una
pastoral (`pastoralIdMescValidado()`, nunca acepta `null` pero sí acepta *cuál*).
Catequesis nunca lo hace: `CatequesisModel::pastoralId()` resuelve la pastoral de
Catecismo por su `slug`, no por un id fijo en PHP (los id de pastorales se generan al
crearlas desde el panel, no se siembran en `install.sql`), y
`CatequesisController::pastoralIdOFallar()` corta el flujo con un mensaje claro si esa
pastoral todavía no existe. Esto vino de un bug real: al copiar el patrón "selector de
pastoral" de MESC tal cual, la pantalla de Catequesis también ofrecía la pastoral de
MESC como opción —cualquier administrador con acceso a ambas la veía en las dos—, algo
que no tiene sentido para un módulo que por diseño es de una sola pastoral.

**Catequesis: catequistas, periodos y el grado vive en la asignación, no en la
persona.** `catequesis_catequistas` es solo nombre y contacto — nada de sacramento ni
grado fijo. `catequesis_periodos` es un ciclo (ej. "2026-2027"), y
`catequesis_periodo_catequistas` es el pivote que junta periodo + catequista +
`grado` (diez valores, de Kinder a Confirmación). El grado se modeló ahí a propósito:
un catequista normalmente no da el mismo grado cada ciclo, así que fijarlo en
`catequesis_catequistas` perdería esa historia en cuanto cambiara de grado el año
siguiente; puesto en la asignación, "qué catequistas dieron clase en cuál periodo y de
qué grado cada uno" queda respondido con una sola consulta y sin perder nada del
pasado. `CatequesisModel::asignarCatequista()` usa
`INSERT ... ON DUPLICATE KEY UPDATE` sobre la llave compuesta `(periodo_id,
catequista_id)`: reasignar a alguien ya presente en el periodo le cambia el grado en
vez de duplicar la fila. `catequesis_actividades` es un tablero con vigencia y
`publicado` propios, como un mini-`eventos`, distinto de la lista fija sin fechas de
`pastoral_actividades`; `catequesis_documentos` es una copia directa de
`pastoral_documentos` (mismo patrón de subida vía `Upload::documento()`).

**Lector** (pastoral "Lectores"): recorta MESC a sus dos piezas no sensibles y
extrapolables —`lector_turnos`/`lector_turno_lectores` calcan
`mesc_turnos`/`mesc_turno_ministros` entrada por entrada, y `lector_lectores` calca
`mesc_ministros`—, y deja fuera lo que no aplica: nada de `mesc_rutas`/`mesc_visitas`, un
lector proclama la Palabra en misa, no reparte comunión a domicilio. `lector_turnos.color_liturgico_id`
apunta al catálogo `mesc_colores_liturgicos` en vez de duplicarlo: el significado de cada
color litúrgico es el mismo calendario para toda la parroquia, no un dato propio de un
módulo en particular — la primera vez que una tabla fuera de `mesc_*` referencia un
catálogo de MESC directamente.

Igual que Catequesis (arriba), Lector también es de una sola pastoral, sin selector:
`LectorModel::pastoralId()` la resuelve por `slug = 'lectores'` y
`LectorController::pastoralIdOFallar()` corta el flujo si no existe o el usuario no
tiene alcance sobre ella. La primera versión de este módulo copió el selector
multi-pastoral de MESC tal cual (igual que le pasó a Catequesis) y por eso ofrecía
Catecismo y MESC como opciones válidas para dar de alta un lector o un turno — un
administrador con acceso a las tres pastorales las veía todas mezcladas. Corregido de
la misma forma en ambos módulos.

### Moderación

Los coordinadores no tienen los permisos `*.publicar`, así que el campo `publicado` se
fuerza a 0 en todas sus escrituras y el panel del editor muestra una bandeja de
"Pendientes de publicar". Con diez coordinadores con cuenta, esto es lo que evita que la
web parroquial amanezca con cualquier cosa.

## Ícono de sacramentos: SVG propio, no una librería nueva

Bootstrap Icons 1.11 no tiene ningún ícono de temática religiosa —ni "cross" ni
"church"—, así que el ícono de Sacramentos (antes `bi-droplet`, una gota) es un SVG
inline propio: `icono_cruz()` en `core/helpers.php`, una cruz latina (travesaño en el
tercio superior, no un "+" centrado) dibujada a mano con un solo `<path>`. Usa
`fill="currentColor"` y `width/height: 1em` para comportarse como un ícono de fuente:
hereda color y tamaño del texto que lo rodea, sin agregar ninguna dependencia nueva solo
por un glifo. `BloqueModel::iconoZona()` decide entre este SVG (zona "sacramentos") o el
`<i class="bi ...">` de siempre (el resto): por eso devuelve el marcado ya completo, no
solo el nombre de una clase como antes.

## Antispam sin servicios de terceros

reCAPTCHA enviaría datos de los visitantes a Google, lo que contradice el aviso de
privacidad del sitio, y sería además una dependencia externa. `core/AntiSpam.php` combina
cuatro medidas:

1. Campo honeypot oculto por CSS. Si llega lleno, la petición se descarta en silencio
   respondiendo 200, para no darle información al bot.
2. Time-trap: un campo con marca de tiempo firmada por HMAC. Un envío en menos de cuatro
   segundos se rechaza.
3. Límite por IP en la tabla `intentos_formulario`: cinco envíos por formulario y hora.
4. El token CSRF, que ya es obligatorio en todo POST.

## Páginas libres y el aviso de privacidad

`paginas` es el tercer mecanismo de contenido editable (§ arriba): a diferencia de
`bloques_contenido`, aquí el panel **sí puede crear y borrar**. Sirve para lo que no cabe
en las secciones fijas, y es donde vive el aviso de privacidad.

Una sola excepción: los slugs listados en `PaginaModel::PROTEGIDAS` (hoy, solo
`aviso-de-privacidad`) no se pueden borrar desde el panel, y su slug no cambia aunque se
envíe otro en el formulario. El sitio depende de que esa dirección exista siempre; sin
esta protección, cualquiera con permiso de editar páginas podría dejar el enlace del pie
apuntando a una página inexistente.

El aviso de privacidad se instala **sin publicar**, con contenido de referencia entre
corchetes (`[DOMICILIO COMPLETO]`, `[CORREO DE CONTACTO]`…) que la parroquia debe
completar. Publicar un aviso legal a medio llenar es peor que no tener aviso: por eso el
pie del sitio solo enlaza a la página cuando `pagina_publicada('aviso-de-privacidad')`
devuelve `true`, y esa comprobación vive en `core/helpers.php` —no en `PaginaModel`—
porque el pie se dibuja en todas las páginas públicas, y cargar un modelo de módulo desde
el arranque global rompería la regla de que los módulos solo se cargan cuando su ruta se
despacha.

## El formulario de contacto y su antispam

`ContactoPublicoController` es el primer controlador público con `$requiereSesion = true`:
la página necesita CSRF —porque tiene un formulario— y CSRF necesita sesión. Es la
excepción documentada a la sesión perezosa.

Flujo de `enviar()`:

1. CSRF (`validarCsrf()`), como cualquier POST del sitio.
2. `AntiSpam::validar('contacto')`. Si el campo señuelo llegó lleno, o el envío tardó
   menos de 4 segundos, **no lanza excepción**: devuelve `false`, y el controlador
   responde con el mismo mensaje de éxito de siempre, sin insertar nada. No hay forma de
   que un script automatizado distinga "se guardó" de "se descartó".
3. Solo si `validar()` lanza `RuntimeException` —firma manipulada o formulario abierto más
   de dos horas— se muestra un error real: eso sí puede pasarle a una persona real que
   dejó la pestaña abierta.
4. Validación de campos: nombre, mensaje, al menos un correo o teléfono, formato de
   correo si se dio, y la casilla de consentimiento. **Los errores no redirigen**: se
   re-renderiza la misma página con los valores ya escritos, igual que el formulario de
   acceso. Solo el envío exitoso hace `redirect` (patrón *Post/Redirect/Get*, para que
   recargar la página no reenvíe el mensaje).

Verificado con seis casos: señuelo relleno, envío inmediato, validación sin datos, correo
mal formado, firma de tiempo manipulada y un envío legítimo — cada uno se comportó como se
describe arriba.

## Pastorales y la activación del rol coordinador

Casi toda la infraestructura de alcance por pastoral se escribió en la etapa 1, antes de
que existiera una sola pastoral: `Auth::pastoralesPermitidas()`,
`Auth::tieneAlcanceGlobal()`, `Auth::puedeSobrePastoral()`,
`Controller::requireAlcancePastoral()` y `filtroPastoralSql()` llevaban ahí desde el
principio, y `Auth::intentarLogin()` ya intentaba leer `usuarios_pastorales` en un
`try/catch` que simplemente devolvía `[]` mientras la tabla no existiera. Esta etapa creó
esa tabla y hay pastorales reales a las que apuntar: el mecanismo empezó a funcionar solo,
sin tocar una línea de `Auth.php`.

Lo que sí fue trabajo de esta etapa:

**Las cuatro columnas `pastoral_id` diferidas** (`organigrama_nodos`, `avisos`, `eventos`,
`galeria_imagenes`) se reescribieron con su FK real hacia `pastorales(id)`. Antes de
hacerlo se comprobó empíricamente que MariaDB acepta declarar una FK hacia una tabla que
todavía no existe en ningún punto del script, mientras `foreign_key_checks` esté en 0 —
que es como corre `install.sql` completo. `galeria_imagenes.evento_id` ya llevaba FK real
desde la etapa 5, porque `eventos` se crea antes en el mismo archivo.

**El selector de pastoral** (`shared/views/parciales/selector_pastoral.php`) es un único
partial reutilizado en avisos, eventos y la subida de galería, alimentado por
`Controller::opcionesPastoral()`:

- Alcance global (admin, editor): select abierto con todas las pastorales, más la opción
  de dejarlo en blanco ("contenido parroquial general").
- Coordinador con una sola pastoral asignada: ni siquiera elige — va en un campo oculto.
- Coordinador con varias: select construido solo con las suyas, nunca con la lista completa.

`Controller::pastoralIdValidado()` vuelve a comprobar el valor recibido **en el servidor**,
sin importar lo que el HTML del select permitiera elegir: un coordinador que manipule el
POST directamente no puede asignar contenido a una pastoral ajena ni dejarlo como general.
Verificado con una prueba directa: el intento se rechaza con un error y el valor en la
base de datos no cambia.

**El filtrado de listados** se resolvió con un método común,
`Model::condicionPastoral()`, usado igual en `AvisoModel`, `EventoModel` y
`GaleriaModel::listar()`. Su parte más delicada no es el caso con pastorales asignadas,
sino el caso sin ninguna: un coordinador sin pastoral asignada debe ver listados
**vacíos**, nunca el listado completo. La condición para ese caso es literalmente
`1 = 0`, y quedó comprobado con una prueba dedicada.

**Borrar una pastoral no borra su contenido.** Las cuatro FK usan `ON DELETE SET NULL`:
al eliminar una pastoral, sus avisos, eventos, fotos y nodos del organigrama sobreviven
como contenido parroquial general (`pastoral_id NULL`). Solo `pastoral_actividades` y
`usuarios_pastorales` tienen `ON DELETE CASCADE`, porque una actividad sin su pastoral no
significa nada y la asignación de un coordinador tampoco. Comprobado borrando una pastoral
con un aviso asociado: el aviso siguió existiendo, con `pastoral_id` en `NULL`.

## Sacramentos: catálogo puramente informativo

En la fase 1 original (etapa 7), esta sección recibía solicitudes de bautizo, primera
comunión, confirmación, matrimonio y unción de enfermos directamente desde el sitio, con
folio, bandeja de estados y campos configurables por sacramento (`sacramento_campos`).
**El issue #3 eliminó todo ese formulario en línea**, a petición explícita del
administrador: la sección queda como información de requisitos, documentos y aportación,
y para llevar a cabo el trámite la persona se acerca a la oficina parroquial.

Se eliminaron por completo: `SolicitudController`, `SolicitudModel`, las tablas
`solicitudes_sacramento`, `solicitudes_bitacora` y `sacramento_campos`, las columnas
`acepta_solicitudes` y `requiere_tutor` de `sacramentos`, el permiso `solicitudes.*` (con
lo que `ROL_SECRETARIA` pierde ese permiso, aunque conserva `inscripciones.*` y
`mensajes.*`), el módulo admin `solicitudes` del Router, y `cli/purgar_solicitudes.php`
—la única pieza de retención automática que existía en todo el sistema—. El texto sembrado
del aviso de privacidad (`install.sql`) se reescribió para no describir un trámite que ya
no existe. Ver [`PRIVACIDAD.md`](PRIVACIDAD.md).

**Lo que queda:** `SacramentoModel` es un CRUD de catálogo puro (`todos`, `porId`,
`porSlugActivo`, `activos`, `crear`, `actualizar`, `eliminar`), igual de sencillo que
`PastoralModel` sin sus actividades. El catálogo sigue siendo fijo —no hay acción para
crear ni borrar un sacramento, los seis se siembran en `install.sql`— por la misma razón
que `bloques_contenido`: agregar un séptimo sacramento no es algo que vaya a pasar en la
práctica.

**Consecuencia para el resto del sistema:** con esto, `inscripciones_curso` (cursos y
catequesis) queda como la **única** fuente de datos de menores que recibe el sitio en
línea. `docs/PRIVACIDAD.md` se actualizó para reflejar esto: ya no hay ningún mecanismo de
retención/anonimización automática en el sistema (existía solo para solicitudes); si la
parroquia decide que `inscripciones_curso` necesita uno, habría que construirlo de nuevo,
no reactivarlo.

## Cursos e inscripciones

El catálogo de cursos y capacitaciones es la primera piedra del LMS de fase 2 (tareas,
entregas y calificaciones quedan fuera de esta fase), pero ya resuelve el problema
inmediato: publicar cursos con temario y recibir inscripciones con control de cupo.

**El correo es obligatorio aquí, a diferencia de otros formularios públicos.** En
contacto basta teléfono o correo; en la inscripción a un curso el correo es la clave que
evita una doble inscripción accidental
(`uq_ins_curso_email` es único por `curso_id` + `email`, y `InscripcionCursoModel::yaInscrito()`
lo comprueba antes de insertar). Verificado: el mismo correo intentando inscribirse dos
veces al mismo curso recibe el mensaje de rechazo explicando por qué el campo es
obligatorio.

**Cupo y lista de espera se deciden dentro de una transacción.** `InscripcionCursoModel::crear()`
cuenta las inscripciones activas (`pendiente` + `confirmada`, no `lista_espera` ni
`cancelada`) y decide el estado inicial del registro nuevo, todo dentro de un
`beginTransaction()`/`commit()` con `rollback()` en caso de error. Sin la transacción, dos
inscripciones casi simultáneas al último lugar disponible podrían leer el mismo conteo y
ambas entrar como `pendiente`, rebasando el cupo. Verificado con cupo=1: la primera
inscripción quedó `pendiente` y la segunda, `lista_espera`.

**`pastoral_id` en `cursos` es solo organizativo, sin scope de coordinador.** A diferencia
de avisos, eventos y galería, el rol `coordinador` tiene únicamente `cursos.ver` — nunca
`cursos.crear` ni `cursos.editar` — así que no hay escritura suya que requiera acotarse a
sus pastorales. La columna existe para poder filtrar "cursos de la pastoral juvenil" en el
listado público, no para controlar permisos.

**Menor de edad, misma regla que en sacramentos.** `es_menor` se calcula en el servidor a
partir de la fecha de nacimiento y, si aplica, exige nombre, parentesco y teléfono del
tutor. Verificado con una inscripción sin datos de tutor (rechazada) y otra con los tres
campos completos (aceptada, `es_menor=1`).

**Bandeja de inscripciones separada del catálogo, para separar roles.** `secretaria`
administra `inscripciones.*` (ver, cambiar estado, exportar) pero no toca `cursos.*`;
`editor` administra el catálogo completo pero no ve una sola inscripción. Verificado de
punta a punta con los tres roles (`coordinador`, `editor`, `secretaria`) contra
`/admin/cursos` y `/admin/inscripciones`.

## Usuarios, roles y auditoría

El CRUD de cuentas y la bandeja de auditoría son, por diseño del plan, exclusivos del
administrador: ningún otro rol tiene entradas `usuarios.*` ni `auditoria.*` en la matriz de
`config/app.php`, así que llegan solo por el comodín `'*'` de `ROL_ADMIN` — igual que
`configuracion.*`. Los controladores llaman `requirePermiso('usuarios.ver')` de todos
modos, en vez de un método `requireAdmin()` aparte: es el mismo patrón que ya usaba
`ConfiguracionController`, y deja abierta la puerta a delegar un permiso suelto en el
futuro sin tocar el controlador.

**`usuarios_pastorales` solo se sincroniza si el rol es coordinador.** El formulario
manda las casillas marcadas como `pastorales[]`, pero `UsuarioController::guardar()`
descarta ese arreglo por completo cuando el rol elegido no es `coordinador`: admin y
editor tienen alcance global sin importar qué haya en el pivote (`Auth::tieneAlcanceGlobal()`),
así que guardarles pastorales sería un dato muerto que solo confundiría al leer la tabla
después. `UsuarioModel::sincronizarPastorales()` borra e inserta de nuevo en cada guardado,
más simple y sin margen de desincronización que calcular un diff.

**Solo hay baja lógica, nunca borrado físico.** A diferencia de `personas` o `paginas`, un
usuario no se puede eliminar de verdad: `auditoria.usuario_id` referencia su fila, y
borrarla de la base dejaría el historial de "quién hizo qué" con nombres huérfanos. Por
eso `UsuarioController::eliminar()` solo llama a `UsuarioModel::desactivar()` (`activo = 0`,
que ya excluye del login por la condición en `Auth::intentarLogin()`), y ni siquiera se
ofrece la opción de borrado físico en la interfaz.

**Autoprotección mínima, calcada de `inventario`:** nadie puede darse de baja a sí mismo
(`UsuarioController::eliminar()` lo bloquea antes de tocar la base) ni desactivar su propia
cuenta desde el formulario (`activo` se fuerza a 1 cuando `$esPropio`, con la casilla
además deshabilitada en la vista). Deliberadamente **no** se bloquea que alguien se cambie
su propio rol: el proyecto de referencia tampoco lo hacía, y añadirlo sería una
protección no pedida para un caso que, si ocurre, se corrige por SQL directo — igual que
cualquier otra cuenta de administración de un sitio pequeño.

**La auditoría es de solo lectura y no audita su propia consulta.** A diferencia de
`inscripciones_curso`, `AuditoriaController::index()` no llama a
`auditoria('consultar', …)`: el listado ya ES la bitácora, y una fila por cada vez que
alguien la revisa no aporta nada, solo la infla. Sí se audita la exportación a CSV, igual
que en el resto del panel.

### Dos errores que este repaso encontró y corrigió

El cierre de esta etapa incluía, por plan, un repaso de seguridad de todo el sistema — no
solo del código nuevo. Ese repaso encontró dos fallas reales que venían de etapas
anteriores y habían pasado inadvertidas porque ninguna prueba anterior había iniciado
sesión como un coordinador con una pastoral ya asignada por la interfaz:

1. **`Controller::opcionesPastoral()` devolvía las claves `opciones`, `fija` y
   `permiteVacio`, pero `shared/views/parciales/selector_pastoral.php` siempre esperó
   `sp_opciones`, `sp_fija` y `sp_permiteVacio`.** Los formularios de avisos, eventos y
   galería nunca las traducían, así que el selector de pastoral fallaba con
   "Undefined variable" para cualquier coordinador (con `APP_DEBUG` en `false` el error
   quedaría oculto y el `<select>` simplemente saldría vacío). Se corrigió devolviendo ya
   las claves con el prefijo `sp_` desde el origen, en vez de parchear cada vista.
2. **El filtro de auditoría por acción no podía llamarse `accion` en la URL.** Ese nombre
   ya lo usa el propio `Router` para elegir el método del controlador: `?accion=crear`
   intentaba invocar `AuditoriaController::crear()`, que no existe, y devolvía 404 en vez
   del listado filtrado. El parámetro público quedó como `tipo_accion` (la columna de la
   tabla se sigue llamando `accion`; solo cambió el nombre expuesto en la URL y el
   formulario). **Cualquier filtro nuevo en el panel debe evitar los nombres reservados
   del Router: `area`, `modulo`, `accion`, `slug`.**

## Impersonación ("Usar como…")

Añadido junto con la restauración de respaldos, también a petición del administrador y
también replicando `C:\xampp\htdocs\inventory school` —ahí la función existe con ese mismo
nombre en la interfaz, "Use as…"—. Deja que el administrador opere el panel con la sesión
de otra cuenta sin conocer su contraseña: para ver el panel tal cual lo ve un coordinador o
secretaría, o para resolver una duda de soporte.

**La sesión se sobrescribe, no se crea una segunda.** `Auth::iniciarImpersonacion()` guarda
la identidad real del administrador en claves de sesión aparte (`_admin_real_id`,
`_admin_real_nombre`, `_admin_real_email`) y sobrescribe `usuario_id`/`usuario_nombre`/
`usuario_email`/`usuario_rol`/`usuario_foto`/`usuario_pastorales` con los de la cuenta
objetivo —recargando sus pastorales reales, no las del administrador—. A partir de ahí,
`Auth::tienePermiso()` y `Auth::tieneAlcanceGlobal()` ven exactamente el rol impersonado:
mientras se actúa como un coordinador, el panel se comporta como si ese coordinador hubiera
iniciado sesión, incluidas sus limitaciones (sin publicar, acotado a sus pastorales, sin
`usuarios.*`/`auditoria.*`/`respaldos.*`). Para recuperar el acceso de administrador hay que
volver primero.

**Nunca se anida, y nunca se impersona a otro administrador.** `AuthController::impersonar()`
exige `usuarios.impersonar` (que ningún rol impersonado tiene, por construcción: solo
`ROL_ADMIN` lo trae por el comodín de la matriz) y además comprueba explícitamente
`Auth::estaImpersonando()` como defensa en profundidad, por si algún día alguien agregara
ese permiso a otro rol sin pensar en este efecto. La cuenta objetivo debe estar activa y su
rol no puede ser `admin`.

**`admin_real_id` en `auditoria`, para distinguir "lo hizo la secretaria" de "lo hizo el
administrador actuando como la secretaria".** `Controller::auditoria()` sigue llenando
`usuario_id` con la identidad efectiva de la sesión (la impersonada, si aplica), y ahora
también `admin_real_id` con `Auth::adminReal()['id']` cuando corresponde. La bandeja de
auditoría (`docs/ARQUITECTURA.md`, sección "Usuarios, roles y auditoría") muestra "admin
real: …" bajo el nombre del usuario en esas filas. Verificado extremo a extremo: crear un
aviso mientras se impersona a un coordinador queda auditado con `usuario_id` = coordinador
y `admin_real_id` = administrador.

**El banner fijo y el propio menú "Usar como…" viven en `layout_admin.php`**, no en un
módulo aparte: es la única vista que envuelve todas las pantallas del panel, así que es el
único lugar donde tanto el aviso persistente como el selector de cuentas tienen sentido.
El selector de candidatos (`UsuarioModel::paraImpersonar()`, activos y sin rol admin) se
consulta directamente ahí, con una búsqueda por nombre o correo enteramente en JavaScript
del lado del cliente —sin ida y vuelta al servidor— porque la lista de usuarios de una
parroquia nunca va a ser tan grande como para justificar un buscador con backend.

## Respaldos y restauración de la base de datos

Añadido después de cerrar las diez etapas del plan original, a petición explícita del
párroco/administrador. El proyecto de referencia correcto para este módulo es
`C:\xampp\htdocs\inventory school` —no `C:\xampp\htdocs\inventario`, que también tiene un
módulo `backups` pero sin restauración—, adaptado a las convenciones propias de este
repositorio.

**Volcado en PHP puro, sin `mysqldump`.** `RespaldoModel::generarDump()` recorre
`SHOW TABLES`, y por cada tabla escribe `DROP TABLE IF EXISTS` + `SHOW CREATE TABLE` (la
estructura) y los datos en lotes de 200 filas vía `INSERT INTO ... VALUES` (para no
construir una sola sentencia gigante), leyendo con `PDOStatement::fetch()` en vez de
`fetchAll()` para no acumular la tabla completa en un arreglo de PHP. Es la misma razón que
justifica no depender de `mysqldump` en el resto del proyecto: un hosting de cPanel sin SSH
tampoco suele permitir `exec()`/`shell_exec()`, así que un volcado nativo vía PDO es la
única vía verdaderamente portable. Ver `docs/DESPLIEGUE.md`.

**Restauración solo de un respaldo que el propio panel generó**, nunca de un `.sql` subido:
`RespaldoController::restaurar()` recibe el `id` de una fila existente en `respaldos_log`,
no un archivo. No hay forma de ejecutar SQL arbitrario que no haya salido de "Generar
respaldo ahora" primero.

**`respaldos_log` queda fuera del propio volcado, a propósito — y esto no es cosmético,
es lo que evita un bug real.** La primera versión sí la incluía (como hace el proyecto de
referencia), y al restaurar un respaldo viejo el `DROP TABLE`/`CREATE TABLE` de esa tabla
en el `.sql` restaurado borraba la fila del **respaldo de seguridad que se acababa de crear
segundos antes** para poder deshacer la restauración: el archivo seguía físicamente en
`backups/`, pero su registro en el historial desaparecía junto con el resto de filas más
nuevas que la foto que se estaba restaurando, y con él la manera fácil de encontrarlo desde
el panel. Se corrigió excluyendo `respaldos_log` de `SHOW TABLES` antes de volcar
(`array_diff` en `generarDump()`): es metadata de esta herramienta, no contenido de la
parroquia, y no tiene sentido que una restauración se borre a sí misma su propia red de
seguridad. Verificado con datos reales: tras restaurar, la fila del respaldo de seguridad
sobrevive y sigue siendo descargable desde el panel.

**Respaldo de seguridad automático antes de restaurar, reutilizando `crear()` tal cual.**
`RespaldoModel::restaurar()` llama a `crear()` como primer paso, antes de tocar nada; si la
restauración falla a medio camino, el mensaje de error indica cuántas sentencias alcanzaron
a ejecutarse y el nombre exacto de ese respaldo de seguridad para volver atrás. No hay
transacción envolviendo la restauración: no serviría de nada, porque el `.sql` mezcla DDL
(`DROP`/`CREATE TABLE`), que en MySQL confirma de forma implícita y rompería cualquier
transacción a la mitad.

**Separador de sentencias propio, no `explode(';', ...)` ni multi-statement de PDO.**
`RespaldoModel::dividirSentencias()` recorre el `.sql` carácter por carácter llevando un
estado "dentro de una cadena" que se activa/desactiva con `'`, tratando `\` como escape
dentro de una cadena (consume el siguiente carácter sin evaluarlo) — así un aviso cuyo
contenido incluya un punto y coma no corta la sentencia a la mitad. Verificado con un texto
que combina punto y coma y comilla simple dentro del mismo campo. No se usa el
multi-statement de PDO porque con sentencias preparadas nativas
(`ATTR_EMULATE_PREPARES=false`) no es confiable.

**Confirmación con casilla obligatoria, sin repetir contraseña.** La vista deshabilita el
botón "Restaurar" hasta marcar "Entiendo que esto reemplaza todos los datos actuales", y el
controlador revalida esa confirmación en el servidor (`postBool('confirmo')`) por si un
POST crudo se saltara el JS. No se pide contraseña como en otras acciones destructivas de
otros proyectos de referencia: se consideró que el respaldo de seguridad automático ya es
suficiente red antes de esta operación, acotada además a solo administrador.

**`backups/` es una carpeta de infraestructura, con nombre en inglés como `uploads/` o
`assets/`**, no una excepción al "todo en español": las clases (`RespaldoModel`,
`RespaldoController`), la tabla (`respaldos_log`), las claves de permiso (`respaldos.*`) y
el texto de la interfaz sí lo respetan. La carpeta se crea sola en el primer respaldo
(`mkdir` recursivo) y está bloqueada por HTTP en `.htaccess`, igual que `config/`, `core/`,
`modules/`, `shared/`, `docs/` y `cli/`.

**`respaldos.*` es exclusivo de administrador**, igual que `usuarios.*` y `auditoria.*`:
sin entradas propias en la matriz de `config/app.php` para los demás roles, llega solo por
el comodín de `ROL_ADMIN`. `respaldos.restaurar` es un permiso propio, separado de
`respaldos.crear`, aunque hoy solo el administrador tenga ninguno de los dos.

**`respaldos_log` distingue `tipo` (`respaldo` / `restauracion`) y une con `usuarios` en vez
de desnormalizar el nombre**, igual que `auditoria` — a diferencia del proyecto de
referencia, que sí guarda `usuario_nombre` como columna propia. Con el volumen de respaldos
de una parroquia (generados a mano, sin cron), la diferencia de rendimiento es irrelevante,
y seguir el patrón ya establecido en `auditoria` es más consistente que introducir uno
nuevo. En una fila `tipo='restauracion'`, `archivo` referencia el `.sql` de un respaldo
ajeno (el que se restauró), no uno propio: `RespaldoModel::eliminar()` por eso solo borra el
archivo físico cuando `tipo='respaldo'`, nunca para una fila de restauración.

## Sede y centros

Primer pendiente del issue #3 (los que siguieron a la fase 1 original). La parroquia tiene
una sede y, hoy, dos centros que dependen de ella (San Pío de Pietrelcina, Jesús el Señor).

**Una sola tabla, no dos.** `centros` con una columna `tipo` ENUM(`sede`, `centro`)
distingue el registro principal de los que dependen de él, igual que `horarios.tipo` o
`respaldos_log.tipo` distinguen variantes de una misma clase de dato en el resto del
proyecto. No hay una tabla `sede` separada con cardinalidad 1: forzar "solo puede haber una
sede" en el esquema es una regla que nadie pidió, y que estorbaría el día que la parroquia
tenga una segunda. La sede de hoy es simplemente la fila con `tipo='sede'`.

**Sembrado con datos reales, no con anclas vacías.** A diferencia de la mayoría de
`bloques_contenido`, esta tabla se siembra con los tres registros reales que ya existen
(la sede y los dos centros nombrados), igual que las seis semillas de `sacramentos`: el
administrador no debería tener que dar de alta a mano algo que ya se sabía desde el primer
día.

**`centros.*` es de alcance parroquial, no de pastoral: lo administra editor, no
coordinador.** Igual que `horarios`/`personas`/`organigrama`, es información de toda la
parroquia, no de una pastoral en particular — el coordinador nunca lo toca.

**Sin dirección en mapa (lat/long) todavía.** Los centros solo llevan una dirección de
texto libre. El mapa con selección de pin es un requisito de la visita a enfermos de MESC
(la ubicación de la persona visitada, no la del centro), no de este catálogo; si más
adelante un centro necesita su propio mapa, se agrega ahí cuando haga falta.

## SEO

Todas las entidades con URL pública llevan `slug` con índice único, generado por
`core/Slug.php`, que translitera acentos y resuelve colisiones con sufijo numérico. **El
slug no se regenera al editar el título**, porque rompería los enlaces ya compartidos; si
hace falta cambiarlo, se edita a mano.

`layout_publico.php` emite `<title>`, `meta description`, `link canonical`, Open Graph y
Twitter Card a partir de las variables que cada controlador público define.

**`url_publica()` y `url_activo()` devuelven rutas relativas a propósito** (empiezan en
`/`, sin esquema ni dominio): son las que necesitan los enlaces internos del sitio, y
generarlas relativas evita depender de que `$_SERVER['HTTP_HOST']` sea confiable en cada
request. Pero `sitemap.xml` exige rutas absolutas por especificación, y el canonical y
`og:image`/`og:url` deberían serlo por buena práctica (algunos rastreadores no las
resuelven bien si llegan relativas). Para eso existe `url_absoluta()` en `core/helpers.php`:
antepone esquema y `HTTP_HOST` a una ruta ya construida, deducido del propio request igual
que `APP_URL` — así no hace falta una clave "dominio del sitio" en `configuracion` que
alguien olvide actualizar el día que cambie de dominio. Se aplica en tres lugares:
`layout_publico.php` (canonical, og:url, og:image), `SitemapController` (cada `<loc>`) y
los constructores de JSON-LD.

**El módulo `sitemap`** (`modules/sitemap/SitemapController.php`) genera `sitemap.xml`
consultando cada modelo de contenido en cada visita, sin escribir un archivo estático: para
el volumen de una parroquia —decenas o pocos cientos de avisos y eventos acumulados en
años— regenerar en cada visita es más simple que invalidar una caché en disco al publicar
algo, y `noCache()` ya dispensa una caché HTTP de 5 minutos para no repetir el trabajo en
cada rastreo. Incluye solo lo visible al público (mismo filtro `publicado`/`activo` que su
propia página de detalle) y usa `AvisoModel::paraSitemap()` / `EventoModel::paraSitemap()`
—añadidos en esta etapa— en vez de sus métodos `publicados()`/`listar()` paginados, que
truncarían el sitemap a una sola página. `robots.txt` referencia la URL con el dominio de
ejemplo; se corrige a mano al desplegar, junto con el resto de los pendientes que anota el
propio archivo.

**Datos estructurados vía un contrato en el layout, no HTML escrito a mano.** El
controlador público arma un arreglo PHP con la forma de schema.org y lo pasa como
`$jsonLd` a `render()`; `layout_publico.php` es el único lugar que llama
`json_encode()` (con `JSON_HEX_TAG` y las demás banderas `JSON_HEX_*`, para que un título
con `</script>` dentro no rompa la etiqueta) y lo imprime como
`<script type="application/ld+json">`. La portada (`InicioController::datosEstructurados()`)
arma tipo `Church`, con `address`, `geo` y `sameAs` solo si esos campos de `configuracion`
tienen valor real —un `"telephone": ""` se lee como dato de mala calidad, no como ausente—.
El detalle de evento (`EventoPublicoController::datosEstructurados()`) arma tipo `Event`.

## Seguridad

Además de lo heredado —CSRF en todo POST, sentencias preparadas, contraseñas con bcrypt de
coste 12, escapado en cada eco— el proyecto añade:

- **`uploads/.htaccess`** con `php_flag engine off` y denegación de `.php`, `.phtml` y
  `.phar`. Es la mitigación crítica: en el sistema de inventario los archivos los sube
  únicamente el personal interno, mientras que aquí hay muchas más cuentas cargando
  imágenes.
- **Extensión derivada del MIME real**, nunca del nombre original del archivo.
- **Content-Security-Policy** en `.htaccess`, acotada a `'self'` y `cdn.jsdelivr.net`.
- **HTTPS forzado y HSTS** en producción.
- **Auditoría también en lectura** de datos personales, no solo en escritura, porque es lo
  que permite responder a un requerimiento de acceso. Ver [`PRIVACIDAD.md`](PRIVACIDAD.md).
