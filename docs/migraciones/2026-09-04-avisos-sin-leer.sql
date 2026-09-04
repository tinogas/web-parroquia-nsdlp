-- ============================================================
-- 2026-09-04 — Quién ha leído cada aviso
-- ============================================================
-- La campana de la barra ya contaba los mensajes de contacto sin abrir, y
-- ahora cuenta también los avisos. Para que ese contador baje al abrir uno
-- —y no vuelva a subir— hace falta guardar quién leyó qué, que es esta tabla.
--
-- Antes de esto lo único que había era la etiqueta «Nuevo» del panel, que se
-- decide contra `usuario_acceso_anterior`: sirve para no volver a señalar lo
-- que ya estaba ahí la última vez que entró, pero no es una lectura. Con ese
-- criterio, un contador en la campana se quedaría encendido toda la sesión
-- aunque la persona hubiera abierto los avisos uno por uno, y el globo rojo
-- significaría una cosa en los mensajes y otra en los avisos.
--
-- No lleva `id` propio: la fila ES el par (aviso, usuario) y no hay nada que
-- referenciarla. Con la clave primaria compuesta, marcar leído dos veces no
-- duplica nada —INSERT IGNORE— y no hace falta consultar antes de escribir.
--
-- `leido_at` guarda cuándo, que no lo usa ninguna pantalla todavía: se
-- escribe porque el dato solo existe en el momento de leerlo, y reconstruirlo
-- después es imposible.
--
-- Los dos ON DELETE CASCADE son a propósito: borrado el aviso o la cuenta, la
-- marca de lectura no significa nada.
--
-- Aplicar con:
--     C:\xampp\mysql\bin\mysql.exe -uroot parroquia_nsdlp < docs/migraciones/2026-09-04-avisos-sin-leer.sql
--
-- Esta sí es una migración inofensiva —solo crea una tabla nueva, no toca
-- ninguna existente—, pero la costumbre de respaldar antes no cambia.

CREATE TABLE IF NOT EXISTS aviso_lecturas (
    aviso_id   INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    leido_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (aviso_id, usuario_id),
    KEY idx_avl_usuario (usuario_id),
    CONSTRAINT fk_avl_aviso   FOREIGN KEY (aviso_id)   REFERENCES avisos(id)   ON DELETE CASCADE,
    CONSTRAINT fk_avl_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
