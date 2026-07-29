-- ============================================================
-- Parroquia Nuestra Señora de la Paz
-- Script de instalación MySQL / MariaDB  (archivo único acumulativo)
-- ============================================================
--
-- INSTALACIÓN EN LOCAL (XAMPP):
--
--   1. En phpMyAdmin crea la base de datos:
--        CREATE DATABASE parroquia_nsdlp
--          CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--   2. Importa este archivo en esa base de datos
--   3. Copia config/database.example.php como config/database.php y ajústalo
--   4. Abre http://localhost/WebParroquia/setup.php y crea el administrador
--   5. Borra setup.php
--
-- INSTALACIÓN EN CPANEL: ver docs/DESPLIEGUE.md
--
-- NOTA: mientras el sitio no esté en producción no hay migraciones. Este
-- archivo se mantiene sincronizado con el esquema real y se reimporta desde
-- cero al cerrar cada etapa. Ver docs/BASE-DE-DATOS.md
--
-- ============================================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ------------------------------------------------------------
-- NÚCLEO Y SEGURIDAD
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS usuarios (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre        VARCHAR(120) NOT NULL,
    email         VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    rol           ENUM('admin','editor','coordinador','secretaria') NOT NULL DEFAULT 'coordinador',
    foto          VARCHAR(255) NULL,
    telefono      VARCHAR(20)  NULL,
    activo        TINYINT(1)   NOT NULL DEFAULT 1,
    ultimo_acceso DATETIME     NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usr_email (email),
    KEY idx_usr_rol (rol, activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pivote: un coordinador puede administrar más de una pastoral a la vez (es
-- lo habitual, no la excepción), así que es una tabla propia y no una columna
-- pastoral_id en usuarios. FK real a pastorales, creada más abajo en este
-- mismo script.
CREATE TABLE IF NOT EXISTS usuarios_pastorales (
    usuario_id  INT UNSIGNED     NOT NULL,
    pastoral_id TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (usuario_id, pastoral_id),
    CONSTRAINT fk_up_usuario  FOREIGN KEY (usuario_id)  REFERENCES usuarios(id)   ON DELETE CASCADE,
    CONSTRAINT fk_up_pastoral FOREIGN KEY (pastoral_id) REFERENCES pastorales(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bitácora de acciones. Registra escrituras y también CONSULTAS de datos
-- personales, que es lo que permite responder a una solicitud de acceso.
-- Ver docs/PRIVACIDAD.md
--
-- admin_real_id: si la acción ocurrió durante una impersonación ("Usar
-- como…"), aquí queda el administrador real detrás del teclado; usuario_id
-- ya guarda la identidad efectiva de la sesión en ese momento (la de la
-- cuenta impersonada). Permite distinguir "lo hizo la secretaria" de "lo
-- hizo el admin actuando como la secretaria". NULL en el uso normal, sin
-- impersonación.
CREATE TABLE IF NOT EXISTS auditoria (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id    INT UNSIGNED    NULL,
    admin_real_id INT UNSIGNED    NULL,
    accion        VARCHAR(40)     NOT NULL,
    tabla_ref     VARCHAR(60)     NULL,
    registro_id   INT UNSIGNED    NULL,
    ip            VARCHAR(45)     NULL,
    descripcion   VARCHAR(255)    NULL,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_aud_usuario (usuario_id),
    KEY idx_aud_fecha   (created_at),
    KEY idx_aud_tabla   (tabla_ref, registro_id),
    CONSTRAINT fk_aud_admin_real FOREIGN KEY (admin_real_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historial de respaldos Y restauraciones de la base de datos
-- (modules/respaldos). El archivo .sql vive en backups/, fuera del control de
-- versiones; esta tabla es lo que permite listarlos, descargarlos y
-- borrarlos desde el panel. usuario_id sin desnormalizar el nombre: se une
-- con usuarios, igual que auditoria.
--
-- tipo distingue una fila "respaldo" (generó un .sql nuevo) de una fila
-- "restauracion" (ejecutó sobre la base uno ya existente); en una fila de
-- restauración, archivo es el .sql que se restauró, y notas anota el nombre
-- del respaldo de seguridad automático tomado justo antes, por si hay que
-- revertir. num_registros se reutiliza para el número de sentencias SQL
-- ejecutadas cuando tipo='restauracion'.
CREATE TABLE IF NOT EXISTS respaldos_log (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    tipo          ENUM('respaldo','restauracion') NOT NULL DEFAULT 'respaldo',
    archivo       VARCHAR(180)    NOT NULL,
    tamano_bytes  BIGINT UNSIGNED NULL,
    num_tablas    SMALLINT UNSIGNED NULL,
    num_registros INT UNSIGNED    NULL,
    usuario_id    INT UNSIGNED    NULL,
    estado        ENUM('completado','error') NOT NULL DEFAULT 'completado',
    notas         VARCHAR(255)    NULL,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rlog_fecha (created_at),
    CONSTRAINT fk_rlog_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos globales de la parroquia, en pares clave/valor. Las claves se siembran
-- aquí y el panel solo edita el valor: son las que las vistas esperan encontrar.
CREATE TABLE IF NOT EXISTS configuracion (
    id    SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    clave VARCHAR(60)       NOT NULL,
    valor TEXT              NULL,
    grupo VARCHAR(30)       NOT NULL DEFAULT 'general',
    PRIMARY KEY (id),
    UNIQUE KEY uq_cfg_clave (clave),
    KEY idx_cfg_grupo (grupo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- CONTENIDO EDITABLE
-- ------------------------------------------------------------

-- Textos largos anclados a zonas fijas del sitio. El panel edita el título, el
-- contenido y la imagen, pero NO puede crear ni borrar claves: son las anclas
-- que las vistas esperan encontrar. Ver docs/ARQUITECTURA.md
CREATE TABLE IF NOT EXISTS bloques_contenido (
    id              SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    clave           VARCHAR(60)       NOT NULL,
    zona            VARCHAR(40)       NOT NULL DEFAULT 'general',
    titulo          VARCHAR(160)      NULL,
    descripcion     VARCHAR(255)      NULL,
    contenido       MEDIUMTEXT        NULL,
    imagen          VARCHAR(255)      NULL,
    orden           SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    activo          TINYINT(1)        NOT NULL DEFAULT 1,
    actualizado_por INT UNSIGNED      NULL,
    updated_at      DATETIME          NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_blq_clave (clave),
    KEY idx_blq_zona (zona, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Páginas libres con dirección propia. Aquí vive el aviso de privacidad.
CREATE TABLE IF NOT EXISTS paginas (
    id               SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug             VARCHAR(120)      NOT NULL,
    titulo           VARCHAR(160)      NOT NULL,
    contenido        MEDIUMTEXT        NULL,
    meta_descripcion VARCHAR(200)      NULL,
    en_menu          TINYINT(1)        NOT NULL DEFAULT 0,
    orden            SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    publicada        TINYINT(1)        NOT NULL DEFAULT 0,
    actualizado_por  INT UNSIGNED      NULL,
    created_at       DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME          NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pag_slug (slug),
    KEY idx_pag_menu (en_menu, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Diapositivas de la portada. No van a ser muchas: id pequeño a propósito.
CREATE TABLE IF NOT EXISTS carrusel (
    id       TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    imagen   VARCHAR(255)     NOT NULL,
    titulo   VARCHAR(120)     NULL,
    subtitulo VARCHAR(200)    NULL,
    enlace   VARCHAR(255)     NULL,
    orden    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    activo   TINYINT(1)       NOT NULL DEFAULT 1,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Galería de fotografías. La columna que importa es autorizacion_imagen: dejar
-- constancia de que existe autorización de uso. La consulta pública filtra
-- WHERE publicada=1 AND autorizacion_imagen=1, así que una foto sin esa
-- autorización no puede llegar al sitio ni por descuido. Ver docs/PRIVACIDAD.md
--
-- evento_id y pastoral_id SÍ llevan FK real: eventos y pastorales se crean más
-- abajo, en este mismo script, así que ya existen para cuando se hagan
-- inserciones reales (las semillas de este archivo no tocan esta tabla).
-- MariaDB permite declarar una FK hacia una tabla que aún no existe mientras
-- foreign_key_checks esté en 0, como ocurre en todo este script; se
-- comprobó empíricamente antes de confiar en ello.
CREATE TABLE IF NOT EXISTS galeria_imagenes (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    archivo             VARCHAR(255) NOT NULL,
    titulo              VARCHAR(140) NULL,
    alt_texto           VARCHAR(160) NULL,
    pastoral_id         TINYINT UNSIGNED NULL,
    evento_id           INT UNSIGNED NULL,
    autorizacion_imagen TINYINT(1)   NOT NULL DEFAULT 0,
    orden               SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    publicada           TINYINT(1)   NOT NULL DEFAULT 0,
    usuario_id          INT UNSIGNED NULL,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_gal_pastoral (pastoral_id),
    KEY idx_gal_evento (evento_id),
    CONSTRAINT fk_gal_evento   FOREIGN KEY (evento_id)   REFERENCES eventos(id)    ON DELETE SET NULL,
    CONSTRAINT fk_gal_pastoral FOREIGN KEY (pastoral_id) REFERENCES pastorales(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- COMUNICACIÓN
-- ------------------------------------------------------------

-- Boletín semanal y noticias. publicado arranca en 0: todo entra como
-- borrador. pastoral_id NULL significa aviso parroquial global, que un
-- coordinador nunca podrá tocar. FK real a pastorales: se crea más abajo, en
-- este mismo script.
CREATE TABLE IF NOT EXISTS avisos (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug              VARCHAR(160) NOT NULL,
    titulo            VARCHAR(200) NOT NULL,
    resumen           VARCHAR(300) NULL,
    contenido         MEDIUMTEXT   NULL,
    imagen            VARCHAR(255) NULL,
    tipo              ENUM('noticia','boletin','comunicado') NOT NULL DEFAULT 'noticia',
    archivo_pdf       VARCHAR(255) NULL,
    pastoral_id       TINYINT UNSIGNED NULL,
    fecha_publicacion DATE         NOT NULL,
    destacado         TINYINT(1)   NOT NULL DEFAULT 0,
    publicado         TINYINT(1)   NOT NULL DEFAULT 0,
    vistas            INT UNSIGNED NOT NULL DEFAULT 0,
    usuario_id        INT UNSIGNED NULL,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_avi_slug (slug),
    KEY idx_avi_pub (publicado, fecha_publicacion),
    KEY idx_avi_pastoral (pastoral_id),
    CONSTRAINT fk_avi_pastoral FOREIGN KEY (pastoral_id) REFERENCES pastorales(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fecha concreta, no recurrencia: lo que se repite cada semana vive en
-- horarios, no aquí. color alimenta el calendario del sitio público.
CREATE TABLE IF NOT EXISTS eventos (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug         VARCHAR(160) NOT NULL,
    titulo       VARCHAR(200) NOT NULL,
    descripcion  MEDIUMTEXT   NULL,
    imagen       VARCHAR(255) NULL,
    lugar        VARCHAR(160) NULL,
    fecha_inicio DATETIME     NOT NULL,
    fecha_fin    DATETIME     NULL,
    todo_el_dia  TINYINT(1)   NOT NULL DEFAULT 0,
    pastoral_id  TINYINT UNSIGNED NULL,
    color        VARCHAR(7)   NULL,
    publicado    TINYINT(1)   NOT NULL DEFAULT 0,
    usuario_id   INT UNSIGNED NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_eve_slug (slug),
    KEY idx_eve_fecha (fecha_inicio),
    KEY idx_eve_pub (publicado, fecha_inicio),
    KEY idx_eve_pastoral (pastoral_id),
    CONSTRAINT fk_eve_pastoral FOREIGN KEY (pastoral_id) REFERENCES pastorales(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mensajes del formulario de contacto. Contienen datos personales de quien
-- escribe, así que guardan la constancia de consentimiento. Ver docs/PRIVACIDAD.md
CREATE TABLE IF NOT EXISTS mensajes_contacto (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre         VARCHAR(140) NOT NULL,
    email          VARCHAR(150) NULL,
    telefono       VARCHAR(20)  NULL,
    asunto         VARCHAR(160) NULL,
    mensaje        TEXT         NOT NULL,
    ip             VARCHAR(45)  NULL,
    leido          TINYINT(1)   NOT NULL DEFAULT 0,
    respondido     TINYINT(1)   NOT NULL DEFAULT 0,
    nota_interna   VARCHAR(255) NULL,
    atendido_por   INT UNSIGNED NULL,
    consentimiento TINYINT(1)   NOT NULL DEFAULT 0,
    aviso_version  VARCHAR(20)  NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_msg_leido (leido, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Control de frecuencia de los formularios públicos. Es la única tabla que se
-- borra de verdad: solo sirve para contar los envíos de la última hora.
CREATE TABLE IF NOT EXISTS intentos_formulario (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip         VARCHAR(45)  NOT NULL,
    formulario VARCHAR(40)  NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_int_ip (ip, formulario, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- PARROQUIA
-- ------------------------------------------------------------

-- Párroco, vicarios, diáconos, religiosos, laicos y personal. Borrado lógico:
-- se desactivan (dejaron el cargo), no se borran. Un delete real sí está
-- permitido para corregir un alta por error (organigrama_nodos.persona_id
-- queda en NULL automáticamente por el ON DELETE SET NULL de su FK).
CREATE TABLE IF NOT EXISTS personas (
    id         SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre     VARCHAR(140)      NOT NULL,
    cargo      VARCHAR(100)      NULL,
    tipo       ENUM('parroco','vicario','diacono','religioso','laico','staff')
                                 NOT NULL DEFAULT 'laico',
    semblanza  TEXT              NULL,
    foto       VARCHAR(255)      NULL,
    email      VARCHAR(150)      NULL,
    telefono   VARCHAR(20)       NULL,
    orden      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    activo     TINYINT(1)        NOT NULL DEFAULT 1,
    created_at DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_per_tipo (tipo, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Árbol autorreferenciado de hasta 4 niveles. Un nodo puede apuntar a una
-- persona, a una pastoral, o a ninguna de las dos y ser solo un título de
-- agrupación ("Consejo Pastoral").
CREATE TABLE IF NOT EXISTS organigrama_nodos (
    id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    padre_id    SMALLINT UNSIGNED NULL,
    titulo      VARCHAR(140)      NOT NULL,
    persona_id  SMALLINT UNSIGNED NULL,
    pastoral_id TINYINT UNSIGNED  NULL,
    nivel       TINYINT UNSIGNED  NOT NULL DEFAULT 1,
    orden       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    activo      TINYINT(1)        NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_org_padre (padre_id),
    KEY idx_org_pastoral (pastoral_id),
    CONSTRAINT fk_org_padre    FOREIGN KEY (padre_id)    REFERENCES organigrama_nodos(id) ON DELETE SET NULL,
    CONSTRAINT fk_org_persona  FOREIGN KEY (persona_id)  REFERENCES personas(id)          ON DELETE SET NULL,
    CONSTRAINT fk_org_pastoral FOREIGN KEY (pastoral_id) REFERENCES pastorales(id)        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recurrencia semanal, no fechas concretas: una misa dominical no es un
-- evento, es un patrón que se repite. vigente_desde/vigente_hasta cubre los
-- horarios de temporada (Cuaresma, verano) sin duplicar la tabla.
CREATE TABLE IF NOT EXISTS horarios (
    id            TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tipo          ENUM('misa','confesion','adoracion','oficina','otro')
                                   NOT NULL DEFAULT 'misa',
    dia_semana    TINYINT UNSIGNED NOT NULL,   -- 0=domingo … 6=sábado
    hora          TIME             NOT NULL,
    hora_fin      TIME             NULL,
    lugar         VARCHAR(120)     NULL,
    nota          VARCHAR(160)     NULL,
    vigente_desde DATE             NULL,
    vigente_hasta DATE             NULL,
    orden         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    activo        TINYINT(1)       NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_hor_tipo_dia (tipo, dia_semana, hora)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Coro, catequesis, caridad, jóvenes, ministros MESC... Cada una puede tener
-- uno o más coordinadores (usuarios_pastorales) con permiso para crear
-- contenido, pero no para publicarlo directamente: eso queda para admin/editor.
CREATE TABLE IF NOT EXISTS pastorales (
    id                 TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug               VARCHAR(80)  NOT NULL,
    nombre             VARCHAR(120) NOT NULL,
    descripcion_corta  VARCHAR(255) NULL,
    descripcion        MEDIUMTEXT   NULL,
    imagen             VARCHAR(255) NULL,
    icono              VARCHAR(40)  NULL,
    responsable_nombre VARCHAR(140) NULL,
    contacto_email     VARCHAR(150) NULL,
    contacto_telefono  VARCHAR(20)  NULL,
    dia_reunion        VARCHAR(60)  NULL,
    hora_reunion       TIME         NULL,
    lugar_reunion      VARCHAR(140) NULL,
    acepta_voluntarios TINYINT(1)   NOT NULL DEFAULT 1,
    orden              SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    activa             TINYINT(1)   NOT NULL DEFAULT 1,
    created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pas_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Actividades comunitarias y de apoyo social de cada pastoral.
CREATE TABLE IF NOT EXISTS pastoral_actividades (
    id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pastoral_id TINYINT UNSIGNED NOT NULL,
    titulo      VARCHAR(160) NOT NULL,
    descripcion TEXT NULL,
    tipo        ENUM('comunitaria','apoyo_social','formacion','liturgica') NOT NULL DEFAULT 'comunitaria',
    orden       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    activa      TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_pta_pastoral (pastoral_id),
    CONSTRAINT fk_pta_pastoral FOREIGN KEY (pastoral_id) REFERENCES pastorales(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- SACRAMENTOS
-- ------------------------------------------------------------

-- Catálogo. Semillas: bautizo, primera comunión, confirmación, matrimonio,
-- confesión, unción de enfermos.
CREATE TABLE IF NOT EXISTS sacramentos (
    id                 TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug               VARCHAR(60)  NOT NULL,
    nombre             VARCHAR(80)  NOT NULL,
    descripcion        MEDIUMTEXT   NULL,
    requisitos         MEDIUMTEXT   NULL,
    documentos         MEDIUMTEXT   NULL,
    aportacion         VARCHAR(80)  NULL,
    imagen             VARCHAR(255) NULL,
    acepta_solicitudes TINYINT(1)   NOT NULL DEFAULT 1,
    requiere_tutor     TINYINT(1)   NOT NULL DEFAULT 0,
    orden              SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    activo             TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sac_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campos adicionales que pide cada sacramento en su formulario: es lo que
-- permite agregar "nombre del padrino" a Confirmación sin tocar el esquema.
-- Los marcados dato_sensible=1 solo se muestran a admin y secretaría.
CREATE TABLE IF NOT EXISTS sacramento_campos (
    id            SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sacramento_id TINYINT UNSIGNED NOT NULL,
    nombre_campo  VARCHAR(40)  NOT NULL,
    etiqueta      VARCHAR(120) NOT NULL,
    tipo          ENUM('texto','textarea','fecha','telefono','email','seleccion','checkbox')
                               NOT NULL DEFAULT 'texto',
    opciones      VARCHAR(255) NULL,
    requerido     TINYINT(1)   NOT NULL DEFAULT 0,
    dato_sensible TINYINT(1)   NOT NULL DEFAULT 0,
    orden         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    activo        TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_scp (sacramento_id, nombre_campo),
    CONSTRAINT fk_scp_sacramento FOREIGN KEY (sacramento_id) REFERENCES sacramentos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La tabla más delicada del sistema: datos personales, con frecuencia de
-- menores. es_menor se calcula en el servidor a partir de fecha_nacimiento,
-- nunca se confía en lo que mande el formulario. Ver docs/PRIVACIDAD.md
CREATE TABLE IF NOT EXISTS solicitudes_sacramento (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    folio              VARCHAR(20)  NOT NULL,
    sacramento_id      TINYINT UNSIGNED NOT NULL,
    nombre_solicitante VARCHAR(150) NOT NULL,
    fecha_nacimiento   DATE         NULL,
    es_menor           TINYINT(1)   NOT NULL DEFAULT 0,
    telefono           VARCHAR(20)  NULL,
    email              VARCHAR(150) NULL,
    direccion          VARCHAR(255) NULL,
    tutor_nombre       VARCHAR(150) NULL,
    tutor_parentesco   VARCHAR(60)  NULL,
    tutor_telefono     VARCHAR(20)  NULL,
    fecha_preferida    DATE         NULL,
    notas              TEXT         NULL,
    datos_extra        JSON         NULL,
    estado             ENUM('pendiente','en_revision','aprobada','rechazada','cancelada','completada')
                                    NOT NULL DEFAULT 'pendiente',
    motivo_estado      VARCHAR(255) NULL,
    atendida_por       INT UNSIGNED NULL,
    atendida_at        DATETIME     NULL,
    consentimiento     TINYINT(1)   NOT NULL DEFAULT 0,
    consentimiento_ip  VARCHAR(45)  NULL,
    aviso_version      VARCHAR(20)  NULL,
    origen             ENUM('web','panel') NOT NULL DEFAULT 'web',
    created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sol_folio (folio),
    KEY idx_sol_estado (estado, created_at),
    KEY idx_sol_sac (sacramento_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historial de cambios de estado: "¿quién aprobó esto y cuándo?" sin adivinar.
CREATE TABLE IF NOT EXISTS solicitudes_bitacora (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    solicitud_id    INT UNSIGNED NOT NULL,
    usuario_id      INT UNSIGNED NULL,
    estado_anterior VARCHAR(20)  NULL,
    estado_nuevo    VARCHAR(20)  NOT NULL,
    comentario      VARCHAR(255) NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_solb_solicitud (solicitud_id),
    CONSTRAINT fk_solb_solicitud FOREIGN KEY (solicitud_id) REFERENCES solicitudes_sacramento(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- CURSOS
-- ------------------------------------------------------------
-- A diferencia de avisos/eventos/galería, aquí pastoral_id es solo una
-- etiqueta organizativa: el rol coordinador no administra cursos (no tiene
-- cursos.crear ni cursos.editar), así que no hace falta el mismo alcance por
-- pastoral ni su validación en el servidor.

CREATE TABLE IF NOT EXISTS cursos (
    id                       SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug                     VARCHAR(120) NOT NULL,
    titulo                   VARCHAR(160) NOT NULL,
    descripcion              MEDIUMTEXT   NULL,
    objetivos                TEXT         NULL,
    dirigido_a               VARCHAR(160) NULL,
    imagen                   VARCHAR(255) NULL,
    modalidad                ENUM('presencial','en_linea','mixta') NOT NULL DEFAULT 'presencial',
    instructor_id            SMALLINT UNSIGNED NULL,
    pastoral_id              TINYINT UNSIGNED  NULL,
    cupo                     SMALLINT UNSIGNED NULL,
    aportacion               VARCHAR(60)  NULL,
    fecha_inicio             DATE         NULL,
    fecha_fin                DATE         NULL,
    horario                  VARCHAR(120) NULL,
    lugar                    VARCHAR(160) NULL,
    inscripciones_abiertas   TINYINT(1)   NOT NULL DEFAULT 1,
    fecha_cierre_inscripcion DATE         NULL,
    requiere_tutor           TINYINT(1)   NOT NULL DEFAULT 0,
    publicado                TINYINT(1)   NOT NULL DEFAULT 0,
    orden                    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at               DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cur_slug (slug),
    KEY idx_cur_pub (publicado, fecha_inicio),
    CONSTRAINT fk_cur_instructor FOREIGN KEY (instructor_id) REFERENCES personas(id)   ON DELETE SET NULL,
    CONSTRAINT fk_cur_pastoral   FOREIGN KEY (pastoral_id)   REFERENCES pastorales(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Temario. Hoy es contenido público informativo; en fase 2 es el ancla del
-- aula virtual (curso_materiales, curso_tareas, curso_entregas,
-- curso_calificaciones colgarán de aquí sin tocar nada existente).
CREATE TABLE IF NOT EXISTS curso_sesiones (
    id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    curso_id    SMALLINT UNSIGNED NOT NULL,
    numero      SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    titulo      VARCHAR(160) NOT NULL,
    descripcion TEXT         NULL,
    fecha       DATE         NULL,
    orden       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_cus_curso (curso_id),
    CONSTRAINT fk_cus_curso FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- uq_ins_curso_email evita que la misma persona se inscriba dos veces al
-- mismo curso.
CREATE TABLE IF NOT EXISTS inscripciones_curso (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    folio            VARCHAR(20)  NOT NULL,
    curso_id         SMALLINT UNSIGNED NOT NULL,
    nombre           VARCHAR(150) NOT NULL,
    fecha_nacimiento DATE         NULL,
    es_menor         TINYINT(1)   NOT NULL DEFAULT 0,
    telefono         VARCHAR(20)  NULL,
    email            VARCHAR(150) NULL,
    tutor_nombre     VARCHAR(150) NULL,
    tutor_parentesco VARCHAR(60)  NULL,
    tutor_telefono   VARCHAR(20)  NULL,
    estado           ENUM('pendiente','confirmada','lista_espera','cancelada') NOT NULL DEFAULT 'pendiente',
    consentimiento   TINYINT(1)   NOT NULL DEFAULT 0,
    consentimiento_ip VARCHAR(45) NULL,
    aviso_version    VARCHAR(20)  NULL,
    notas            TEXT         NULL,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ins_folio (folio),
    UNIQUE KEY uq_ins_curso_email (curso_id, email),
    KEY idx_ins_estado (estado),
    CONSTRAINT fk_ins_curso FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- SEMILLAS DE CONFIGURACIÓN
-- ------------------------------------------------------------
-- INSERT IGNORE: al reimportar sobre una base con datos no se pisan los
-- valores ya capturados, y una clave nueva de una versión posterior aparece
-- sola sin necesidad de migrar.

INSERT IGNORE INTO configuracion (clave, valor, grupo) VALUES
    ('parroquia_nombre',         'Parroquia Nuestra Señora de la Paz', 'general'),
    ('parroquia_diocesis',       '',   'general'),
    ('logo',                     '',   'general'),
    ('favicon',                  '',   'general'),
    ('organigrama_imagen',       '',   'general'),

    ('direccion',                '',   'contacto'),
    ('ciudad',                   '',   'contacto'),
    ('cp',                       '',   'contacto'),
    ('telefono',                 '',   'contacto'),
    ('whatsapp',                 '',   'contacto'),
    ('email',                    '',   'contacto'),
    ('horario_oficina',          '',   'contacto'),
    ('mapa_embed',               '',   'contacto'),
    ('latitud',                  '',   'contacto'),
    ('longitud',                 '',   'contacto'),

    ('facebook',                 '',   'redes'),
    ('instagram',                '',   'redes'),
    ('youtube',                  '',   'redes'),

    ('meta_descripcion',         '',   'seo'),
    ('og_imagen',                '',   'seo'),

    ('aviso_privacidad_version', '1.0', 'legal'),
    ('retencion_meses_solicitudes', '36', 'legal');

-- ------------------------------------------------------------
-- SEMILLAS DE BLOQUES DE CONTENIDO
-- ------------------------------------------------------------
-- La descripción explica al editor dónde aparece cada bloque; sin ella, una
-- lista de claves como «mision» o «historia» no le dice nada a nadie.

INSERT IGNORE INTO bloques_contenido (clave, zona, titulo, descripcion, orden) VALUES
    ('bienvenida_parroco', 'inicio',      'Bienvenida',
     'Saludo del párroco. Aparece en la portada, debajo de la imagen principal.', 10),
    ('inicio_intro',       'inicio',      'Presentación',
     'Párrafo breve de presentación de la parroquia en la portada.', 20),

    ('historia',           'nosotros',    'Nuestra historia',
     'Historia de la parroquia. Aparece en «Quiénes somos».', 10),
    ('mision',             'nosotros',    'Misión',
     'Misión de la parroquia. Aparece en «Quiénes somos».', 20),
    ('vision',             'nosotros',    'Visión',
     'Visión de la parroquia. Aparece en «Quiénes somos».', 30),
    ('valores',            'nosotros',    'Valores',
     'Valores de la comunidad. Aparece en «Quiénes somos».', 40),

    ('horarios_intro',     'horarios',    'Horarios',
     'Texto introductorio de la página de horarios de misas y celebraciones.', 10),
    ('sacramentos_intro',  'sacramentos', 'Sacramentos',
     'Texto introductorio de la página de sacramentos, antes del listado.', 10),
    ('pastorales_intro',   'pastorales',  'Pastorales',
     'Texto introductorio de la página de pastorales, antes del listado.', 10),
    ('cursos_intro',       'cursos',      'Cursos y capacitaciones',
     'Texto introductorio de la página de cursos, antes del catálogo.', 10),
    ('contacto_intro',     'contacto',    'Contacto',
     'Texto introductorio de la página de contacto, antes del formulario.', 10);

-- ------------------------------------------------------------
-- SEMILLAS DE SACRAMENTOS
-- ------------------------------------------------------------
-- Los seis son universales en la práctica católica; lo que varía por
-- parroquia son los requisitos y documentos, que quedan en blanco para que
-- la parroquia los capture desde el panel antes de publicar el sitio.

INSERT IGNORE INTO sacramentos (slug, nombre, acepta_solicitudes, requiere_tutor, orden) VALUES
    ('bautizo',            'Bautizo',                   1, 1, 10),
    ('primera-comunion',   'Primera Comunión',           1, 1, 20),
    ('confirmacion',       'Confirmación',                1, 1, 30),
    ('matrimonio',         'Matrimonio',                 1, 0, 40),
    ('confesion',          'Confesión',                  0, 0, 50),
    ('uncion-enfermos',    'Unción de los Enfermos',     1, 0, 60);

-- ------------------------------------------------------------
-- AVISO DE PRIVACIDAD (BORRADOR)
-- ------------------------------------------------------------
-- Se instala SIN PUBLICAR a propósito. Es una base redactada conforme a la
-- LFPDPPP, pero tiene datos por completar y debe revisarla la parroquia antes
-- de que el sitio salga al público. Un aviso legal incompleto y publicado es
-- peor que no tenerlo. Ver docs/PRIVACIDAD.md

INSERT IGNORE INTO paginas (slug, titulo, contenido, meta_descripcion, publicada, orden) VALUES
('aviso-de-privacidad', 'Aviso de Privacidad',
'<blockquote><strong>Borrador pendiente de revision.</strong> Este texto es una base conforme a la Ley Federal de Proteccion de Datos Personales en Posesion de los Particulares. Antes de publicar el sitio hay que completar los datos entre corchetes y revisarlo con la parroquia.</blockquote>
<h2>Responsable del tratamiento</h2>
<p>La [DENOMINACION LEGAL DE LA ASOCIACION RELIGIOSA], con domicilio en [DOMICILIO COMPLETO], es responsable del uso y proteccion de sus datos personales.</p>
<h2>Datos que recabamos</h2>
<p>Segun el tramite que solicite, podemos recabar: nombre completo, fecha de nacimiento, domicilio, telefono, correo electronico y los datos de sus padres, madres o tutores cuando el solicitante sea menor de edad.</p>
<p>No solicitamos datos patrimoniales ni datos personales sensibles.</p>
<h2>Para que usamos sus datos</h2>
<p>Finalidades necesarias para el tramite solicitado:</p>
<ul>
<li>Atender y dar seguimiento a solicitudes de sacramentos.</li>
<li>Registrar inscripciones a cursos y catequesis.</li>
<li>Responder los mensajes que nos envia por el formulario de contacto.</li>
<li>Integrar los registros sacramentales que la parroquia debe conservar.</li>
</ul>
<p>Finalidades adicionales, que no son necesarias y a las que puede oponerse:</p>
<ul>
<li>Informarle de actividades, celebraciones y avisos de la comunidad parroquial.</li>
</ul>
<h2>Datos de menores de edad</h2>
<p>Cuando el solicitante es menor de edad, el padre, la madre o el tutor debe otorgar el consentimiento y proporcionar sus propios datos de contacto. No publicamos nombres ni fotografias de menores en este sitio sin autorizacion expresa por escrito.</p>
<h2>Transferencias</h2>
<p>No transferimos sus datos a terceros, salvo a la [DIOCESIS O ARQUIDIOCESIS] cuando la normativa eclesiastica lo requiera para el registro del sacramento, y a las autoridades competentes cuando exista una obligacion legal.</p>
<h2>Como ejercer sus derechos</h2>
<p>Usted puede acceder a sus datos, rectificarlos si son inexactos, cancelarlos u oponerse a su uso. Tambien puede revocar el consentimiento que nos otorgo.</p>
<p>Para ello, escriba a <strong>[CORREO DE CONTACTO]</strong> o acuda a la oficina parroquial en el domicilio senalado, durante el horario de atencion. Le pediremos identificarse y describir con claridad que dato desea corregir o eliminar. Responderemos en un plazo maximo de veinte dias habiles.</p>
<h2>Conservacion de los datos</h2>
<p>Los datos de solicitudes e inscripciones ya atendidas se conservan el tiempo indicado en la configuracion del sitio, y despues se anonimizan: se conservan solo las cifras, sin los datos que identifican a la persona. Los registros sacramentales se conservan de forma permanente por obligacion eclesiastica.</p>
<h2>Cambios a este aviso</h2>
<p>Cualquier modificacion se publicara en esta misma pagina. Cada solicitud que recibimos queda asociada a la version del aviso que estaba vigente al momento de enviarla.</p>
<p><em>Ultima actualizacion: [FECHA].</em></p>',
'Aviso de privacidad de la parroquia: que datos recabamos, para que los usamos y como ejercer sus derechos.',
0, 100);

SET foreign_key_checks = 1;
