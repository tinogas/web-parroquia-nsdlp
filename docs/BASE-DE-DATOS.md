# Base de datos

Diccionario de las 23 tablas del sistema: las 24 de las diez etapas del plan original, más
`respaldos_log` y `centros` (issue #3), menos `sacramento_campos`, `solicitudes_sacramento`
y `solicitudes_bitacora` (también issue #3: se eliminó el formulario de solicitud en línea
de sacramentos). El esquema real vive en `install.sql`; este documento explica el porqué de
cada tabla y sus columnas relevantes.

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
| `rol` | ENUM | `admin`, `editor`, `coordinador`, `secretaria` |
| `foto` | VARCHAR(255) | Opcional |
| `telefono` | VARCHAR(20) | |
| `activo` | TINYINT(1) | Baja lógica |
| `ultimo_acceso` | DATETIME | |
| `created_at` | DATETIME | |

### `usuarios_pastorales`

Pivote. Una persona puede coordinar varias pastorales a la vez, que es lo habitual.
Clave primaria compuesta `(usuario_id, pastoral_id)`, ambas foráneas con
`ON DELETE CASCADE`.

### `usuarios_centros`

Pivote análogo, pero por centro/sede completo (issue #3, "usuarios por centro/sede"):
quien administra un centro administra todas sus pastorales sin que alguien tenga que
marcarlas una por una en `usuarios_pastorales`. Clave primaria compuesta
`(usuario_id, centro_id)`, ambas foráneas con `ON DELETE CASCADE`.
`Auth::pastoralesPermitidas()` calcula la unión de ambas tablas (ver
[`ARQUITECTURA.md`](ARQUITECTURA.md)).

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
`email`, `telefono`, `orden`, `activo`.

Solo se publica el correo institucional. Ver [`PRIVACIDAD.md`](PRIVACIDAD.md).

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

**Agrupado público (`HorarioModel::vigentesPorCentro()`)**: el sitio público agrupa
primero por sede/centro (issue #3, una columna por cada una) y, dentro de cada
columna, por tipo —misa arriba, confesión al final, orden distinto al de `TIPOS` que usa el
admin— y dentro de cada tipo por día y hora, de lunes a domingo (`MOD(dia_semana + 6, 7)`
para reordenar sin tocar el valor guardado) y de la mañana a la noche. Los horarios sin
`centro_id` se agrupan aparte, al final, bajo "Otros horarios". El listado de admin
(`todos()`) conserva el orden por `tipo` (misa primero) y sin agrupar por centro, para
facilitar la edición masiva.

### `pastorales`

`centro_id` (issue #3: FK a `centros`, `ON DELETE SET NULL`, NULL en las que ya existían
antes de este campo), `slug` con `uq_pas_slug`, `nombre`, `descripcion_corta`,
`descripcion` MEDIUMTEXT, `imagen`, `icono` (clase de Bootstrap Icons),
`responsable_nombre`, `contacto_email`, `contacto_telefono`, `dia_reunion`,
`hora_reunion`, `lugar_reunion`, `acepta_voluntarios`, `orden`, `activa`.

### `pastoral_actividades`

Actividades comunitarias y de apoyo social de cada pastoral. `pastoral_id`, `titulo`,
`descripcion`, `tipo` ENUM(`comunitaria`, `apoyo_social`, `formacion`, `liturgica`),
`orden`, `activa`. Foránea con `ON DELETE CASCADE`.

### `pastoral_documentos`

Documentación descargable de cada pastoral (issue #3): reglamentos, guías, formatos.
`pastoral_id` (FK, `ON DELETE CASCADE`), `titulo`, `archivo` (ruta bajo `uploads/`, misma
convención que `avisos.archivo_pdf`; solo PDF por ahora), `orden`, `activo`, `usuario_id`
(FK a `usuarios`, `ON DELETE SET NULL`, quién lo subió), `created_at`. Sin edición desde el
panel más allá de agregar o quitar: para cambiar un documento se sube uno nuevo.

---

## MESC — Ministros Extraordinarios de la Comunión

> **Dato sensible.** De las siete tablas de este bloque, solo `mesc_visitas` (y su ruta,
> `mesc_rutas`/`mesc_ruta_visitas`) guarda el único dato de salud que trata el sistema.
> `mesc_ministros`/`mesc_turnos`/`mesc_turno_ministros`/`mesc_colores_liturgicos` son un
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
"calendario de turnos"). `pastoral_id` (FK, `ON DELETE CASCADE`), `nombre`, `telefono`,
`activo` (solo los activos se pueden asignar a un turno nuevo), `created_at`.

Aparte de `personas` a propósito: `personas` es el equipo pastoral que se muestra en
público, con foto y semblanza; un ministro MESC es un voluntario interno que no
necesariamente forma parte de esa vitrina. Índice `idx_mmi_pastoral (pastoral_id, activo)`.

### `mesc_colores_liturgicos`

Catálogo de referencia: los cinco colores litúrgicos de la Iglesia (blanco, verde,
morado, rojo, rosa), cada uno con `nombre`, `color_hex` y `significado` (texto explicativo
de cuándo se usa cada uno). `orden` controla en qué secuencia aparecen. Mantenimiento
libre desde el panel — no está codificado en PHP — para que la parroquia pueda ajustar el
texto o agregar alguno si hiciera falta.

### `mesc_turnos` y `mesc_turno_ministros`

Un turno cubre una misa o evento en una fecha concreta: `pastoral_id` (FK), `fecha`,
`hora` NULL, `color_liturgico_id` (FK a `mesc_colores_liturgicos`, `ON DELETE SET NULL`,
opcional), `usuario_id`, `created_at`. Sin FK a `horarios` ni a `eventos` (ver
[`ARQUITECTURA.md`](ARQUITECTURA.md)) y sin columna de descripción propia: el turno se
identifica por su fecha, hora y los ministros que lo cubren, que ya basta en la práctica;
`MescController::etiquetaTurno()` arma un texto de referencia a partir de fecha y hora
donde hace falta un título. `mesc_turno_ministros` es el pivote `(turno_id, ministro_id)`,
de 1 a N ministros por turno. `MescController::turnoGuardar()` revalida cada
`ministro_id` recibido contra `ministrosActivos()` de esa pastoral antes de guardar: un
ministro dado de baja no puede colarse en un turno nuevo aunque se manipule el
formulario.

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

### `curso_sesiones`

Temario: `curso_id`, `numero`, `titulo`, `descripcion`, `fecha`, `orden`. Foránea con
`ON DELETE CASCADE`.

Hoy es contenido público informativo. En fase 2 es el ancla del aula virtual: las tablas
`curso_materiales`, `curso_tareas`, `curso_entregas` y `curso_calificaciones` colgarán de
`curso_sesiones.id` e `inscripciones_curso.id` **sin tocar nada de lo existente**.

### `inscripciones_curso`

`folio` con `uq_ins_folio`, `curso_id`, `nombre`, `fecha_nacimiento`, `es_menor`,
`telefono`, `email`, datos de tutor, `estado` ENUM(`pendiente`, `confirmada`,
`lista_espera`, `cancelada`), `consentimiento`, `consentimiento_ip`, `aviso_version`,
`notas`.

Único `uq_ins_curso_email (curso_id, email)` para evitar inscripciones duplicadas.

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
DATETIME, `fecha_fin` DATETIME NULL, `todo_el_dia`, `pastoral_id`, `color` VARCHAR(7) para
el calendario, `publicado`, `usuario_id`. Índices `idx_eve_fecha (fecha_inicio)` e
`idx_eve_pub (publicado, fecha_inicio)`.

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
| Núcleo y seguridad | `usuarios`, `usuarios_pastorales`, `usuarios_centros`, `auditoria`, `respaldos_log`, `configuracion` |
| Contenido | `bloques_contenido`, `paginas`, `carrusel`, `galeria_imagenes` |
| Parroquia | `centros`, `personas`, `persona_pastorales`, `persona_centros`, `organigrama_nodos`, `horarios`, `pastorales`, `pastoral_actividades`, `pastoral_documentos` |
| MESC | `mesc_visitas`, `mesc_rutas`, `mesc_ruta_visitas`, `mesc_ministros`, `mesc_turnos`, `mesc_turno_ministros`, `mesc_colores_liturgicos` |
| Sacramentos | `sacramentos` |
| Cursos | `cursos`, `curso_sesiones`, `inscripciones_curso` |
| Comunicación | `avisos`, `eventos`, `mensajes_contacto`, `intentos_formulario` |

**Total: 34 tablas** (24 de las diez etapas del plan original, más `respaldos_log`,
`centros`, `usuarios_centros`, `persona_pastorales`, `persona_centros`,
`pastoral_documentos`, `mesc_visitas`, `mesc_rutas`, `mesc_ruta_visitas`,
`mesc_ministros`, `mesc_turnos`, `mesc_turno_ministros` y `mesc_colores_liturgicos`,
menos `sacramento_campos`, `solicitudes_sacramento` y
`solicitudes_bitacora`).
