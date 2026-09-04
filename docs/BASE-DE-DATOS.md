# Base de datos

Diccionario de las **42 tablas** del sistema: las 24 de las diez etapas del plan original,
más las que trajeron el issue #3 y la revisión de módulos —`centros`, `usuarios_centros`,
`respaldos_log`, las seis tablas de MESC, las tres de Catequesis, las tres de
Proclamadores, el catálogo de colores litúrgicos que MESC y Proclamadores comparten y el
tablero de actividades que puede usar cualquier pastoral,
entre otras—, menos `sacramento_campos`, `solicitudes_sacramento` y `solicitudes_bitacora`
(el issue #3 eliminó el formulario de solicitud en línea de sacramentos). El desglose exacto
está en el resumen del final. El esquema real vive en `install.sql`; este documento explica
el porqué de cada tabla y sus columnas relevantes.

## Convenciones

Las mismas del sistema de inventario, sin excepciones:

- `CREATE TABLE IF NOT EXISTS`, `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`.
- Nombres de tabla en español, plural, `snake_case`.
- `id` autoincremental con el **tipo entero más pequeño que sirva**: `TINYINT UNSIGNED`
  para catálogos cortos, `SMALLINT UNSIGNED` para catálogos medianos, `INT UNSIGNED` para
  tablas de volumen, `BIGINT UNSIGNED` solo para la auditoría.
- Booleanos como `TINYINT(1) NOT NULL DEFAULT 1`, típicamente `activo`, `activa` o
  `publicado`.
- Marcas de tiempo: `created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`.
- Índices con prefijo: `uq_` para únicos, `idx_` para búsqueda, `fk_` para claves foráneas.
- **Borrado lógico**: los registros se desactivan, no se eliminan.
- Columnas alineadas verticalmente y secciones separadas por comentarios `-- ---- … ----`.

No hay sistema de migraciones. `install.sql` es un archivo único acumulativo que se
mantiene sincronizado etapa por etapa, hasta que el sitio salga a producción.

Sigue sin haberlo, pero ya existen las **primeras migraciones puntuales**.
`docs/migraciones/` —la carpeta que
[`DESPLIEGUE.md`](DESPLIEGUE.md#actualizaciones-posteriores) prometía para cuando el
esquema dejara de ser desechable— se estrenó con `2026-09-04-proclamadores.sql`, el
renombre de las tablas de Lectores a Proclamadores, y el mismo día se le sumó su hermana
`2026-09-04-tablero-y-documentos-compartidos.sql`, que saca de Catequesis el tablero de
actividades y los documentos para que sirvan a cualquier pastoral. Hicieron falta porque
la base de desarrollo ya tenía turnos, colores y documentos capturados y reimportar
`install.sql` los habría borrado. No cambia la regla de arriba: `install.sql` sigue siendo
acumulativo y ya trae los nombres nuevos, así que una instalación nueva no aplica ninguna
migración; los archivos de `docs/migraciones/` son para las bases que ya tienen datos. Las
dos crean las tablas y copian las filas en vez de renombrar con `ALTER`, porque MariaDB
10.4 no admite `ALTER TABLE … RENAME INDEX` y renombrando se quedarían los índices con el
nombre viejo — una base migrada dejaría de ser idéntica a una recién instalada, que es
justo lo que este documento describe.

Cuidado con una consecuencia que antes no existía: **la base local ya contiene contenido real
que `install.sql` no siembra** (la agenda de 2026, los centros, las pastorales con sus
ministros y catequistas, el equipo pastoral, los horarios). Reimportar el archivo desde cero
sigue siendo la prueba de que el esquema está completo, pero hay que respaldar y restaurar
alrededor. Ver [`DESPLIEGUE.md`](DESPLIEGUE.md#actualizaciones-posteriores).

---

## Núcleo y seguridad

### `usuarios`

Cuentas del panel de administración.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | INT UNSIGNED | |
| `nombre` | VARCHAR(120) | |
| `email` | VARCHAR(150) | `uq_usr_email`; es el identificador de acceso |
| `password_hash` | VARCHAR(255) | bcrypt con coste 12 |
| `persona_id` | SMALLINT UNSIGNED NULL | `uq_usr_persona`, FK a `personas` `ON DELETE SET NULL`. Su ficha del equipo pastoral, de donde salen nombre, teléfono y foto. NULL = cuenta sin ficha (la del administrador), que así no aparece en el directorio público |
| `rol` | ENUM | `admin`, `editor`, `secretaria`, `coordinador`, `coordinador_general`, `consulta`. Los seis roles con la pastoral en el nombre (`admin_mesc`, `consulta_catequesis`…) se retiraron: ver [`ARQUITECTURA.md`](ARQUITECTURA.md#roles-y-permisos) |
| `foto` | VARCHAR(255) | Opcional |
| `telefono` | VARCHAR(20) | |
| `activo` | TINYINT(1) | Baja lógica |
| `ultimo_acceso` | DATETIME | |
| `created_at` | DATETIME | |

### `usuarios_pastorales`

Pivote. Una persona puede coordinar varias pastorales a la vez, que es lo habitual —y es
también como se representa a quien coordina la misma pastoral en varias sedes: una fila por
sede. **Es la única fuente del alcance**: `Auth::cargarPastorales()` no lee ninguna otra
tabla. Clave primaria compuesta `(usuario_id, pastoral_id)`, ambas foráneas con
`ON DELETE CASCADE`.

### `usuarios_centros`

Las sedes en las que trabaja la persona, y **la otra mitad del alcance**: acota sus
pastorales a esas comunidades. Clave primaria compuesta `(usuario_id, centro_id)`, ambas
foráneas con `ON DELETE CASCADE`.

**Ninguna fila significa «en todas las sedes»**, al revés que `usuarios_pastorales`, donde
ninguna fila es no poder con nada. Así se representa una coordinación general. La asimetría
está explicada en [`ARQUITECTURA.md`](ARQUITECTURA.md), "El alcance tiene dos mitades".

Ojo con el histórico: hasta la revisión de alcance esta misma tabla significaba «administra
el centro completo» y *añadía* todas las pastorales de esa sede. Un respaldo anterior a ese
cambio tiene filas que hoy quieren decir otra cosa —donde antes daban permiso de más, ahora
recortan—, así que conviene revisarlas al restaurarlo. No confundir tampoco con
`persona_centros`: esa es la adscripción de alguien del directorio a una sede, no un permiso.

### `usuarios_perfiles`

Perfiles adicionales de acceso: una persona con dos responsabilidades de nivel distinto a
la vez (coordina una pastoral, solo consulta otra) sin necesitar una segunda cuenta —
`usuarios.persona_id` es UNIQUE a propósito, una persona tiene como máximo una fila en
`usuarios`—. Cada fila es "un rol distinto sobre UNA pastoral distinta"; a diferencia de
`usuarios_pastorales`/`usuarios_centros` no es un pivote, `pastoral_id` y `centro_id` son
columnas directas: nadie necesita hoy que un solo perfil adicional cubra varias pastorales
a la vez —si fuera el mismo nivel, esa pastoral se agrega al checklist normal de la
cuenta—. `centro_id` NULL significa «todas las sedes», misma semántica que
`usuarios_centros`. `rol` se restringe en código a los roles con alcance por pastoral
(nunca admin/editor/secretaría).

Quien inicia sesión y tiene 1+ perfiles adicionales activos elige con cuál entrar antes de
completar el login (`Auth::intentarLogin()`/`AuthController::elegirPerfil()`); sin
perfiles, el login es idéntico al de siempre. Ver [`ARQUITECTURA.md`](ARQUITECTURA.md).

### `auditoria`

Bitácora de acciones. `id BIGINT UNSIGNED`, más `usuario_id`, `admin_real_id` (FK a
`usuarios`, `ON DELETE SET NULL`), `accion` VARCHAR(40), `tabla_ref` VARCHAR(60),
`registro_id`, `ip` VARCHAR(45), `descripcion` VARCHAR(255) y `created_at`. Índices
`idx_aud_fecha (created_at)` e `idx_aud_tabla (tabla_ref, registro_id)`.

Registra escrituras **y también lecturas de datos personales**, incluidas las
exportaciones a CSV. Ver [`PRIVACIDAD.md`](PRIVACIDAD.md).

`admin_real_id` solo se llena durante una impersonación ("Usar como…", ver
[`ARQUITECTURA.md`](ARQUITECTURA.md)): `usuario_id` queda con la identidad efectiva de la
sesión en ese momento (la cuenta impersonada), y `admin_real_id` con el administrador real
detrás. NULL en el uso normal, sin impersonación.

### `respaldos_log`

Historial de respaldos **y restauraciones** de la base de datos (`modules/respaldos`),
añadido tras cerrar las diez etapas del plan original. `tipo` ENUM(`respaldo`,
`restauracion`), `archivo` VARCHAR(180) — el nombre del `.sql` dentro de `backups/`, fuera
del control de versiones —, `tamano_bytes`, `num_tablas`, `num_registros` (en una fila de
restauración, el número de sentencias SQL ejecutadas), `usuario_id` (FK a `usuarios`,
`ON DELETE SET NULL`), `estado` ENUM(`completado`, `error`), `notas` (mensaje de error, o el
nombre del respaldo de seguridad automático previo si aplica) y `created_at`.

El archivo físico no vive en la base de datos, solo su referencia; `RespaldoModel` verifica
con `is_file()` si todavía existe antes de ofrecer la descarga o la restauración. Esta misma
tabla queda **excluida** del volcado que ella describe (ver
[`ARQUITECTURA.md`](ARQUITECTURA.md), sección "Respaldos y restauración de la base de
datos"): incluirla causaba que restaurar un respaldo viejo borrara la fila del respaldo de
seguridad recién creado para esa misma restauración.

### `configuracion`

Pares clave-valor globales, agrupados por `grupo` (`general`, `contacto`, `redes`, `seo`,
`legal`, `secciones`). `clave` VARCHAR(60) con `uq_cfg_clave`, `valor` TEXT.

Claves sembradas: `parroquia_nombre`, `parroquia_diocesis`, `direccion`, `ciudad`, `cp`,
`telefono`, `whatsapp`, `email`, `mapa_embed`, `latitud`, `longitud`, `horario_oficina`,
`facebook`, `instagram`, `youtube`, `logo`, `favicon`, `og_imagen`, `meta_descripcion`,
`aviso_privacidad_version`, `organigrama_imagen`, `cursos_activo`.

**`cursos_activo`** (`'1'`/`'0'`, tipo `booleano` en `ConfiguracionModel::CAMPOS`, grupo
`secciones`): interruptor manual e independiente del contenido para ocultar la sección
pública de Cursos. A diferencia de `Router::existeRutaPublica()` —que solo dice si el
módulo ya está conectado en el código, permanente una vez integrado— esta clave la
apaga y prende el administrador desde el panel, sin tocar código. Se revisa en
`shared/views/parciales/publico_navbar.php` (oculta el enlace del menú) y en
`CursoPublicoController::activo()` (además del enlace, bloquea también el acceso
directo por URL: con la clave en `'0'`, `index()`, `ver()` e `inscribirse()` responden
404 en vez de mostrar una sección vacía). No afecta al panel de administración: el
módulo de Cursos sigue totalmente operable ahí para preparar contenido mientras la
sección pública está apagada.

---

## Contenido editable

### `bloques_contenido`

Textos largos anclados a zonas fijas del sitio.

| Columna | Notas |
|---|---|
| `clave` | VARCHAR(60) con `uq_blq_clave`; es lo que busca la vista |
| `zona` | Agrupa por página: `inicio`, `nosotros`, `horarios`, `sacramentos`, `pastorales`, `cursos`, `contacto` |
| `titulo` | Encabezado que se muestra sobre el texto |
| `descripcion` | Explica al editor **dónde aparece** el bloque. Sin esto, una lista de claves como `mision` o `historia` no le dice nada a nadie |
| `contenido` | MEDIUMTEXT con HTML ya saneado |
| `imagen` | Opcional, acompaña al texto |
| `orden`, `activo` | Un bloque desactivado no se muestra en la página |
| `actualizado_por`, `updated_at` | Quién lo cambió y cuándo |

Once claves sembradas: `bienvenida_parroco`, `inicio_intro`, `historia`, `mision`,
`vision`, `valores`, `horarios_intro`, `sacramentos_intro`, `pastorales_intro`,
`cursos_intro`, `contacto_intro`.

El panel edita el contenido pero **no puede crear ni borrar claves**: son las anclas que
las vistas esperan encontrar. Las semillas usan `INSERT IGNORE`, así que reimportar
`install.sql` sobre una base con datos no pisa nada y una clave nueva aparece sola.

### `paginas`

Páginas libres con slug propio, para lo que no cabe en las secciones fijas. Aquí vive el
aviso de privacidad. `slug` VARCHAR(120) con `uq_pag_slug`, `titulo`, `contenido`
MEDIUMTEXT, `meta_descripcion` VARCHAR(200), `en_menu`, `orden`, `publicada`,
`actualizado_por`, `updated_at`.

El slug `aviso-de-privacidad` está protegido en código (`PaginaModel::PROTEGIDAS`): no se
borra desde el panel y su slug no cambia aunque se envíe otro. Se instala con
`publicada = 0`: es contenido de referencia con datos entre corchetes que la parroquia
debe completar antes de publicarlo.

### `carrusel`

Diapositivas de la portada. `imagen` obligatoria, más `titulo`, `subtitulo`, `enlace`,
`orden` y `activo`. `id TINYINT UNSIGNED`: no van a ser muchas.

### `galeria_imagenes`

`archivo`, `titulo`, `alt_texto`, `pastoral_id` y `evento_id` opcionales, `orden`,
`publicada`, `usuario_id`, `created_at`. `evento_id` tiene FK real a `eventos(id)` — esa
tabla ya existe en el mismo `install.sql`. `pastoral_id` todavía no: se agrega en la
etapa 6, cuando exista `pastorales`. Mismo caso en `avisos.pastoral_id` y
`eventos.pastoral_id`.

La columna que importa es **`autorizacion_imagen TINYINT(1) NOT NULL DEFAULT 0`**: deja
constancia de que existe autorización para usar esa fotografía. La consulta pública filtra
`WHERE publicada = 1 AND autorizacion_imagen = 1`, de modo que una foto sin autorización
registrada no puede llegar al sitio ni por descuido.

---

## Parroquia

### `centros`

La sede parroquial y los centros que dependen de ella, en un solo catálogo: `tipo`
ENUM(`sede`, `centro`), `nombre`, `direccion`, `telefono`, `descripcion`, `imagen`, `orden`,
`activo`. Sembrada con los datos reales de la parroquia (issue #3): una fila `sede`
("Parroquia Nuestra Señora de la Paz") y dos `centro` ("San Pío de Pietrelcina", "Jesús el
Señor"). Ver [`ARQUITECTURA.md`](ARQUITECTURA.md), sección "Sede y centros".

### `personas`

Párroco, vicarios, diáconos, religiosos, laicos y personal. `nombre`, `cargo`, `tipo`
ENUM(`parroco`, `vicario`, `diacono`, `religioso`, `laico`, `staff`), `semblanza`, `foto`,
`email`, `telefono`, `fecha_nacimiento`, `orden`, `activo`.

Solo se publica el correo institucional. Ver [`PRIVACIDAD.md`](PRIVACIDAD.md).

`fecha_nacimiento` (DATE, opcional) es para `PersonaModel::cumpleanerosDelMes()`, que arma
la tarjeta "Cumpleaños de [mes]" del panel de inicio (`PanelController::index()`). Solo
importan mes y día: no se muestra el año ni se calcula edad en ningún lado, aunque la
columna lo guarde porque `DATE` no admite mes/día sin año.

Cada cumpleañero lleva **su pastoral debajo del nombre**, en letra más pequeña: media
parroquia no se conoce entre sí y un nombre a secas no dice de dónde es. Sale de la misma
subconsulta de `persona_pastorales` que usa `todas()`, y la vista recorta a dos con un `+N`
—hay quien está marcada en cinco, Comisiones incluidas, y una pastoral se llama "Ministro
Extraordinario de la Sagrada Comunión": sin recortar, una sola persona desborda la tarjeta—.
La lista completa va en el `title` del elemento.

**El año `1900` es la marca convenida de "año desconocido"**, y sale de un caso real: la
lista que levantó Proclamadores traía el año autocompletado por el formulario en el que se
capturó —el año en curso— en 12 de sus 26 filas, más una fecha ilegible. Se guarda 1900
conservando el día y el mes, que son los que se celebran, en vez de dejar la columna en
NULL y perderlos también. Encaja con `cumpleanerosDelMes()`, que solo usa `MONTH()` y
`DAY()`, así que el año falso no se asoma a ninguna pantalla del sitio. Donde sí importa es
al editar la ficha: `modules/personas/views/form.php` avisa cuando la fecha que muestra
tiene ese año, para que quien la abra entienda que 1900 no es un dato sino un hueco por
llenar. La fecha la construye así `herramientas/extraer_proclamadores.py`, y
`herramientas/importar_proclamadores.php` repite el valor solo para avisar en pantalla
cuántas fichas quedan con él: los dos scripts declaran la misma constante
`ANIO_DESCONOCIDO`, y cambiar uno sin el otro descuadra ese aviso. Es la convención a
seguir en cualquier carga posterior.

### `persona_pastorales`

Pivote análogo a `usuarios_pastorales`: una persona del equipo suele llevar más de una
pastoral a la vez (catequesis y liturgia, por ejemplo). Clave primaria compuesta
`(persona_id, pastoral_id)`, ambas foráneas con `ON DELETE CASCADE`.

### `persona_centros`

Pivote análogo, pero por centro/sede: `(persona_id, centro_id)`, ambas foráneas con
`ON DELETE CASCADE`. Igual que con las pastorales, alguien del equipo puede estar
adscrito a más de un centro a la vez.

### `organigrama_nodos`

Árbol autorreferenciado de hasta cuatro niveles. `padre_id` con clave foránea a sí misma y
`ON DELETE SET NULL`, `titulo`, `persona_id` y `pastoral_id` opcionales, `nivel`, `orden`,
`activo`. Índice `idx_org_padre`.

Un nodo puede apuntar a una persona, a una pastoral, o a ninguna de las dos y ser
simplemente un título de agrupación.

### `horarios`

Recurrencia semanal, no fechas concretas.

| Columna | Tipo | Notas |
|---|---|---|
| `centro_id` | SMALLINT UNSIGNED NULL | issue #3: FK a `centros`, `ON DELETE SET NULL`. NULL = horario sin sede/centro asignado |
| `tipo` | ENUM | `misa`, `confesion`, `adoracion`, `oficina`, `otro` |
| `dia_semana` | TINYINT UNSIGNED | 0 = domingo … 6 = sábado |
| `hora` | TIME | |
| `hora_fin` | TIME NULL | Para adoración y oficina |
| `lugar` | VARCHAR(120) | Templo, capilla, etc. |
| `nota` | VARCHAR(160) | "Con coro", "Bilingüe"… |
| `vigente_desde` | DATE NULL | Horarios de temporada |
| `vigente_hasta` | DATE NULL | |
| `orden`, `activo` | | |

Índices `idx_hor_tipo_dia (tipo, dia_semana, hora)` e `idx_hor_centro (centro_id)`.

**Agrupado público (`HorarioModel::vigentesPorTipo(?int $centroId = null)`)**: el sitio
público agrupa por tipo —misa arriba, confesión al final, orden distinto al de `TIPOS`
que usa el admin— y dentro de cada tipo por día y hora, de lunes a domingo
(`MOD(dia_semana + 6, 7)` para reordenar sin tocar el valor guardado) y de la mañana a
la noche. `$centroId` es un filtro opcional (`?centro=` en la URL, validado contra
`centros.activo`): `null` mezcla todas las sedes/centros dentro de cada tipo (mostrando
el nombre del centro como etiqueta en cada horario); un id concreto acota todo a esa
sola sede/centro. El listado de admin (`todos()`) conserva el orden por `tipo` (misa
primero) y sin agrupar ni filtrar por centro, para facilitar la edición masiva.

**`tipo = 'otro'` es hoy la mitad de la tabla**: 22 de las 42 filas son las actividades
semanales de la agenda parroquial 2026 (grupos, ensayos, catequesis), cargadas con
`herramientas/importar_agenda.php`. Entraron aquí y no en `eventos` porque son recurrencia de
día y hora, que es exactamente para lo que existe esta tabla; el nombre de la actividad va en
`nota`, ya que `horarios` no tiene columna de título y la nota es lo que la página pública
muestra de cada fila.

### `pastorales`

`centro_id` (issue #3: FK a `centros`, `ON DELETE SET NULL`, NULL en las que ya existían
antes de este campo), `pastoral_padre_id`, `slug` con `uq_pas_slug`, `nombre`, `descripcion_corta`,
`descripcion` MEDIUMTEXT, `imagen`, `icono` (clase de Bootstrap Icons),
`responsable_nombre`, `responsable_persona_id`, `contacto_email`, `contacto_telefono`,
`dia_reunion`, `hora_reunion`, `lugar_reunion`, `acepta_voluntarios`, `orden`, `activa`,
`visible_en_menu`.

`responsable_persona_id` (FK a `personas`, `ON DELETE SET NULL`, índice `idx_pas_responsable`)
es el select del formulario: el responsable se elige del equipo pastoral. Con persona
elegida, `responsable_nombre` y `contacto_email` se recalculan solos —del nombre de su
ficha y del correo de acceso de su cuenta, si tiene una— y dejan de ser editables a mano;
`responsable_nombre` solo se sigue escribiendo libre cuando `responsable_persona_id` es
NULL (la persona todavía no está de alta en el equipo). Ver
[`ARQUITECTURA.md`](ARQUITECTURA.md#contenido-propio-por-pastoral-issue-3).

`pastoral_padre_id` (self-FK, `ON DELETE SET NULL`, índice `idx_pas_padre`) agrupa
pastorales bajo la Comisión que las coordina (Litúrgica agrupa a MESC, Coros...;
Profética agrupa a Catequesis, Misión). Máximo 2 niveles: una Comisión no puede a su vez
tener padre, y una pastoral que ya agrupa hijas no puede recibir uno — ninguna de las dos
reglas es un CHECK de SQL (no puede mirar otras filas), las valida
`PastoralController::guardar()`. No hay columna `tipo`/`es_comision`: "es Comisión" se
deriva de "tiene alguna hija" (`PastoralModel::tieneHijos()`), calculado en cada lectura
en vez de guardado aparte, para que nunca pueda desincronizarse de la realidad.

`visible_en_menu` (`TINYINT(1)`, default 0) decide si la pastoral aparece en el bloque
"Pastorales y comisiones" del menú del panel, con acceso a su panel básico (avisos,
eventos, cursos, documentos — ver `PastoralController::panel()`). No se activa sola al
crear la pastoral: es una acción deliberada y separada
(`PastoralController::menuActivar()`), restringida a `Auth::esAdmin()` y con confirmación
de contraseña, para no generar accesos de más antes de que de verdad se quiera exponer la
pastoral. Ver [`ARQUITECTURA.md`](ARQUITECTURA.md#panel-básico-por-pastoral-y-activación-en-el-menú).

### Los dos escalones de publicación (`avisos` y `cursos`)

Publicar no es un interruptor sino una escalera de dos peldaños, y las dos tablas la
modelan igual con dos columnas: `publicado_interno` (`TINYINT(1)`, default 0) lo hace
visible **dentro del panel** a los miembros de su pastoral, y `publicado` —que no cambió
de significado— lo saca al **sitio web**. Los tres estados de `ESTADOS_PUBLICACION`
(`config/app.php`) se derivan de esas dos columnas con `estado_publicacion()`
(`core/helpers.php`); no hay una tercera columna de estado.

`publicado = 1` exige `publicado_interno = 1`, y no solo por convención: lo aplican los
`CHECK` `chk_avi_escalon` y `chk_cur_escalon` (MariaDB los cumple desde 10.2). Hace falta
en la base y no solo en PHP porque hay guiones sueltos que escriben `publicado` por SQL
directo, fuera de la aplicación.

`publicado_interno_at` (`DATETIME`, nulo) se sella cuando el flag pasa de 0 a 1 y se
conserva mientras siga publicado. Es el momento real de publicación —ni `created_at`, que
es cuando se empezó el borrador, ni `fecha_publicacion`, que es un "visible desde" que
escribe la persona—, y de él salen el orden de las novedades del panel y la etiqueta
«Nuevo». Índices `idx_avi_interno` / `idx_cur_interno`.

Se eligió añadir columnas en vez de sustituir `publicado` por un `ENUM` justamente para no
tocar ninguna consulta pública: las cinco de `AvisoModel` que comparten `VIGENTE` y las
tres de `CursoModel` que repiten `publicado = 1` siguen igual, y los índices `idx_avi_pub`
/ `idx_cur_pub` se conservan. Ver
[`ARQUITECTURA.md`](ARQUITECTURA.md#publicar-en-dos-escalones-interno-y-público).

**Al actualizar una base ya existente** hacen falta el `ALTER TABLE` y el relleno, porque
`install.sql` solo cubre instalaciones nuevas: todo lo que hoy está público es por
definición interno, así que `UPDATE ... SET publicado_interno = 1, publicado_interno_at =
COALESCE(updated_at, created_at) WHERE publicado = 1` (en `cursos`, `created_at` a secas:
no tiene `updated_at`).

### `pastoral_actividades`

Actividades comunitarias y de apoyo social de cada pastoral. `pastoral_id`, `titulo`,
`descripcion`, `tipo` ENUM(`comunitaria`, `apoyo_social`, `formacion`, `liturgica`),
`orden`, `activa`. Foránea con `ON DELETE CASCADE`.

**No confundir con `pastoral_tablero`, la ficha de abajo, y de ahí que aquella cambiara de
nombre.** Esta tabla es la lista fija de *qué hace* la pastoral —tiene `tipo`, no tiene
fechas, y es la que se lee en su página pública—; la otra son actividades con vigencia. Las
dos se llamaron "actividades" durante un tiempo, con una escondida detrás de un prefijo de
módulo, y eso no ayudaba a nadie.

### `pastoral_tablero`

El "tablero o calendario" de una pastoral: actividades con vigencia que se publican o no,
como un mini-`eventos` —de hecho, mismas columnas—. `pastoral_id`
(FK, `ON DELETE CASCADE`), `titulo`, `descripcion`, `fecha_inicio` (NOT NULL), `fecha_fin`
(NULL = sin fecha de término), `publicado`, `orden`, `usuario_id`
(FK a `usuarios`, `ON DELETE SET NULL`), `created_at`. Índices `idx_ptb_pastoral` e
`idx_ptb_publicado (publicado, fecha_inicio)`.

**Se llamó `catequesis_actividades` y perdió el prefijo de módulo**, igual que
`colores_liturgicos` y por el mismo motivo: nació dentro de Catequesis, que fue quien
primero necesitó la pantalla, pero la pantalla no tiene nada de catequético. Cuando
Proclamadores pidió la misma, copiarla habría significado copiar también la tabla, y con la
plantilla de módulos que viene después cada módulo nuevo habría arrastrado otra igual.
La mudanza es `docs/migraciones/2026-09-04-tablero-y-documentos-compartidos.sql`, que
rebautizó de paso sus índices y restricciones —`idx_cta_*`/`fk_cta_*` pasaron a
`idx_ptb_pastoral`, `idx_ptb_publicado`, `fk_ptb_pastoral` y `fk_ptb_usuario`—; ninguna
columna cambió.

La administra `PastoralModel`, con `tablero()`, `entradaTablero()`,
`crearEntradaTablero()`, `actualizarEntradaTablero()` y `eliminarEntradaTablero()` —no
`actividad*()`, que en ese mismo modelo ya significa la lista fija de arriba—, y por ahí
entran los dos módulos que hoy la usan, Catequesis y Proclamadores. Es tabla de cualquier
pastoral, pero el panel básico (`PastoralController::panel()`) todavía no ofrece esta
pantalla: hoy solo se llega desde un módulo dedicado. Ver
[`ARQUITECTURA.md`](ARQUITECTURA.md).

### `pastoral_documentos`

Documentación descargable de cada pastoral (issue #3): reglamentos, guías, formatos.
`pastoral_id` (FK, `ON DELETE CASCADE`), `titulo`, `archivo` (ruta bajo `uploads/`, misma
convención que `avisos.archivo_pdf`; solo PDF por ahora), `orden`, `activo`, `usuario_id`
(FK a `usuarios`, `ON DELETE SET NULL`, quién lo subió), `created_at`. Sin edición desde el
panel más allá de agregar o quitar: para cambiar un documento se sube uno nuevo.

**Es una sola lista, escrita desde varios sitios.** La comparten el panel básico de
cualquier pastoral (`PastoralController::panel()`, que sube a
`uploads/pastorales/AAAA/MM/`) y los módulos dedicados de Catequesis y Proclamadores
(`uploads/catequesis/…`, `uploads/proclamadores/…`), los tres a través de los mismos
métodos de `PastoralModel`, y los tres leen las mismas filas. Hasta la migración
`2026-09-04-tablero-y-documentos-compartidos.sql` existía además una
`catequesis_documentos` idéntica columna por columna a esta, y eran dos listas ciegas la
una a la otra: un documento subido desde el panel básico de Catequesis no aparecía en su
módulo, y al revés. Sus filas se mudaron aquí —sin sus id, que habrían chocado con los que
esta tabla ya tenía; nadie los referenciaba con una foránea, y `archivo` viajó igual, así
que los PDF ya subidos siguen descargándose de donde están— y la tabla se eliminó. La
pantalla de Documentos de Proclamadores lo avisa en su cabecera, para que nadie capture el
mismo documento dos veces.

---

## MESC — Ministros Extraordinarios de la Comunión

> **Dato sensible.** De las seis tablas de este bloque, solo `mesc_visitas` (y su ruta,
> `mesc_rutas`/`mesc_ruta_visitas`) guarda el único dato de salud que trata el sistema.
> `mesc_ministros`/`mesc_turnos`/`mesc_turno_ministros` —y `colores_liturgicos`, que se
> describe aquí pero ya no es de este módulo— son un
> catálogo operativo normal, sin esa protección reforzada. Ver
> [`PRIVACIDAD.md`](PRIVACIDAD.md), sección "Dato
> sensible: MESC".

### `mesc_visitas`

Registro de visitas a enfermos para llevarles la comunión (issue #3). `pastoral_id`
(FK a `pastorales`, `ON DELETE CASCADE`, **NOT NULL** —a diferencia de avisos/eventos,
nunca es contenido parroquial general—), `nombre_enfermo`, `direccion` (obligatoria),
`latitud`/`longitud` DECIMAL(10,7) NULL (solo si se marcó el pin en el mapa),
`telefono`, `solicitante_nombre`/`solicitante_parentesco`/`solicitante_telefono` (quien
pide la visita en nombre del enfermo), `notas`, `activo` (deja de entrar en el cálculo de
rutas nuevas sin borrar el historial), `usuario_id` (FK, `ON DELETE SET NULL`),
`created_at`, `updated_at`.

No hereda `folio`, `estado` ni las columnas de consentimiento de la extinta
`solicitudes_sacramento`: no hay formulario público ni bandeja de aprobación, es una
herramienta interna del panel. Índice `idx_mvi_pastoral (pastoral_id, activo)`.

### `mesc_rutas` y `mesc_ruta_visitas`

Una ruta agrupa visitas activas en un orden concreto para un recorrido. `mesc_rutas`:
`pastoral_id` (FK), `nombre`, `usuario_id` (quién la generó), `created_at`.
`mesc_ruta_visitas`: pivote `(ruta_id, visita_id)` con `orden` — la única columna que se
edita después de generar la ruta, para ajustarla a mano.

`MescModel::ordenSugerido()` calcula el orden inicial con una heurística de vecino más
cercano sobre distancia Haversine (línea recta, no ruta real por calles), partiendo de
`configuracion.latitud`/`longitud` si están configuradas. Sin API de mapas de pago: ver
[`ARQUITECTURA.md`](ARQUITECTURA.md).

### `mesc_ministros`

Catálogo de quién sirve como Ministro Extraordinario de la Comunión (issue #3,
"calendario de turnos"). `pastoral_id` (FK, `ON DELETE CASCADE`), `persona_id`
(FK a `personas`, `ON DELETE SET NULL`, `UNIQUE` — `uq_mmi_persona`), `nombre`,
`telefono`, `activo` (solo los activos se pueden asignar a un turno nuevo), `created_at`.

Sigue siendo una tabla aparte de `personas` a propósito: `personas` es el equipo
pastoral que se muestra en público, con foto y semblanza, y un ministro MESC es un
voluntario interno que no necesariamente forma parte de esa vitrina. `persona_id` es el
puente opcional entre ambas cuando sí coinciden: con la persona elegida, el `telefono` se
toma de su ficha y `PersonaModel::sincronizarPersonal()` lo mantiene al día si la ficha
cambia; sin persona (todavía no está de alta en el equipo), es texto libre como siempre.

**`nombre` es la excepción: aquí es el nombre CORTO** —«Zulema», «Tino»—, el que cabe en
una casilla del calendario de turnos y con el que se reconoce a cada ministro al capturar
un calendario que venga de fuera. Se guarda siempre, aunque haya persona vinculada, y la
sincronización desde la ficha no lo pisa. En `catequesis_catequistas` y `proclamadores`,
en cambio, `nombre` sí viene de la ficha (ver más abajo). Índice
`idx_mmi_pastoral (pastoral_id, activo)`.

### `colores_liturgicos`

Catálogo de referencia: los cinco colores litúrgicos de la Iglesia (blanco, verde,
morado, rojo, rosa), cada uno con `nombre` (único, `uq_col_nombre`), `color_hex` y
`significado` (texto explicativo de cuándo se usa cada uno). `orden` controla en qué
secuencia aparecen. Mantenimiento libre desde el panel — no está codificado en PHP — para
que la parroquia pueda ajustar el texto o agregar alguno si hiciera falta.

**Es la única tabla de este bloque sin prefijo de módulo, y a propósito.** Se llamó
`mesc_colores_liturgicos` mientras MESC era el único que la administraba, aunque los
turnos de Proclamadores ya la referenciaran; cuando Proclamadores pasó a administrarla
también, desde su propia pantalla, el prefijo afirmaba algo falso —el significado de cada
color es el mismo para toda la parroquia, no un dato de un módulo— y se le quitó en
`docs/migraciones/2026-09-04-proclamadores.sql`, que de paso rebautizó su índice único de
`uq_mcl_nombre` a `uq_col_nombre`. Se sigue creando en este bloque de `install.sql`, antes
que `mesc_turnos`, nada más porque esa tabla la necesita ya declarada para su clave
foránea.

### `mesc_turnos` y `mesc_turno_ministros`

Un turno cubre una misa o evento en una fecha concreta: `pastoral_id` (FK), `fecha`,
`hora` NULL, `descripcion` VARCHAR(160) (qué se cubre: "Misa", "Santísimo", "Hora Santa",
"Misa de Niños"…), `color_liturgico_id` (FK a `colores_liturgicos`,
`ON DELETE SET NULL`, opcional), `usuario_id`, `created_at`. Sin FK a `horarios` ni a
`eventos` (ver [`ARQUITECTURA.md`](ARQUITECTURA.md)): un turno es una ocurrencia concreta,
no la recurrencia semanal de `horarios` ni un evento formal. `mesc_turno_ministros` es el
pivote `(turno_id, ministro_id)`, de 1 a N ministros por turno.
`MescController::turnoGuardar()` revalida cada `ministro_id` recibido contra
`ministrosActivos()` de esa pastoral antes de guardar: un ministro dado de baja no puede
colarse en un turno nuevo aunque se manipule el formulario.

---

## Catequesis — catequistas y periodos

Módulo dedicado **exclusivamente** a la pastoral de Catecismo, igual que MESC y
Proclamadores:
no hay selector de pastoral en ningún formulario — `CatequesisModel::pastoralId()`
resuelve la única pastoral por su `slug = 'catecismo'`, no por un id fijo (los id de
pastorales no se siembran en `install.sql`, se crean desde el panel)—. Sin controlador
público ni datos sensibles.

Son tres tablas, no cinco: sus pantallas de Actividades y Documentos siguen ahí, pero ya
no escriben en tablas propias sino en `pastoral_tablero` y `pastoral_documentos`, las
genéricas de cualquier pastoral. Ver esas dos fichas, más arriba.

### `catequesis_catequistas`

Nombre y contacto: `pastoral_id` (FK a `pastorales`, `ON DELETE CASCADE`), `persona_id`
(FK a `personas`, `ON DELETE SET NULL`, `UNIQUE` — `uq_ctq_persona`, mismo patrón que
`mesc_ministros.persona_id`), `nombre`, `telefono`, `email`, `orden`, `activo`. **No
tiene grado ni sacramento** — ver `catequesis_periodo_catequistas`: un catequista
normalmente no da el mismo grado cada ciclo, así que ese dato no puede ser fijo de la
persona.

### `catequesis_periodos`

Un ciclo de catecismo (ej. "2026-2027", de agosto a junio): `pastoral_id`, `nombre`,
`fecha_inicio`, `fecha_fin` (ambas NOT NULL: un periodo siempre tiene principio y fin,
a diferencia de `pastoral_tablero.fecha_fin` que sí puede quedar abierta),
`activo` (marca cuál es el periodo vigente).

### `catequesis_periodo_catequistas`

Qué catequista dio clase en qué periodo, y de qué grado — el pivote que responde
"qué catequistas estuvieron en cuál periodo". `grado` ENUM(`kinder`, `primero_primaria`,
`segundo_primaria`, `tercero_primaria`, `comunion`, `quinto_misionero`,
`sexto_misionero`, `primero_secundaria_misionero`, `segundo_secundaria`, `confirmacion`)
vive **aquí, no en `catequesis_catequistas`**: el mismo catequista puede dar
Segundo Primaria un ciclo y Tercero Primaria el siguiente, y esta tabla es la que
conserva esa historia completa en vez de sobrescribirla. Llave primaria compuesta
`(periodo_id, catequista_id)` — un catequista no puede tener dos grados a la vez en el
mismo periodo —, y `CatequesisModel::asignarCatequista()` usa
`INSERT ... ON DUPLICATE KEY UPDATE grado = VALUES(grado)` para que reasignar a alguien
ya presente en el periodo simplemente le cambie el grado, sin duplicar la fila.

### Las que este módulo dejó de tener: `catequesis_actividades` y `catequesis_documentos`

Hasta `docs/migraciones/2026-09-04-tablero-y-documentos-compartidos.sql`, el tablero de
actividades y los documentos de esta pastoral vivían en dos tablas propias. Ya no existen:
la primera es hoy `pastoral_tablero` —mismas columnas, sin prefijo— y las filas de la
segunda se mudaron a `pastoral_documentos`, que era idéntica a ella y que el panel básico
de la pastoral ya mostraba. Las dos fichas están en el bloque de Parroquia, con el porqué
completo. Las pantallas del módulo no se movieron de sitio; lo que cambió es en qué tabla
escriben y a través de qué modelo (`PastoralModel`, no `CatequesisModel`, que perdió sus
métodos `actividad*` y `documento*`).

---

## Proclamadores — turnos y catálogo de quién proclama

Módulo dedicado para la pastoral de Proclamadores (se llamó "Lectores" y luego
"Liturgia"; el slug de esa fila sigue siendo `liturgia` porque la URL pública ya está en
uso — ver la nota de `PASTORAL_PROCLAMADORES` en `config/app.php`), calcado de
`mesc_turnos`/`mesc_ministros`/`mesc_turno_ministros`, pero sin rutas ni visitas: quien
proclama la Palabra lo hace en misa, no reparte comunión a domicilio.

**Siguen siendo tres tablas aunque el módulo tenga cinco pantallas.** Las de Actividades y
Documentos, copiadas de Catequesis, escriben en `pastoral_tablero` y
`pastoral_documentos`, y los colores litúrgicos en `colores_liturgicos`: tres tablas que no
son de ningún módulo. Que este módulo las estrenara sin agregar ni una tabla propia es
precisamente lo que se buscaba al quitarles el prefijo.

Sus tres tablas se llamaron `lector_lectores`, `lector_turnos` y `lector_turno_lectores`
hasta el renombre, y con ellas sus índices y restricciones: `idx_lec_*`/`fk_lec_*`,
`idx_ltu_*`/`fk_ltu_*` y `fk_ltl_*` pasaron a `idx_pro_*`/`fk_pro_*`,
`idx_ptu_*`/`fk_ptu_*` y `fk_ptp_*`. Los nombres viejos solo aparecen ya en
`docs/migraciones/2026-09-04-proclamadores.sql`, que es también el motivo de que la
migración recree las tablas en vez de renombrarlas: los índices no se rebautizan con un
`ALTER` en MariaDB 10.4.

### `proclamadores`

Catálogo de quién proclama. `pastoral_id` (FK, `ON DELETE CASCADE`, índice
`idx_pro_pastoral`), `persona_id` (FK a `personas`, `ON DELETE SET NULL`, `UNIQUE` —
`uq_pro_persona`, mismo patrón que `mesc_ministros.persona_id`), `nombre`, `telefono`,
`email`, `preferencias`, `orden`, `activo`.

**`preferencias`** es `SET('monitor', 'lectura', 'salmo')`, NULL por omisión: lo que cada
quien prefiere hacer al proclamar, y el dato con el que la coordinación arma un turno
—quién va de monitor, quién lee y quién canta el salmo—. Es una columna `SET` y no una
tabla aparte porque son tres valores cerrados, conocidos de antemano y sin nada propio que
colgar de cada uno; un pivote habría costado una tabla y un JOIN para guardar exactamente
lo mismo. **NULL no es lo mismo que vacío**: NULL es "no se le ha preguntado" y la cadena
vacía es "no prefiere ninguna", que es una respuesta legítima y conviene distinguir de un
hueco. Y no limita nada —es una preferencia, no un permiso—: cualquiera puede asignarse a
cualquier turno. Se muestra en el catálogo y junto a cada nombre en el formulario de
turno, que es donde de verdad se consulta.

### `proclamadores_turnos` y `proclamadores_turno_proclamadores`

Calendario de turnos, misma forma que `mesc_turnos`: `pastoral_id`, `fecha`, `hora`,
`descripcion`, `color_liturgico_id`, `usuario_id`, `created_at`. Índices
`idx_ptu_pastoral` e `idx_ptu_fecha`. `color_liturgico_id` reutiliza el catálogo
compartido `colores_liturgicos` en vez de duplicarlo: el significado litúrgico de cada
color es el mismo para toda la parroquia, no un dato propio de este módulo — y desde el
renombre este módulo también lo administra, con su propia pantalla sobre esas mismas
filas. `proclamadores_turno_proclamadores` es el pivote `(turno_id, proclamador_id)`, de 1
a N por turno (una lectura puede repartirse entre dos personas).

---

## Sacramentos

### `sacramentos`

Catálogo puramente informativo (issue #3: se eliminaron `acepta_solicitudes` y
`requiere_tutor`, junto con todo el formulario de solicitud en línea). `slug` con
`uq_sac_slug`, `nombre`, `descripcion`, `requisitos` MEDIUMTEXT, `documentos` MEDIUMTEXT,
`aportacion`, `imagen`, `orden`, `activo`.

Semillas: bautizo, primera comunión, confirmación, matrimonio, confesión, unción de
enfermos.

> Hasta el issue #3, aquí vivían también `sacramento_campos`, `solicitudes_sacramento` y
> `solicitudes_bitacora` (formulario de solicitud en línea, con folio, bandeja de estados y
> campos configurables por sacramento). Se eliminaron las tres tablas por completo. Ver
> [`ARQUITECTURA.md`](ARQUITECTURA.md), sección "Sacramentos: catálogo puramente
> informativo".

---

## Cursos

### `cursos`

`slug` con `uq_cur_slug`, `titulo`, `descripcion`, `objetivos`, `dirigido_a`, `imagen`,
`modalidad` ENUM(`presencial`, `en_linea`, `mixta`), `instructor_id` hacia `personas`,
`pastoral_id`, `cupo`, `aportacion`, `fecha_inicio`, `fecha_fin`, `horario`, `lugar`,
`inscripciones_abiertas`, `fecha_cierre_inscripcion`, `requiere_tutor`, `publicado`,
`orden`.

`pastoral_id` empezó siendo una etiqueta organizativa y desde el issue de filtrado por
pastoral pesa lo mismo que `avisos.pastoral_id` o `eventos.pastoral_id`: decide quién puede
editar el curso, y NULL significa curso parroquial general, que solo tocan los roles con
alcance global. La columna no cambió; lo que cambió es quién la respeta.

`centro_id` (SMALLINT UNSIGNED NULL, FK a `centros` `ON DELETE SET NULL`, índice
`idx_cur_centro`) es la segunda mitad de esa decisión: en qué sede se da el curso. Las dos se
exigen juntas para editar —ver [`ARQUITECTURA.md`](ARQUITECTURA.md), "El alcance tiene dos
mitades"—. NULL = de toda la parroquia.

`fecha_inicio` y `fecha_fin` son DATE, sin hora —la hora vive como texto libre en
`horario`—, así que en la agenda interna un curso ocupa días enteros. Un curso sin
`fecha_inicio` no se dibuja en el calendario: `CursoModel::sinFechas()` lo recoge para
listarlo aparte.

### `curso_sesiones`

Temario: `curso_id`, `numero`, `titulo`, `descripcion`, `fecha`, `orden`. Foránea con
`ON DELETE CASCADE`.

Hoy es contenido público informativo. En fase 2 es el ancla del aula virtual: las tablas
`curso_materiales`, `curso_tareas`, `curso_entregas` y `curso_calificaciones` colgarán de
`curso_sesiones.id` e `inscripciones_curso.id` **sin tocar nada de lo existente**.

### `inscripciones_curso`

`folio` con `uq_ins_folio`, `curso_id`, `nombre`, `fecha_nacimiento`, `es_menor`,
`telefono`, `email`, `centro` (texto libre: "Centro al que perteneces", no es FK a
`centros`), datos de tutor, `estado` ENUM(`pendiente`, `confirmada`,
`lista_espera`, `cancelada`), `consentimiento`, `consentimiento_ip`, `aviso_version`,
`notas`.

Único `uq_ins_curso_email (curso_id, email)` para evitar inscripciones duplicadas.

Los datos de tutor (`tutor_nombre`, `tutor_parentesco`, `tutor_telefono`) se guardan si
`es_menor` (calculado de `fecha_nacimiento`, y entonces obligatorios) o si la persona
marcó la casilla "Padre, madre o tutor" del formulario aunque no sea menor (entonces son
opcionales). `CursoPublicoController::validarInscripcion()` decide esto con
`$guardarTutor = $esMenor || $tieneTutor`; la vista de detalle (`inscripcion_ver.php`)
muestra esa sección siempre que haya algún dato de tutor, no solo cuando `es_menor`.

---

## Comunicación

### `avisos`

Boletín semanal y noticias. `slug` con `uq_avi_slug`, `titulo`, `resumen` VARCHAR(300),
`contenido` MEDIUMTEXT, `imagen`, `tipo` ENUM(`noticia`, `boletin`, `comunicado`),
`archivo_pdf` para el boletín, `pastoral_id`, `fecha_publicacion`, `vigente_hasta`,
`destacado`, `publicado`, `vistas`, `usuario_id`.

`publicado` arranca en **0**: todo entra como borrador. `pastoral_id NULL` significa aviso
parroquial global, que un coordinador nunca puede tocar. Índices
`idx_avi_pub (publicado, fecha_publicacion)` e `idx_avi_pastoral`.

**Vigencia (issue #3).** `fecha_publicacion` es el "visible desde" (ya existía: una fecha
futura no se muestra hasta llegar ese día); `vigente_hasta` DATE NULL es el "visible hasta"
que agrega el issue #3. `AvisoModel::VIGENTE` combina ambas en una sola condición SQL
reutilizada por `publicados()`, `porSlugPublicado()`, `recientes()` y `paraSitemap()`:
`publicado = 1 AND fecha_publicacion <= CURDATE() AND (vigente_hasta IS NULL OR
vigente_hasta >= CURDATE())`. NULL en `vigente_hasta` significa sin fecha de baja. El
listado del panel (`listar()`) **no** aplica esta condición — un editor debe poder ver y
reeditar un aviso vencido, solo el público deja de verlo. Deliberadamente no se aplicó el
mismo mecanismo a `eventos`: un evento ya tiene su propio ciclo de vida natural
(`fecha_inicio`/`fecha_fin`) y ocultar automáticamente los pasados eliminaría el registro
histórico de lo que la parroquia ha organizado.

### `eventos`

`slug` con `uq_eve_slug`, `titulo`, `descripcion`, `imagen`, `lugar`, `fecha_inicio`
DATETIME, `fecha_fin` DATETIME NULL, `todo_el_dia`, `pastoral_id`, `centro_id`, `color`
VARCHAR(7) para el calendario, `publicado`, `usuario_id`. Índices `idx_eve_fecha
(fecha_inicio)`, `idx_eve_pub (publicado, fecha_inicio)` e `idx_eve_centro (centro_id)`.

`pastoral_id` dice quién organiza el evento y `centro_id` (FK a `centros`, `ON DELETE SET
NULL`) en qué sede ocurre; las dos juntas son el alcance de quien puede editarlo. NULL en
`centro_id` = evento de toda la parroquia. Los 467 eventos de la agenda 2026 quedaron
marcados como de la sede principal al añadirse la columna.

`fecha_fin` cubre dos cosas a la vez: la hora de término dentro del mismo día y el periodo de
varios días. Cuando cae en otro día, el evento se marca en **todos** los días que dura, tanto
en el calendario público como en el JSON de `?accion=datos`.

**Es hoy la tabla con más filas del sistema**: 467, la agenda parroquial de 2026
completa, cargada con `herramientas/importar_agenda.php` y no desde el panel (ver
[`ARQUITECTURA.md`](ARQUITECTURA.md#carga-de-la-agenda-parroquial-2026-herramientas)). Dos
consecuencias prácticas: esas 467 filas **no tienen contrapartida en `auditoria`**, porque la
carga no pasó por un controlador; y `idx_eve_fecha` dejó de ser decorativo — el filtro por
fecha del listado del panel compara por rango precisamente para poder usarlo.

### `mensajes_contacto`

`nombre`, `email`, `telefono`, `asunto`, `mensaje`, `ip`, `leido`, `respondido`,
`nota_interna`, `atendido_por`, `consentimiento`, `aviso_version`. Índice
`idx_msg_leido (leido, created_at)`.

### `intentos_formulario`

Control de frecuencia contra spam. `ip` VARCHAR(45), `formulario` VARCHAR(40),
`created_at`, con índice `idx_int_ip (ip, formulario, created_at)`. Cinco envíos por IP,
formulario y hora.

Es la única tabla que se purga de verdad: los registros de más de 24 horas se borran.

---

## Resumen

| Grupo | Tablas |
|---|---|
| Núcleo y seguridad | `usuarios`, `usuarios_pastorales`, `usuarios_centros`, `usuarios_perfiles`, `auditoria`, `respaldos_log`, `configuracion` |
| Contenido | `bloques_contenido`, `paginas`, `carrusel`, `galeria_imagenes` |
| Parroquia | `centros`, `personas`, `persona_pastorales`, `persona_centros`, `organigrama_nodos`, `horarios`, `pastorales`, `pastoral_actividades`, `pastoral_tablero`, `pastoral_documentos` |
| MESC | `mesc_visitas`, `mesc_rutas`, `mesc_ruta_visitas`, `mesc_ministros`, `mesc_turnos`, `mesc_turno_ministros`, y `colores_liturgicos` (sin prefijo: la comparte con Proclamadores) |
| Catequesis | `catequesis_catequistas`, `catequesis_periodos`, `catequesis_periodo_catequistas`, más `pastoral_tablero` y `pastoral_documentos`, que no son suyas |
| Proclamadores | `proclamadores`, `proclamadores_turnos`, `proclamadores_turno_proclamadores`, y las compartidas: esa misma `colores_liturgicos`, `pastoral_tablero` y `pastoral_documentos` |
| Sacramentos | `sacramentos` |
| Cursos | `cursos`, `curso_sesiones`, `inscripciones_curso` |
| Comunicación | `avisos`, `eventos`, `mensajes_contacto`, `intentos_formulario` |

**Total: 42 tablas** (24 de las diez etapas del plan original, más `respaldos_log`,
`centros`, `usuarios_centros`, `usuarios_perfiles`, `persona_pastorales`, `persona_centros`,
`pastoral_tablero`, `pastoral_documentos`, `mesc_visitas`, `mesc_rutas`,
`mesc_ruta_visitas`, `mesc_ministros`, `mesc_turnos`, `mesc_turno_ministros`,
`colores_liturgicos`,
`catequesis_catequistas`, `catequesis_periodos`, `catequesis_periodo_catequistas`,
`proclamadores`, `proclamadores_turnos` y `proclamadores_turno_proclamadores`,
menos `sacramento_campos`, `solicitudes_sacramento` y
`solicitudes_bitacora`). El renombre de Proclamadores no movió la cuenta: cambió nombres
de tabla, no su número. La que sí la movió, de 43 a 42, es la migración del tablero y los
documentos compartidos: se fueron `catequesis_actividades` y `catequesis_documentos`, y
entró `pastoral_tablero`.
