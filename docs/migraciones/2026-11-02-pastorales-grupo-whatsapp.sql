-- ============================================================
-- 2026-11-02 — Enlace al grupo de WhatsApp de cada pastoral
-- ============================================================
-- Junto al cuadro "Mensaje para todos" de los listados (Catequesis, Coros,
-- MESC, Proclamadores y el panel básico de cada pastoral) aparece un botón
-- "Copiar y abrir grupo": copia el mensaje y abre el grupo de la pastoral.
-- WhatsApp no tiene enlace que abra un grupo con el texto ya escrito, así que
-- la persona solo pega y envía. El sitio no crea grupos ni manda nada.
--
-- Se guarda el enlace de invitación (https://chat.whatsapp.com/…), que se saca
-- en WhatsApp desde Info del grupo → Invitar mediante enlace. Es INTERNO:
-- quien lo tiene puede unirse al grupo, así que no se publica en el sitio.
--
-- Aplicar con:
--     C:\xampp\mysql\bin\mysql.exe -uroot parroquia_nsdlp < docs/migraciones/2026-11-02-pastorales-grupo-whatsapp.sql
--
-- OJO: sin esta columna, guardar la ficha de una pastoral falla. Aplicarla
-- antes de subir el código. No modifica ninguna fila (nace en NULL).

ALTER TABLE pastorales
    ADD COLUMN whatsapp_grupo VARCHAR(255) NULL AFTER contacto_telefono;
