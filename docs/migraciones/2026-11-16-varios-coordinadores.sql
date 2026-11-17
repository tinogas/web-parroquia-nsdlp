-- ============================================================
-- 2026-11-16 — De uno a varios coordinadores por pastoral
-- ============================================================
-- Hasta ahora una pastoral tenía de responsable una persona o una pareja:
-- dos columnas excluyentes en `pastorales` (`responsable_persona_id`,
-- `responsable_pareja_id`). Eso dejaba fuera un caso real: en Raíces van a
-- coordinar Betesaida y Dante, que no son matrimonio. Registrarlos como
-- pareja para que cupieran en el selector habría sido inventar una pareja
-- que no existe, y ese dato vale en toda la parroquia —sale en el panel de
-- cada pastoral, junto a cada integrante—.
--
-- ── Qué cambia ─────────────────────────────────────────────────────────────
--
-- Quién coordina pasa a una tabla propia, `pastoral_coordinadores`: una fila
-- por persona, con su orden, sin límite. Un matrimonio que coordina son sus
-- dos fichas, igual que dos personas cualesquiera; que sean pareja se sigue
-- sabiendo por `parejas`, y de ahí sale el corazón del panel: se dibuja solo
-- cuando coordinan exactamente dos personas y son pareja entre sí. La pareja
-- deja de ser una forma de coordinar y vuelve a ser solo un dato de las
-- personas.
--
-- `responsable_nombre` se queda, pero cambia de papel: ya no es la copia del
-- nombre de la ficha —el nombre se calcula al leer, de las fichas, y así no
-- hay copia que se quede vieja ni nada que sincronizar—, sino solo el nombre
-- escrito a mano de quien coordina sin tener ficha todavía. Se suma al final
-- de los elegidos ("Betesaida y Dante", "Betesaida, Dante y Jesús Ríos").
--
-- ── Qué hace este archivo ──────────────────────────────────────────────────
--
-- 1. Crea `pastoral_coordinadores`.
-- 2. Copia ahí a cada responsable actual: la persona, o las dos personas de la
--    pareja, en el mismo orden en que se escribían ("Ella y Él").
-- 3. Vacía `responsable_nombre` donde era una copia de la ficha —donde había
--    persona o pareja elegida—; si no, saldría repetido. Donde se escribió a
--    mano se queda igual.
-- 4. Quita las dos columnas viejas con sus llaves foráneas.
--
-- Aplicar con:
--     C:\xampp\mysql\bin\mysql.exe -uroot parroquia_nsdlp < docs/migraciones/2026-11-16-varios-coordinadores.sql
--
-- No se puede repetir una vez completa, pero repetirla no hace daño: falla en
-- el paso 2, porque las columnas viejas ya no existen, antes de tocar nada.
-- Respaldar antes de aplicarla: el paso 4 no tiene vuelta atrás.

CREATE TABLE IF NOT EXISTS pastoral_coordinadores (
    pastoral_id TINYINT UNSIGNED  NOT NULL,
    persona_id  SMALLINT UNSIGNED NOT NULL,
    orden       TINYINT UNSIGNED  NOT NULL DEFAULT 0,
    PRIMARY KEY (pastoral_id, persona_id),
    KEY idx_pco_persona (persona_id),
    CONSTRAINT fk_pco_pastoral FOREIGN KEY (pastoral_id) REFERENCES pastorales(id) ON DELETE CASCADE,
    CONSTRAINT fk_pco_persona  FOREIGN KEY (persona_id)  REFERENCES personas(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO pastoral_coordinadores (pastoral_id, persona_id, orden)
SELECT id, responsable_persona_id, 0
  FROM pastorales
 WHERE responsable_persona_id IS NOT NULL;

-- `persona_a_id` es siempre el id menor, y "a y b" era el orden en que se
-- escribía el nombre de la pareja (PersonaModel::parejaPorId()).
INSERT IGNORE INTO pastoral_coordinadores (pastoral_id, persona_id, orden)
SELECT p.id, j.persona_a_id, 0
  FROM pastorales p
  JOIN parejas j ON j.id = p.responsable_pareja_id;

INSERT IGNORE INTO pastoral_coordinadores (pastoral_id, persona_id, orden)
SELECT p.id, j.persona_b_id, 1
  FROM pastorales p
  JOIN parejas j ON j.id = p.responsable_pareja_id;

UPDATE pastorales
   SET responsable_nombre = NULL
 WHERE responsable_persona_id IS NOT NULL OR responsable_pareja_id IS NOT NULL;

-- Primero las llaves foráneas, en su propia sentencia: MySQL no deja quitar
-- en la misma el índice que una de ellas está usando. Al quitar cada columna
-- se va también su índice (idx_pas_responsable, idx_pas_resp_pareja).
ALTER TABLE pastorales
    DROP FOREIGN KEY fk_pas_responsable,
    DROP FOREIGN KEY fk_pas_resp_pareja;

ALTER TABLE pastorales
    DROP COLUMN responsable_persona_id,
    DROP COLUMN responsable_pareja_id;
