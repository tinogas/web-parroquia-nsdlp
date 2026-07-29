# Base de datos

Diccionario de las 25 tablas del sistema (24 de las diez etapas del plan original, más
`respaldos_log` añadida después). El esquema real vive en `install.sql`; este documento
explica el porqué de cada tabla y sus columnas relevantes.

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
`legal`). `clave` VARCHAR(60) con `uq_cfg_clave`, `valor` TEXT.

Claves sembradas: `parroquia_nombre`, `parroquia_diocesis`, `direccion`, `ciudad`, `cp`,
`telefono`, `whatsapp`, `email`, `mapa_embed`, `latitud`, `longitud`, `horario_oficina`,
`facebook`, `instagram`, `youtube`, `logo`, `favicon`, `og_imagen`, `meta_descripcion`,
`aviso_privacidad_version`, `organigrama_imagen`, `retencion_meses_solicitudes`.

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

### `personas`

Párroco, vicarios, diáconos, religiosos, laicos y personal. `nombre`, `cargo`, `tipo`
ENUM(`parroco`, `vicario`, `diacono`, `religioso`, `laico`, `staff`), `semblanza`, `foto`,
`email`, `telefono`, `orden`, `activo`.

Solo se publica el correo institucional. Ver [`PRIVACIDAD.md`](PRIVACIDAD.md).

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
| `tipo` | ENUM | `misa`, `confesion`, `adoracion`, `oficina`, `otro` |
| `dia_semana` | TINYINT UNSIGNED | 0 = domingo … 6 = sábado |
| `hora` | TIME | |
| `hora_fin` | TIME NULL | Para adoración y oficina |
| `lugar` | VARCHAR(120) | Templo, capilla, etc. |
| `nota` | VARCHAR(160) | "Con coro", "Bilingüe"… |
| `vigente_desde` | DATE NULL | Horarios de temporada |
| `vigente_hasta` | DATE NULL | |
| `orden`, `activo` | | |

Índice `idx_hor_tipo_dia (tipo, dia_semana, hora)`.

### `pastorales`

`slug` con `uq_pas_slug`, `nombre`, `descripcion_corta`, `descripcion` MEDIUMTEXT,
`imagen`, `icono` (clase de Bootstrap Icons), `responsable_nombre`, `contacto_email`,
`contacto_telefono`, `dia_reunion`, `hora_reunion`, `lugar_reunion`,
`acepta_voluntarios`, `orden`, `activa`.

### `pastoral_actividades`

Actividades comunitarias y de apoyo social de cada pastoral. `pastoral_id`, `titulo`,
`descripcion`, `tipo` ENUM(`comunitaria`, `apoyo_social`, `formacion`, `liturgica`),
`orden`, `activa`. Foránea con `ON DELETE CASCADE`.

---

## Sacramentos y solicitudes

### `sacramentos`

Catálogo. `slug` con `uq_sac_slug`, `nombre`, `descripcion`, `requisitos` MEDIUMTEXT,
`documentos` MEDIUMTEXT, `aportacion`, `imagen`, `acepta_solicitudes`, `requiere_tutor`,
`orden`, `activo`.

Semillas: bautizo, primera comunión, confirmación, matrimonio, confesión, unción de
enfermos.

### `sacramento_campos`

Define qué campos adicionales pide cada sacramento en su formulario. `sacramento_id`,
`nombre_campo`, `etiqueta`, `tipo` ENUM(`texto`, `textarea`, `fecha`, `telefono`, `email`,
`seleccion`, `checkbox`), `opciones`, `requerido`, `dato_sensible`, `orden`, `activo`.
Único `uq_scp (sacramento_id, nombre_campo)`.

Es lo que permite que el párroco agregue "nombre del padrino" a Confirmación sin tocar el
esquema. Los campos marcados con `dato_sensible = 1` solo se muestran a administrador y
secretaría.

### `solicitudes_sacramento`

La tabla más delicada del sistema: contiene datos personales, con frecuencia de menores.

| Columna | Tipo | Notas |
|---|---|---|
| `folio` | VARCHAR(20) | `uq_sol_folio`; formato `BAU-2026-00001` |
| `sacramento_id` | TINYINT UNSIGNED | |
| `nombre_solicitante` | VARCHAR(150) | |
| `fecha_nacimiento` | DATE | |
| `es_menor` | TINYINT(1) | Calculado en el servidor, no confiado al cliente |
| `telefono`, `email`, `direccion` | | |
| `tutor_nombre`, `tutor_parentesco`, `tutor_telefono` | | Obligatorios si `es_menor = 1` |
| `fecha_preferida` | DATE NULL | |
| `notas` | TEXT | |
| `datos_extra` | JSON NULL | Respuestas de `sacramento_campos` |
| `estado` | ENUM | `pendiente`, `en_revision`, `aprobada`, `rechazada`, `cancelada`, `completada` |
| `motivo_estado` | VARCHAR(255) | |
| `atendida_por`, `atendida_at` | | |
| `consentimiento` | TINYINT(1) | Sin él no se inserta |
| `consentimiento_ip` | VARCHAR(45) | |
| `aviso_version` | VARCHAR(20) | Versión del aviso de privacidad aceptada |
| `origen` | ENUM | `web` o `panel` |

Índices `idx_sol_estado (estado, created_at)` e `idx_sol_sac (sacramento_id)`.

Sobre `datos_extra JSON`: la decisión y su trade-off están explicados en
[`ARQUITECTURA.md`](ARQUITECTURA.md#campos-de-sacramento-configurables). En resumen, no es
cómodo de buscar, y se acepta porque las búsquedas reales son por folio, nombre,
sacramento y estado, que son columnas reales.

### `solicitudes_bitacora`

Historial de cambios de estado: `solicitud_id`, `usuario_id`, `estado_anterior`,
`estado_nuevo`, `comentario`, `created_at`. Foránea con `ON DELETE CASCADE`.

Permite responder "¿quién aprobó esto y cuándo?" sin adivinar.

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
`archivo_pdf` para el boletín, `pastoral_id`, `fecha_publicacion`, `destacado`,
`publicado`, `vistas`, `usuario_id`.

`publicado` arranca en **0**: todo entra como borrador. `pastoral_id NULL` significa aviso
parroquial global, que un coordinador nunca puede tocar. Índices
`idx_avi_pub (publicado, fecha_publicacion)` e `idx_avi_pastoral`.

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
| Núcleo y seguridad | `usuarios`, `usuarios_pastorales`, `auditoria`, `respaldos_log`, `configuracion` |
| Contenido | `bloques_contenido`, `paginas`, `carrusel`, `galeria_imagenes` |
| Parroquia | `personas`, `organigrama_nodos`, `horarios`, `pastorales`, `pastoral_actividades` |
| Sacramentos | `sacramentos`, `sacramento_campos`, `solicitudes_sacramento`, `solicitudes_bitacora` |
| Cursos | `cursos`, `curso_sesiones`, `inscripciones_curso` |
| Comunicación | `avisos`, `eventos`, `mensajes_contacto`, `intentos_formulario` |

**Total: 25 tablas** (24 de las diez etapas del plan original, más `respaldos_log`).
