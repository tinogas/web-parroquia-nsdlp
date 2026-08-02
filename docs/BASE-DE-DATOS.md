# Base de datos

Diccionario de las **42 tablas** del sistema: las 24 de las diez etapas del plan original,
más las que trajeron el issue #3 y la revisión de módulos —`centros`, `usuarios_centros`,
`respaldos_log`, las siete tablas de MESC, las cinco de Catequesis y las tres de Lector,
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
antes de este campo), `slug` con `uq_pas_slug`, `nombre`, `descripcion_corta`,
`descripcion` MEDIUMTEXT, `imagen`, `icono` (clase de Bootstrap Icons),
`responsable_nombre`, `responsable_persona_id`, `contacto_email`, `contacto_telefono`,
`dia_reunion`, `hora_reunion`, `lugar_reunion`, `acepta_voluntarios`, `orden`, `activa`.

`responsable_persona_id` (FK a `personas`, `ON DELETE SET NULL`, índice `idx_pas_responsable`)
es el select del formulario: el responsable se elige del equipo pastoral. Con persona
elegida, `responsable_nombre` y `contacto_email` se recalculan solos —del nombre de su
ficha y del correo de acceso de su cuenta, si tiene una— y dejan de ser editables a mano;
`responsable_nombre` solo se sigue escribiendo libre cuando `responsable_persona_id` es
NULL (la persona todavía no está de alta en el equipo). Ver
[`ARQUITECTURA.md`](ARQUITECTURA.md#contenido-propio-por-pastoral-issue-3).

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
"calendario de turnos"). `pastoral_id` (FK, `ON DELETE CASCADE`), `persona_id`
(FK a `personas`, `ON DELETE SET NULL`, `UNIQUE` — `uq_mmi_persona`), `nombre`,
`telefono`, `activo` (solo los activos se pueden asignar a un turno nuevo), `created_at`.

Sigue siendo una tabla aparte de `personas` a propósito: `personas` es el equipo
pastoral que se muestra en público, con foto y semblanza, y un ministro MESC es un
voluntario interno que no necesariamente forma parte de esa vitrina. `persona_id` es el
puente opcional entre ambas cuando sí coinciden: con la persona elegida, `nombre` y
`telefono` se toman de su ficha y `PersonaModel::sincronizarPersonal()` los mantiene al
día si la ficha cambia; sin persona (todavía no está de alta en el equipo), los dos
campos son texto libre como siempre. Mismo patrón en `catequesis_catequistas` y
`lector_lectores`, ver más abajo. Índice `idx_mmi_pastoral (pastoral_id, activo)`.

### `mesc_colores_liturgicos`

Catálogo de referencia: los cinco colores litúrgicos de la Iglesia (blanco, verde,
morado, rojo, rosa), cada uno con `nombre`, `color_hex` y `significado` (texto explicativo
de cuándo se usa cada uno). `orden` controla en qué secuencia aparecen. Mantenimiento
libre desde el panel — no está codificado en PHP — para que la parroquia pueda ajustar el
texto o agregar alguno si hiciera falta.

### `mesc_turnos` y `mesc_turno_ministros`

Un turno cubre una misa o evento en una fecha concreta: `pastoral_id` (FK), `fecha`,
`hora` NULL, `descripcion` VARCHAR(160) (qué se cubre: "Misa", "Santísimo", "Hora Santa",
"Misa de Niños"…), `color_liturgico_id` (FK a `mesc_colores_liturgicos`,
`ON DELETE SET NULL`, opcional), `usuario_id`, `created_at`. Sin FK a `horarios` ni a
`eventos` (ver [`ARQUITECTURA.md`](ARQUITECTURA.md)): un turno es una ocurrencia concreta,
no la recurrencia semanal de `horarios` ni un evento formal. `mesc_turno_ministros` es el
pivote `(turno_id, ministro_id)`, de 1 a N ministros por turno.
`MescController::turnoGuardar()` revalida cada `ministro_id` recibido contra
`ministrosActivos()` de esa pastoral antes de guardar: un ministro dado de baja no puede
colarse en un turno nuevo aunque se manipule el formulario.

---

## Catequesis — catequistas, periodos, tablero de actividades y documentos

Módulo dedicado **exclusivamente** a la pastoral de Catecismo, igual que MESC y Lector:
no hay selector de pastoral en ningún formulario — `CatequesisModel::pastoralId()`
resuelve la única pastoral por su `slug = 'catecismo'`, no por un id fijo (los id de
pastorales no se siembran en `install.sql`, se crean desde el panel)—. Sin controlador
público ni datos sensibles.

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
a diferencia de `catequesis_actividades.fecha_fin` que sí puede quedar abierta),
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

### `catequesis_actividades`

El "tablero o calendario" (issue de revisión de módulos): a diferencia de
`pastoral_actividades` (lista fija de qué hace la pastoral, sin fechas),
`catequesis_actividades` tiene vigencia y se publica o no, como un mini-evento —mismas
columnas que `eventos`—: `pastoral_id`, `titulo`, `descripcion`, `fecha_inicio` (NOT
NULL), `fecha_fin` (NULL = sin fecha de término), `publicado`, `orden`, `usuario_id`,
`created_at`. Índice `idx_cta_publicado (publicado, fecha_inicio)`.

### `catequesis_documentos`

Documentos descargables, mismo patrón que `pastoral_documentos`: `pastoral_id`, `titulo`,
`archivo` (ruta bajo `uploads/catequesis/AAAA/MM/`, solo PDF vía `Upload::documento()`),
`orden`, `activo`, `usuario_id`, `created_at`. Sin columna de edición: como en
`pastoral_documentos`, un documento se sube o se borra, no se reemplaza in situ.

---

## Lector — turnos y catálogo de lectores

Módulo dedicado para la pastoral de Liturgia (se llamaba "Lectores"; el nombre del
módulo y de sus tablas no cambió, ver la nota de `PASTORAL_LECTOR` en
`config/app.php`), calcado de
`mesc_turnos`/`mesc_ministros`/`mesc_turno_ministros`, pero sin rutas ni visitas: un
lector proclama la Palabra en misa, no reparte comunión a domicilio.

### `lector_lectores`

Catálogo de lectores. `pastoral_id` (FK, `ON DELETE CASCADE`), `persona_id` (FK a
`personas`, `ON DELETE SET NULL`, `UNIQUE` — `uq_lec_persona`, mismo patrón que
`mesc_ministros.persona_id`), `nombre`, `telefono`, `email`, `orden`, `activo`.

### `lector_turnos` y `lector_turno_lectores`

Calendario de turnos, misma forma que `mesc_turnos`: `pastoral_id`, `fecha`, `hora`,
`descripcion`, `color_liturgico_id`, `usuario_id`, `created_at`. `color_liturgico_id`
reutiliza el catálogo **de MESC** (`mesc_colores_liturgicos`) en vez de duplicarlo: el
significado litúrgico de cada color es el mismo para toda la parroquia, no un dato propio
de este módulo. `lector_turno_lectores` es el pivote `(turno_id, lector_id)`, de 1 a N
lectores por turno (una lectura puede repartirse entre dos personas).

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
| Núcleo y seguridad | `usuarios`, `usuarios_pastorales`, `usuarios_centros`, `auditoria`, `respaldos_log`, `configuracion` |
| Contenido | `bloques_contenido`, `paginas`, `carrusel`, `galeria_imagenes` |
| Parroquia | `centros`, `personas`, `persona_pastorales`, `persona_centros`, `organigrama_nodos`, `horarios`, `pastorales`, `pastoral_actividades`, `pastoral_documentos` |
| MESC | `mesc_visitas`, `mesc_rutas`, `mesc_ruta_visitas`, `mesc_ministros`, `mesc_turnos`, `mesc_turno_ministros`, `mesc_colores_liturgicos` |
| Catequesis | `catequesis_catequistas`, `catequesis_periodos`, `catequesis_periodo_catequistas`, `catequesis_actividades`, `catequesis_documentos` |
| Lector | `lector_lectores`, `lector_turnos`, `lector_turno_lectores` |
| Sacramentos | `sacramentos` |
| Cursos | `cursos`, `curso_sesiones`, `inscripciones_curso` |
| Comunicación | `avisos`, `eventos`, `mensajes_contacto`, `intentos_formulario` |

**Total: 42 tablas** (24 de las diez etapas del plan original, más `respaldos_log`,
`centros`, `usuarios_centros`, `persona_pastorales`, `persona_centros`,
`pastoral_documentos`, `mesc_visitas`, `mesc_rutas`, `mesc_ruta_visitas`,
`mesc_ministros`, `mesc_turnos`, `mesc_turno_ministros`, `mesc_colores_liturgicos`,
`catequesis_catequistas`, `catequesis_periodos`, `catequesis_periodo_catequistas`,
`catequesis_actividades`, `catequesis_documentos`,
`lector_lectores`, `lector_turnos` y `lector_turno_lectores`,
menos `sacramento_campos`, `solicitudes_sacramento` y
`solicitudes_bitacora`).
