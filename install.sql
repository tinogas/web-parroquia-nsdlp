-- ============================================================
-- Parroquia Nuestra Señora de la Paz
-- Script de instalación MySQL / MariaDB  (archivo único acumulativo)
-- ============================================================
--
-- INSTALACIÓN EN LOCAL (XAMPP):
--
--   1. En phpMyAdmin crea la base de datos:
--        CREATE DATABASE parroquia_nsdlp
--          CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--   2. Importa este archivo en esa base de datos
--   3. Copia config/database.example.php como config/database.php y ajústalo
--   4. Abre http://localhost/WebParroquia/setup.php y crea el administrador
--   5. Borra setup.php
--
-- INSTALACIÓN EN CPANEL: ver docs/DESPLIEGUE.md
--
-- NOTA: mientras el sitio no esté en producción no hay migraciones. Este
-- archivo se mantiene sincronizado con el esquema real y se reimporta desde
-- cero al cerrar cada etapa. Ver docs/BASE-DE-DATOS.md
--
-- ============================================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ------------------------------------------------------------
-- NÚCLEO Y SEGURIDAD
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS usuarios (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre        VARCHAR(120) NOT NULL,
    email         VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    rol           ENUM('admin','editor','coordinador','secretaria') NOT NULL DEFAULT 'coordinador',
    foto          VARCHAR(255) NULL,
    telefono      VARCHAR(20)  NULL,
    activo        TINYINT(1)   NOT NULL DEFAULT 1,
    ultimo_acceso DATETIME     NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usr_email (email),
    KEY idx_usr_rol (rol, activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bitácora de acciones. Registra escrituras y también CONSULTAS de datos
-- personales, que es lo que permite responder a una solicitud de acceso.
-- Ver docs/PRIVACIDAD.md
CREATE TABLE IF NOT EXISTS auditoria (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id  INT UNSIGNED    NULL,
    accion      VARCHAR(40)     NOT NULL,
    tabla_ref   VARCHAR(60)     NULL,
    registro_id INT UNSIGNED    NULL,
    ip          VARCHAR(45)     NULL,
    descripcion VARCHAR(255)    NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_aud_usuario (usuario_id),
    KEY idx_aud_fecha   (created_at),
    KEY idx_aud_tabla   (tabla_ref, registro_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos globales de la parroquia, en pares clave/valor. Las claves se siembran
-- aquí y el panel solo edita el valor: son las que las vistas esperan encontrar.
CREATE TABLE IF NOT EXISTS configuracion (
    id    SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    clave VARCHAR(60)       NOT NULL,
    valor TEXT              NULL,
    grupo VARCHAR(30)       NOT NULL DEFAULT 'general',
    PRIMARY KEY (id),
    UNIQUE KEY uq_cfg_clave (clave),
    KEY idx_cfg_grupo (grupo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- SEMILLAS DE CONFIGURACIÓN
-- ------------------------------------------------------------
-- INSERT IGNORE: al reimportar sobre una base con datos no se pisan los
-- valores ya capturados, y una clave nueva de una versión posterior aparece
-- sola sin necesidad de migrar.

INSERT IGNORE INTO configuracion (clave, valor, grupo) VALUES
    ('parroquia_nombre',         'Parroquia Nuestra Señora de la Paz', 'general'),
    ('parroquia_diocesis',       '',   'general'),
    ('logo',                     '',   'general'),
    ('favicon',                  '',   'general'),
    ('organigrama_imagen',       '',   'general'),

    ('direccion',                '',   'contacto'),
    ('ciudad',                   '',   'contacto'),
    ('cp',                       '',   'contacto'),
    ('telefono',                 '',   'contacto'),
    ('whatsapp',                 '',   'contacto'),
    ('email',                    '',   'contacto'),
    ('horario_oficina',          '',   'contacto'),
    ('mapa_embed',               '',   'contacto'),
    ('latitud',                  '',   'contacto'),
    ('longitud',                 '',   'contacto'),

    ('facebook',                 '',   'redes'),
    ('instagram',                '',   'redes'),
    ('youtube',                  '',   'redes'),

    ('meta_descripcion',         '',   'seo'),
    ('og_imagen',                '',   'seo'),

    ('aviso_privacidad_version', '1.0', 'legal'),
    ('retencion_meses_solicitudes', '36', 'legal');

SET foreign_key_checks = 1;
