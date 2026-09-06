-- ============================================================
-- 2026-09-05 — Parejas de la parroquia, y la pareja que coordina
-- ============================================================
-- Un matrimonio es un matrimonio en toda la parroquia, no dentro de una
-- pastoral. Esta migración guarda esa liga una sola vez (`parejas`) y permite
-- que el responsable de una pastoral sea una pareja y no una sola persona
-- (`pastorales.responsable_pareja_id`).
--
-- ── De dónde viene esto ────────────────────────────────────────────────────
--
-- Primero se hizo por pastoral: una tabla `pastoral_parejas` donde Matrimonios
-- y AMA ligaban a su propia gente. Se corrigió el mismo día, antes de subirse a
-- ninguna parte, al aparecer el caso que no cabía: en JECSA y en Raíces
-- coordina un matrimonio, y esas dos no se organizan por parejas —no tendrían
-- dónde registrarlo—. Además el mismo matrimonio que está en Matrimonios y en
-- AMA había que capturarlo dos veces, y dos filas para un solo hecho acaban
-- diciendo cosas distintas. La liga subió entonces a la parroquia entera.
--
-- ── Dónde se captura ───────────────────────────────────────────────────────
--
-- En la ficha de la persona, en Equipo pastoral, junto a sus pastorales y sus
-- sedes: es un dato de la persona, como su teléfono, no de la pastoral. Por eso
-- las pastorales ya no tienen formulario para ligar a nadie; su panel solo
-- muestra con quién está cada integrante, y `pastorales.organiza_parejas` —la
-- casilla del borrador de la mañana— desaparece, porque ya no gobierna ninguna
-- pantalla.
--
-- ── Una persona, una pareja ────────────────────────────────────────────────
--
-- Las dos UNIQUE cubren el caso directo (alguien repetido en la misma columna).
-- Que además no esté en la otra columna de otra fila lo comprueba
-- `PersonaModel::parejaDe()` antes de guardar, porque eso no lo expresa ninguna
-- clave. Se guarda siempre el id menor en `persona_a_id`, así que "él con ella"
-- y "ella con él" no pueden ser dos filas distintas.
--
-- ── El responsable: o una persona, o una pareja ────────────────────────────
--
-- `responsable_persona_id` y `responsable_pareja_id` son excluyentes: el
-- formulario los ofrece en un solo selector y `PastoralController::guardar()`
-- limpia el otro. Con pareja, `responsable_nombre` es "Ella y Él", calculado de
-- las dos fichas y mantenido por `PersonaModel::sincronizarResponsable()`.
-- `contacto_email`, en cambio, deja de sincronizarse solo: con dos cuentas no
-- hay forma no arbitraria de elegir una, así que se escribe a mano —el
-- formulario lo dice—.
--
-- Aplicar con:
--     C:\xampp\mysql\bin\mysql.exe -uroot parroquia_nsdlp < docs/migraciones/2026-09-05-parejas-de-la-parroquia.sql
--
-- No modifica ninguna fila existente. Aun así, respaldar antes.

-- ── Solo para la base local que alcanzó a correr el borrador de la mañana ──
-- En una base que no lo corrió, este DROP no encuentra nada y no hace daño; la
-- columna, en cambio, hay que quitarla a mano solo si existe:
--     ALTER TABLE pastorales DROP COLUMN organiza_parejas;
DROP TABLE IF EXISTS pastoral_parejas;

CREATE TABLE IF NOT EXISTS parejas (
    id           SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    persona_a_id SMALLINT UNSIGNED NOT NULL,
    persona_b_id SMALLINT UNSIGNED NOT NULL,
    created_at   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_par_a (persona_a_id),
    UNIQUE KEY uq_par_b (persona_b_id),
    CONSTRAINT fk_par_a FOREIGN KEY (persona_a_id) REFERENCES personas(id) ON DELETE CASCADE,
    CONSTRAINT fk_par_b FOREIGN KEY (persona_b_id) REFERENCES personas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE pastorales
    ADD COLUMN responsable_pareja_id SMALLINT UNSIGNED NULL AFTER responsable_persona_id,
    ADD KEY idx_pas_resp_pareja (responsable_pareja_id),
    ADD CONSTRAINT fk_pas_resp_pareja FOREIGN KEY (responsable_pareja_id) REFERENCES parejas(id) ON DELETE SET NULL;
