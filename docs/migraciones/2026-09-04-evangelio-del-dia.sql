-- ============================================================
-- 2026-09-04 — Evangelio del día y reflexión del párroco
-- ============================================================
-- Cuarta migración del día. A diferencia de las tres anteriores, esta no
-- renombra ni reparte nada existente: solo agrega la tabla del módulo nuevo
-- "Evangelio del día", con el que el párroco publica la lectura de hoy y su
-- reflexión, cada una en su propio campo.
--
-- Una fila por fecha (UNIQUE), para que "el de hoy" sea un
-- WHERE fecha = CURDATE() trivial y no puedan quedar dos capturados el mismo
-- día por accidente. `evangelio` es obligatorio; `reflexion` no, porque un día
-- puede publicarse solo la lectura sin que el párroco haya tenido tiempo de
-- escribir su reflexión.
--
-- Sin `slug`: se identifica por `fecha`, no por una URL de detalle con título
-- libre —mismo caso que `bloques_contenido`/`configuracion` con `clave`—. Un
-- solo `publicado`, no el escalón interno/público de avisos y cursos: esto es
-- de toda la parroquia o no es de nadie, no hay pastoral que lo acote.
--
-- Aplicar con:
--     C:\xampp\mysql\bin\mysql.exe -uroot parroquia_nsdlp < docs/migraciones/2026-09-04-evangelio-del-dia.sql
--
-- Es una migración inofensiva —solo crea una tabla nueva, no toca ninguna
-- existente—, pero la costumbre de respaldar antes no cambia.

CREATE TABLE IF NOT EXISTS evangelios_dia (
    id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    fecha       DATE              NOT NULL,
    evangelio   MEDIUMTEXT        NOT NULL,
    reflexion   MEDIUMTEXT        NULL,
    publicado   TINYINT(1)        NOT NULL DEFAULT 0,
    usuario_id  INT UNSIGNED      NULL,
    created_at  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME          NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_evd_fecha (fecha),
    KEY idx_evd_publicado (publicado, fecha),
    CONSTRAINT fk_evd_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
