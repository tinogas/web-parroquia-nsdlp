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

-- persona_id ata la cuenta a su ficha del equipo pastoral, que es el registro
-- principal: de ahí salen el organigrama y las cuentas, y de ahí vienen el
-- nombre, el teléfono y la foto (la cuenta guarda copia y PersonaModel la
-- refresca al guardar la ficha). Es NULL para las cuentas que no deben
-- aparecer en el directorio público, como la del administrador. UNIQUE: una
-- persona, una cuenta. FK a personas, creada más abajo en este script.
CREATE TABLE IF NOT EXISTS usuarios (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    persona_id    SMALLINT UNSIGNED NULL,
    nombre        VARCHAR(120) NOT NULL,
    email         VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    -- El rol dice QUÉ puede hacer; las pastorales y sedes asignadas, SOBRE QUÉ.
    -- Hubo seis roles con la pastoral en el nombre (admin_mesc, consulta_lector…)
    -- y se retiraron: no sabían distinguir a tres coordinadoras de catequesis,
    -- una por comunidad. Ver docs/ARQUITECTURA.md
    rol           ENUM('admin','editor','secretaria',
                       'coordinador','coordinador_general','consulta')
                  NOT NULL DEFAULT 'coordinador',
    foto          VARCHAR(255) NULL,
    telefono      VARCHAR(20)  NULL,
    activo        TINYINT(1)   NOT NULL DEFAULT 1,
    ultimo_acceso DATETIME     NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usr_email (email),
    UNIQUE KEY uq_usr_persona (persona_id),
    KEY idx_usr_rol (rol, activo),
    CONSTRAINT fk_usr_persona FOREIGN KEY (persona_id) REFERENCES personas(id) ON DELETE SET NULL
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

-- Las sedes en las que trabaja, que acotan sus pastorales: la otra mitad del
-- alcance. NINGUNA fila = todas las sedes, al revés que usuarios_pastorales
-- (ninguna fila = nada), y así se representa una coordinación general.
--
-- Ojo con los respaldos anteriores a la revisión de alcance: entonces esta
-- tabla decía "administra el centro completo" y AÑADÍA todas las pastorales de
-- esa sede. Ver docs/ARQUITECTURA.md. FK a centros, creada más abajo.
CREATE TABLE IF NOT EXISTS usuarios_centros (
    usuario_id INT UNSIGNED      NOT NULL,
    centro_id  SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (usuario_id, centro_id),
    CONSTRAINT fk_uc_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_uc_centro  FOREIGN KEY (centro_id)  REFERENCES centros(id)  ON DELETE CASCADE
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
--
-- Vigencia (issue #3): fecha_publicacion ya funcionaba como "visible desde"
-- (una fecha futura no se muestra hasta llegar ese día); vigente_hasta es el
-- "visible hasta" que faltaba. Con las dos, un aviso se publica y despublica
-- solo según su ventana de vigencia, sin que nadie tenga que tocar el flag
-- publicado dos veces. NULL en vigente_hasta = sin fecha de baja.
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
    vigente_hasta     DATE         NULL,
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
--
-- pastoral_id dice QUIÉN lo organiza y centro_id DÓNDE. Las dos juntas son el
-- alcance: la catequesis de Jesús el Señor y la de la sede son el mismo equipo
-- en dos comunidades, y cada coordinadora administra la suya. NULL en centro_id
-- = evento de toda la parroquia. Ver docs/ARQUITECTURA.md, "Alcance por sede".
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
    centro_id    SMALLINT UNSIGNED NULL,
    color        VARCHAR(7)   NULL,
    publicado    TINYINT(1)   NOT NULL DEFAULT 0,
    usuario_id   INT UNSIGNED NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_eve_slug (slug),
    KEY idx_eve_fecha (fecha_inicio),
    KEY idx_eve_pub (publicado, fecha_inicio),
    KEY idx_eve_pastoral (pastoral_id),
    KEY idx_eve_centro (centro_id),
    CONSTRAINT fk_eve_pastoral FOREIGN KEY (pastoral_id) REFERENCES pastorales(id) ON DELETE SET NULL,
    CONSTRAINT fk_eve_centro   FOREIGN KEY (centro_id)   REFERENCES centros(id)    ON DELETE SET NULL
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

-- La sede parroquial y los centros que dependen de ella, en un solo catálogo:
-- "sede" y "centro" son el mismo tipo de dato, distinguidos por tipo. Sin
-- tabla aparte para la sede -hoy hay una sola, pero forzar esa cardinalidad
-- en el esquema es una regla que nadie pidió.
CREATE TABLE IF NOT EXISTS centros (
    id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tipo        ENUM('sede','centro') NOT NULL DEFAULT 'centro',
    nombre      VARCHAR(150)      NOT NULL,
    direccion   VARCHAR(255)      NULL,
    telefono    VARCHAR(20)       NULL,
    descripcion TEXT              NULL,
    imagen      VARCHAR(255)      NULL,
    orden       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    activo      TINYINT(1)        NOT NULL DEFAULT 1,
    created_at  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cen_tipo (tipo, activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO centros (tipo, nombre, orden) VALUES
    ('sede',   'Parroquia Nuestra Señora de la Paz', 10),
    ('centro', 'San Pío de Pietrelcina',             20),
    ('centro', 'Jesús el Señor',                     30);

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
    -- Solo mes y día importan (avisar cumpleaños en el panel de inicio); el
    -- año viaja igual porque DATE no permite guardar uno sin el otro, pero no
    -- se muestra ni se usa para calcular edad.
    fecha_nacimiento DATE         NULL,
    orden      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    activo     TINYINT(1)        NOT NULL DEFAULT 1,
    created_at DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_per_tipo (tipo, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pivote: una misma persona del equipo pastoral suele llevar más de una
-- pastoral a la vez (catequesis y liturgia, por ejemplo), igual que ya pasa
-- con usuarios_pastorales. FK real a pastorales, creada más abajo.
CREATE TABLE IF NOT EXISTS persona_pastorales (
    persona_id  SMALLINT UNSIGNED NOT NULL,
    pastoral_id TINYINT UNSIGNED  NOT NULL,
    PRIMARY KEY (persona_id, pastoral_id),
    CONSTRAINT fk_pp_persona  FOREIGN KEY (persona_id)  REFERENCES personas(id)   ON DELETE CASCADE,
    CONSTRAINT fk_pp_pastoral FOREIGN KEY (pastoral_id) REFERENCES pastorales(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pivote análogo, pero por centro/sede: alguien del equipo puede estar
-- adscrito a más de un centro (igual que usuarios_centros para el rol
-- coordinador). FK real a centros, creada más abajo.
CREATE TABLE IF NOT EXISTS persona_centros (
    persona_id SMALLINT UNSIGNED NOT NULL,
    centro_id  SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (persona_id, centro_id),
    CONSTRAINT fk_pc_persona FOREIGN KEY (persona_id) REFERENCES personas(id) ON DELETE CASCADE,
    CONSTRAINT fk_pc_centro  FOREIGN KEY (centro_id)  REFERENCES centros(id)  ON DELETE CASCADE
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
-- horarios de temporada (Cuaresma, verano) sin duplicar la tabla. centro_id
-- (issue #3) agrupa los horarios por sede/centro en la vista pública; NULL
-- en los que no son de un lugar concreto. lugar sigue siendo texto libre
-- para el detalle dentro de ese centro ("Capilla lateral", por ejemplo).
CREATE TABLE IF NOT EXISTS horarios (
    id            TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    centro_id     SMALLINT UNSIGNED NULL,
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
    KEY idx_hor_tipo_dia (tipo, dia_semana, hora),
    KEY idx_hor_centro (centro_id),
    CONSTRAINT fk_hor_centro FOREIGN KEY (centro_id) REFERENCES centros(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Coro, catequesis, caridad, jóvenes, ministros MESC... Cada una puede tener
-- uno o más coordinadores (usuarios_pastorales) con permiso para crear
-- contenido, pero no para publicarlo directamente: eso queda para admin/editor.
-- centro_id (issue #3, "contenido propio por pastoral") liga la pastoral a su
-- sede o centro; NULL en las que ya existían antes de este campo.
CREATE TABLE IF NOT EXISTS pastorales (
    id                 TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    centro_id          SMALLINT UNSIGNED NULL,
    -- pastoral_padre_id agrupa pastorales bajo la Comisión que las coordina
    -- (Litúrgica, Profética...). Máximo 2 niveles, igual que en la vida real
    -- de la parroquia: no lo garantiza un CHECK entre filas, lo valida
    -- PastoralController::guardar() (una Comisión no puede a su vez tener
    -- padre, y una pastoral con hijas no puede recibir una). "Es Comisión" no
    -- es una columna aparte: se deriva de "tiene alguna hija".
    pastoral_padre_id  TINYINT UNSIGNED NULL,
    slug               VARCHAR(80)  NOT NULL,
    nombre             VARCHAR(120) NOT NULL,
    descripcion_corta  VARCHAR(255) NULL,
    descripcion        MEDIUMTEXT   NULL,
    imagen             VARCHAR(255) NULL,
    icono              VARCHAR(40)  NULL,
    -- responsable_nombre es el respaldo para cuando el responsable todavía no
    -- tiene ficha en el equipo pastoral (p. ej. un voluntario sin cuenta ni
    -- ficha propia); en cuanto se elige de responsable_persona_id, este campo
    -- se limpia y el nombre se toma de personas.nombre — PastoralModel no lo
    -- duplica, PersonaModel::sincronizarCuenta() lo mantiene si la ficha cambia
    -- de nombre. Ver docs/ARQUITECTURA.md
    responsable_nombre     VARCHAR(140) NULL,
    responsable_persona_id SMALLINT UNSIGNED NULL,
    -- contacto_email se sincroniza con el correo de acceso (usuarios.email) de
    -- la cuenta del responsable, si tiene una — UsuarioModel lo empuja aquí en
    -- cada guardado. Sin cuenta vinculada, es un campo libre normal.
    contacto_email     VARCHAR(150) NULL,
    contacto_telefono  VARCHAR(20)  NULL,
    dia_reunion        VARCHAR(60)  NULL,
    hora_reunion       TIME         NULL,
    lugar_reunion      VARCHAR(140) NULL,
    acepta_voluntarios TINYINT(1)   NOT NULL DEFAULT 1,
    orden              SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    activa             TINYINT(1)   NOT NULL DEFAULT 1,
    -- Publicarla en el menú del panel (agrupada bajo su Comisión, con acceso
    -- a avisos/eventos/cursos/documentos) es un paso aparte y deliberado, no
    -- automático al crearla: solo Administrador puede activarlo (confirmando
    -- su contraseña), vía PastoralController::menuActivar().
    visible_en_menu    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pas_slug (slug),
    KEY idx_pas_centro (centro_id),
    KEY idx_pas_responsable (responsable_persona_id),
    KEY idx_pas_padre (pastoral_padre_id),
    CONSTRAINT fk_pas_centro      FOREIGN KEY (centro_id)              REFERENCES centros(id)  ON DELETE SET NULL,
    CONSTRAINT fk_pas_responsable FOREIGN KEY (responsable_persona_id) REFERENCES personas(id) ON DELETE SET NULL,
    CONSTRAINT fk_pas_padre       FOREIGN KEY (pastoral_padre_id)      REFERENCES pastorales(id) ON DELETE SET NULL
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

-- Documentación descargable de cada pastoral (issue #3): reglamentos, guías,
-- formatos. archivo guarda la ruta bajo uploads/, igual convención que
-- avisos.archivo_pdf.
CREATE TABLE IF NOT EXISTS pastoral_documentos (
    id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pastoral_id TINYINT UNSIGNED NOT NULL,
    titulo      VARCHAR(160)     NOT NULL,
    archivo     VARCHAR(255)     NOT NULL,
    orden       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    activo      TINYINT(1)       NOT NULL DEFAULT 1,
    usuario_id  INT UNSIGNED     NULL,
    created_at  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pdo_pastoral (pastoral_id),
    CONSTRAINT fk_pdo_pastoral FOREIGN KEY (pastoral_id) REFERENCES pastorales(id) ON DELETE CASCADE,
    CONSTRAINT fk_pdo_usuario  FOREIGN KEY (usuario_id)  REFERENCES usuarios(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- MESC — MINISTROS EXTRAORDINARIOS DE LA COMUNIÓN
-- ------------------------------------------------------------
-- Issue #3. Registro de visitas a enfermos para llevar la comunión, con los
-- mismos datos que tenía la extinta solicitud de unción de enfermos (folio,
-- estado y bitácora de aprobación NO se heredan: aquí no hay formulario
-- público ni flujo de revisión, es una herramienta 100% interna del panel).
-- pastoral_id es obligatoria y NUNCA null —a diferencia de avisos/eventos—
-- porque esta actividad siempre pertenece a la pastoral de MESC, jamás es
-- contenido parroquial general.
--
-- direccion (texto) es lo único obligatorio para ubicar al enfermo.
-- latitud/longitud quedan NULL si solo se capturó la dirección a mano, sin
-- fijar el pin en el mapa (issue #3: "en mapa (pin) o captura manual" son
-- alternativas, no un requisito doble).
--
-- OJO — dato sensible: el solo hecho de aparecer aquí revela que la persona
-- está enferma, lo que la LFPDPPP trata como dato sensible (Art. 3, fr. VI).
-- El consentimiento para tratarlo se obtiene en persona, al solicitar la
-- visita —no hay formulario web que lo capture—, así que no hay columnas de
-- consentimiento aquí. Ver docs/PRIVACIDAD.md.
CREATE TABLE IF NOT EXISTS mesc_visitas (
    id                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    pastoral_id            TINYINT UNSIGNED NOT NULL,
    nombre_enfermo         VARCHAR(150) NOT NULL,
    direccion              VARCHAR(255) NOT NULL,
    latitud                DECIMAL(10,7) NULL,
    longitud               DECIMAL(10,7) NULL,
    telefono               VARCHAR(20)  NULL,
    solicitante_nombre     VARCHAR(150) NULL,
    solicitante_parentesco VARCHAR(60)  NULL,
    solicitante_telefono   VARCHAR(20)  NULL,
    notas                  TEXT         NULL,
    activo                 TINYINT(1)   NOT NULL DEFAULT 1,
    usuario_id             INT UNSIGNED NULL,
    created_at             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             DATETIME     NULL,
    PRIMARY KEY (id),
    KEY idx_mvi_pastoral (pastoral_id, activo),
    CONSTRAINT fk_mvi_pastoral FOREIGN KEY (pastoral_id) REFERENCES pastorales(id) ON DELETE CASCADE,
    CONSTRAINT fk_mvi_usuario  FOREIGN KEY (usuario_id)  REFERENCES usuarios(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Una ruta es un recorrido para un grupo de visitas activas, en el orden que
-- MescModel::ordenSugerido() calcula (vecino más cercano sobre distancia
-- Haversine, partiendo de la parroquia) y que sigue siendo editable después:
-- mesc_ruta_visitas.orden se puede reacomodar a mano antes de exportar el
-- CSV final. "Óptima" es una aproximación geométrica en línea recta, no una
-- ruta real por calles ni de tráfico —el proyecto no depende de ningún
-- servicio de mapas de pago para calcularla—.
CREATE TABLE IF NOT EXISTS mesc_rutas (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    pastoral_id TINYINT UNSIGNED NOT NULL,
    nombre      VARCHAR(150) NOT NULL,
    usuario_id  INT UNSIGNED NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_mru_pastoral (pastoral_id),
    CONSTRAINT fk_mru_pastoral FOREIGN KEY (pastoral_id) REFERENCES pastorales(id) ON DELETE CASCADE,
    CONSTRAINT fk_mru_usuario  FOREIGN KEY (usuario_id)  REFERENCES usuarios(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mesc_ruta_visitas (
    ruta_id   INT UNSIGNED NOT NULL,
    visita_id INT UNSIGNED NOT NULL,
    orden     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (ruta_id, visita_id),
    CONSTRAINT fk_mrv_ruta   FOREIGN KEY (ruta_id)   REFERENCES mesc_rutas(id)   ON DELETE CASCADE,
    CONSTRAINT fk_mrv_visita FOREIGN KEY (visita_id) REFERENCES mesc_visitas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catálogo de ministros MESC (issue #3, "calendario de turnos"): a diferencia
-- de mesc_visitas, esto NO es un dato sensible por sí mismo —es la lista de
-- quién sirve, no de quién recibe la visita—, así que es una tabla simple sin
-- las mismas protecciones reforzadas. Se modela aparte de personas (que es el
-- equipo pastoral PÚBLICO mostrado en "Quiénes somos" con foto y semblanza):
-- un ministro MESC es un voluntario interno, no necesariamente parte de esa
-- vitrina pública — de ahí que persona_id sea opcional: vincula con su ficha
-- cuando sí está de alta ahí (para que el teléfono salga de una sola fuente,
-- ver PersonaModel::sincronizarPersonal()), y queda NULL cuando no.
--
-- OJO con `nombre`: aquí es el nombre CORTO del ministro —«Zulema», «Tino»—,
-- el que cabe en una casilla del calendario de turnos y con el que se le
-- reconoce al capturar un calendario hecho fuera del sistema. Es un dato
-- propio: se guarda aunque haya persona vinculada y la ficha no lo pisa, a
-- diferencia de catequesis_catequistas y lector_lectores.
CREATE TABLE IF NOT EXISTS mesc_ministros (
    id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pastoral_id TINYINT UNSIGNED NOT NULL,
    persona_id  SMALLINT UNSIGNED NULL,
    nombre      VARCHAR(150) NOT NULL,
    telefono    VARCHAR(20)  NULL,
    activo      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_mmi_pastoral (pastoral_id, activo),
    UNIQUE KEY uq_mmi_persona (persona_id),
    CONSTRAINT fk_mmi_pastoral FOREIGN KEY (pastoral_id) REFERENCES pastorales(id) ON DELETE CASCADE,
    CONSTRAINT fk_mmi_persona  FOREIGN KEY (persona_id)  REFERENCES personas(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Un turno cubre una misa o evento en una fecha concreta. Deliberadamente sin
-- FK a horarios ni a eventos: horarios es recurrencia semanal (no una fecha),
-- y atarlo a eventos obligaría a que exista un evento formal para cada misa
-- dominical, que es justo lo que horarios evita. descripcion (texto libre,
-- "Misa", "Santísimo", "Hora Santa", "Misa de Niños") es más simple y no
-- depende de que ese horario o evento exista formalmente en el sistema.
-- Colores litúrgicos de la Iglesia (issue #3, calendario de turnos): catálogo
-- de referencia —blanco, verde, morado, rojo, rosa— para etiquetar cada turno
-- según el tiempo o fiesta del día. Mantenimiento libre desde el panel: la
-- tradición reconoce estos cinco, pero nada impide que la parroquia ajuste el
-- texto o el tono exacto. significado se muestra tal cual como referencia en
-- el propio módulo, no solo como ayuda del formulario.
CREATE TABLE IF NOT EXISTS mesc_colores_liturgicos (
    id          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre      VARCHAR(30)  NOT NULL,
    color_hex   VARCHAR(7)   NOT NULL,
    significado VARCHAR(400) NOT NULL,
    orden       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_mcl_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO mesc_colores_liturgicos (nombre, color_hex, significado, orden) VALUES
('Blanco', '#f4f1ea', 'Significa pureza, alegría y luz. Se usa en fiestas grandes como la Navidad, la Pascua y en las celebraciones de la Virgen y de los santos que no fueron mártires.', 10),
('Verde',  '#2e7d46', 'Representa la esperanza y la vida de cada día. Se usa durante el Tiempo Ordinario, que son las semanas largas del año donde no se celebra una fiesta especial.', 20),
('Morado', '#6a4c93', 'Es el signo de la penitencia, la espera y la humildad. Se viste en la Cuaresma y en el Adviento, que son los tiempos de preparación antes de la Pascua y la Navidad.', 30),
('Rojo',   '#b23a2e', 'Simboliza el fuego del Espíritu Santo y la sangre de los mártires. Se usa en el día de Pentecostés, en Viernes Santo y en las fiestas de los apóstoles y mártires.', 40),
('Rosa',   '#e0a8c0', 'Muestra un poco de alegría en medio de un tiempo serio. Se puede usar solo dos veces al año: el tercer domingo de Adviento y el cuarto domingo de Cuaresma.', 50);

CREATE TABLE IF NOT EXISTS mesc_turnos (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    pastoral_id         TINYINT UNSIGNED NOT NULL,
    fecha               DATE         NOT NULL,
    hora                TIME         NULL,
    descripcion         VARCHAR(160) NOT NULL,
    color_liturgico_id  TINYINT UNSIGNED NULL,
    usuario_id          INT UNSIGNED NULL,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_mtu_pastoral_fecha (pastoral_id, fecha),
    CONSTRAINT fk_mtu_pastoral FOREIGN KEY (pastoral_id)        REFERENCES pastorales(id)             ON DELETE CASCADE,
    CONSTRAINT fk_mtu_usuario  FOREIGN KEY (usuario_id)         REFERENCES usuarios(id)               ON DELETE SET NULL,
    CONSTRAINT fk_mtu_color    FOREIGN KEY (color_liturgico_id) REFERENCES mesc_colores_liturgicos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- De 1 a N ministros por turno (issue #3).
CREATE TABLE IF NOT EXISTS mesc_turno_ministros (
    turno_id    INT UNSIGNED      NOT NULL,
    ministro_id SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (turno_id, ministro_id),
    CONSTRAINT fk_mtm_turno    FOREIGN KEY (turno_id)    REFERENCES mesc_turnos(id)    ON DELETE CASCADE,
    CONSTRAINT fk_mtm_ministro FOREIGN KEY (ministro_id) REFERENCES mesc_ministros(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- CATEQUESIS — CATEQUISTAS, PERIODOS, ACTIVIDADES Y DOCUMENTOS
-- ------------------------------------------------------------
-- Módulo dedicado exclusivamente a la pastoral de Catecismo (issue de
-- revisión de módulos: a diferencia de MESC, aquí NO hay selector de
-- pastoral — el controlador resuelve la pastoral por su slug 'catecismo' y
-- nunca muestra ni acepta otra). No hay controlador público: vive
-- enteramente en el panel.

-- Catequista: solo nombre y contacto. El grado que da NO es un dato fijo
-- suyo —vive en catequesis_periodo_catequistas—, porque normalmente no da
-- el mismo grado cada ciclo (ver esa tabla). persona_id opcional, mismo
-- criterio que mesc_ministros.persona_id: vincula con el equipo pastoral
-- cuando ya está de alta ahí, NULL cuando todavía no.
CREATE TABLE IF NOT EXISTS catequesis_catequistas (
    id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pastoral_id TINYINT UNSIGNED  NOT NULL,
    persona_id  SMALLINT UNSIGNED NULL,
    nombre      VARCHAR(140)      NOT NULL,
    telefono    VARCHAR(20)       NULL,
    email       VARCHAR(150)      NULL,
    orden       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    activo      TINYINT(1)        NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_ctq_pastoral (pastoral_id),
    UNIQUE KEY uq_ctq_persona (persona_id),
    CONSTRAINT fk_ctq_pastoral FOREIGN KEY (pastoral_id) REFERENCES pastorales(id) ON DELETE CASCADE,
    CONSTRAINT fk_ctq_persona  FOREIGN KEY (persona_id)  REFERENCES personas(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ciclo de catecismo (ej. "2026-2027", agosto a junio). Para saber qué
-- catequistas dieron clase en cuál ciclo.
CREATE TABLE IF NOT EXISTS catequesis_periodos (
    id           SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pastoral_id  TINYINT UNSIGNED  NOT NULL,
    nombre       VARCHAR(60)       NOT NULL,
    fecha_inicio DATE              NOT NULL,
    fecha_fin    DATE              NOT NULL,
    activo       TINYINT(1)        NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_ctp_pastoral (pastoral_id),
    CONSTRAINT fk_ctp_pastoral FOREIGN KEY (pastoral_id) REFERENCES pastorales(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Qué catequista dio clase en qué periodo, y de qué grado — el grado vive
-- aquí, no en catequesis_catequistas, porque el mismo catequista puede dar
-- un grado distinto cada periodo (issue de revisión de módulos). Un
-- catequista no puede tener dos grados a la vez en un mismo periodo, de
-- ahí la llave primaria compuesta.
CREATE TABLE IF NOT EXISTS catequesis_periodo_catequistas (
    periodo_id    SMALLINT UNSIGNED NOT NULL,
    catequista_id SMALLINT UNSIGNED NOT NULL,
    grado         ENUM('kinder', 'primero_primaria', 'segundo_primaria', 'tercero_primaria',
                        'comunion', 'quinto_misionero', 'sexto_misionero',
                        'primero_secundaria_misionero', 'segundo_secundaria', 'confirmacion') NOT NULL,
    PRIMARY KEY (periodo_id, catequista_id),
    CONSTRAINT fk_cpc_periodo    FOREIGN KEY (periodo_id)    REFERENCES catequesis_periodos(id)    ON DELETE CASCADE,
    CONSTRAINT fk_cpc_catequista FOREIGN KEY (catequista_id) REFERENCES catequesis_catequistas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tablero/calendario de actividades: a diferencia de pastoral_actividades
-- (lista fija de "qué hace la pastoral", sin fechas), esto tiene vigencia y
-- se publica o no, como un mini-evento — mismas columnas que eventos.*.
CREATE TABLE IF NOT EXISTS catequesis_actividades (
    id           SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pastoral_id  TINYINT UNSIGNED  NOT NULL,
    titulo       VARCHAR(160)      NOT NULL,
    descripcion  TEXT              NULL,
    fecha_inicio DATE              NOT NULL,
    fecha_fin    DATE              NULL,
    publicado    TINYINT(1)        NOT NULL DEFAULT 0,
    orden        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    usuario_id   INT UNSIGNED      NULL,
    created_at   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cta_pastoral (pastoral_id),
    KEY idx_cta_publicado (publicado, fecha_inicio),
    CONSTRAINT fk_cta_pastoral FOREIGN KEY (pastoral_id) REFERENCES pastorales(id) ON DELETE CASCADE,
    CONSTRAINT fk_cta_usuario  FOREIGN KEY (usuario_id)  REFERENCES usuarios(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Documentos descargables, mismo patrón que pastoral_documentos.
CREATE TABLE IF NOT EXISTS catequesis_documentos (
    id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pastoral_id TINYINT UNSIGNED  NOT NULL,
    titulo      VARCHAR(160)      NOT NULL,
    archivo     VARCHAR(255)      NOT NULL,
    orden       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    activo      TINYINT(1)        NOT NULL DEFAULT 1,
    usuario_id  INT UNSIGNED      NULL,
    created_at  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ctd_pastoral (pastoral_id),
    CONSTRAINT fk_ctd_pastoral FOREIGN KEY (pastoral_id) REFERENCES pastorales(id) ON DELETE CASCADE,
    CONSTRAINT fk_ctd_usuario  FOREIGN KEY (usuario_id)  REFERENCES usuarios(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- LECTOR — TURNOS Y CATÁLOGO DE LECTORES
-- ------------------------------------------------------------
-- Módulo dedicado para la pastoral de Liturgia (se llamaba "Lectores"; el
-- nombre del módulo, de sus tablas y su ruta /admin/lector no cambiaron, ver
-- la nota de PASTORAL_LECTOR en config/app.php), calcado de
-- mesc_turnos/mesc_ministros/mesc_turno_ministros pero sin rutas ni
-- visitas: un lector proclama la Palabra en misa, no reparte comunión a
-- domicilio. color_liturgico_id reutiliza el catálogo de MESC
-- (mesc_colores_liturgicos): el significado litúrgico de cada color no es
-- propio de ese módulo, es el mismo calendario para toda la parroquia.

CREATE TABLE IF NOT EXISTS lector_lectores (
    id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pastoral_id TINYINT UNSIGNED  NOT NULL,
    persona_id  SMALLINT UNSIGNED NULL,
    nombre      VARCHAR(140)      NOT NULL,
    telefono    VARCHAR(20)       NULL,
    email       VARCHAR(150)      NULL,
    orden       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    activo      TINYINT(1)        NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_lec_pastoral (pastoral_id),
    UNIQUE KEY uq_lec_persona (persona_id),
    CONSTRAINT fk_lec_pastoral FOREIGN KEY (pastoral_id) REFERENCES pastorales(id) ON DELETE CASCADE,
    CONSTRAINT fk_lec_persona  FOREIGN KEY (persona_id)  REFERENCES personas(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lector_turnos (
    id                 INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    pastoral_id        TINYINT UNSIGNED NOT NULL,
    fecha              DATE             NOT NULL,
    hora               TIME             NOT NULL,
    descripcion        VARCHAR(160)     NULL,
    color_liturgico_id TINYINT UNSIGNED NULL,
    usuario_id         INT UNSIGNED     NULL,
    created_at         DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ltu_pastoral (pastoral_id),
    KEY idx_ltu_fecha (fecha),
    CONSTRAINT fk_ltu_pastoral FOREIGN KEY (pastoral_id)        REFERENCES pastorales(id)             ON DELETE CASCADE,
    CONSTRAINT fk_ltu_color    FOREIGN KEY (color_liturgico_id) REFERENCES mesc_colores_liturgicos(id) ON DELETE SET NULL,
    CONSTRAINT fk_ltu_usuario  FOREIGN KEY (usuario_id)         REFERENCES usuarios(id)                ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lector_turno_lectores (
    turno_id  INT UNSIGNED      NOT NULL,
    lector_id SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (turno_id, lector_id),
    CONSTRAINT fk_ltl_turno  FOREIGN KEY (turno_id)  REFERENCES lector_turnos(id)   ON DELETE CASCADE,
    CONSTRAINT fk_ltl_lector FOREIGN KEY (lector_id) REFERENCES lector_lectores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- SACRAMENTOS
-- ------------------------------------------------------------

-- Catálogo puramente informativo (issue #3: se eliminó el formulario de
-- solicitud en línea, junto con sacramento_campos, solicitudes_sacramento y
-- solicitudes_bitacora — ver docs/ARQUITECTURA.md). Semillas: bautizo,
-- primera comunión, confirmación, matrimonio, confesión, unción de enfermos.
CREATE TABLE IF NOT EXISTS sacramentos (
    id          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug        VARCHAR(60)  NOT NULL,
    nombre      VARCHAR(80)  NOT NULL,
    descripcion MEDIUMTEXT   NULL,
    requisitos  MEDIUMTEXT   NULL,
    documentos  MEDIUMTEXT   NULL,
    aportacion  VARCHAR(80)  NULL,
    imagen      VARCHAR(255) NULL,
    orden       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    activo      TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sac_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- CURSOS
-- ------------------------------------------------------------
-- pastoral_id y centro_id rigen igual que en eventos: quién organiza el curso
-- y en qué sede se da. Dejaron de ser etiquetas informativas cuando el
-- coordinador y los administradores de pastoral recibieron cursos.crear /
-- cursos.editar; hoy se validan en el servidor al guardar y al borrar.

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
    centro_id                SMALLINT UNSIGNED NULL,
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
    KEY idx_cur_centro (centro_id),
    CONSTRAINT fk_cur_instructor FOREIGN KEY (instructor_id) REFERENCES personas(id)   ON DELETE SET NULL,
    CONSTRAINT fk_cur_pastoral   FOREIGN KEY (pastoral_id)   REFERENCES pastorales(id) ON DELETE SET NULL,
    CONSTRAINT fk_cur_centro     FOREIGN KEY (centro_id)     REFERENCES centros(id)    ON DELETE SET NULL
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
    centro           VARCHAR(140) NULL,
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

    ('cursos_activo',            '1',  'secciones');

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

-- A diferencia de los bloques de arriba, este sí trae contenido de fábrica: el
-- enlace a la arquidiócesis tiene sentido desde el primer día, no hay que
-- esperar a que alguien lo capture. El admin puede editarlo o agregar más
-- enlaces como cualquier otro bloque de texto.
INSERT IGNORE INTO bloques_contenido (clave, zona, titulo, descripcion, contenido, orden) VALUES
    ('ligas_interes', 'inicio', 'Ligas de interés',
     'Enlaces a sitios externos de interés. Aparece al final de la portada.',
     '<ul><li><a href="https://www.arquidiocesisdehermosillo.org/" target="_blank" rel="noopener">Arquidiócesis de Hermosillo</a></li></ul>',
     90);

-- ------------------------------------------------------------
-- SEMILLAS DE SACRAMENTOS
-- ------------------------------------------------------------
-- Los seis son universales en la práctica católica; lo que varía por
-- parroquia son los requisitos y documentos, que quedan en blanco para que
-- la parroquia los capture desde el panel antes de publicar el sitio.

INSERT IGNORE INTO sacramentos (slug, nombre, orden) VALUES
    ('bautizo',          'Bautizo',                 10),
    ('primera-comunion', 'Primera Comunión',        20),
    ('confirmacion',     'Confirmación',            30),
    ('matrimonio',       'Matrimonio',               40),
    ('confesion',        'Confesión',                50),
    ('uncion-enfermos',  'Unción de los Enfermos',   60);

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
<p>Segun el tramite que solicite, podemos recabar: nombre completo, fecha de nacimiento, telefono, correo electronico y los datos de sus padres, madres o tutores cuando el solicitante sea menor de edad.</p>
<p>No solicitamos datos patrimoniales. La unica excepcion a que no tratemos datos personales sensibles es el estado de salud, y unicamente para coordinar la visita de los Ministros Extraordinarios de la Comunion a personas enfermas: ver mas abajo.</p>
<h2>Para que usamos sus datos</h2>
<p>Finalidades necesarias para el tramite solicitado:</p>
<ul>
<li>Registrar inscripciones a cursos y catequesis.</li>
<li>Responder los mensajes que nos envia por el formulario de contacto.</li>
<li>Coordinar la visita de un Ministro Extraordinario de la Comunion a una persona enferma.</li>
</ul>
<p>Finalidades adicionales, que no son necesarias y a las que puede oponerse:</p>
<ul>
<li>Informarle de actividades, celebraciones y avisos de la comunidad parroquial.</li>
</ul>
<h2>Datos de menores de edad</h2>
<p>Cuando el solicitante es menor de edad, el padre, la madre o el tutor debe otorgar el consentimiento y proporcionar sus propios datos de contacto. No publicamos nombres ni fotografias de menores en este sitio sin autorizacion expresa por escrito.</p>
<h2>Datos de salud: visitas de los Ministros Extraordinarios de la Comunion</h2>
<p>Cuando una persona enferma o su familia solicita la visita de un Ministro Extraordinario de la Comunion, recabamos su nombre, direccion, telefono y los datos de quien solicita la visita en su nombre. Este es un dato personal sensible por revelar un estado de salud, y por eso su tratamiento requiere su consentimiento expreso, que se otorga de viva voz o por telefono al momento de solicitar la visita, no a traves de este sitio web. Este registro nunca se publica ni se comparte fuera del equipo pastoral que coordina las visitas.</p>
<h2>Transferencias</h2>
<p>No transferimos sus datos a terceros, salvo a las autoridades competentes cuando exista una obligacion legal.</p>
<h2>Como ejercer sus derechos</h2>
<p>Usted puede acceder a sus datos, rectificarlos si son inexactos, cancelarlos u oponerse a su uso. Tambien puede revocar el consentimiento que nos otorgo.</p>
<p>Para ello, escriba a <strong>[CORREO DE CONTACTO]</strong> o acuda a la oficina parroquial en el domicilio senalado, durante el horario de atencion. Le pediremos identificarse y describir con claridad que dato desea corregir o eliminar. Responderemos en un plazo maximo de veinte dias habiles.</p>
<h2>Conservacion de los datos</h2>
<p>Conservamos los datos de sus inscripciones solo el tiempo necesario para el curso o la actividad de que se trate. Puede solicitar su cancelacion en cualquier momento por los medios indicados en la seccion "Como ejercer sus derechos".</p>
<h2>Cambios a este aviso</h2>
<p>Cualquier modificacion se publicara en esta misma pagina. Cada inscripcion o mensaje que recibimos queda asociado a la version del aviso que estaba vigente al momento de enviarlo.</p>
<p><em>Ultima actualizacion: [FECHA].</em></p>',
'Aviso de privacidad de la parroquia: que datos recabamos, para que los usamos y como ejercer sus derechos.',
0, 100);

SET foreign_key_checks = 1;
