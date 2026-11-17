-- ============================================================
-- 2026-11-15 — Enlace personal de los integrantes
-- ============================================================
-- Darle una cuenta con contraseña a cada ministro y a cada proclamador era
-- demasiado trabajo para la coordinación, y una cuenta compartida por pastoral
-- no sirve para pedir cambios de turno: el sistema tiene que saber quién pide,
-- a quién avisarle de la respuesta y quién puede retirar qué.
--
-- En vez de eso, cada persona del equipo pastoral tiene un enlace propio,
-- largo e imposible de adivinar (/mi/<token>), que la coordinación le manda
-- por WhatsApp. Con él ve sus turnos y el rol del mes, y pide cambios de turno
-- que la coordinación aprueba. No administra nada. Ver modules/mi/.
--
-- persona_enlaces: un enlace vigente por persona. Regenerarlo cambia el token y
-- el anterior deja de servir. El token se guarda tal cual, no su hash, para que
-- la coordinación pueda volver a copiarlo y reenviarlo cuando alguien lo pierde
-- —como la «dirección secreta» de un calendario de Google—: lo que abre es
-- poco (consultar y pedir), y una copia de la base expone de todos modos datos
-- más delicados que este. `ultimo_uso_at` le dice a la coordinación si la
-- persona ya lo abrió.
--
-- persona_id en mesc_cambios / proclamadores_cambios: quién pidió el cambio
-- desde su enlace. Con el enlace no hay cuenta de por medio, así que
-- `usuario_id` queda vacío y esta columna dice de quién es la solicitud: a
-- quién avisarle de la respuesta y quién puede retirarla.
--
-- Aplicar con:
--     C:\xampp\mysql\bin\mysql.exe -uroot parroquia_nsdlp < docs/migraciones/2026-11-15-enlace-personal.sql
--
-- Va DESPUÉS de 2026-11-15-cambios-de-turno.sql. Crea una tabla y agrega una
-- columna vacía a dos tablas: no modifica ninguna fila existente.

CREATE TABLE IF NOT EXISTS persona_enlaces (
    persona_id    SMALLINT UNSIGNED NOT NULL,
    token         CHAR(32)          NOT NULL,
    creado_por    INT UNSIGNED      NULL,
    created_at    DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultimo_uso_at DATETIME          NULL,
    PRIMARY KEY (persona_id),
    UNIQUE KEY uq_penl_token (token),
    CONSTRAINT fk_penl_persona FOREIGN KEY (persona_id) REFERENCES personas(id) ON DELETE CASCADE,
    CONSTRAINT fk_penl_creador FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE mesc_cambios
    ADD COLUMN persona_id SMALLINT UNSIGNED NULL AFTER usuario_id,
    ADD CONSTRAINT fk_mca_persona FOREIGN KEY (persona_id) REFERENCES personas(id) ON DELETE SET NULL;

ALTER TABLE proclamadores_cambios
    ADD COLUMN persona_id SMALLINT UNSIGNED NULL AFTER usuario_id,
    ADD CONSTRAINT fk_pca_persona FOREIGN KEY (persona_id) REFERENCES personas(id) ON DELETE SET NULL;
