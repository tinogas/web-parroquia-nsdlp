-- ============================================================
-- 2026-09-13 — Directorio telefónico del equipo pastoral
-- ============================================================
-- Casilla nueva en la ficha de cada persona («Incluir en el directorio
-- telefónico») y una vista de impresión aparte, dentro del panel, que solo
-- lista a quien la tenga marcada — pensada para altos mandos y cargos
-- importantes del organigrama, no para todo el equipo pastoral.
--
-- Se guarda como casilla propia y no "todo activo" ni "todo el que está en el
-- organigrama": no todo el equipo activo debe salir en un directorio para
-- imprimir (expondría el teléfono de gente que no hace falta), y no todo
-- puesto importante tiene necesariamente un nodo en el organigrama. Quien
-- arma el directorio decide, ficha por ficha, igual que decide quién aparece
-- en "Quiénes somos" con el interruptor de activo.
--
-- El teléfono nunca se publica en el sitio (docs/PRIVACIDAD.md), así que esta
-- vista de impresión vive en el panel (PersonaController::directorioImprimir,
-- permiso personas.ver) y no en el sitio público, a diferencia del organigrama.
--
-- Aplicar con:
--     C:\xampp\mysql\bin\mysql.exe -uroot parroquia_nsdlp < docs/migraciones/2026-09-13-directorio-telefonico.sql
--
-- No modifica ninguna fila existente (la casilla nace en 0 para todos). Aun
-- así, respaldar antes.

ALTER TABLE personas
    ADD COLUMN en_directorio TINYINT(1) NOT NULL DEFAULT 0 AFTER activo;
