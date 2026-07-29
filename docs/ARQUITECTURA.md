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

área admin     auth · panel · configuracion · bloques · paginas · personas
               organigrama · horarios · sacramentos · solicitudes · pastorales
               cursos · inscripciones · avisos · eventos · galeria · carrusel
               mensajes · usuarios · auditoria
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
/sacramentos/bautizo/solicitar   → area=publico&modulo=sacramentos&slug=bautizo&accion=solicitar
/avisos?pagina=2                 → area=publico&modulo=avisos
/admin/solicitudes/ver?id=12     → area=admin&modulo=solicitudes&accion=ver
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

Las semillas de `bloques_contenido` se reinsertan con `INSERT IGNORE` al arrancar el
modelo, siguiendo el patrón `ensureTable()` del módulo de empresa en inventario. Así, si
una versión futura añade una clave nueva, aparece sola sin necesidad de migrar.

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

### Campos de sacramento configurables

Cada sacramento pide datos distintos: el bautizo pide padrinos, el matrimonio pide dos
contrayentes y expediente prematrimonial. Las opciones eran:

| Opción | Problema |
|---|---|
| Una columna por cada dato posible | Tabla de sesenta columnas, casi todas nulas |
| Tabla entidad-atributo-valor | JOINs incómodos para un volumen bajo |
| **Columnas fijas + JSON** ← elegida | — |

Se usan columnas reales para lo común —nombre, fecha de nacimiento, contacto, tutor,
estado, consentimiento: todo lo que se filtra, ordena y audita— y una columna
`datos_extra` de tipo JSON para lo variable, con la tabla `sacramento_campos` definiendo
qué se pide en cada caso. Así el párroco agrega "nombre del padrino" a Confirmación desde
el panel, sin que nadie toque el esquema.

**Trade-off explícito**: `datos_extra` no es cómodo de buscar ni de indexar. Se acepta
porque son cientos de registros al año y las búsquedas reales son por folio, nombre,
sacramento y estado, que son columnas reales. Si el hosting resultara tener MySQL anterior
a 5.7, la columna pasa a `TEXT` y el código PHP no cambia.

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

### Moderación

Los coordinadores no tienen los permisos `*.publicar`, así que el campo `publicado` se
fuerza a 0 en todas sus escrituras y el panel del editor muestra una bandeja de
"Pendientes de publicar". Con diez coordinadores con cuenta, esto es lo que evita que la
web parroquial amanezca con cualquier cosa.

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

## Sacramentos y solicitudes

La pieza de más valor operativo del sistema, y la más delicada legalmente: recibe
solicitudes de bautizo, primera comunión, confirmación, matrimonio y unción de enfermos
directamente desde el sitio, con frecuencia de menores de edad.

**El folio no se deriva del slug.** `SacramentoModel::prefijoFolio()` usa un mapa
explícito (`bautizo`→`BAU`, `confirmacion`→`CNF`, `confesion`→`CNS`…) en vez de tomar las
tres primeras letras del slug: "confirmacion" y "confesion" comparten esas tres letras
("CON"), lo que habría hecho indistinguibles sus folios para la secretaría. Un sacramento
nuevo que se cree desde el panel —el catálogo lo permite en principio, aunque solo existen
los seis universales— cae en una derivación genérica de respaldo, con el riesgo aceptado
de coincidir con otro.

**El catálogo es fijo, el contenido es editable.** No hay acción para crear ni borrar un
sacramento: los seis se siembran en `install.sql` y solo se edita su descripción,
requisitos, documentos e imagen. Es la misma filosofía que `bloques_contenido`, aplicada
aquí porque agregar un séptimo sacramento no es algo que vaya a pasar en la práctica.

**El menor de edad se calcula en el servidor**, siempre, a partir de la fecha de
nacimiento — nunca se confía en lo que el formulario indique. La sección de tutor se
muestra **siempre** en el formulario público (con la nota "completa esto solo si el
solicitante es menor"), en vez de mostrarla u ocultarla con JavaScript según la fecha
capturada: así funciona igual con o sin JavaScript, y el control real de todos modos
ocurre en el servidor al validar, no en la vista.

**Solo la lectura respeta el flag `dato_sensible`.** El formulario público pide todos los
campos configurados de un sacramento sin distinción; ese flag únicamente decide qué se
muestra al ver la solicitud en el panel — es una barrera de visualización para admin y
secretaría, no una barrera de captura.

**Auditoría de lectura, no solo de escritura.** `SolicitudController::index()` y `::ver()`
llaman `auditoria('consultar', …)` antes de mostrar nada, incluida la exportación a CSV.
Verificado: cada apertura de una solicitud deja una fila en `auditoria` con su folio.

**La purga anonimiza, nunca borra.** `SolicitudModel::purgarVencidas()` vacía nombre,
contacto, tutor y `datos_extra` de las solicitudes ya cerradas (aprobada→completada,
rechazada, cancelada) más viejas que `configuracion.retencion_meses_solicitudes`, pero
conserva folio, sacramento, estado y fechas para poder seguir contando cuántos bautizos
hubo en un año. Verificado con una solicitud cerrada de 40 meses: la purga la anonimizó
sin tocar una solicitud abierta más reciente.

**Separación de roles, verificada de punta a punta.** `secretaria` administra
`solicitudes.*` pero no puede tocar el catálogo de sacramentos (`sacramentos.editar`);
`editor` administra el catálogo pero no ve una sola solicitud. Es el reflejo exacto de
"quién ve datos personales" contra "quién edita el sitio" que exige
[`PRIVACIDAD.md`](PRIVACIDAD.md).

## Cursos e inscripciones

El catálogo de cursos y capacitaciones es la primera piedra del LMS de fase 2 (tareas,
entregas y calificaciones quedan fuera de esta fase), pero ya resuelve el problema
inmediato: publicar cursos con temario y recibir inscripciones con control de cupo.

**El correo es obligatorio aquí, a diferencia de otros formularios públicos.** En
contacto y en solicitudes de sacramento basta teléfono o correo; en la inscripción a un
curso el correo es la clave que evita una doble inscripción accidental
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
`solicitudes` e `inscripciones_curso`, `AuditoriaController::index()` no llama a
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

## Respaldos de la base de datos

Añadido después de cerrar las diez etapas del plan original, a petición explícita del
párroco/administrador. Replica el módulo `modules/backups` de `C:\xampp\htdocs\inventario`
—el mismo proyecto de referencia del resto de la arquitectura— adaptado a las convenciones
propias de este repositorio.

**Volcado en PHP puro, sin `mysqldump`.** `RespaldoModel::generarDump()` recorre
`SHOW TABLES`, y por cada una escribe `DROP TABLE IF EXISTS` + `SHOW CREATE TABLE` (la
estructura) y los datos en lotes de 200 filas vía `INSERT INTO ... VALUES` (para no
construir una sola sentencia gigante), leyendo con `PDOStatement::fetch()` en vez de
`fetchAll()` para no acumular la tabla completa en un arreglo de PHP. Es la misma razón que
justifica no depender de `mysqldump` en el resto del proyecto: un hosting de cPanel sin SSH
tampoco suele permitir `exec()`/`shell_exec()`, así que un volcado nativo vía PDO es la
única vía verdaderamente portable. Ver `docs/DESPLIEGUE.md`.

**Sin restauración automática, a propósito.** El panel solo genera, descarga y elimina
archivos `.sql`; para restaurar se importa desde phpMyAdmin, igual que en el proyecto de
referencia. Automatizar la restauración reintroduciría por la puerta trasera el mismo
riesgo (ejecutar SQL arbitrario desde la aplicación) que justifica no tener acceso SSH.

**`backups/` es una carpeta de infraestructura, con nombre en inglés como `uploads/` o
`assets/`**, no una excepción al "todo en español": las clases (`RespaldoModel`,
`RespaldoController`), la tabla (`respaldos_log`), las claves de permiso (`respaldos.*`) y
el texto de la interfaz sí lo respetan. La carpeta se crea sola en el primer respaldo
(`mkdir` recursivo) y está bloqueada por HTTP en `.htaccess`, igual que `config/`, `core/`,
`modules/`, `shared/`, `docs/` y `cli/`.

**`respaldos.*` es exclusivo de administrador**, igual que `usuarios.*` y `auditoria.*`:
sin entradas propias en la matriz de `config/app.php` para los demás roles, llega solo por
el comodín de `ROL_ADMIN`.

**`respaldos_log` une con `usuarios` en vez de desnormalizar el nombre**, igual que
`auditoria` — a diferencia del proyecto de referencia, que sí guarda `usuario_nombre` como
columna propia. Con el volumen de respaldos de una parroquia (generados a mano, sin cron),
la diferencia de rendimiento es irrelevante, y seguir el patrón ya establecido en
`auditoria` es más consistente que introducir uno nuevo solo para este módulo.

**Efecto autorreferencial, heredado del proyecto de referencia y aceptado tal cual:** como
`SHOW TABLES` se ejecuta después de que la tabla `respaldos_log` ya existe, cada respaldo
incluye una copia de su propio historial —incluidas las filas de respaldos anteriores—.
No es un error: es simplemente lo que implica volcar "todas las tablas" de una base de
datos que incluye la tabla que registra los volcados.

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
