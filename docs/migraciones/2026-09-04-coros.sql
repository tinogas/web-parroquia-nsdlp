-- ============================================================
-- 2026-09-04 — Los coros de la parroquia, uno por misa dominical
-- ============================================================
-- Quinta migración del día, y la del cuarto módulo de pastoral dedicada
-- (después de MESC, Catequesis y Proclamadores). La pastoral "Coros" ya existe
-- —slug 'coros', hija de la Comisión Litúrgica— pero no tenía dónde operar:
-- su único registro era una persona marcada en `persona_pastorales`, y ni el
-- panel básico de la pastoral ni el checklist de la ficha de `personas` pueden
-- guardar a qué misa canta cada quien ni quién manda en cada coro.
--
-- Tres tablas, y el orden de creación importa: `coros` referencia a `coristas`
-- para su encargado, así que aquella va primero. No hay ciclo — `coristas` no
-- apunta a `coros`; quien junta las dos es el pivote, que va al final.
--
-- ── Por qué aquí SÍ hay una FK a `horarios`, y en los turnos no ────────────
--
-- `mesc_turnos` y `proclamadores_turnos` evitan a propósito cualquier FK a
-- `horarios`, y con razón: un turno cubre una OCURRENCIA concreta ("el domingo
-- 3 de agosto") mientras que `horarios` es RECURRENCIA semanal ("los domingos
-- a las 12:00"), así que atarlo ahí no resolvería la fecha.
--
-- Un coro es justamente lo contrario: es la recurrencia misma. El coro de las
-- 12:00 canta todos los domingos, y "la misa de las 12:00 del domingo" es
-- exactamente la fila de `horarios` que ya existe. La FK es correcta aquí por
-- el mismo motivo por el que allí no lo era.
--
-- De ahí también que el coro no tenga nombre propio: se nombra con su misa, y
-- `uq_cor_horario` impide que dos coros se disputen el mismo horario.
--
-- ── Un integrante puede cantar en varias misas ─────────────────────────────
--
-- `coro_coristas` es un pivote N-M y no una columna `coro_id` en `coristas`
-- porque quien canta en la de 9 suele cantar también en la de 12. El encargado,
-- en cambio, es UNO por coro, y por eso vive como columna en `coros` y no como
-- un `es_encargado` en el pivote: una columna no puede tener dos valores, un
-- flag repetido sí. Que ese corista pertenezca además a ese coro no lo puede
-- exigir una FK; lo valida CoroController::coroGuardar(), y
-- CoroModel::sincronizarCorosDeCorista() lo deja en NULL si al integrante se
-- le quita del coro que encabezaba.
--
-- Sin semillas: los coros no se crean aquí. La portada del módulo lista las
-- misas dominicales y ofrece "Crear coro" en las que aún no lo tienen, que se
-- explica solo y deja la decisión en la pastoral.
--
-- Aplicar con:
--     C:\xampp\mysql\bin\mysql.exe -uroot parroquia_nsdlp < docs/migraciones/2026-09-04-coros.sql
--
-- Es una migración inofensiva —solo crea tablas nuevas, no toca ninguna
-- existente—, pero la costumbre de respaldar antes no cambia.

-- ── 1. Quiénes cantan ───────────────────────────────────────────────────────
-- Calca `proclamadores`: se elige del equipo pastoral, y los campos de texto
-- libre son el respaldo para quien todavía no tiene ficha. `persona_id` es
-- UNIQUE dentro de esta tabla y solo dentro de ella — la misma persona puede
-- ser a la vez corista y ministra de MESC, como ya ocurre entre los otros
-- catálogos.
CREATE TABLE IF NOT EXISTS coristas (
    id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pastoral_id TINYINT UNSIGNED  NOT NULL,
    persona_id  SMALLINT UNSIGNED NULL,
    nombre      VARCHAR(140)      NOT NULL,
    telefono    VARCHAR(20)       NULL,
    email       VARCHAR(150)      NULL,
    orden       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    activo      TINYINT(1)        NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_cri_pastoral (pastoral_id),
    UNIQUE KEY uq_cri_persona (persona_id),
    CONSTRAINT fk_cri_pastoral FOREIGN KEY (pastoral_id) REFERENCES pastorales(id) ON DELETE CASCADE,
    CONSTRAINT fk_cri_persona  FOREIGN KEY (persona_id)  REFERENCES personas(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2. Los coros ────────────────────────────────────────────────────────────
-- `horario_id` es NOT NULL y CASCADE, no NULL con SET NULL: el horario no es un
-- atributo del coro sino su identidad entera, y un coro sin misa no significa
-- nada. Borrar la misa de las 12:00 se lleva su coro y las asignaciones, nunca
-- a los coristas, que siguen cantando en las otras.
--
-- `encargado_id` es SET NULL: dar de baja a quien encabezaba el coro deja al
-- coro sin encargado, no lo borra.
CREATE TABLE IF NOT EXISTS coros (
    id           SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pastoral_id  TINYINT UNSIGNED  NOT NULL,
    horario_id   TINYINT UNSIGNED  NOT NULL,
    encargado_id SMALLINT UNSIGNED NULL,
    nota         VARCHAR(160)      NULL,
    activo       TINYINT(1)        NOT NULL DEFAULT 1,
    created_at   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cor_horario (horario_id),
    KEY idx_cor_pastoral (pastoral_id),
    CONSTRAINT fk_cor_pastoral  FOREIGN KEY (pastoral_id)  REFERENCES pastorales(id) ON DELETE CASCADE,
    CONSTRAINT fk_cor_horario   FOREIGN KEY (horario_id)   REFERENCES horarios(id)   ON DELETE CASCADE,
    CONSTRAINT fk_cor_encargado FOREIGN KEY (encargado_id) REFERENCES coristas(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3. Quién canta en qué coro ──────────────────────────────────────────────
-- Calca `proclamadores_turno_proclamadores`: llave compuesta y nada más, sin id
-- propio ni columnas de la relación.
CREATE TABLE IF NOT EXISTS coro_coristas (
    coro_id    SMALLINT UNSIGNED NOT NULL,
    corista_id SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (coro_id, corista_id),
    KEY idx_cco_corista (corista_id),
    CONSTRAINT fk_cco_coro    FOREIGN KEY (coro_id)    REFERENCES coros(id)    ON DELETE CASCADE,
    CONSTRAINT fk_cco_corista FOREIGN KEY (corista_id) REFERENCES coristas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
