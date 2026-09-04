-- ============================================================
-- 2026-09-04 — Lectores pasa a llamarse Proclamadores
-- ============================================================
-- Primera migración del proyecto, y la que estrena docs/migraciones/ (ver
-- DESPLIEGUE.md → "Actualizaciones posteriores"). Hace falta porque la base de
-- desarrollo ya tiene turnos y catálogo capturados: reimportar install.sql los
-- borraría.
--
-- Qué cambia:
--   1. `mesc_colores_liturgicos` → `colores_liturgicos`. El catálogo siempre
--      fue compartido —lector_turnos ya lo usaba—, pero el nombre decía que
--      era de MESC. Ahora que Proclamadores también lo administra, el prefijo
--      era una mentira.
--   2. Las tres tablas del módulo pierden el prefijo `lector_`, y
--      `lector_id` pasa a `proclamador_id`.
--   3. `proclamadores.preferencias` es nueva: qué prefiere hacer cada quien
--      al proclamar (monitor, lectura, salmo cantado), que es lo que la
--      coordinación necesita para armar un turno.
--   4. La pastoral cambia de nombre visible. El slug NO: sigue siendo
--      'liturgia' —cambiarlo rompería la URL pública que ya está en uso, y
--      PASTORAL_PROCLAMADORES en config/app.php sigue apuntando ahí—.
--   5. De paso, `hora` deja de ser NOT NULL en los turnos: el formulario la
--      ofrece como opcional y guardarla vacía reventaba.
--
-- Las tablas se crean y se copian en vez de renombrarse con ALTER porque
-- MariaDB 10.4 no admite `ALTER TABLE … RENAME INDEX`: renombrando se
-- quedarían los índices y las restricciones con el nombre viejo, y una base
-- migrada dejaría de ser idéntica a una recién instalada desde install.sql.
--
-- Aplicar con:
--     C:\xampp\mysql\bin\mysql.exe -uroot parroquia_nsdlp < docs/migraciones/2026-09-04-proclamadores.sql
--
-- RESPALDAR ANTES, sin excepción: MariaDB no revierte DDL, así que un fallo a
-- mitad de este archivo no se deshace solo —no hay transacción que envuelva un
-- RENAME o un DROP—. El respaldo de esta migración quedó en
-- backups/antes-proclamadores.sql.

-- ── 1. El catálogo de colores deja de llevar el prefijo de MESC ──────────────
-- RENAME TABLE actualiza solo las FK que apuntan a esta tabla (mesc_turnos y
-- el turno de proclamadores); el índice único sí hay que rebautizarlo a mano.
RENAME TABLE mesc_colores_liturgicos TO colores_liturgicos;

ALTER TABLE colores_liturgicos
    DROP INDEX uq_mcl_nombre,
    ADD UNIQUE KEY uq_col_nombre (nombre);

-- ── 2. Las tablas del módulo, tal como quedan en install.sql ────────────────
CREATE TABLE proclamadores (
    id           SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pastoral_id  TINYINT UNSIGNED  NOT NULL,
    persona_id   SMALLINT UNSIGNED NULL,
    nombre       VARCHAR(140)      NOT NULL,
    telefono     VARCHAR(20)       NULL,
    email        VARCHAR(150)      NULL,
    preferencias SET('monitor', 'lectura', 'salmo') NULL,
    orden        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    activo       TINYINT(1)        NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_pro_pastoral (pastoral_id),
    UNIQUE KEY uq_pro_persona (persona_id),
    CONSTRAINT fk_pro_pastoral FOREIGN KEY (pastoral_id) REFERENCES pastorales(id) ON DELETE CASCADE,
    CONSTRAINT fk_pro_persona  FOREIGN KEY (persona_id)  REFERENCES personas(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- `hora` pasa a admitir vacío, igual que en mesc_turnos: el formulario la
-- ofrece como opcional y era NOT NULL, así que guardar un turno sin hora
-- reventaba con un error de integridad. Se arregla de paso, aprovechando que
-- la tabla se crea de nuevo.
CREATE TABLE proclamadores_turnos (
    id                 INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    pastoral_id        TINYINT UNSIGNED NOT NULL,
    fecha              DATE             NOT NULL,
    hora               TIME             NULL,
    descripcion        VARCHAR(160)     NULL,
    color_liturgico_id TINYINT UNSIGNED NULL,
    usuario_id         INT UNSIGNED     NULL,
    created_at         DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ptu_pastoral (pastoral_id),
    KEY idx_ptu_fecha (fecha),
    CONSTRAINT fk_ptu_pastoral FOREIGN KEY (pastoral_id)        REFERENCES pastorales(id)         ON DELETE CASCADE,
    CONSTRAINT fk_ptu_color    FOREIGN KEY (color_liturgico_id) REFERENCES colores_liturgicos(id) ON DELETE SET NULL,
    CONSTRAINT fk_ptu_usuario  FOREIGN KEY (usuario_id)         REFERENCES usuarios(id)           ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE proclamadores_turno_proclamadores (
    turno_id       INT UNSIGNED      NOT NULL,
    proclamador_id SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (turno_id, proclamador_id),
    CONSTRAINT fk_ptp_turno       FOREIGN KEY (turno_id)       REFERENCES proclamadores_turnos(id) ON DELETE CASCADE,
    CONSTRAINT fk_ptp_proclamador FOREIGN KEY (proclamador_id) REFERENCES proclamadores(id)        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3. Los datos capturados se mudan con su id ──────────────────────────────
-- Con los id originales: los turnos ya capturados apuntan a ellos, y así el
-- AUTO_INCREMENT continúa donde iba.
INSERT INTO proclamadores (id, pastoral_id, persona_id, nombre, telefono, email, preferencias, orden, activo)
SELECT id, pastoral_id, persona_id, nombre, telefono, email, NULL, orden, activo
  FROM lector_lectores;

INSERT INTO proclamadores_turnos (id, pastoral_id, fecha, hora, descripcion, color_liturgico_id, usuario_id, created_at)
SELECT id, pastoral_id, fecha, hora, descripcion, color_liturgico_id, usuario_id, created_at
  FROM lector_turnos;

INSERT INTO proclamadores_turno_proclamadores (turno_id, proclamador_id)
SELECT turno_id, lector_id
  FROM lector_turno_lectores;

DROP TABLE lector_turno_lectores;
DROP TABLE lector_turnos;
DROP TABLE lector_lectores;

-- ── 4. El nombre visible de la pastoral ─────────────────────────────────────
-- Por slug y no por id: los id de pastorales no se siembran en install.sql,
-- se crean desde el panel, así que cambian de una instalación a otra.
UPDATE pastorales SET nombre = 'Proclamadores' WHERE slug = 'liturgia';
