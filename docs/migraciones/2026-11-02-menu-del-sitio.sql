-- ============================================================
-- 2026-11-02 — Menú del sitio administrable
-- ============================================================
-- Hasta ahora el menú público estaba escrito en el código
-- (shared/views/parciales/publico_navbar.php): las secciones fijas en un orden
-- fijo, con su nombre fijo, y detrás las páginas con «Mostrar en el menú».
-- Desde el panel solo se podía ocultar Cursos.
--
-- La tabla nueva guarda un renglón por cada entrada del menú —una sección fija
-- o una página libre— con su orden, si se muestra y, opcionalmente, un nombre
-- distinto del de siempre. La pantalla es Contenido → Menú del sitio.
--
-- Inicio y Contacto no están aquí a propósito: son fijos, el primero y el
-- botón dorado del final.
--
-- Aplicar con:
--     C:\xampp\mysql\bin\mysql.exe -uroot parroquia_nsdlp < docs/migraciones/2026-11-02-menu-del-sitio.sql
--
-- Mientras no se aplique, el sitio sigue funcionando: el menú cae al orden de
-- siempre (ver menu_del_sitio() en core/helpers.php). Respaldar antes de todos
-- modos.

CREATE TABLE IF NOT EXISTS menu_sitio (
    id         SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    -- Una de las dos: la sección fija (el módulo público) o la página libre.
    clave      VARCHAR(30)       NULL,
    pagina_id  SMALLINT UNSIGNED NULL,
    etiqueta   VARCHAR(60)       NULL,      -- NULL = el nombre de siempre
    visible    TINYINT(1)        NOT NULL DEFAULT 1,
    orden      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME          NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_menu_clave (clave),
    UNIQUE KEY uq_menu_pagina (pagina_id),
    KEY idx_menu_orden (orden),
    CONSTRAINT fk_menu_pagina FOREIGN KEY (pagina_id) REFERENCES paginas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Las secciones fijas, en el orden que ya tenían.
INSERT IGNORE INTO menu_sitio (clave, orden) VALUES
    ('nosotros',    10),
    ('horarios',    20),
    ('sacramentos', 30),
    ('pastorales',  40),
    ('cursos',      50),
    ('avisos',      60),
    ('eventos',     70),
    ('galeria',     80);

-- Las páginas que ya estaban en el menú, detrás, en su orden de antes.
INSERT IGNORE INTO menu_sitio (pagina_id, visible, orden)
SELECT id, 1, 100 + orden FROM paginas WHERE en_menu = 1;
