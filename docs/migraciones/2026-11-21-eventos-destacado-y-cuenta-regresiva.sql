-- ============================================================
-- 2026-11-21 — Eventos destacados y con cuenta regresiva en la portada
-- ============================================================
-- Dos casillas nuevas en el evento, que solo marcan el administrador y el
-- editor (los roles con alcance global):
--
--   destacado         El evento lleva estrella dorada en "Lo que viene en la
--                     parroquia" y aparece ahí desde 60 días antes, aunque
--                     no esté entre los próximos nueve.
--   cuenta_regresiva  El evento tiene franja propia en la portada con los
--                     días que faltan, como la fiesta patronal. La más
--                     próxima sale desde 60 días antes; si hay otras, salen
--                     también cuando les faltan 30 días o menos.
--
-- La fiesta patronal sigue reconociéndose sola por el título (ver
-- EventoModel::fiestaPatronalProxima()); no hace falta marcarla.
--
-- Aplicar con:
--     C:\xampp\mysql\bin\mysql.exe -uroot parroquia_nsdlp < docs/migraciones/2026-11-21-eventos-destacado-y-cuenta-regresiva.sql
--
-- No modifica ninguna fila existente (las dos casillas nacen en 0). Aun así,
-- respaldar antes. No se puede repetir: la segunda vez falla en el ALTER
-- porque las columnas ya existen, sin tocar nada.

ALTER TABLE eventos
    ADD COLUMN destacado        TINYINT(1) NOT NULL DEFAULT 0 AFTER publicado,
    ADD COLUMN cuenta_regresiva TINYINT(1) NOT NULL DEFAULT 0 AFTER destacado;
