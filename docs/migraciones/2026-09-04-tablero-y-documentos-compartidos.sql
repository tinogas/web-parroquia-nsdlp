-- ============================================================
-- 2026-09-04 — Un tablero y unos documentos por pastoral, no por módulo
-- ============================================================
-- Segunda migración del día, y la que evita que el problema se multiplique:
-- Proclamadores necesitaba sus pantallas de Actividades y Documentos, y
-- copiarlas de Catequesis significaba copiar también sus dos tablas. Con la
-- plantilla de módulos en camino, cada módulo nuevo habría arrastrado dos
-- tablas más de la misma forma.
--
-- Qué cambia:
--   1. `catequesis_documentos` desaparece: era columna por columna idéntica a
--      `pastoral_documentos`, que ya existía y que el panel básico de
--      cualquier pastoral usa. Eran dos listas de lo mismo que no se veían
--      entre sí: un documento subido desde el panel básico de Catequesis no
--      aparecía en su módulo, y al revés. Sus filas se mudan a la genérica.
--   2. `catequesis_actividades` → `pastoral_tablero`. Aquí sí eran dos cosas
--      distintas y las dos se llamaban "actividades": `pastoral_actividades`
--      es la lista fija de qué hace la pastoral (con `tipo`, sin fechas) y
--      esta otra es un tablero con vigencia y publicación, como un
--      mini-`eventos`. El nombre nuevo dice cuál es cuál, y al no llevar
--      prefijo de módulo sirve a cualquier pastoral que lo necesite.
--
-- Ninguna de las dos gana ni pierde columnas: solo cambian de nombre y de
-- dueño. `pastoral_documentos` y `pastoral_actividades` no se tocan.
--
-- Aplicar con:
--     C:\xampp\mysql\bin\mysql.exe -uroot parroquia_nsdlp < docs/migraciones/2026-09-04-tablero-y-documentos-compartidos.sql
--
-- RESPALDAR ANTES: MariaDB no revierte DDL. Ver la nota de la migración
-- hermana, 2026-09-04-proclamadores.sql.

-- ── 1. El tablero, sin prefijo de módulo ────────────────────────────────────
-- Se crea y se copia en vez de renombrarse, por lo mismo que en la migración
-- hermana: MariaDB 10.4 no admite `ALTER TABLE … RENAME INDEX`, y renombrando
-- se quedarían los índices con el nombre viejo.
CREATE TABLE pastoral_tablero (
    id           SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pastoral_id  TINYINT UNSIGNED  NOT NULL,
    titulo       VARCHAR(160)      NOT NULL,
    descripcion  TEXT              NULL,
    fecha_inicio DATE              NOT NULL,
    fecha_fin    DATE              NULL,
    publicado    TINYINT(1)        NOT NULL DEFAULT 0,
    orden        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    usuario_id   INT UNSIGNED      NULL,
    created_at   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ptb_pastoral (pastoral_id),
    KEY idx_ptb_publicado (publicado, fecha_inicio),
    CONSTRAINT fk_ptb_pastoral FOREIGN KEY (pastoral_id) REFERENCES pastorales(id) ON DELETE CASCADE,
    CONSTRAINT fk_ptb_usuario  FOREIGN KEY (usuario_id)  REFERENCES usuarios(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Con los id originales, para que el AUTO_INCREMENT continúe donde iba.
INSERT INTO pastoral_tablero
    (id, pastoral_id, titulo, descripcion, fecha_inicio, fecha_fin, publicado, orden, usuario_id, created_at)
SELECT id, pastoral_id, titulo, descripcion, fecha_inicio, fecha_fin, publicado, orden, usuario_id, created_at
  FROM catequesis_actividades;

DROP TABLE catequesis_actividades;

-- ── 2. Los documentos se juntan en la tabla genérica ────────────────────────
-- SIN los id: `pastoral_documentos` ya tiene filas propias y los suyos
-- chocarían. Nadie apunta a estos id con una clave foránea, y `archivo`
-- —la ruta bajo uploads/— viaja igual, así que los PDF ya subidos siguen
-- descargándose desde donde están.
INSERT INTO pastoral_documentos (pastoral_id, titulo, archivo, orden, activo, usuario_id, created_at)
SELECT pastoral_id, titulo, archivo, orden, activo, usuario_id, created_at
  FROM catequesis_documentos;

DROP TABLE catequesis_documentos;
