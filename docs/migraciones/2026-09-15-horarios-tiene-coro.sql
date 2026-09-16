-- ============================================================
-- 2026-09-15 — Qué misas llevan coro
-- ============================================================
-- Casilla nueva en el horario ("Tiene coro"), para que el módulo de Coros
-- pueda asignarles personal y encargado a esas misas en concreto. Antes se
-- escribía "Con coro" a mano en `nota` (texto libre): no había forma de
-- filtrar por eso ni de ligarlo a nada, solo de leerlo.
--
-- Aplicar con:
--     C:\xampp\mysql\bin\mysql.exe -uroot parroquia_nsdlp < docs/migraciones/2026-09-15-horarios-tiene-coro.sql
--
-- No modifica ninguna fila existente (la casilla nace en 0 para todas). Aun
-- así, respaldar antes. Activar las misas dominicales actuales es un paso
-- aparte, con UPDATE, no parte de esta migración.

ALTER TABLE horarios
    ADD COLUMN tiene_coro TINYINT(1) NOT NULL DEFAULT 0 AFTER activo;
