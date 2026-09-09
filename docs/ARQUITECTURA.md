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
  entrar, mensajes flash y token CSRF —que aquí dura lo que la sesión y no rota en cada
  envío; ver "Seguridad"—.
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
               avisos · eventos · galeria · contacto · pagina · sitemap

área admin     auth · panel · agenda · configuracion · bloques · paginas · evangelio
               personas · centros · organigrama · horarios · sacramentos
               pastorales · mesc · catequesis · proclamadores · coros
               cursos · inscripciones · avisos · eventos · galeria · carrusel
               mensajes · usuarios · auditoria · respaldos
```

Los cuatro módulos de pastoral dedicada —`mesc`, `catequesis`, `proclamadores`, `coros`—
aparecen **solo en la tabla de administración**: ninguno tiene controlador público, a diferencia de los demás
módulos de contenido. La razón está en su propia sección, más abajo.

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

En la navbar hay además **una campana con lo que esta persona tiene sin abrir**, de las dos
cosas que llegan solas al panel sin que nadie las haya ido a buscar: los mensajes del
formulario de contacto (`mensajes_contacto.leido = 0`, que `MensajeController::ver()` ya
marca solo al abrir uno) y los avisos publicados hacia dentro que todavía no ha leído
(`aviso_lecturas`, ver "Publicar en dos escalones"). Empezó siendo solo un enlace a los
mensajes; hoy el globo lleva la **suma de las dos** —hasta `99+`, que es donde deja de caber
y de importar el número exacto— y al pulsarla se despliega la lista, con una sección por
origen, su encabezado y su propia cuenta. Cada aviso sale con el icono de su tipo
(`AvisoModel::icono()`), su título, su resumen recortado y una línea con el tipo y la
pastoral que lo publica —o «Toda la parroquia» cuando no tiene—; cada mensaje, con quién
escribe y su asunto. Cinco de cada uno como máximo, y un "y N más…" al listado completo
cuando hay más. Sin nada pendiente, la campana queda vacía y el desplegable dice «Nada sin
leer». Cuatro detalles que no son casuales:

- **Una sola campana, y no un icono por cosa.** Lo que se quiere saber al entrar es «¿hay
  algo?», y son dos números pequeños: dos campanas obligarían a mirar en dos sitios para
  responder a eso. La lista es justamente lo que resuelve el problema de una campana que
  suma dos orígenes distintos: si el clic tuviera que llevar a un solo destino habría que
  elegir entre los mensajes y los avisos, y para la mitad de las cuentas sería el
  equivocado.
- **El conteo se hace una sola vez por página.** `layout_admin.php` deja `$mensajesSinLeer`
  y `$avisosSinLeer` antes de incluir `admin_sidebar.php`, y el menú reusa las variables
  para sus insignias —hoy hay una en "Mensajes" y otra en "Avisos"— en vez de repetir las
  consultas. El parcial usa `empty()` y no `isset()` para que, incluido desde otro sitio sin
  esas variables, simplemente no dibuje el globo.
- **Cada mitad se cuenta y se anuncia solo a quien puede abrirla** (`mensajes.ver`, que
  llevan administración y secretaría; `avisos.ver`, que llega hasta el rol de consulta,
  porque es el de los miembros de a pie). Anunciarle a un coordinador que hay tres mensajes
  esperando sería enseñarle un dato personal a medias y darle una campana que no lleva a
  ninguna parte; sin ninguno de los dos permisos no hay campana, ni globo, ni consulta. En
  la práctica, una cuenta de administrador ve las dos secciones del desplegable y una
  coordinadora solo la de avisos.
- **La lista trae solo lo que se pinta en ella.** `ContactoModel::ultimosNoLeidos()`
  selecciona id, nombre, asunto y fecha, y nada más: el mensaje completo y el teléfono son
  datos personales, y su sitio es la ficha, que además deja constancia de la lectura en la
  auditoría (ver [`PRIVACIDAD.md`](PRIVACIDAD.md)).

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

`evangelios_dia` (ver "Evangelio del día", más abajo) no es un cuarto mecanismo de esta
tríada: los tres de arriba mantienen fija la estructura del sitio, mientras que
`evangelios_dia` es un cuarto **tipo** de contenido fechado, de la misma familia que
`avisos` o `eventos` —una fila por día, no una clave fija que alguien podría borrar—.

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
indexable por buscadores, y editable sin abrir Photoshop.

**Responsivo (revisión de módulos): la promesa de "se lee igual en celular" no estaba
respaldada por CSS.** `.arbol-organigrama`/`.nodo-organigrama` no tenían ni una sola regla
`@media` en `publico.css` ni en `app.css`: la indentación acumulada de `padding-left` por
cada nivel anidado, sumada a `.nodo-organigrama` sin `max-width` ni forma de romper texto
largo, podía desbordar horizontalmente toda la página en una pantalla angosta. Se agregó
`min-width: 0` + `overflow-wrap` en `.nodo-texto`, `max-width: 100%` en `.nodo-organigrama`,
un `@media (max-width: 575.98px)` que reduce la indentación y el tamaño de fuente, y un
`.arbol-organigrama-contenedor` con `overflow-x: auto` como red de seguridad si aun así algo
no cupiera, en vez de dejar que el desborde se propague al `<body>`.

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

**Vista de impresión/PDF: aquí sí es un diagrama de caja y línea (revisión de módulos).**
`NosotrosController::organigramaImprimir()` + `modules/nosotros/views/publico/
organigrama_imprimir.php` sirven una página independiente —sin `layout_publico.php`, vía
`Controller::renderSinLayout()` (ya pensado para esto: "fragmentos para peticiones
asíncronas, vistas de impresión")— con su propia hoja de estilos
(`assets/css/organigrama_imprimir.css`). Ahí sí se dibuja un árbol de caja-y-línea real
con el patrón clásico de pseudo-elementos `::before`/`::after` sobre `<li>` (línea vertical
del padre a la fila de hijos vía `ul::before`, conector en T entre hermanos, casos
especiales para hijo único y para primer/último hermano). Es exactamente el diseño que la
sección de arriba descarta para el sitio en vivo, y por la misma razón que ahí se evita
(frágil en CSS puro) aquí es viable: una página de impresión tiene tamaño de página fijo,
no un viewport que cambia en vivo entre celular y escritorio. Sin librería de PDF: el
botón "Imprimir / Guardar como PDF" solo llama a `window.print()`; quien lo abre usa el
diálogo de impresión de su propio navegador para guardar el archivo. Reutiliza
`OrganigramaModel::arbolPublico()` tal cual, con una función de render recursiva propia
(`organigrama_imprimir_nodo()`) distinta de `organigrama_render_nodo()`, porque el HTML
que necesita cada diseño es distinto de raíz.

### Calendario propio

**El motor vive en `core/Calendario.php`, compartido por las dos caras.** Nació dentro de
`EventoPublicoController`, que era el único calendario del sitio; al aparecer la agenda
interna del panel (ver "Agenda interna", más abajo) hubo que elegir entre duplicar las
cuatro vistas o compartirlas. El calendario de turnos MESC sí duplicó su cuadrícula, y con
razón —eran veinte líneas—; aquí son doscientas, y dos copias de eso se separan a la
primera corrección que se aplique solo en una. `Calendario` no construye URLs ni consulta
la base: recibe «ítems» —cualquier arreglo con `fecha_inicio`, `fecha_fin`, `todo_el_dia`,
`titulo` y `color`— y devuelve los periodos, sus saltos, sus títulos y las cuadrículas ya
repartidas por día. Lo propio de cada cara —qué se lee y a dónde enlaza— se queda en su
controlador.

Mejora progresiva de verdad, no solo de palabra: `EventoPublicoController::index()` arma
el periodo solicitado **en el servidor** y lo sirve como HTML normal — funciona sin
JavaScript, incluida la navegación y el cambio de vista, porque todos esos enlaces son
URLs comunes (`?vista=&fecha=`) que el propio controlador sabe responder. Sin
FullCalendar ni ninguna otra librería. Debajo del calendario, una lista de "próximos
eventos" en HTML plano cubre a quien tiene JavaScript desactivado.

**Cuatro vistas** (`?vista=dia|semana|mes|anio`, `mes` por omisión), cada una con su
plantilla en `modules/eventos/views/publico/vista_*.php`:

| Vista | Qué dibuja | Navega de |
|---|---|---|
| `dia` | La lista de la jornada, con hora, lugar y hasta cuándo dura | día en día |
| `semana` | Los siete días en columnas, domingo primero | semana en semana |
| `mes` | La cuadrícula de siempre | mes en mes |
| `anio` | Los doce meses en mini-cuadrículas; el día con eventos se marca | año en año |

`calendario()` es el único sitio donde se decide qué trae cada vista, y `calendario.php`
el único que dibuja la cabecera común (selector, periodo, contador) e incluye la
plantilla que toque. La fecha de referencia se resuelve en `fechaSolicitada()`, que
acepta `?fecha=Y-m-d` y también el `?anio=&mes=` anterior, para que los enlaces que ya
circulan sigan funcionando. Los saltos de periodo (`desplazar()`) normalizan al día 1
antes de sumar meses y al 1 de enero antes de sumar años: sin eso, "un mes menos" sobre
un día 31 y "un año menos" sobre un 29 de febrero se van al mes o al día equivocado.

`assets/js/calendario.js` intercepta los clics para pedir **el mismo bloque ya
renderizado** (`?accion=fragmento`, que devuelve `calendario.php` sin layout) y
sustituirlo sin recargar; si el `fetch` falla, cae al enlace normal sin más. Pide HTML y
no JSON a propósito: antes reconstruía la cuadrícula en JavaScript, duplicando lo que PHP
ya hacía, y con cuatro vistas distintas esa duplicación solo podía acabar separándose de
la de PHP. Para consumir los eventos como datos sigue estando `?accion=datos`, que
devuelve JSON con una entrada por día ocupado. Se llama `datos` y no `json` porque
`Controller` ya tiene un método `json()` para emitir la respuesta, y una acción de ruta
con ese mismo nombre lo taparía.

Un evento de varios días (`fecha_fin` en un día distinto a `fecha_inicio`) marca **todos**
los días que dura, no solo el de inicio (revisión de módulos: antes solo aparecía el primer
día, y desaparecía del todo del calendario en cuanto el mes cruzaba mientras seguía en
curso). `EventoModel::entreFechas()` trae cualquier evento cuyo rango
`[fecha_inicio, fecha_fin]` se traslape con el periodo pedido, aunque haya empezado antes
o termine después —`delMes()` es un atajo suyo—; luego
`EventoPublicoController::diasDelEvento()` recorta ese rango a los días que de verdad
caen dentro, y `repartirPorDia()` deja el resultado indexado por fecha, que es lo que
consumen las cuatro plantillas y también `datos()`. El contador del encabezado cuenta
eventos distintos, no días ocupados: uno de nueve días es un evento aunque se dibuje en
nueve casillas. La ficha de detalle (`eventos/publico/detalle.php`) sigue la misma idea:
si `fecha_fin` cae en un día distinto de `fecha_inicio` muestra el rango completo ("Del …
al …"), no solo la fecha de inicio con ambas horas pegadas.

**El listado del panel filtra por día, mes y año de `fecha_inicio`**, además del filtro de
estado (todos / publicados / borradores). Con la agenda de un año entero cargada son 467
eventos, o 32 páginas de 15: encontrar uno concreto pasando páginas no es viable. Los dos
filtros se combinan y cada uno arrastra el estado del otro en sus enlaces, y la paginación
conserva los dos.

Los tres campos de fecha son independientes y se combinan en cualquier orden: un año
entero, un mes de un año, un día concreto, o "los días 16 de cualquier mes".
`EventoModel::condicionFecha()` compara por rango de fechas siempre que hay año, en vez de
con `YEAR()`/`MONTH()`/`DAY()`, para no dejar fuera el índice `idx_eve_fecha`; sin año no
queda más remedio que usarlas. Un día que no existe en su mes devuelve `1 = 0` en vez de un
rango: sin eso, el "29 de febrero de 2026" se normalizaría al 1 de marzo y el listado
mostraría los eventos de otro día.

Son **cuatro** filtros combinables: estado, fecha, pastoral (todas / solo las mías / una
concreta) y sede. Los dos últimos se cruzan con el alcance de quien mira —ver "Coordinarse y
administrar son dos pantallas, no una" y "El alcance tiene dos mitades"—, así que dentro de
lo que le corresponde puede acotar a mano. El de sede no se dibuja cuando solo hay una que
elegir. Cada filtro arrastra el estado de los demás en sus enlaces, y la paginación los
conserva todos.

El selector de año se llena con `aniosConEventos()`, que solo ofrece los años que de verdad
tienen eventos y acompaña al filtro de pastoral que esté puesto —para que los años ofrecidos
correspondan a lo que se está viendo, no a todo el histórico—; el de día ofrece los del mes
elegido (28, 29, 30 o 31), y 31 mientras no haya mes. Cualquier valor fuera de rango se
ignora y el listado vuelve a mostrarlo todo: es un filtro, no una búsqueda que deba fallar
con un error. Si el día deja de existir al cambiar de mes —del 31 de enero a febrero— se
descarta el día y queda el mes, que es lo que la persona tenía delante.

### Agenda interna: el calendario del equipo, aparte del del sitio

El sitio tenía un solo calendario, el público, y el panel una lista paginada. Para
coordinarse entre pastorales eso no alcanza: lo que el equipo necesita ver es *todo* lo que
hay programado —lo publicado y lo que sigue en borrador, los eventos y también los cursos—,
y el sitio público no debe enseñar ni una cosa ni la otra.

De ahí `modules/agenda/`, en `/admin/agenda`, con las mismas cuatro vistas que el público
(día, semana, mes, año) y **sin tocar el calendario del sitio**, que quedó exactamente como
estaba. Tres decisiones lo definen:

- **Mezcla eventos y cursos.** `EventoModel::agenda()` y `CursoModel::agenda()` son las
  consultas de esta pantalla; ninguna filtra por `publicado`, que es justamente la
  diferencia con las públicas. `AgendaController` normaliza cada fila a la forma que
  `Calendario` espera: un curso tiene `fecha_inicio`/`fecha_fin` en DATE, sin hora, así que
  entra como «todo el día» y su `horario` ("martes de 19:00 a 21:00") viaja como texto,
  que es como está escrito. Un curso sin fecha de inicio no cabe en ninguna casilla: en
  vez de desaparecerlo en silencio, `sinFechas()` los recoge y la vista los lista debajo
  del calendario.
- **Sin AJAX**, igual que el calendario de turnos MESC y a diferencia del público: cada
  cambio de periodo es un enlace normal que recarga. El panel no tiene el tráfico que
  justifique un endpoint de fragmentos aparte.
- **Los estilos se copiaron otra vez de `publico.css` a `app.css`**, ahora los de las
  vistas de día, semana y año —MESC ya había copiado los de la cuadrícula de mes—, porque
  el panel carga una hoja distinta y las dos no comparten variables de color. Es
  duplicación consciente, marcada con un comentario en ambos bloques.

`agenda.ver` es un permiso propio, y lo tienen todos los roles del panel que administran o
consultan contenido, **incluidos los seis de Consulta**: para un ministro, catequista o
proclamador es la única pantalla que verán además de la de su pastoral, y es la que responde
"¿qué hay programado esta semana?" sin darles con qué editar nada.

### Coordinarse y administrar son dos pantallas, no una

Hasta aquí, el alcance por pastoral servía para dos cosas a la vez: qué se puede escribir y
qué se puede *ver*. `EventoModel::listar()` recibía `Auth::pastoralesPermitidas()` y un
coordinador solo veía sus eventos en el panel.

Eso hace imposible coordinarse. Si la pastoral juvenil no ve que catequesis ya tiene el
salón ocupado el sábado, lo va a apartar igual. Pero lo contrario tampoco funciona: un
listado de trabajo con los eventos de las seis pastorales mezclados no sirve para
administrar los propios. Cada pregunta acabó teniendo su pantalla:

- **La agenda interna no recorta nada.** `/admin/agenda` enseña todas las pastorales,
  publicado y borrador, eventos y cursos. Es *la* pantalla de coordinarse, y por eso
  `agenda.ver` lo tienen hasta los seis roles de Consulta. Ninguna de sus consultas pasa
  por el alcance.
- **Los listados de eventos y cursos traen lo suyo y lo general.**
  `Controller::pastoralesVisibles()` cruza el filtro elegido en pantalla
  —`filtroPastoral()`: vacío, `mias` o el id de una— con el alcance real de quien mira.
  Sin alcance global eso son sus pastorales **más el contenido sin pastoral**: 311 de los
  467 eventos de la agenda 2026 son parroquiales generales, y esconderlos dejaría a cada
  pastoral administrando su propio recorte del calendario. Los generales viajan al modelo
  como un `null` dentro de la lista de ids, porque `IN (…)` nunca casa con `NULL`
  (`Model::condicionPastoral()`).
- **Escribir sigue igual de acotado.** `requireAlcancePastoral()` con el `pastoral_id`
  leído de la base, en editar, guardar y eliminar, sin una sola excepción; y los botones de
  editar y borrar no se dibujan sobre lo ajeno, por lo mismo que ya se aplicó al calendario
  de turnos MESC cuando aparecieron los roles de consulta: un enlace que acaba en «ese
  contenido pertenece a otra pastoral» es peor que no tener enlace. El modal de borrado
  tampoco se genera —ocultar el botón y dejar el modal en el HTML sería teatro—, aunque el
  límite de verdad siga estando en el controlador. Un evento general se ve pero no se toca:
  `Auth::puedeSobrePastoral(null)` es falso para todo el que no tenga alcance global.

El selector de pastoral del listado se acota igual (`pastoralesDelFiltro()`): ofrecer una
pastoral cuyos eventos ese listado no va a mostrar es prometer algo que no ocurre. De paso
cierra la puerta trasera, porque un id que no está en el selector cae a «todas» —que para
esa persona son las suyas y las generales—, así que escribir `?pastoral=` a mano no asoma a
lo ajeno.

**Avisos y galería no cambiaron**: ahí `filtroPastoralSql()` sigue recortando el listado al
alcance. No es incoherencia sino falta de motivo: nadie necesita leer los borradores de
avisos de otra pastoral para no pisarse, que es lo que sí pasa con las fechas. El día que
haga falta, el camino ya está hecho.

**Los cursos pasaron a tener alcance de verdad.** `cursos.pastoral_id` existía desde el
principio, pero era "solo una etiqueta organizativa" —así estaba escrito en `CursoModel`—
porque únicamente admin y editor entraban al módulo: `pastoral_id` se tomaba del POST tal
cual y ninguna acción comprobaba nada. Al darle `cursos.crear`/`cursos.editar` al
coordinador y a los tres administradores de pastoral, eso dejó de ser inocuo, y el módulo
adoptó el mismo patrón que eventos: selector acotado con `opcionesPastoral()`,
`pastoralIdValidado()` al guardar y `requireAlcancePastoral()` al editar, borrar y también
al tocar el temario, que hereda el alcance de su curso en vez de tener el suyo. Con
`cursos.publicar`, igual que sus eventos: ver abajo.

### Publicación: el calendario lo publica quien lo organiza

`avisos.publicado` y `eventos.publicado` arrancan en 0, y los controladores comprueban el
permiso `*.publicar` por separado de `*.crear`/`*.editar`. Esa separación es la que permite
que el mismo formulario sirva para dos regímenes distintos, sin una línea de código
condicional más allá de `Auth::tienePermiso()`:

- **Fechas —eventos y cursos— las publica la propia pastoral.** Además de `admin` y
  `editor`, tienen `eventos.publicar` y `cursos.publicar` el coordinador y los tres
  administradores de pastoral (MESC, Catequesis, Proclamadores). Una fecha en el calendario es
  algo que la pastoral ya decidió, y que las demás necesitan ver publicada para no pisarla;
  hacerla esperar a que un editor pase a revisarla convertía la moderación en un cuello de
  botella sobre información que de todos modos es cierta.
- **Textos —avisos y galería— siguen moderados.** Ahí el coordinador tiene
  `*.crear`/`*.editar` pero no `*.publicar`: un aviso va dirigido a toda la parroquia y
  entra como borrador para que un editor lo revise. `galeria.publicar` sigue el mismo
  patrón, independiente de `galeria.editar`.

Cuando falta el permiso, el controlador fuerza el valor guardado (`$puedePublicar ?
$this->postBool('publicado') : …`) y el formulario cambia la casilla por el aviso de que
eso se enviará como borrador. El límite está en el controlador; la vista solo lo explica.

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

Desde que la campana de la barra cuenta avisos sin leer, `vigente_hasta` gobierna también
una consulta que no es pública: `AvisoModel::sinLeer()` y `contarSinLeer()` no miran
`publicado` ni recortan por mes, pero sí descartan lo vencido, porque es lo único que apaga
un aviso que nadie llegó a abrir. Ver "Publicar en dos escalones".

No se replicó el mismo campo en `eventos`: un evento ya tiene su propio ciclo de vida
(`fecha_inicio`/`fecha_fin`), y ocultar automáticamente los que ya pasaron trabajaría contra
el interés de conservar un registro histórico de lo organizado.

### Lo último de Facebook, en la portada

`modules/inicio/views/publico/index.php` muestra, cuando `Config::tiene('facebook')`, el
Page Plugin oficial de Facebook: un `<iframe>` a `facebook.com/plugins/page.php` que la propia
Facebook mantiene con las últimas publicaciones de la página, en su propio diseño. Reutiliza
el campo `facebook` de Configuración → Redes sociales que ya alimentaba el icono del pie —no
se agregó un campo nuevo—, así que en cuanto hay una URL capturada ahí, el widget aparece
solo.

Se eligió esta variante y no la API de Graph con un Page Access Token por lo que exige cada
una: el Page Plugin es un iframe público, sin cuenta de desarrollador, sin token que
renovar y sin caché que mantener del lado del servidor; a cambio, el diseño de esa tarjeta
es el de Facebook, no personalizable con el CSS del sitio. Es la variante consistente con
"cero dependencias en el servidor": nada que el sitio deba pedir, cachear ni volver a
autenticar.

**Único cambio de superficie:** `frame-src` en la CSP del `.htaccess` suma
`https://www.facebook.com`. No hizo falta tocar `script-src`: el Page Plugin sin SDK no
carga ningún script en el documento del sitio, todo el JavaScript que pinta el feed corre
dentro del propio `<iframe>`, bajo el origen y la CSP de Facebook, no la del sitio.

## Roles y permisos

```
ROL_ADMIN               Todo, incluidos usuarios, configuración y auditoría.
ROL_EDITOR              Todo el contenido del sitio; publica y modera.
                        Sin acceso a usuarios ni configuración.
ROL_COORDINADOR         Su pastoral en UNA sede. Publica sus eventos, sus
                        cursos y sus avisos (los dos escalones); su galería
                        la sube, pero publicarla no le toca. NO edita la ficha
                        de la pastoral.
ROL_COORDINADOR_GENERAL Lo mismo, en varias sedes o en todas. Además edita la
                        ficha de su pastoral y administra las cuentas
                        Coordinador y Consulta de la misma.
ROL_CONSULTA            Solo mira lo de su pastoral y su sede.
ROL_SECRETARIA          Inscripciones y mensajes. No edita el sitio.
```

**Lo mismo, dicho en el panel.** `ROLES_DESCRIPCION` (en `config/app.php`, junto a
`ROLES_NOMBRES` y a un paso de la matriz) explica cada rol en una frase, y esa frase sale
donde hace falta: debajo del selector de rol al dar de alta o editar una cuenta —cambia al
cambiar de opción—, en el modal de perfiles adicionales, y al pasar el ratón sobre el rol en
el listado de cuentas. Es texto para quien reparte accesos, no para quien lee código: dice qué
puede hacer el rol, nunca sobre qué pastoral. Vive en el mismo archivo que la matriz a
propósito, para que quien cambie lo que un rol puede hacer tenga delante la frase que lo
explica; y viaja hasta la vista en el `data-descripcion` de cada `<option>`, de modo que
agregar un rol no obligue a acordarse de una segunda lista en JavaScript.

**El rol dice qué puede hacer; la pastoral y la sede asignadas, sobre qué.** Hubo seis roles
con la pastoral en el nombre —`admin_mesc`, `consulta_catequesis`, `admin_lector`…— pensados
para que se leyera de un vistazo qué administraba cada cuenta. Se retiraron cuando aparecieron
**tres coordinadoras de catequesis, una por comunidad**: el rol no sabía distinguirlas, porque
lo que las separa es la sede, no la función. Hoy «coordinadora de catequesis en Jesús el
Señor» es el rol Coordinador con Catecismo y esa sede, y el nombre bonito —su cargo real—
vive en su ficha del equipo pastoral, que es donde ya estaba.

Coordinador y Coordinador general **parten de la misma lista**, `PERMISOS_COORDINACION`, para
que no puedan divergir por descuido: el general le suma lo suyo con un `array_merge` y ahí
está, en una línea, todo lo que los separa —hoy, editar la ficha de su pastoral y administrar
las cuentas de la misma—. Lo demás lo exige el formulario de usuarios: el primero necesita
**exactamente una** sede marcada y el segundo admite varias o ninguna. Sin esa regla, un
coordinador de sede al que se le olvidara marcarla acabaría mandando en las tres, que es justo
el error que se quería evitar.

**La ficha de la pastoral no la edita quien coordina una sola sede.** `pastorales.editar` salió
de `PERMISOS_COORDINACION` a petición de la parroquia: esa ficha —el responsable, el correo, el
horario de reunión, la descripción que se publica en el sitio— es de la pastoral entera, y
quien coordina una comunidad no tiene por qué poder cambiar lo que se dice de todas. Sigue
viendo el panel de su pastoral y trabajando en él (avisos, eventos, cursos, documentos,
actividades y su módulo dedicado); lo único que desaparece para ese rol es el botón "Editar
pastoral", que no se dibuja sin el permiso.

**Los cuatro módulos dedicados se ofrecen por pastoral, no por permiso.** `mesc.*`,
`catequesis.*`, `proclamadores.*` y `coros.*` los lleva cualquier coordinador, así que
mostrarían los cuatro a todo el mundo si solo se mirara el permiso; `Auth::administraPastoral(PASTORAL_MESC)` y sus
gemelas son las que deciden, y el controlador del módulo lo revalida con
`puedeSobrePastoral()`. Los slugs de esas cuatro pastorales están en `config/app.php`
(`PASTORAL_MESC`, `PASTORAL_CATEQUESIS`, `PASTORAL_PROCLAMADORES`, `PASTORAL_COROS`) en vez
de repetidos a mano en cada modelo.

Este cruce vive en **dos** sitios, no uno: el menú lateral
(`shared/views/parciales/admin_sidebar.php`) y las tarjetas de acceso rápido del panel
(`modules/panel/views/index.php`) —cada uno arma su propia lista de secciones y aplica el
mismo `Auth::administraPastoral()` por su cuenta—. Los dos hay que revisarlos cada vez que
se agrega un módulo dedicado: un permiso nuevo sin este cruce en cualquiera de los dos
vuelve a abrir el mismo hueco que dejaba ver la tarjeta de un módulo ajeno aunque el clic
ya estuviera bien protegido. Coros, el cuarto, fue la primera vez que se recorrió esta
lista entera, y la advertencia resultó exacta: son dos sitios, no uno.

**En el menú lateral son ya una sección propia, "Pastorales".** Estaban sueltos dentro de
"Parroquia", entre el catálogo de pastorales y Sacramentos, y ahí no es su sitio: estos
módulos no administran la parroquia entera sino una pastoral concreta, y van a ser varios.
La sección nueva va después de "Parroquia" y antes de "Trámites", y quien no administre
ninguna de esas pastorales no ve ni su encabezado. Coros entró ahí añadiendo una línea al
array, que era exactamente lo que este cambio perseguía. De paso, la lista dejó de ser tres
variables `$verMesc`/`$verCatequesis`/`$verProclamadores` con sus tres bloques `if`
repetidos y pasó a ser un array, `$modulosDePastoral`, con una entrada por módulo —módulo,
etiqueta, icono, permiso y slug de pastoral— filtrada por el mismo `Auth::tienePermiso()`
**y** `Auth::administraPastoral()` que ya se aplicaba: agregar un módulo al menú es hoy una
línea ahí, no un bloque más que copiar. El enlace general "Pastorales" —el catálogo de
todas— se queda en la sección "Parroquia", junto a Equipo pastoral y Organigrama, porque
eso sí es de la parroquia entera.

**Cursos se movió al mismo tiempo, de "Parroquia" a "Comunicación"**, detrás de Avisos y
Eventos. Es el grupo al que pertenece: los tres se administran por pastoral, los tres se
publican en dos escalones y los tres son contenido que se anuncia, a diferencia de los
horarios o las sedes, que son catálogos de la parroquia. Las inscripciones que llegan a un
curso sí son un trámite y siguen en su propia sección, que es la distinción que este menú
venía sin hacer. Las tarjetas del panel de inicio (`/admin/panel`) no tienen secciones, así
que ahí el orden no cambió.

### La cuenta es de alguien del equipo pastoral

`personas` es el registro principal de quién es quién: de ahí sale el organigrama
(`organigrama_nodos.persona_id`) y de ahí salen ahora también las cuentas
(`usuarios.persona_id`, único, `ON DELETE SET NULL`). El formulario de usuarios empieza
preguntando **quién es**, y solo ofrece a las personas activas que aún no tienen cuenta.

Lo pidió el uso real: la coordinadora general de catequesis estaba escrita de tres formas
distintas entre las dos tablas —Ivett, Ivette, Iveth; Vilanueva, Villareal— y el alcance de
otra decía una cosa en su ficha y otra en su cuenta. Con el vínculo, **el nombre, el teléfono
y la foto son de la ficha**: la cuenta guarda copia y `PersonaModel::sincronizarCuenta()` la
refresca cada vez que se guarda la ficha, así que no hay dos versiones que puedan discrepar.

Tres decisiones que lo acotan:

- **El vínculo es opcional.** Una ficha con `activo = 1` se publica en «Quiénes somos», y la
  cuenta técnica del administrador no tiene por qué salir en el directorio de la parroquia.
  `persona_id` nulo es legítimo y el listado lo marca como «Sin ficha en el equipo».
- **Los permisos no se heredan de la ficha, se copian al crear.** Si no se marca alcance en el
  formulario, se toman las pastorales y sedes de la ficha —lo que evita cuentas sin nada
  asignado—, pero a partir de ahí viven en la cuenta. Servir en una pastoral no es
  administrarla: el vicario figura en Raíces y en las tres sedes, y no por eso publica nada.
  Si el alcance se leyera de la ficha, cualquier retoque del organigrama cambiaría permisos
  sin que nadie lo pidiera.
- **Una persona, una cuenta** (`uq_usr_persona`). `UsuarioController::personaDelPost()` lo
  comprueba antes para poder explicarlo con un mensaje, pero el límite de verdad es el índice.

### Coordinador general también administra cuentas — de su propia pastoral

`usuarios.*` era, hasta esta revisión, exclusivamente del administrador: toda cuenta nueva
—incluida la de un coordinador de sede— la daba de alta él. En la práctica eso convertía a
un administrador técnico en el cuello de botella para algo puramente organizativo: la
coordinadora general de una pastoral es quien sabe si necesita un coordinador nuevo en una
sede, no quien administra la base de datos.

`ROL_COORDINADOR_GENERAL` tiene ahora `usuarios.ver`/`crear`/`editar`, pero acotados por
`UsuarioController::dentroDeMiAlcance()`, que exige las **dos** condiciones a la vez:

- **La cuenta objetivo es de rango que administra**: `ROL_COORDINADOR` o `ROL_CONSULTA`,
  nunca otro `ROL_COORDINADOR_GENERAL` —ni siquiera de su misma pastoral, eso sigue siendo
  cosa del administrador—, y nunca `ROL_SECRETARIA`, `ROL_EDITOR` ni `ROL_ADMIN`.
- **Comparte alguna de sus propias pastorales** (`Auth::pastoralesPermitidas()` cruzado con
  las de la cuenta objetivo). La sede no entra en esta cuenta: administrar cuentas es por
  pastoral entera, no por sede, porque quien da de alta a un coordinador de sede necesita
  poder hacerlo aunque él mismo tenga otra sede asignada.

Esta doble condición se aplica en tres sitios con la misma función, nunca solo en la vista:
`index()` filtra el listado (`UsuarioModel::todos()` recibe las pastorales del que mira, o
`null` con alcance global), y `editar()`/`guardar()`/`eliminar()` la revalidan por id antes de
tocar nada — llegar por URL a la cuenta de otra pastoral, o a un rango superior, redirige con
"Esa cuenta no está dentro de lo que administras" en vez de un error de PHP.

**Deliberadamente sin excepción para "es mi propia cuenta".** El rol de un Coordinador
general (`coordinador_general`) no está entre los que puede asignar
(`UsuarioController::rolesAsignables()` solo ofrece `coordinador`/`consulta` sin alcance
global), así que si se editara a sí mismo el `<select>` de rol no traería su propio valor
entre las opciones, y guardar lo degradaría a Coordinador sin avisar. No poder auto-editarse
desde esta pantalla no es una regresión: antes de este cambio tampoco podía, porque la
pantalla entera era solo del administrador.

Tres capas de validación server-side, no solo ocultar opciones en el formulario: el rol
enviado se revalida contra `rolesAsignables()` (no solo lo que el `<select>` ofrece), y las
pastorales/sedes enviadas se revalidan como subconjunto de `Auth::pastoralesPermitidas()`/
`centrosPermitidos()` — un POST manipulado que intente asignar una pastoral ajena o un rol
superior se rechaza con un mensaje, nunca se guarda parcialmente.

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

- Toda escritura sobre `avisos`, `eventos`, `cursos`, `galeria_imagenes` y
  `pastoral_actividades` llama a `requireAlcancePastoral()` con el `pastoral_id` **leído de
  la base de datos** cuando se edita o borra, nunca el que venga en el POST.
- Los listados del panel de `avisos` y `galeria_imagenes` pasan `pastoralesPermitidas()` al
  modelo, que añade `AND pastoral_id IN (…)`. **Eventos, cursos y la agenda ya no**: ahí
  leer es de todos y solo escribir está acotado — ver "Ver es de todos, editar es de la
  pastoral dueña".
- Al crear, el coordinador no elige pastoral en un select abierto: si tiene una sola, va
  en un campo oculto; si tiene varias, el select se construye solo con las suyas. En
  ambos casos se revalida en el servidor.
- `pastoral_id NULL` significa contenido parroquial global. Un coordinador nunca lo toca.

### Administrador y Consulta por pastoral (revisión de módulos), y su retirada

Las pastorales con módulo propio —MESC, Catequesis, Proclamadores— tuvieron durante un tiempo un
par de roles con nombre explícito cada una: `ROL_ADMIN_MESC`/`ROL_CONSULTA_MESC` y sus
equivalentes. La idea era que crear la cuenta diera de una vez claridad sobre qué
administraba, en vez de un rol abstracto más una asignación de pastoral aparte.

**Se retiraron.** El nombre del rol es un mal sitio para guardar un dato: en cuanto la misma
pastoral tuvo tres coordinadoras, una por comunidad, «Administrador Catequesis» dejó de
identificar a nadie, y las seis entradas de la matriz eran seis copias de la misma lista de
permisos esperando a divergir. Hoy quedan tres roles acotados —Coordinador, Coordinador
general y Consulta—, la pastoral y la sede se asignan, y el nombre con el que la parroquia
llama a cada quien vive en su ficha del equipo pastoral, que el listado de usuarios muestra
debajo del rol. `ROLES_CON_ALCANCE_PASTORAL` sigue agrupándolos para que el formulario y su
guardado no repitan `=== ROL_COORDINADOR` en cada punto.

Lo que sí se conservó es la idea de **Consulta**: solo lectura, para que un ministro,
catequista o proclamador de a pie entre al panel a ver su propio calendario y sus documentos sin
poder cambiar nada; con `agenda.ver` ve además lo que hay programado en toda la parroquia,
que es la pregunta que traía la mayoría de las veces.

### Perfiles adicionales: un rol distinto sobre otra pastoral, sin segunda cuenta

Retirar los roles con la pastoral en el nombre resolvió "la misma pastoral, varias
coordinadoras" (arriba), pero dejó abierto el caso inverso: **la misma persona, dos
responsabilidades de nivel distinto a la vez**. Zulema es Coordinadora de Catequesis
(administra) y, en Ministro Extraordinario de la Sagrada Comunión, solo Ministro sin cargo
—necesita, cuando mucho, Consulta ahí, nunca escritura—. `usuarios.rol` es un único ENUM
por cuenta, aplicado igual a todas las pastorales que administra: no hay forma de decir
"coordinador en Catequesis, consulta en MESC" con una sola fila de `usuarios.rol`.

La primera idea —darle una segunda cuenta— choca con el esquema a propósito:
`usuarios.email` y `usuarios.persona_id` son ambos `UNIQUE`. Con dos cuentas y el mismo
correo se violaría `uq_usr_email`; con dos correos distintos, `Auth::intentarLogin()`
resolvería cada uno a su cuenta sin ninguna ambigüedad que preguntar —serían dos logins
independientes, como si fueran dos personas distintas del sistema—, perdiendo justo la
idea de "un solo login que pregunta con cuál entrar".

En vez de eso, `usuarios_perfiles`: perfiles adicionales de la MISMA cuenta (mismo correo,
misma contraseña, misma ficha de Persona vinculada), cada uno con su propio `rol` y una
sola pastoral (y opcionalmente una sede). Al iniciar sesión, `Auth::intentarLogin()`
verifica la contraseña igual que siempre; si la cuenta no tiene perfiles adicionales
activos —el caso de casi todas—, el login sigue siendo un único paso, sin cambios. Si
tiene uno o más, en vez de completar el llenado de sesión de inmediato, queda un estado
transitorio (`_login_pendiente_id`, expira a los 10 minutos) con `usuario_id` real **aún
sin fijar** —`Auth::estaAutenticado()` sigue en `false`, así que nada del panel es
alcanzable todavía— y se muestra una pantalla para elegir con cuál perfil entrar: el
principal de la cuenta o uno de los adicionales. Al elegir, `Auth::completarLogin()`
puebla la sesión (mismo bloque de `Session::set()` de siempre) con el rol y el alcance de
ese perfil.

No es un pivote como `usuarios_pastorales`/`usuarios_centros`: `pastoral_id` y `centro_id`
son columnas directas en `usuarios_perfiles`, porque un perfil adicional es, por
definición, "un rol distinto sobre una sola pastoral distinta" —si fuera el mismo nivel,
esa pastoral simplemente se agrega al checklist normal de la cuenta—.

**Efecto colateral que había que cerrar de raíz**: la herencia de pastoral/sede desde la
ficha de Persona hacia la cuenta (ver "La cuenta hereda de su ficha, no de un checklist
propio" más abajo) sincronizaba `usuarios_pastorales` para que fuera *exactamente* igual a
`persona_pastorales`. Zulema pertenece a MESC en su ficha (es Ministro ahí) sin
administrarlo; sin corrección, guardar su ficha por cualquier motivo —el teléfono, un
typo— le habría dado de golpe escritura completa sobre MESC, incluidas las visitas con
datos de salud. La sincronización ahora resta `UsuarioPerfilModel::pastoralesReservadas()`
antes de heredar: una pastoral cubierta por un perfil adicional deja de heredarse del lado
de la ficha con el rol principal.

`Auth::iniciarImpersonacion()` ("Usar como…") no pasa por esta pantalla: entra siempre con
el perfil principal de la cuenta objetivo. Es deliberado, no una omisión —impersonar ya es
una operación de Administrador con su propio permiso; añadirle una segunda elección encima
sería fricción sin necesidad real hasta hoy—.

Además de elegir al iniciar sesión, quien ya tiene sesión abierta puede cambiar entre sus
propios perfiles sin cerrar sesión: un botón "Cambiar de perfil" en el menú de la cuenta
(visible solo si tiene 1+ perfiles adicionales activos), que lleva a
`AuthController::cambiarPerfil()`. No pide contraseña de nuevo —la sesión ya demostró quién
es, igual que "Usar como…" tampoco la vuelve a pedir— y reutiliza el mismo
`Auth::completarLogin()` del login normal, solo que `usuario_id` no cambia: es la misma
cuenta, otro rol y alcance. Deliberadamente no disponible mientras se impersona (para no
mezclar dos cambios de identidad a la vez): el botón no se muestra si
`Auth::estaImpersonando()`, y el controlador lo rechaza igual si alguien llega ahí por otra
vía.

### Un botón sin permiso no se muestra, no se muestra deshabilitado

Antes de los roles de Consulta, cualquiera que pudiera *ver* un módulo (coordinador,
editor, admin) también podía *editarlo* — nunca hizo falta que una vista distinguiera
entre ambos. Consulta rompió ese supuesto (solo tiene `X.ver`) y expuso un hueco real en
las vistas de MESC (construidas antes de que existiera ese rol) y, por copiarlas tal
cual, también en Catequesis y Proclamadores: botones de Nuevo/Editar/Eliminar sin ningún
`Auth::tienePermiso()`, y —el más importante— el calendario de turnos enlazaba cada
evento directo a `turno_editar`, así que un usuario de Consulta que le diera clic caía
en `requirePermiso('mesc.editar')`, era redirigido a `/admin/panel` con un error de
permisos, y no entendía por qué. `requirePermiso()` y `requireAlcancePastoral()`
comparten ese mismo destino (`Controller.php`), así que cualquier acción sin permiso —no
solo un botón, un enlace directo como el del calendario— termina ahí.

La regla, ya aplicada en MESC, Catequesis y Proclamadores: si `Auth::tienePermiso()` es falso
para la acción, el botón o enlace **no se dibuja**, no se muestra gris ni deshabilitado.
En el calendario de turnos, el evento sigue mostrándose (`<span>` en vez de `<a>`,
mismo color y título) para que Consulta vea su turno, solo que no es clickeable. Esto
es además de la comprobación real en el controlador (`requirePermiso()` sigue ahí):
ocultar el botón es una cortesía de UX, no el límite de seguridad.

### El alcance tiene dos mitades: la pastoral y la sede

Una pastoral no vive en un solo sitio. La catequesis se da en la sede y en los dos centros,
y cada comunidad tiene su coordinadora: Zulema en la parroquia, Carmelita en Jesús el Señor,
Yaneth en San Pío; por encima, una coordinación general que responde por las tres. Con el
alcance atado solo a `pastoral_id`, las tres coordinadoras eran indistinguibles: quien
tuviera «Catecismo» tenía toda la catequesis de la parroquia.

De ahí `eventos.centro_id` y `cursos.centro_id`, y la regla que las junta:

```
puede tocar  ⇔  Auth::puedeSobrePastoral(pastoral_id)  ∧  Auth::puedeSobreCentro(centro_id)
```

Las dos mitades no funcionan igual, y esa asimetría es deliberada:

| | Sin ninguna asignada | Con algunas asignadas | Contenido sin valor (NULL) |
|---|---|---|---|
| **Pastorales** (`usuarios_pastorales`) | no puede con nada | solo con esas | solo alcance global |
| **Sedes** (`usuarios_centros`) | **puede en todas** | solo en esas | solo quien no tenga sedes |

No marcar sedes es la forma de decir «coordinación general», que es lo que son Aimeé en MESC
e Ivett en catequesis; y no marcar pastorales sigue siendo no tener nada que administrar. Por
eso son dos tablas con reglas propias y no una sola columna.

Del lado de la lectura, `Controller::centrosVisibles()` hace con la sede lo mismo que
`pastoralesVisibles()` con la pastoral, y `EventoModel::listar()` aplica las dos condiciones
con la misma `Model::condicionAlcance()`, que recibe la columna como argumento —de ahí que
el nombre del marcador SQL se derive de la columna: con un prefijo fijo, el segundo juego de
parámetros pisaría al primero—. En los formularios, `opcionesCentro()` y `centroIdValidado()`
son los gemelos de `opcionesPastoral()` y `pastoralIdValidado()`: quien trabaja en una sola
sede no ve un desplegable de una sola opción, la lleva en un campo oculto, y el POST se
revalida contra sus sedes reales.

**La agenda interna no aplica nada de esto**, a propósito: ahí se ven las tres sedes y todas
las pastorales, porque su función es que nadie aparte el mismo salón dos veces. Lo único que
respeta el alcance es el lápiz de editar.

**Los módulos dedicados —MESC, Catequesis, Proclamadores, Coros— siguen siendo por pastoral,
sin sede.** Cada uno resuelve la suya por slug (`MescModel::pastoralId()`), así que los
catequistas, los periodos y los turnos son de la pastoral entera y los comparten las tres
coordinadoras. Separarlos por sede exigiría que el catálogo de pastorales se desdoblara —una
«Catequesis» por comunidad— y que esos módulos supieran elegir entre las hermanas; es un
trabajo distinto y hoy no hace falta, porque lo que se pisaba eran las fechas, no los
catequistas.

### Alcance por centro/sede (issue #3), y por qué se retiró la herencia

Cada pastoral está ligada a un `centro_id` (FK a `centros`, `ON DELETE SET NULL`, NULL en
las que ya existían antes de este campo). El issue pidió, además de "usuarios
administradores de la pastoral" (ya cubierto por `usuarios_pastorales`), "usuarios por
centro/sede": alguien que administrara San Pío de Pietrelcina completo no tendría que
marcar, una por una, cada pastoral que ese centro tenga hoy o llegue a tener mañana. Se
implementó con `usuarios_centros`, la pivote análoga a `usuarios_pastorales`, y
`Auth::cargarPastorales()` devolvía la **unión** de las dos fuentes con un `UNION` SQL.

**Esa herencia se quitó.** El uso real la desmintió: MESC opera en las tres sedes, así que
a su administradora se le marcaron los tres centros —que es lo que parecía significar «esta
persona trabaja en los tres centros»—, y lo que el `UNION` le entregó fueron las **cinco**
pastorales ligadas al centro principal: MESC, Catecismo, AMA, Raíces y Grupo JECSA. Terminó
pudiendo editar los cursos de catequesis. El problema no era el dato mal capturado sino la
regla: en esta parroquia el eje de responsabilidad es la pastoral, no la sede, y una misma
pastoral se coordina en varias sedes a la vez. Un modelo que reparte alcance «hacia los
lados» —a las vecinas de sede— no describe cómo se organiza el equipo.

Hoy `Auth::cargarPastorales()` lee solo `usuarios_pastorales`: **lo que administra una
cuenta es exactamente lo que se le marcó, y se ve en pantalla**, sin herencia ninguna.

`usuarios_centros` no desapareció: cambió de significado, que es lo que cuenta la sección
anterior. Antes decía «administra este centro entero» y repartía pastorales; ahora dice «esta
persona trabaja en esta sede» y solo **acota** lo que ya tiene. Marcar los tres centros a
Aimeé, que fue lo que provocó el problema, hoy es exactamente lo mismo que no marcarle
ninguno: MESC en las tres sedes.

La adscripción de una **persona** del equipo a un centro (`persona_centros`) es otra cosa
distinta de las dos y sigue igual: eso es un dato de directorio, no un permiso.

### Contenido propio por pastoral (issue #3)

La ficha pública de una pastoral (`pastorales/publico/detalle.php`) reúne, todo filtrado
por su propio `pastoral_id`: sus avisos vigentes (`AvisoModel::publicadosPorPastoral()`,
reutiliza la misma condición `VIGENTE` de la sección de avisos), sus próximos eventos
(`EventoModel::proximos($limite, $pastoralId)`) con un enlace a su propio calendario
mensual completo en `/eventos?pastoral=slug`, y sus documentos descargables
(`pastoral_documentos`, solo agregar/quitar — para cambiar uno se sube uno nuevo, no hay
edición de archivo). El centro/sede al que pertenece se muestra en la tarjeta de
información.

**El responsable se elige del equipo pastoral, no se escribe a mano.**
`pastorales.responsable_persona_id` (FK a `personas`, `ON DELETE SET NULL`) es el select del
formulario; `responsable_nombre` sigue existiendo solo como respaldo para cuando esa persona
todavía no tiene ficha en el equipo (AMA y Grupo JECSA, hoy). Con persona elegida, el nombre
mostrado **no es editable**: viene de `personas.nombre` y `PersonaModel::sincronizarResponsable()`
lo vuelve a copiar cada vez que la ficha se guarda, exactamente con el mismo mecanismo que ya
mantiene sincronizados `usuarios.nombre/telefono/foto` (`PersonaModel::sincronizarCuenta()`).

El correo de contacto **no** sigue esa idea, y esto cambió: hoy `contacto_email` es un campo
propio de la pastoral, se escribe a mano y no se hereda de ninguna cuenta. Durante un tiempo se
tomó de `usuarios.email` —el correo de acceso de quien coordinaba, «el del rol»— y
`UsuarioModel::sincronizarPastoralResponsable()` lo volvía a empujar cada vez que esa cuenta se
guardaba. El problema que resolvía era real: la ficha de MESC decía `aime.dessens@…` y la cuenta
de la coordinadora era `aimee.dessens@…`, una letra distinta. Pero lo resolvía publicando en el
sitio la dirección personal de quien está al frente hoy, y dejando a la pastoral sin dirección
propia: al cambiar de coordinadora cambiaba también el correo al que la parroquia llevaba años
escribiendo, y el anterior seguía recibiendo lo de una pastoral que ya no lleva. A petición de
la parroquia, cada pastoral tiene ahora su propio correo, que sobrevive a los relevos —quien
coordine puede poner el de su cuenta, pero como decisión suya y explícita, no como copia
automática—. La discrepancia de MESC vuelve a ser posible en teoría; lo que la evita ya no es
el sistema sino que las dos direcciones dejen de ser la misma cosa.

**Deliberadamente no hay un "organigrama de esta pastoral" aparte.**
`organigrama_nodos.pastoral_id` ya existe desde antes de este issue: cada nodo del
organigrama general puede ligarse a una pastoral. Duplicar esa misma información
filtrada dentro de la ficha de cada pastoral sería mostrar dos veces lo mismo con dos
caminos de código distintos, sin que nadie lo haya pedido.

**`/eventos?pastoral=slug`** reutiliza el calendario general en vez de duplicarlo:
`EventoModel::entreFechas()`/`proximos()` reciben un `$pastoralId` opcional,
`EventoPublicoController` resuelve el slug con `pastoralSolicitada()` (si no resuelve a
una pastoral activa, se ignora el filtro — la página cae en el calendario completo en vez
de mostrar uno vacío), y el filtro viaja en las propias URLs de navegación y de cambio de
vista, así que `calendario.js` lo arrastra sin tener que saber que existe.

### Jerarquía de pastorales: Comisión y sus pastorales hijas

La parroquia organiza su acción pastoral en Comisiones (Profética, Litúrgica, Pastoral de
la Salud, De la Familia, De la Comunicación) que agrupan pastorales concretas —estructura
real, no solo una forma de ordenar el listado—. `pastorales.pastoral_padre_id` (self-FK,
`ON DELETE SET NULL`) la modela con el mismo patrón que `organigrama_nodos.padre_id`, pero
a diferencia de aquel (hasta 4 niveles) aquí son exactamente 2: una Comisión no puede a su
vez tener padre, y una pastoral que agrupa hijas no puede recibir uno. Ninguna de las dos
reglas es un CHECK de SQL —no puede mirar otras filas—, las valida
`PastoralController::guardar()`; la UI (`candidatosPadre()`) ya solo ofrece candidatos
válidos, así que la validación en el controlador es defensa ante un POST manipulado, no un
caso de uso real.

**No hay columna `tipo`/`es_comision`.** Sin padre y sin hijas es una pastoral suelta; sin
padre y con hijas es una Comisión; con padre es una pastoral hija — los tres estados se
derivan de los datos (`PastoralModel::tieneHijos()`), no se guardan aparte, para que nunca
puedan desincronizarse de la realidad si alguien reasigna un padre sin acordarse de tocar
un flag.

**Lectores se separó de Litúrgica al introducir esto, sin migrar ningún dato.** Antes de
la jerarquía, la fila `slug='liturgia'` era a la vez "la pastoral Litúrgica" y, en la
práctica, el contenido operativo completo de Lectores (sus eventos, su foto, su
coordinadora con cuenta) — la constante del módulo resolvía por ese slug. Al necesitar que
Litúrgica agrupe también a MESC, Coros, Monaguillos, Piedad Popular y Social, esa misma
fila se renombró a "Lectores" **conservando su slug** —la constante siguió apuntando
ahí sin cambiar una línea de código— y se creó una fila nueva y vacía para la Comisión
"Litúrgica". Migrar en cambio el contenido a una fila "Lectores" nueva habría significado
mover quince registros entre cuatro tablas para el mismo resultado, con mucho más riesgo.

**Y esa misma fila se llama hoy "Proclamadores", que es como la pastoral se nombra a sí
misma; el slug sigue siendo `liturgia`.** Es un nombre visible más sobre una fila cuyo slug
no se ha vuelto a tocar desde entonces, y el criterio es el mismo: `/pastorales/liturgia`
es una URL pública que ya está en uso, y renombrarla rompería los enlaces repartidos a
cambio de nada que nadie note —el visitante lee el nombre, no el slug—. Lo que sí se
renombró es todo lo que solo se lee desde el código: la constante pasó de `PASTORAL_LECTOR`
a `PASTORAL_PROCLAMADORES` conservando su valor `'liturgia'`, de ahí que su nombre no se
parezca a lo que vale. El comentario que la acompaña en `config/app.php` cuenta esta
historia justamente para que nadie "corrija" uno sin el otro:
`ProclamadoresModel::pastoralId()` busca por slug, no por nombre, así que tocar el slug sin
tocar la constante deja el módulo sin pastoral. Sigue colgada de la Comisión "Litúrgica".

### Panel básico por pastoral y activación en el menú

Antes de esto, dar de alta una pastoral nueva no le daba ningún acceso visible en el
panel: avisos, eventos, cursos y `pastoral_documentos` ya resolvían `pastoral_id`
dinámicamente, pero nadie sabía que existían para esa pastoral sin entrar módulo por
módulo y elegirla a mano en un selector. `PastoralController::panel()` (ruta
`pastorales/panel?id=`) es la respuesta: una ficha con tarjetas a esos cuatro genéricos ya
filtrados —Documentos se gestiona ahí mismo, reusando `documentoGuardar()`/
`documentoEliminar()`, y es la misma lista que muestran los módulos dedicados de Catequesis
y Proclamadores, no una copia; Avisos, Eventos y Cursos solo se enlazan ya filtrados
(`?pastoral=`) y con "nuevo" ya preseleccionado (`?pastoral_id=`), no se duplica su CRUD—.
Si la pastoral tiene módulo dedicado (MESC/Catequesis/Proclamadores, `MODULO_POR_PASTORAL` en
`config/app.php`), el panel agrega un botón de salto a su módulo de turnos y catálogo, que
sigue existiendo tal cual.

**Al final del panel va quién pertenece a la pastoral** (`persona_pastorales`, resuelto con
`PersonaModel::todas([$pastoralId])`), que era la pregunta que obligaba a salir a Equipo
pastoral y filtrar por pastoral a mano. Tres decisiones ahí:

- **La lista no exige `personas.ver`**, solo el alcance sobre la pastoral que
  `requireAlcancePastoral()` ya comprobó: saber quién está en tu propia pastoral es parte de
  coordinarla, y esos nombres y cargos ya salen en el directorio público. Lo que sí exige
  permiso (`personas.editar`) es el botón que lleva a la ficha, donde están el teléfono, el
  correo y la fecha de nacimiento — por eso `PERMISOS_COORDINACION` sigue sin incluir
  `personas.*` y esta pantalla no lo contradice.
- **La pertenencia no se edita aquí**: el botón lleva a la ficha, que es su única fuente (el
  checklist de pastorales de `personas`). Dos sitios para marcar lo mismo es como se acaba
  con alguien en dos pastorales por descuido, que es el problema que
  `PersonaModel::sincronizarPastorales()` existe para no tener.
- **Una Comisión vacía se explica en vez de parecer un error.** Su gente suele estar marcada
  en las pastorales que agrupa, no en ella, así que cuando no hay nadie y
  `PastoralModel::tieneHijos()` dice que agrupa a otras, el mensaje lo dice. Es la misma
  confusión que hace que una Comisión sin hijas sea indistinguible de una pastoral suelta.

**Aparecer en el menú del panel (`pastorales.visible_en_menu`) es un paso deliberado, no
automático al crear la pastoral.** El bloque "Pastorales y comisiones" de
`modules/panel/views/index.php` (agrupado por Comisión, igual criterio de alcance y de
"encabezado de solo lectura" que `PastoralController::index()` — ambos comparten
`PastoralModel::agrupadoVisible()`) solo muestra las que tienen `visible_en_menu = 1`
(`PastoralModel::soloEnMenu()`). Publicarla es una acción de
`PastoralController::menuActivar()` restringida a `Auth::esAdmin()` **y** a confirmar la
propia contraseña en el momento (`Controller::requireAdminConPassword()`), para que crear
una pastoral no genere de más accesos ni estructura hasta que de verdad se quiera exponer.
El mismo candado protege `eliminar()`, que es un `DELETE` físico de la fila (no un
desactivado): confirmar con contraseña es la única fricción entre un clic y perder la
pastoral y sus documentos/actividades para siempre (avisos, eventos y cursos sobreviven
como contenido general, por `ON DELETE SET NULL`).

### Parejas: un dato de la parroquia, y la pastoral que coordina un matrimonio

Matrimonios y AMA no trabajan con personas sueltas sino con parejas, y en JECSA y en Raíces
quien coordina es un matrimonio. El sistema sabía que él y ella estaban los dos en el equipo,
pero no que estaban el uno con el otro. `parejas` guarda esa liga —solo la liga: ni fecha de
matrimonio ni notas, se pidió el vínculo y nada más, y añadirle columnas después no obliga a
rehacer nada de esto—.

**Es de la parroquia, no de la pastoral, y se corrigió a los veinte minutos.** El primer
intento fue una tabla `pastoral_parejas` donde cada pastoral ligaba a su propia gente, y no
sobrevivió al segundo requisito del mismo día: el matrimonio que coordina JECSA no tenía dónde
existir, porque JECSA no se organiza por parejas; y el que está en Matrimonios y en AMA había
que capturarlo dos veces, con dos filas para un solo hecho que acaban divergiendo. Un
matrimonio es el mismo en toda la parroquia, así que la liga subió ahí. Con eso desapareció
también `pastorales.organiza_parejas`, la casilla que decidía qué pastoral podía ligar: ya no
gobierna ninguna pantalla, porque ligar dejó de hacerse en la pastoral.

**Se captura en la ficha, en Equipo pastoral**, junto a sus pastorales y sus sedes, que es
donde ya viven los demás datos parroquiales de una persona. El panel de cada pastoral solo lo
muestra —"♥ Con Fulana" bajo cada integrante—, y lo muestra en cualquier pastoral, no solo en
las de familia: es un dato de la persona, y saber que dos de tu gente son matrimonio sirve
igual en Coros. Es la misma regla que ya rige la pertenencia: el panel de la pastoral enseña
quién está en ella pero no lo edita, porque dos sitios para marcar lo mismo es como se acaba
con una persona en dos pastorales por descuido.

**Una fila es una pareja.** La alternativa era una columna `pareja_persona_id` en `personas`, y
se descartó porque obliga a escribir el mismo hecho dos veces —la fila de cada uno apuntando al
otro— y basta con que una quede sin actualizar para que la base diga que él está con ella y
ella con nadie. El id menor va siempre en `persona_a_id`, así que "él con ella" y "ella con él"
tampoco pueden ser dos filas distintas. Cambiar de pareja deshace la anterior de los dos:
nadie está en dos a la vez, y ese es todo el mantenimiento que hay.

**Emparejar no es obligatorio, y no emparejar no es un registro a medias.** La mayoría del
equipo no tiene pareja registrada y no le falta nada; quien participa solo aparece en las
listas como cualquier otro, sin advertencia ni marca.

**El responsable de una pastoral puede ser una persona o una pareja.** Son dos columnas
(`responsable_persona_id`, `responsable_pareja_id`) pero un solo selector, con el valor
prefijado `persona:12`/`pareja:3`: con dos listas separadas se podrían elegir las dos a la vez
y habría que decidir cuál gana, que es exactamente el tipo de estado contradictorio que no
conviene poder representar. Con pareja, `responsable_nombre` es "Ella y Él", calculado de las
dos fichas y mantenido desde ahí, igual que ya pasaba con una persona. El correo no entra en
esto: es de la pastoral y se escribe a mano, coordine quien coordine (ver más arriba).

### Publicar en dos escalones: interno y público

Publicar un aviso o un curso era un interruptor de dos posiciones —borrador o en el sitio
web—, y eso dejaba sin resolver lo que una pastoral hace todo el tiempo: avisar **a los
suyos** de algo que no tiene por qué salir en la portada. Ahora hay un peldaño intermedio:

1. **Borrador** — solo lo ven admin y editor, y quien administra esa pastoral.
2. **Publicado para la pastoral** — lo leen sus miembros desde el panel. No sale al sitio.
3. **Publicado en la página** — además, se publica en el sitio web.

**El modelo son dos booleanos, no un `ENUM`.** `publicado_interno` es el escalón 2 y
`publicado` conserva exactamente el significado que ya tenía, el 3. La ventaja es que
ninguna consulta pública se tocó: siguen preguntando `publicado = 1` y no se enteran de
que ahora hay un peldaño antes. El invariante "público exige interno" no lo garantiza solo
el controlador sino un `CHECK` en la base, porque el repositorio tiene guiones que
escriben `publicado` por SQL directo. `Controller::escalonPublicacion()` traduce el
formulario a las dos columnas en un único sitio para avisos y cursos, y es también quien
fuerza los descensos: bajar a interno apaga `publicado`, y bajar a borrador apaga los dos
—sin eso, bajar de escalón dejaría el registro en el estado que el `CHECK` rechaza—.

**Leer no hereda igual que escribir.** El escalón interno de una Comisión alcanza a los
miembros de sus pastorales hijas: un aviso puesto en Litúrgica lo leen los de MESC,
Proclamadores o Coros. Eso lo resuelve `Auth::pastoralesAudiencia()`, que expande hacia arriba
las pastorales de la cuenta sumándoles sus padres. Va deliberadamente **aparte** de
`Auth::pastoralesPermitidas()`, que gobierna la escritura y sigue sin heredar nada:
estar en Proclamadores te deja leer lo de Litúrgica, no escribir en Litúrgica. Tampoco se
cachea en sesión —a diferencia de las pastorales asignadas, que solo cambian con la
cuenta—, porque depende de `pastoral_padre_id` y reasignar el padre de una pastoral no
debería esperar a que todo el mundo vuelva a entrar.

**El comunicado atraviesa el alcance, y es el primer tipo de aviso que hace algo.**
`avisos.tipo` —`noticia`, `boletin`, `comunicado`— era hasta ahora una etiqueta: se mostraba
en cuatro pantallas y lo único que de verdad dependía de ella era que el campo del PDF
siguiera apareciendo al editar un `boletin` ya creado. Ahora
`AvisoModel::TIPO_PARA_TODOS` (`comunicado`) quiere decir que el aviso va dirigido a **toda
la parroquia**, sea de la pastoral que sea: un comunicado publicado por MESC lo lee la
coordinadora de Coros, que no tiene nada que ver con MESC. No sustituye a dejar la pastoral
vacía, que sigue significando otra cosa —un aviso de la parroquia, sin dueño, que solo
tocan los roles de alcance global—: un comunicado es de una pastoral **para** todos,
conserva su `pastoral_id` y el panel lo muestra con el nombre de quien lo publica. Y solo
cuenta sobre lo ya publicado hacia dentro; un comunicado en borrador es de quien lo
escribe, como cualquier otro borrador.

**Se envuelve la condición, no se cambian los helpers compartidos.**
`AvisoModel::conComunicados()` recibe una condición de alcance y le añade
`OR (a.publicado_interno = 1 AND a.tipo = 'comunicado')`; por ahí pasan `listar()`,
`internosDelMes()`, `sinLeer()` y `contarSinLeer()`. Deliberadamente **no** se tocaron
`Model::condicionAlcance()` ni `Model::condicionVisibilidadPanel()`: esos dos los comparte
con cursos, que no tiene tipos y donde un comunicado no significa nada, así que meter la
excepción ahí la habría metido en una consulta a la que no le corresponde. El método trata
los dos extremos a propósito distinto: con la condición vacía no añade nada —quien lo ve
todo ya lo ve—, y con `1 = 0` —una cuenta de alcance limitado a la que no se le marcó
ninguna pastoral— el comunicado **sí** entra, porque va dirigido a toda la parroquia y esa
cuenta también es de la parroquia.

Se aplica además al **listado del panel** y no solo al tablón de inicio, y la razón importa:
la insignia del menú anuncia avisos sin leer, así que si al pulsar "Avisos" el comunicado no
apareciera en la lista, el contador estaría mintiendo.

**El guardia de la ficha aprende lo mismo, como bandera.**
`Controller::puedeLeerInterno()` gana un tercer parámetro opcional,
`bool $paraTodaLaParroquia = false`, que `AvisoController::ver()` rellena con
`$aviso['tipo'] === AvisoModel::TIPO_PARA_TODOS`. Llega como bandera y no como tipo para
que ese mismo guardia siga sirviendo igual a cursos; y se consulta después de la rama del
borrador, no antes, porque también aquí solo cuenta lo publicado hacia dentro.

**De paso se cerró una fuga anterior.** `Controller::pastoralesVisibles()` añade el `null`
del contenido parroquial general al alcance sin mirar el estado, así que cualquier
coordinador venía leyendo los borradores que admin y editor tuvieran a medias. La regla
del listado del panel pasa a ser explícita en `Model::condicionVisibilidadPanel()`: lo
publicado hacia dentro de su audiencia (donde el general sí entra), **más** los borradores
de sus propias pastorales y solo esos.

**Quién publica.** `avisos.publicar` se añadió a `PERMISOS_COORDINACION`, donde
`cursos.publicar` ya estaba: partir la publicación en dos es justamente lo que quita el
motivo por el que los avisos entraban siempre como borrador —el primer escalón ya no llega
a la portada—. Y `ROL_CONSULTA` recibió `avisos.ver`, sin lo cual el escalón interno no
tendría a quién llegar: ese es el rol de los miembros de a pie.

**Dos piezas nuevas de interfaz** hacían falta para que esto sirviera de algo. Una acción
de lectura (`AvisoController::ver()`, `CursoController::ver()`): hasta ahora el panel solo
sabía llevar al formulario de edición, y lo que aún no es público no tiene página que
enseñar. Y las miniaturas del panel de inicio, con lo último publicado a sus pastorales;
se marcan «Nuevo» comparando `publicado_interno_at` contra el ingreso anterior de esa
persona, que `Auth::intentarLogin()` guarda en sesión antes de pisar `ultimo_acceso`. Las
tarjetas de aviso llevan el **icono de su tipo** (`AvisoModel::ICONOS` e `icono()`, un
icono de Bootstrap por valor del ENUM) y no el megáfono para los tres: un comunicado es lo
que hay que leer hoy y una noticia puede esperar, y el icono lo dice antes que el texto. Lo
demás de la tarjeta —imagen, pastoral, título, resumen y la propia etiqueta «Nuevo»— ya
estaba.

**«Sin leer» y «Nuevo» son dos preguntas distintas, y conviven.** «Nuevo» se decide contra
`usuario_acceso_anterior` y responde «¿esto ya estaba la última vez que entré?»: sirve para
no volver a señalar lo de siempre, pero no es una lectura. En cuanto la campana de la barra
empezó a contar avisos —ver "Los dos layouts"— hizo falta la otra pregunta, «¿abrió esta
persona este aviso?», porque un contador tiene que bajar al abrir uno y no volver a subir:
con el criterio de «Nuevo» el globo rojo se quedaría encendido toda la sesión aunque los
hubiera abierto uno por uno, y significaría una cosa en los mensajes y otra en los avisos.
Esa segunda pregunta es la tabla `aviso_lecturas` —una fila por par aviso-usuario, sin `id`
propio; ver [`BASE-DE-DATOS.md`](BASE-DE-DATOS.md)—, que escribe
`AvisoModel::marcarLeido()` con `INSERT IGNORE` y leen `sinLeer()` y `contarSinLeer()` con
un `NOT EXISTS`. Esas dos comparten la condición en un solo sitio
(`AvisoModel::condicionSinLeer()`) a propósito: la lista de la campana y su globo salen de
ahí, y con una copia por consulta acabarían diciendo números distintos. Ninguna de las dos
preguntas sustituyó a la otra: la etiqueta del tablón sigue funcionando exactamente como
antes.

Marcar leído tiene **dos condiciones**, y las dos son por lo mismo, que la marca diga la
verdad: solo si el aviso ya está publicado hacia dentro —el borrador que su autora abre
veinte veces mientras lo escribe no es la lectura de nadie— y solo fuera de una
impersonación, donde marcaría como leído lo que esa persona no ha visto.

**La campana no recorta por mes y el tablón sí**, también a propósito.
`internosDelMes()` se queda en el mes en curso porque es un tablón de novedades;
`sinLeer()`/`contarSinLeer()` no llevan ese recorte, porque lo que se anuncia no deja de
estar sin leer porque cambie el mes —lo que sí lo cierra es `vigente_hasta`, que es la
forma que ya tenía el sistema de decir «esto ya pasó»—. La consecuencia conviene tenerla
presente: puede haber un aviso sin leer que la campana cuenta y el tablón del panel no
muestra, y de ahí que la campana lleve su propia lista con enlace a cada uno en vez de un
enlace único al listado.

**La agenda interna es la excepción conocida.** Sigue enseñando borradores a cualquiera
con `agenda.ver`, sin recortar por pastoral, porque para eso existe: que nadie reserve el
salón dos veces. Lo único que se corrigió ahí es cómo los llama —un curso publicado a su
pastoral ya no se etiqueta «Borrador»—, no a quién se los enseña.

### MESC: visitas a enfermos y rutas (issue #3)

`modules/mesc/` es, a propósito, el único módulo del sitio **sin ningún controlador
público**: `MescModel` no tiene una sola consulta que no pase por
`requirePermiso('mesc.*')` + `requireAlcancePastoral()`. La razón está en
[`PRIVACIDAD.md`](PRIVACIDAD.md): el solo hecho de aparecer en `mesc_visitas` revela un
estado de salud, el primer dato sensible en sentido estricto de la LFPDPPP que maneja el
sistema. `pastoral_id` en `mesc_visitas`/`mesc_rutas`/`mesc_ministros`/`mesc_turnos` es
**obligatorio y fijo**, a diferencia de avisos o eventos: esta actividad nunca es
"contenido parroquial general", siempre pertenece a la única pastoral de MESC, resuelta
por `MescModel::pastoralId()` y `MescController::pastoralIdOFallar()` — ver la sección
de Catequesis y Proclamadores más abajo para la historia completa de este patrón, que MESC
adoptó más tarde que ellos.

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
análoga a `EventoPublicoController::cuadriculaDelMes()`, deliberadamente duplicada en
vez de compartida —cruzar la frontera pública/admin por una función de 20 líneas no vale
el acoplamiento—. Los estilos (`.calendario-tabla`, `.numero-dia`, `.evento-punto`) se
copiaron de `assets/css/publico.css` a `assets/css/app.css` porque el panel carga una
hoja de estilos distinta a la del sitio público.

**Colores litúrgicos, como catálogo de mantenimiento, no como constante en PHP.**
`colores_liturgicos` (blanco, verde, morado, rojo, rosa, con su significado) es
editable desde el panel en `mesc/colores` en vez de vivir hardcodeado en el código: el
propio equipo pastoral puede ajustar el texto o el tono exacto sin tocar una línea de PHP.
La tabla se llamó `mesc_colores_liturgicos` mientras MESC fue el único que la administraba,
y perdió el prefijo cuando Proclamadores estrenó su propia pantalla sobre esas mismas filas
—ver más abajo—: el catálogo nunca fue de un módulo, y el nombre acabó diciendo lo
contrario. Cada turno referencia opcionalmente un color (`color_liturgico_id`, `ON DELETE SET NULL`
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

### MESC, Catequesis, Proclamadores y Coros: un módulo por pastoral dedicada, siempre de una sola (revisión de módulos)

Cuatro módulos, `modules/mesc/`, `modules/catequesis/`, `modules/proclamadores/` y
`modules/coros/`, comparten el mismo patrón: módulo propio y separado para una pastoral específica, sin controlador
público, en vez de ampliar el sistema genérico de "contenido propio por pastoral"
(`pastoral_actividades`/`pastoral_documentos`). La razón es la misma en los tres: cada
uno necesita columnas y pantallas que ese sistema genérico no tiene y que no tendría
sentido forzar sobre *todas* las pastorales.

**Pero no todo lo que hay dentro de un módulo dedicado es propio de él.** Dos de sus
pantallas —el tablero de actividades con fechas y los documentos descargables— no piden ni
una columna que el sistema genérico no tenga, así que viven en `pastoral_tablero` y
`pastoral_documentos`, las administra `PastoralModel`, y Catequesis, Proclamadores y Coros
entran por ahí con una propiedad `private PastoralModel $pastorales` cada uno. Repetir la
pantalla en cada módulo es deliberado —quien coordina una pastoral trabaja dentro del suyo,
y no tiene por qué saber que hay dos caminos al mismo sitio—; lo que no se repite es la
tabla. Catequesis las tuvo
un tiempo como tablas propias, `catequesis_actividades` y `catequesis_documentos`; cuando
Proclamadores pidió esas mismas dos pantallas, copiarlas significaba copiar también las dos
tablas, y con la plantilla de módulos que viene después cada módulo nuevo habría arrastrado
otras dos iguales. Ver [`BASE-DE-DATOS.md`](BASE-DE-DATOS.md) y
`docs/migraciones/2026-09-04-tablero-y-documentos-compartidos.sql`.

**Los métodos del tablero se llaman `tablero*` y no `actividad*` a propósito.**
`PastoralModel::actividades()` ya significaba otra cosa —la lista fija de qué hace la
pastoral, la de `pastoral_actividades`, sin fechas, la que se lee en su página pública—, así
que `tablero()`, `entradaTablero()`, `crearEntradaTablero()`, `actualizarEntradaTablero()` y
`eliminarEntradaTablero()` conviven con ella sin que haya que abrir el SQL para saber cuál
es cuál; es el mismo desambiguar que motivó el nombre de la tabla. Los de documentos ya
estaban en ese modelo desde el issue #3 (`documentos()`, `documentosActivos()`,
`documentoPorId()`, `crearDocumento()`, `eliminarDocumento()`) y no hizo falta escribir
ninguno.

**Los cuatro son de una sola pastoral, fija, sin selector.** `MescModel::pastoralId()`,
`CatequesisModel::pastoralId()`, `ProclamadoresModel::pastoralId()` y
`CoroModel::pastoralId()` resuelven su pastoral por
`slug` (no por un id fijo en PHP: los id de pastorales se generan al crearlas desde el
panel, no se siembran en `install.sql`), y `pastoralIdOFallar()` en su respectivo
controlador corta el flujo con un mensaje claro si esa pastoral todavía no existe o el
usuario no tiene alcance sobre ella. Ningún formulario de estos cuatro módulos acepta ni
muestra otra pastoral.

Esto no fue el diseño original de MESC: al ser el primer módulo de este tipo (issue #3),
`pastoralIdMescValidado()` solo exigía que `pastoral_id` no fuera nulo, pero aceptaba
*cuál* de las pastorales que el usuario administrara, mostrando un selector con todas
ellas. Catequesis y Proclamadores copiaron ese mismo selector al construirse sobre MESC como
plantilla, y en ambos casos resultó en el mismo bug: un administrador con acceso a más
de una pastoral (algo habitual, no la excepción) veía —y podía usar— pastorales ajenas
al módulo en pantallas como "agregar ministro/proclamador" o "nueva visita/turno". Se corrigió
primero en Catequesis y Proclamadores (fijando su pastoral por `slug` con `pastoralIdOFallar()`)
y, al reportarse el mismo síntoma en MESC ("se agregan ministros en pastorales que no
son MESC" y un selector de pastoral en el formulario de visita a enfermos), se le aplicó
el mismo arreglo: MESC dejó de ser la plantilla con la excepción y pasó a seguir su
propio patrón.

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
vez de duplicar la fila.

Sus otras dos pantallas, Actividades y Documentos, siguen donde estaban pero ya no son
suyas: escriben en `pastoral_tablero` y `pastoral_documentos` a través de `PastoralModel`
—ver los dos párrafos de arriba—, y `CatequesisModel` perdió con eso sus métodos
`actividad*` y `documento*`, unas noventa líneas que se habían quedado sin tabla debajo. La
auditoría de esas acciones registra ahora `pastoral_tablero` y `pastoral_documentos` como
tabla afectada, que es donde de verdad se escribe. La de Documentos, además, dejó de ser una
segunda lista ciega a la del panel básico de la pastoral: eran dos y hoy es una.

**Proclamadores** (la pastoral de slug `liturgia` —se llamó "Lectores" y luego "Liturgia";
ver la nota de `PASTORAL_PROCLAMADORES` en `config/app.php`—): recorta MESC a sus dos
piezas no sensibles y extrapolables
—`proclamadores_turnos`/`proclamadores_turno_proclamadores` calcan
`mesc_turnos`/`mesc_turno_ministros` entrada por entrada, y `proclamadores` calca
`mesc_ministros`—, y deja fuera lo que no aplica: nada de `mesc_rutas`/`mesc_visitas`,
quien proclama la Palabra lo hace en misa y no reparte comunión a domicilio.
`proclamadores_turnos.color_liturgico_id` apunta al catálogo compartido
`colores_liturgicos` en vez de duplicarlo: el significado de cada color litúrgico es el
mismo calendario para toda la parroquia, no un dato propio de un módulo en particular — y
fue la primera vez que una tabla de fuera referenció ese catálogo, que entonces todavía
se llamaba `mesc_colores_liturgicos`.

**Lo único que no tiene equivalente en MESC es `proclamadores.preferencias`**, un
`SET('monitor','lectura','salmo')` con lo que cada quien prefiere hacer al proclamar. Es el
dato con el que la coordinación arma un turno —quién va de monitor, quién lee y quién canta
el salmo—, así que se muestra donde se decide: en el catálogo y junto a cada nombre en el
formulario de turno, no en una pantalla aparte. No restringe nada, es una preferencia y no
un permiso; el porqué de la columna `SET` está en [`BASE-DE-DATOS.md`](BASE-DE-DATOS.md).

**Su paridad con MESC se completó después, y es paridad menos las visitas.** El módulo
nació con catálogo y calendario y le faltaban las dos pantallas que MESC sí tenía:

- **La hoja imprimible del mes** (`turnosImprimir()` + `turnos_imprimir.php`), que comparte
  con MESC la hoja de estilos `assets/css/turnos_imprimir.css` en vez de duplicarla: la
  cuadrícula que se reparte en papel es la misma, solo cambia quién va escrito en cada
  casilla. De ahí que en ese CSS las clases `.ti-ministros`/`.ti-sin-ministros` pasaran a
  `.ti-nombres`/`.ti-sin-nombres` — con dos módulos usando la misma hoja, el nombre de la
  clase mentía en la mitad de los casos.
- **Los colores litúrgicos, con alta, edición y borrado** (`colores()`, `colorGuardar()`,
  `colorEliminar()`), sobre la misma tabla `colores_liturgicos` que administra MESC. La
  pantalla se repite a propósito y no contradice el "no duplicar": quien coordina
  Proclamadores no administra MESC, así que sin ella dependía de pedirle a alguien más un
  color nuevo para poder etiquetar su turno. Las dos pantallas avisan en su cabecera de que
  el catálogo es uno solo para toda la parroquia, porque editar aquí también cambia lo que
  ve el otro módulo. Igual que en MESC, esta parte **no tiene alcance por pastoral** —un
  color litúrgico no es de nadie—: solo exige el permiso del módulo.

**Con la paridad completa, la portada del módulo cambió: es el catálogo, no el calendario.**
`/admin/proclamadores` abría en la cuadrícula de turnos, que es lo que había heredado de
MESC; hoy abre en el catálogo de quién proclama y el calendario se mudó a
`/admin/proclamadores/turnos`. Lo primero que se consulta es quién está y qué prefiere hacer
—monitor, lectura o salmo cantado—, porque el turno se arma a partir de ahí; abrir en la
cuadrícula era empezar por el resultado. La acción `catalogo`, que existió unas horas
mientras esto se movía, ya no está: el catálogo es `index()`.

**Dos pantallas más, calcadas de Catequesis.** Actividades (`actividades`,
`actividad_guardar`, `actividad_eliminar`, vista `actividades_lista.php`) sobre
`pastoral_tablero`, y Documentos (`documentos`, `documento_guardar`, `documento_eliminar`,
vista `documentos_lista.php`) sobre `pastoral_documentos`, con subida de PDF por
`Upload::documento()` bajo `uploads/proclamadores/`. Son las dos que motivaron sacar esas
tablas de Catequesis —ver el arranque de esta sección—: copiar la pantalla estaba bien,
copiar la tabla no.

La de Documentos **avisa en su cabecera de que la lista es la misma que la del panel básico
de la pastoral**, porque lo es, y sin decirlo alguien acabaría capturando el mismo documento
dos veces. La de Actividades no lleva ese aviso, y la asimetría es deliberada:
`pastoral_tablero` también es compartida, pero el panel básico todavía no ofrece esa
pantalla, así que anunciarlo hoy sería falso.

**Con cinco pantallas, la barra de botones para saltar entre ellas se sacó a un parcial**,
`modules/proclamadores/views/_nav.php`, en vez de repetirla en cada vista como sigue estando
en Catequesis, que tiene tres. Con cinco copias, agregar una sexta pantalla obliga a
acordarse de editar cinco archivos, y la que se olvide queda con una barra distinta de las
demás. El parcial declara las pantallas en un array y omite la de la pantalla actual —ya la
nombra el título de la página, y un botón que lleva a donde ya estás no sirve de nada—, que
es el criterio que las vistas de Catequesis ya seguían a mano.

**El nombre de un ministro de MESC es su nombre corto, y es un dato propio.** En
`mesc_ministros`, `nombre` no es una copia del de su ficha sino el nombre con el que se le
conoce en el calendario de turnos —«Zulema», «Tino», «Aimeé»—, que es lo único que cabe en
una casilla de una cuadrícula de siete columnas. Es además la clave con la que se reconoce
a cada quien al capturar un calendario que llega de fuera, hecho a mano en otra
herramienta. Por eso:

- El campo se guarda **siempre**, aunque haya persona vinculada, y
  `PersonaModel::sincronizarPersonal()` es la excepción documentada que no lo pisa —sí
  sincroniza el teléfono, que no tiene esa doble vida—.
- Si se deja en blanco al vincular a alguien, `MescController::ministroGuardar()` toma el
  primer nombre de su ficha («Zulema Maria Alavrez Andrade» → «Zulema»), que es
  exactamente la forma en que están capturados los demás.

Catequistas y proclamadores **no** siguen esta excepción: ahí el nombre sí viene de la ficha,
porque sus pantallas son listados donde el nombre completo cabe sin problema.

**El calendario de turnos se puede sacar en hoja aparte.**
`MescController::turnosImprimir()` + `modules/mesc/views/turnos_imprimir.php` +
`assets/css/turnos_imprimir.css` (esa hoja la comparten hoy los dos módulos de turnos, ver
arriba) replican, para el calendario mensual de MESC, el mismo
patrón que la vista de impresión del organigrama: página independiente vía
`renderSinLayout()`, hoja de estilos propia, y un botón que solo llama a `window.print()`
—sin librería de PDF ni de imagen, que el proyecto no admite en el servidor—. Es el
formato que se reparte impreso a los ministros y que hasta ahora se armaba a mano fuera
del sistema.

Dos diferencias deliberadas con el calendario del panel, las dos por el mismo motivo —en
papel no hay dónde pasar el ratón—: los ministros de cada turno van **escritos en la
casilla** en vez de en el `title`, y el CSS fuerza `print-color-adjust: exact` sobre las
etiquetas, porque su color es el código litúrgico del día, no un adorno que el navegador
pueda descartar al imprimir.

**El ministro/catequista/proclamador también se elige del equipo pastoral —tercera vez que
se construye este vínculo.** `mesc_ministros`, `catequesis_catequistas` y
`proclamadores` tenían el mismo problema que ya se había resuelto antes para
`usuarios` y para el responsable de una pastoral: el nombre era texto libre, sin
relación con `personas`, y eso permitía —de hecho, ya había pasado— que la misma
persona real quedara escrita de formas distintas en cada tabla. Encontrado al revisar
por qué el panel de una coordinadora mostraba módulos ajenos: Zulema estaba como
"Zulema" en `mesc_ministros`, "Zulema Alvarez" en `catequesis_catequistas` y con su
nombre completo en `personas` —mismo teléfono en las tres filas, misma persona sirviendo
a la vez como ministra MESC y catequista—.

Las tres tablas ganaron `persona_id` (FK a `personas`, `ON DELETE SET NULL`, `UNIQUE`
por tabla —la unicidad es solo dentro de cada catálogo, nunca cruzada entre las tres: el
caso de Zulema muestra que una misma persona sirve legítimamente en más de uno a la
vez—). Con persona elegida, `nombre`/`telefono` (y `email`, en las dos tablas que lo
tienen; `mesc_ministros` no lo necesitó nunca y no se le agregó) se toman de la ficha y
`PersonaModel::sincronizarPersonal()` los mantiene al día si la ficha cambia —mismo
mecanismo que `sincronizarCuenta()` para `usuarios` y `sincronizarResponsable()` para
`pastorales.responsable_nombre`—; sin persona, los campos de texto libre de siempre
siguen funcionando igual, para quien todavía no está de alta en el equipo pastoral.

Deliberadamente **sin migración automática** de los 13 ministros y 5 catequistas que ya
existían: a diferencia del responsable de pastoral, donde tres coincidencias de nombre
eran inequívocas, aquí ninguna fila coincide por texto exacto contra `personas` —el
caso de Zulema se encontró por teléfono, no por nombre—, evidencia insuficiente para
vincular sin que alguien lo confirme. Quedan con `persona_id NULL` hasta que se
vinculen a mano desde el panel.

### Coros: el módulo dedicado más pequeño, y el único atado a `horarios`

`modules/coros/` es el cuarto de esta familia y la prueba de que el patrón admite tamaños
muy distintos: tres tablas propias y ni una línea de calendario. Lo que la pastoral
necesitaba registrar era **quién canta en qué misa dominical y quién encabeza cada coro**,
y eso no cabía en el sistema genérico —ni el panel básico de la pastoral ni el checklist
de la ficha de `personas` tienen dónde guardar ninguna de las dos cosas—, que es
exactamente el criterio con el que se justifican MESC, Catequesis y Proclamadores.

**Un coro no tiene nombre: es el de su misa.** `coros.horario_id` es `NOT NULL`, `UNIQUE`
y `ON DELETE CASCADE` —las tres por lo mismo—, y el nombre visible lo compone
`CoroController::etiquetaDeCoro()` con la hora, el centro y la nota del horario:
«12:00 p. m. · Parroquia Nuestra Señora de la Paz», «10:30 a. m. · Jesús el Señor (Misa
para Niños)» —sin la palabra «domingo», que en este módulo se sobreentiende en todas las
filas—. Si esa misa
cambia de hora, el coro cambia de nombre solo; si se borra, el coro deja de significar algo
y se va con ella. La nota entra en la etiqueta porque es lo único que distingue dos misas
del mismo lugar en la misma mañana.

**Y aquí sí hay FK a `horarios`, justo lo que los turnos evitan.** `mesc_turnos` y
`proclamadores_turnos` no la tienen a propósito —ver arriba— porque cubren una
*ocurrencia* concreta y `horarios` es *recurrencia semanal*, así que atarlos ahí no
resolvería la fecha. Un coro es justamente la recurrencia. La regla, entonces, no es
«nunca referenciar `horarios`» sino «referenciarla cuando lo que se modela se repite cada
semana», y este es el primer caso del sistema que cumple eso.

**El encargado es uno de los suyos, y esa regla no la puede guardar el esquema.**
`coros.encargado_id` es columna y no un `es_encargado` en el pivote porque el encargado es
uno solo, y una columna no puede tener dos valores. Lo que la FK no alcanza a exigir —que
esa persona además cante en *ese* coro— se comprueba en dos sitios que se complementan:
`CoroController::coroGuardar()` la cruza contra la lista de integrantes que se está
guardando y, si no está, guarda el coro sin encargado y lo dice; y
`CoroModel::limpiarEncargadosHuerfanos()` corre después de **cada** sincronización —en las
dos direcciones— y devuelve a NULL cualquier encargado que haya dejado de pertenecer. Sin
lo segundo, quitar a alguien de un coro desde su propia ficha lo dejaría encabezando un
coro en el que ya no canta: `ON DELETE SET NULL` cubre borrarlo del catálogo, no
desasignarlo. Es una sola sentencia SQL en vez de calcular a mano qué coros quedaron
afectados, para que ninguna ruta de código pueda olvidarse de un caso.

**La portada se recorre por misa, no por coro.** `/admin/coros` lista las misas dominicales
de `horarios` —hoy son seis— y, en cada una, o su coro o un botón de «Crear coro». Listar los coros
existentes habría dejado invisible la pregunta que de verdad se hace —«¿quién canta en la
de 12?», cuya respuesta puede ser «nadie»— y sin sitio donde ofrecer el alta. Por eso
tampoco se sembró ningún coro en la migración: la pantalla los pide donde se echan
en falta, que se explica solo.

**La voz y el instrumento son texto libre, al revés que las preferencias de un
proclamador.** `proclamadores.preferencias` es una columna `SET` porque monitor, lectura y
salmo es un catálogo cerrado que no va a crecer; aquí se decidió lo contrario por el mismo
razonamiento aplicado a un caso que no lo cumple. Un coro parroquial no siempre canta a
cuatro voces —«barítono», «mezzo», «segunda voz»— y los instrumentos no tienen lista: cada
uno nuevo sería una migración para guardar una palabra. Se pierde poder filtrar por voz,
que nadie ha pedido, y se gana que la pastoral escriba lo que de verdad hace cada quien
sin pedirle a nadie que toque el esquema. Es además lo que ya estaba escrito a mano en el
`cargo` de la ficha de Horacio: «guitarra».

Las dos se muestran **donde se decide**, no en una pantalla aparte: en el catálogo, y
junto a cada nombre en el checklist con el que se arma un coro. Es el mismo criterio por
el que las preferencias de un proclamador salen en el formulario de turno. Y ninguna de
las dos entra en `PersonaModel::sincronizarPersonal()` aunque haya persona vinculada: son
datos del módulo, no de la ficha del equipo pastoral —la segunda excepción de esa clase,
junto al nombre corto de un ministro de MESC—.

**Un integrante puede cantar en varias misas**, así que `coro_coristas` es un pivote N-M y
no una columna en `coristas`. Se marca desde las dos pantallas —quiénes cantan en un coro,
o en qué coros canta alguien— a propósito, a diferencia de la pertenencia a una pastoral,
que tiene una sola fuente: aquí no hay dos tablas que puedan desincronizarse, es la misma
fila del mismo pivote vista desde sus dos extremos.

**Lo que deliberadamente no tiene**, y no por falta de tiempo: calendario de turnos (la
asignación es permanente, no hay nada que capturar cada semana), colores litúrgicos (son
del turno de un día, y no hay turnos) y hoja imprimible (ídem).

**Actividades y documentos sí los tiene, y esa es una corrección.** El módulo nació sin
ellos con el argumento de que `pastoral_tablero` y `pastoral_documentos` ya se administran
desde el panel básico de la pastoral, así que repetir la pantalla sería duplicar. El
argumento confundía dos cosas distintas: **duplicar la tabla** —que es lo que se corrigió al
sacar esas dos de Catequesis— y **repetir la pantalla**, que es justamente lo que ya hacen
Catequesis y Proclamadores sobre esas mismas filas, y por una razón: quien coordina una
pastoral trabaja dentro de su módulo, y mandarla a otra sección a subir su cancionero es
pedirle que sepa que existen dos caminos al mismo sitio. La tabla sigue siendo una sola —la
pantalla de Documentos lo dice en su cabecera, igual que en Proclamadores—; lo que se repite
son seis acciones que delegan en `PastoralModel`, sin una línea de SQL propia.

Con cuatro pantallas, la barra para saltar entre ellas ya estaba en
`modules/coros/views/_nav.php` desde el primer día, cuando eran dos: Proclamadores llegó a
cinco copias del mismo bloque antes de sacarlo a un parcial, y esa es la lección, no el
número. Aquí se notó el mismo día: pasar de dos pantallas a cuatro fue una línea.

**Cuarto módulo, y por tanto la primera vez que se recorre entera la lista de puntos de
registro** que la sección de Roles y permisos advierte: el menú lateral y las tarjetas del
panel, **los dos**, con su cruce de `Auth::tienePermiso()` y `Auth::administraPastoral()`
cada uno por su cuenta; `PASTORAL_COROS` y `MODULO_POR_PASTORAL` en `config/app.php`;
`coros.*` en `PERMISOS_COORDINACION`, en `ROL_EDITOR` y solo `coros.ver` en `ROL_CONSULTA`;
la ruta en `Router::$rutasAdmin`; y `PersonaModel::sincronizarPersonal()`, que pasó de tres
tablas a cuatro. La advertencia resultó exacta: el mapa `$pastoralPorModulo` del panel es
independiente del array del menú y hay que tocar los dos.

Hoy nadie tiene la pastoral de Coros asignada en `usuarios_pastorales`, así que el módulo
solo lo ven las cuentas de alcance global. Darle acceso a la pastoral es añadir esa fila
desde Usuarios, no tocar código.

### Escribirle al equipo son enlaces `wa.me`, no la API de Meta

La parroquia pidió poder avisarle por WhatsApp a la gente de una pastoral —una reunión, un
cambio de turno—, y el caso incluía expresamente a **quien no tiene ficha**: en MESC,
Catequesis, Proclamadores y Coros un integrante puede existir con `persona_id = NULL`, solo
con su nombre y su teléfono escritos a mano. Por eso el botón vive en los listados de
integrantes y no en la ficha de `personas`: en la ficha esa gente no está.

**El botón abre la conversación, no manda el mensaje.** Es un enlace `https://wa.me/<número>`
con el texto en `?text=`; lo abre WhatsApp Web o la app, y quien envía es la persona desde
su propio WhatsApp. La alternativa —la Cloud API de Meta— manda sola, pero exige cuenta de
Meta Business verificada, un número exclusivo que deja de servir en la app normal,
plantillas aprobadas por Meta para iniciar conversación y pago por conversación. Es la
misma decisión que ya sostiene "Antispam sin servicios de terceros": el proyecto no hace
**ninguna** llamada HTTP saliente, y esta no iba a ser la primera para mandar un recado. El
día que la parroquia contrate la API, la normalización del número y el lugar del botón se
reaprovechan enteros.

**La normalización es al dibujar, no al guardar.** `wa.me` exige el número internacional,
solo dígitos y sin `+`; lo guardado conviene tres formatos —`6622240453`, `662 220 7214`,
`(662) 220 7214`— y ninguno trae clave de país. `telefono_internacional()`
(`core/helpers.php`) le antepone `LADA_PAIS` a los de diez dígitos y respeta los que ya la
traen, incluidos los `521…` de trece que dejó el celular mexicano de antes de 2019. No hay
migración ni `UPDATE`: cada quien sigue leyendo el número como lo escribió, y un dato raro
no se corrompe. Cuando el número no se puede normalizar, el helper devuelve `''` y **el
botón no se dibuja** — la misma regla que rige los permisos, nada gris y nada muerto. Los
cuatro `preg_replace` inline que armaban los `tel:` a mano se retiraron a favor de
`tel_enlace()`, que emite `tel:+52…`.

**El mensaje es un cuadro, no una plantilla guardada.** Arriba de cada listado hay un
"Mensaje para todos" (`shared/views/parciales/mensaje_whatsapp.php`) que `assets/js/app.js`
pega al enlace de cada quien, sustituyendo `{nombre}` por su nombre de pila y guardando el
borrador en `sessionStorage`. Se consideró una clave en `configuracion` y se descartó: una
plantilla fija solo puede saludar, y el aviso de la reunión —que es lo que de verdad se
manda a quince personas— cambia cada vez. Así además no toca la base de datos.

**El permiso es propio: `personas.contactar`.** No sirve `personas.ver`, que no lo tiene
ninguna coordinación; ni bastan `mesc.ver` y sus gemelos, que los lleva también Consulta —el
integrante de a pie— y que ya dejan el teléfono a la vista en texto plano desde antes. El
permiso nuevo va en `PERMISOS_COORDINACION` y en `ROL_EDITOR`, y separa exactamente lo que
hay que separar: escribirle a la gente de tu pastoral es parte de coordinarla; abrir su
expediente —domicilio, fecha de nacimiento, sus otras pastorales— sigue siendo
`personas.ver`. En el panel básico de pastoral, que es la única pantalla donde el dato es
nuevo, el número **no se imprime**: va en el enlace.

### El teléfono se valida al capturarlo, con una regla más estricta que la de dibujarlo

El botón de WhatsApp dejó a la vista un hueco que llevaba ahí desde la fase 1: **no había
ninguna validación de teléfono en el proyecto**. Los catorce campos eran `type="tel"` sin
`pattern`, `postStr()` solo hacía `trim` y `strip_tags`, y en el formulario público de
contacto bastaba con que el campo no estuviera vacío —una sola letra pasaba, y luego no
había a quién responderle—. Con eso, `662 220 7214 ext 12` se guardaba tal cual, daba doce
dígitos y armaba un enlace de WhatsApp que no lleva a nadie.

`telefono_valido()` (`core/helpers.php`) es ahora el control, y corre **en el servidor** en
los trece puntos de captura: las cuatro fichas del panel (equipo pastoral, sedes, ficha de
pastoral, cuentas), los dos teléfonos de una visita de MESC, los cuatro catálogos de
pastoral, los dos campos del sitio público (contacto e inscripción a un curso) y el tipo
`telefono` de la configuración, que caía en el `default` de `valorDelCampo()` y se guardaba
sin mirar. El `placeholder` y la ayuda de cada campo son la cortesía; la regla del proyecto
sigue siendo que lo del navegador no es un control.

**Es más estricta que `telefono_internacional()`, y a propósito.** Esa función tiene que
arreglárselas con lo que ya está guardado, así que acepta cualquier cosa de once a quince
dígitos. La validación, con el dato todavía en la mano de quien lo escribe, puede pedir
bien: diez dígitos (con espacios, guiones o paréntesis, como se quiera); o de otro país
**solo si lo dice un `+` o un `00`** delante, con lo que un número de once dígitos deja de
ser ambiguo entre un extranjero y un local mal teclado; o doce y trece dígitos que empiecen
con `LADA_PAIS`, para quien escribe su propio número con el 52 por delante. Y cualquier
letra lo invalida, que es lo que atrapa la extensión pegada.

**Ninguna de las dos reglas invalida lo que ya está guardado**: se comprobaron los 27
teléfonos de la base real —los tres formatos de la parroquia, en trece columnas de doce
tablas— y todos pasan. Era la condición para no romper la edición de una ficha existente.

El texto que explica la regla vive en `TELEFONO_FORMATO` (`config/app.php`), a un paso de
`LADA_PAIS` y del validador, y lo usan tanto los mensajes de error como la ayuda debajo de
cada campo: cambiar la regla y cambiar la explicación son el mismo gesto. Es la lección de
los nombres de las pastorales, que se escribían en tres sitios distintos.

Lo que la validación **no** puede es cuidar la puntería: diez dígitos bien formados pero
equivocados abren la conversación de un desconocido, y eso no lo detecta nada.

### Moderación

Los coordinadores no tienen `avisos.publicar` ni `galeria.publicar`, así que en esas dos
escrituras el campo `publicado` se fuerza a 0 y el panel del editor muestra una bandeja de
"Pendientes de publicar". Con diez coordinadores con cuenta, esto es lo que evita que la
web parroquial amanezca con cualquier cosa. Sus eventos y sus cursos sí se publican solos
—ver "Publicación: el calendario lo publica quien lo organiza"—: una fecha del calendario
no es un texto dirigido a la parroquia, y la demora de la revisión costaba más que el
riesgo que evitaba.

## Dependencias: lo que se sirve y lo que solo está declarado

La regla de cero dependencias sigue en pie **del lado de PHP**: no hay Composer, no hay
`vendor/`, y lo que hace falta se escribe con funciones nativas —de ahí el sanitizador
sobre `DOMDocument`, el editor en JavaScript vanilla, el volcado de respaldos vía PDO y el
CSV en vez de un PDF—.

Del lado del navegador sí se cargan tres cosas de `cdn.jsdelivr.net`, las únicas
autorizadas en la CSP del `.htaccess`:

| Qué | Versión | Dónde |
|---|---|---|
| Bootstrap, hoja de estilos | 5.3.3 | Los dos layouts, `login.php` y `setup.php` |
| Bootstrap, JavaScript (`bundle`) | 5.3.3 | Solo los dos layouts — login e instalador no lo necesitan |
| Bootstrap Icons (fuente) | 1.11.3 | Los cuatro |
| Leaflet + los iconos de su marcador | 1.9.4 | Solo el formulario de visita de MESC (`MescController::assetsMapa()`) |

**`package.json` declara una dependencia que hoy no se sirve.** El repositorio versiona un
`package.json`/`package-lock.json` con `bootstrap-icons ^1.13.1`, y `node_modules/` queda
ignorado (se reinstala con `npm install`). Es constancia de un `npm install bootstrap-icons`
que se corrió en la máquina de desarrollo, no un cambio en lo que el sitio entrega: las
cuatro plantillas que cargan los iconos siguen apuntando al CDN, así que **la copia local no
se usa** y su versión (`^1.13.1`) ni siquiera coincide con la que piden las plantillas
(1.11.3). Mientras eso siga así la discrepancia no afecta a nada, y `npm` no hace falta ni
para instalar ni para desplegar el sitio.

Servir los iconos desde el propio repositorio —para no depender del CDN, que es la razón
por la que valdría la pena— es una decisión aparte y no se ha tomado: exigiría copiar
`node_modules/bootstrap-icons/font/` a `assets/`, cambiar las cuatro plantillas y ajustar
`font-src` en la CSP. Se deja anotado aquí para que la incoherencia no se lea como un
descuido.

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
como contenido parroquial general (`pastoral_id NULL`). Las tres tablas que solo existen
por su pastoral —`pastoral_actividades`, `pastoral_tablero` y `pastoral_documentos`— y
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

## Evangelio del día

`modules/evangelio/` calca `paginas` casi al detalle —es el módulo más parecido del
proyecto: contenido editorial de toda la parroquia, sin alcance por pastoral, un solo
booleano de publicación que cualquiera con el permiso de editar cambia libremente, sin un
permiso `.publicar` aparte—. El párroco, o cualquier cuenta Editor o Administrador, publica
desde el panel el evangelio del día y su reflexión, cada uno en su propio campo de texto
largo con el editor casero (`shared/views/parciales/editor_html.php`) y el mismo saneo con
lista blanca (`SanitizadorHtml::limpiar()`) que ya usan `bloques`/`sacramentos`/`paginas`
al guardar.

**Una fila por fecha, con `UNIQUE KEY` de verdad.** A diferencia de `avisos` o `eventos`,
donde repetir una fecha es normal (dos avisos el mismo día son dos avisos distintos), aquí
"el evangelio de hoy" es un concepto singular: solo puede haber uno. `uq_evd_fecha` hace
que `EvangelioModel::deHoy()` sea un `WHERE fecha = CURDATE() AND publicado = 1` trivial,
sin un `ORDER BY … LIMIT 1` que además tendría que decidir cuál de dos evangelios del mismo
día es el bueno, y sin que un descuido pueda dejar dos filas del mismo día capturadas por
error.

**Chocar con una fecha ya capturada no siempre es un error, y ahí está la única lógica
propia del módulo.** `EvangelioController::guardar()` distingue dos casos:

- **Alta (`id = 0`) que coincide con una fecha ya capturada**: se actualiza esa fila en vez
  de fallar con un error de clave duplicada. No hay una segunda entidad real que se esté
  perdiendo —es la misma intención de siempre, "publicar el evangelio de hoy", capturada
  dos veces sin querer, por una pestaña vieja o un doble clic en "Nueva entrada"—, así que
  corregir la intención sin drama vale más que un mensaje de error que nadie sabría
  interpretar.
- **Edición que reasigna la fecha a una que pertenece a OTRA fila ya existente**: aquí sí
  se rechaza, sin tocar ninguna de las dos. Ahí sí hay dos textos capturados por separado en
  momentos distintos, y fusionarlos en silencio perdería uno de los dos sin avisar.

Verificado en la aplicación real, los tres caminos: crear con una fecha nueva; crear dos
veces con la misma fecha (queda una sola fila, actualizada); y editar una fila
reasignándole la fecha de otra ya existente (rechazado, las dos filas intactas).

**Sin `slug`, sin `core/Slug.php`.** Se identifica por `fecha`, no por una URL de detalle
con título libre —el mismo caso que `bloques_contenido`/`configuracion` con su `clave`—.
Tampoco lleva columna `orden`: es el único contenido editable del proyecto sin ella, porque
aquí el orden ya lo da la propia fecha.

**El párroco necesita una cuenta con rol Editor o Administrador para publicar él mismo.**
`evangelio.ver` y `evangelio.editar` —este último cubre crear, actualizar, publicar y
eliminar, sin permisos separados para cada acción— los llevan únicamente esos dos roles
(`ROL_ADMIN` por el comodín `'*'`, `ROL_EDITOR` de forma explícita en `config/app.php`);
ningún coordinador, consulta o secretaría los tiene. No existe en el proyecto ninguna forma
de dar un permiso a una sola cuenta sin dárselo a todo el rol —**"el rol dice qué puede
hacer; la pastoral y la sede asignadas, sobre qué"** (ver "Roles y permisos")—, y no se
construyó ningún mecanismo nuevo para este caso: sería una arquitectura paralela sin
precedente para algo que el proyecto entero ya resuelve de otra forma. Ni siquiera
`usuarios_perfiles` (perfiles adicionales) ayuda aquí, porque un perfil adicional es "un rol
distinto sobre UNA pastoral", y `evangelio.*` no pertenece a ninguna: es de toda la
parroquia, igual que `paginas.*` o `bloques.*`. Es exactamente la misma situación que ya
existe hoy con el bloque "bienvenida del párroco" de la portada (`bloques_contenido`), que
también edita cualquier cuenta con rol Editor.

**En el sitio público, solo una sección en la portada.** `InicioController` consulta
`EvangelioModel::deHoy()` directo, sin controlador público propio ni entrada en
`$rutasPublicas` —mismo patrón que `BloqueModel`/`CarruselModel`—, y
`modules/inicio/views/publico/index.php` la dibuja justo después de la bienvenida del
párroco y antes de "Próximas misas": es el contenido que cambia más rápido de toda la
portada, a diario contra semanal, y sigue temáticamente a las palabras del párroco.
Desaparece por completo si no hay entrada publicada para hoy, la misma regla que ya sigue
el resto de la portada (ver "La portada", abajo). Deliberadamente no tiene archivo
navegable ni página de detalle propia: no hay forma de ver el evangelio de un día anterior
desde el sitio público. Es una decisión tomada, no una limitación técnica —se puede añadir
después sin tocar el modelo ni el controlador del panel, sumando un
`EvangelioPublicoController` y una entrada en `$rutasPublicas`, calcando `avisos`/
`sacramentos`—.

## La portada

`InicioController::index()` es el controlador que consulta más modelos de todo el sitio —siete—
y todo lo que arma son listas cortas con enlace a la sección completa: el carrusel
(`CarruselModel`, vía `hero()`), la bienvenida del párroco y las ligas de interés (dos bloques
de `bloques_contenido`), el evangelio de hoy (`EvangelioModel::deHoy()`, ver "Evangelio del
día"), `HorarioModel::proximasMisas(3)`, `EventoModel::proximos(3)`,
`CursoModel::proximos(3)` y `AvisoModel::recientes(3)`. Cada sección desaparece por completo
si su consulta viene vacía, en vez de dejar un encabezado con un hueco debajo; es lo que
permite desplegar el sitio con el contenido a medio cargar sin que se note como un error.

**Próximos cursos, en su propia columna junto a los eventos.** La portada no anunciaba los
cursos, que son de lo poco del sitio con una fecha límite real —cuando alguien se enteraba,
la inscripción podía llevar semanas cerrada—. Ahora eventos y cursos van uno en cada mitad
de una misma fila, y los avisos bajan a la suya.

`CursoModel::proximos()` **no** es una copia de `EventoModel::proximos()`, y la diferencia es
deliberada:

- Trae los publicados que no han terminado (`COALESCE(fecha_fin, fecha_inicio) >= CURDATE()`)
  **más los que todavía no tienen fechas puestas** — un curso permanente, o uno cuyas fechas
  aún no se deciden, sigue siendo información útil para quien quiere apuntarse.
- Un curso ya terminado deja de aparecer. Un evento pasado sí se conserva como registro
  histórico de lo que la parroquia organizó; a un curso lo que le importa es que alguien se
  pueda inscribir, y anunciar uno que ya acabó solo confunde.
- El orden es `(fecha_inicio IS NULL), fecha_inicio, orden, titulo`: primero lo que tiene
  fecha, en orden cronológico, y los sin fecha al final, donde el `orden` del catálogo
  decide.

La tarjeta anuncia "inscripciones abiertas" **solo si de verdad se puede uno inscribir**: la
casilla `inscripciones_abiertas` activa *y*, si hay `fecha_cierre_inscripcion`, que no haya
pasado. Es la misma condición que aplica `CursoPublicoController` al decidir si dibuja el
formulario; anunciarlo con una sola de las dos mitades llevaría a la gente a una ficha sin
formulario.

**Fuera el bloque de contacto rápido.** Repetía dirección, teléfono, correo y horario de
oficina que el pie de página ya lleva en **todas** las pantallas del sitio, incluida la
portada. Ese bloque venía del planteamiento de landing page (ver
[`PLAN.md`](PLAN.md#sitio-tradicional-no-landing-page)), donde tiene sentido porque no hay
otra página a la que ir; aquí no lo tenía. De paso, las secciones de eventos y avisos iban a
ancho completo mientras el resto de la portada va centrado en `col-lg-8`, así que quedaban
desalineadas entre sí: ahora todas usan el mismo contenedor.

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
iniciado sesión, incluidas sus limitaciones (sin publicar avisos, acotado a sus pastorales
también en lo que *ve* en eventos y cursos, sin
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
`modules/`, `shared/` y `docs/`. Esa misma regla del `.htaccess` nombra además `cli/`, que se
eliminó con el formulario de solicitud de sacramentos y ya no existe, y no nombra
`herramientas/`, que sí (ver "Carga de la agenda parroquial 2026").

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

## Carga de la agenda parroquial 2026 (`herramientas/`)

Primera carga masiva de contenido real del proyecto: la agenda impresa de 2026 completa —467
eventos y 22 actividades semanales— en vez de capturarla evento por evento desde el panel.
Dos scripts, ninguno de los cuales forma parte del sitio: `herramientas/` no se despliega, y
nada de lo que hay ahí se ejecuta durante una petición HTTP.

**Por qué dos scripts y no uno.** El punto de partida es un `.xlsx` en el que alguien
transcribió la agenda impresa, y está montado para imprimirse, no para leerse con un
programa:

- Las actividades no están en las celdas, sino en **cuadros de texto flotantes**; las celdas
  solo llevan los números de día.
- Cada bloque de 8 columnas trae **dos medios meses**, porque así se imprime: DOM-MIE de un
  mes con fondo blanco y JUE-SAB de **otro** mes con fondo amarillo, donde el mes amarillo es
  el complemento a 12 del blanco (mayo↔julio, abril↔agosto…).
- Los títulos de las barras de periodo vienen con las letras separadas
  (`S E M A N A   D E   C A T E Q U E S I S`) para que ocupen todo el ancho, y troceados
  entre varios cuadros cuando el periodo cruza semanas.

`extraer_agenda.py` lee la geometría de esos cuadros (posición y tamaño en EMU), la cruza con
la rejilla del calendario para deducir a qué día corresponde cada uno —con un umbral de
solape del 30 %, porque los cuadros se dibujan más anchos que la celda y desbordan a las
vecinas— y saca **una hoja revisable**, no la carga directa. Parsea el XML del `.xlsx` a mano
porque `openpyxl.load_workbook()` se cae con este archivo (un `pitchFamily` del drawing está
fuera del rango que acepta); openpyxl sí se usa para *escribir* el resultado.

La hoja de salida (`agenda-2026-extraida.xlsx`) tiene cuatro pestañas, y el paso por Excel es
el punto de la herramienta: **lo que no se puede resolver automáticamente se marca en vez de
adivinarse**. La pestaña `Revisar` reúne los números de día mal transcritos, las barras de
periodo que no se pudieron recomponer, las horas sin a.m./p.m. y las filas sin fecha
resuelta; `Resumen` da los totales por mes y por clase, y el mapa de qué mes cayó en cada
mitad de cada bloque, que es lo que permite detectar un bloque leído al revés de un vistazo.
Las tres clases de fila (`evento`, `mensual` para lo recurrente de cada mes, `periodo` para
las barras de varios días) solo deciden el color del evento —verde para los periodos, azul
para el resto— y aparecen en el resumen.

`importar_agenda.php` es la segunda mitad, y solo corre desde la línea de órdenes: si llega
por HTTP responde 403 y no hace nada. Tres decisiones:

- **Escribe con `EventoModel` y `HorarioModel`, no con SQL a pelo**, para que las filas queden
  exactamente como si se hubieran creado desde el panel: mismos valores por omisión, mismo
  `Slug::unico()` resolviendo colisiones. El texto que va a `descripcion` se escapa con
  `htmlspecialchars()` y se envuelve en un `<p>` —en vez de pasar por
  `SanitizadorHtml`— porque lo que llega de la hoja es texto plano, no HTML: no hay etiquetas
  que filtrar, solo acentos y `&` que escapar.
- **Es idempotente, comparando por día + título + hora, no por slug.** Un slug es lo primero
  que cambia si el mismo evento se capturó a mano con otro título, así que compararlo no
  sirve para "¿ya está esto?". La hora entra en la comparación porque la misma celebración a
  otra hora **sí** es otro evento: el Miércoles de Ceniza son cuatro misas ese día, no una
  fila repetida cuatro veces. Con esa regla, las 478 filas del JSON dieron 467 eventos y 11
  omisiones —repeticiones de la misma actividad en más de un cuadro de la hoja—, y volver a
  ejecutarlo no crea nada.
- **No deja rastro en `auditoria`.** Al no pasar por un controlador no hay
  `Controller::auditoria()` que lo registre, y añadirlo a mano habría significado 467 filas de
  bitácora para una sola operación. Está advertido en la cabecera del script y en su salida;
  la constancia de la carga es el respaldo previo (`backups/antes-agenda-2026.sql`), no la
  bitácora.

**Las actividades semanales van a `horarios`, no a `eventos`**, que es exactamente la
distinción de "Las misas recurrentes no son eventos" (§ arriba) aplicada a una carga
automática: el panel de "actividades semanales" de la agenda es recurrencia de día de la
semana más hora. Entran con `tipo = 'otro'` y el nombre de la actividad en `nota`, porque
`horarios` no tiene columna de título —lo que la página pública muestra de cada horario es
precisamente la nota—. Antes de insertar, el propio extractor consulta los horarios ya
cargados y marca cada fila en la columna `¿Ya existe?`: coincidir día + hora + centro es "ya
está" y no se reimporta; coincidir solo día y hora se avisa como "quizá, misma hora en otra
sede", que es una decisión para la persona que revisa, no para el script.

**El paso intermedio por JSON existe por una limitación del entorno, no por diseño:** el PHP
de este XAMPP no trae la extensión `zip`, así que no puede abrir un `.xlsx`. `--a-json`
convierte la hoja ya revisada a lo que el importador lee. Si algún día ese PHP tuviera `zip`,
el paso se podría quitar sin cambiar nada más.

**Dos cosas que este par de scripts asume, y conviene saber antes de reutilizarlos:**

1. **Python y `openpyxl` son dependencias de desarrollo, no del proyecto.** No las necesita
   ni el sitio ni el despliegue; solo la máquina donde se convierte la hoja. La regla de cero
   dependencias es sobre lo que corre en el servidor.
2. **Los ids de `centros` y `pastorales` están escritos en el propio script Python.** No se
   siembran en `install.sql` —se generan al crearlos desde el panel—, así que las tablas
   `CENTROS` y `PASTORALES` de `extraer_agenda.py` reflejan la instalación local de hoy
   (sede = 1, San Pío = 2, Jesús el Señor = 3; MESC = 16, Catecismo = 18…). En otra base de
   datos habría que revisarlos, o el evento acabaría colgado de la pastoral equivocada. Las
   casas de oración y Didec se reconocen como lugar pero no existen como centro, así que se
   guardan solo en el texto de `lugar`, con `centro_id` nulo.

**El patrón se reutilizó tal cual para la lista de Proclamadores.**
`extraer_proclamadores.py` + `importar_proclamadores.php` metieron al equipo pastoral las 26
personas de la hoja que levantó la propia pastoral: mismo reparto de trabajo —Python abre el
`.xlsx` porque este PHP sigue sin `zip`—, mismo `--dry-run`, misma idempotencia y misma
escritura a través del modelo (`PersonaModel`), para que las fichas queden como si se
hubieran creado desde el panel. Y la misma regla de marcar en vez de adivinar: los años de
nacimiento que el formulario de la hoja autocompletó se guardan como 1900, la marca de "año
desconocido" (ver [`BASE-DE-DATOS.md`](BASE-DE-DATOS.md)), y el nombre repetido con dos
fechas que se contradicen entró sin fecha para que alguien lo confirme. Entraron 22 fichas
nuevas; las otras cuatro personas ya estaban en el equipo —tres sirviendo también en MESC, y
la coordinadora— y solo se les completó lo que faltaba.

**Pendiente conocido:** la lista de carpetas que el `.htaccess` bloquea por HTTP
(`^(config|core|modules|shared|docs|cli|backups)/`) sigue nombrando `cli/`, que ya no existe,
y **no incluye `herramientas/`**. Hoy nada es explotable —el importador rechaza cualquier
invocación que no venga de la línea de órdenes, y el `.py` se serviría como texto—, pero es
defensa en profundidad que falta, y `herramientas/` tampoco está en la lista de exclusiones
del paquete de despliegue de [`DESPLIEGUE.md`](DESPLIEGUE.md).

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

### El token CSRF dura lo que dura la sesión, y no rota en cada envío

`Controller::validarCsrf()` llamaba a `Session::renovarCsrf()` después de cada POST válido.
Parecía lo más estricto y resultó ser solo lo más frágil: con el token rotando por
petición, **cualquier página cargada antes del último envío se queda con un token muerto**.
Eso rompe tres cosas normales —dos pestañas abiertas del panel, el botón atrás, y volver a
enviar un formulario tras un error— y una que es peor que las tres: **recargar la propia
pantalla de «Token de seguridad inválido»**, porque el navegador reenvía el mismo POST con
el mismo token ya gastado y vuelve a fallar por muchas veces que se recargue. Quien lo
sufre no tiene forma de salir del bucle salvo volver a escribir la URL a mano, y desde
fuera parece que el sitio está roto.

Rotar por petición **no añade protección real contra CSRF**: lo que la da es que un tercero
no pueda leer el token, y de eso se encargan el mismo origen y la cookie `samesite=Strict`.
Un token por sesión es la línea base que recomienda OWASP, y es lo que hay ahora.

Lo que sí importa es que el token cambie **cuando cambia quién eres**, para que uno obtenido
antes de autenticarse no siga valiendo después. De eso se encarga `Session::regenerar()`,
que ahora renueva el token además del identificador de sesión y que ya se llamaba en los
dos momentos en que la identidad cambia —iniciar sesión y elegir perfil adicional—; al
salir, `Session::destruir()` se lleva la sesión entera. Un token inventado o ausente se
sigue rechazando con 403, comprobado.

- **`uploads/.htaccess`** con `php_flag engine off` y denegación de `.php`, `.phtml` y
  `.phar`. Es la mitigación crítica: en el sistema de inventario los archivos los sube
  únicamente el personal interno, mientras que aquí hay muchas más cuentas cargando
  imágenes.
- **Extensión derivada del MIME real**, nunca del nombre original del archivo.
- **Content-Security-Policy** en `.htaccess`, acotada a `'self'` y `cdn.jsdelivr.net`.
- **HTTPS forzado y HSTS** en producción.
- **Auditoría también en lectura** de datos personales, no solo en escritura, porque es lo
  que permite responder a un requerimiento de acceso. Ver [`PRIVACIDAD.md`](PRIVACIDAD.md).
