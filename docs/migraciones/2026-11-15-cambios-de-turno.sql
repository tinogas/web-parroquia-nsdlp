-- ============================================================
-- 2026-11-15 — Cambios de turno: permutas, coberturas y refuerzos
-- ============================================================
-- El rol de MESC y de Proclamadores se arma cada mes, pero después los
-- propios ministros se acomodan entre ellos —«yo te cubro el domingo 5 y tú
-- a mí el 15»— y el cambio se cumple sin quedar escrito en ningún lado. El
-- calendario seguía diciendo quién estaba en el rol original, no quién sirvió.
--
-- Estas tablas registran esos cambios SIN tocar el rol original: el pivote de
-- siempre (mesc_turno_ministros / proclamadores_turno_proclamadores) sigue
-- siendo «quién estaba asignado», y lo que realmente pasó se calcula aplicando
-- encima, en orden, los movimientos registrados aquí. Así la consulta muestra
-- las dos cosas: quién sirve y quién estaba originalmente.
--
-- Un cambio es por persona, no por pareja, y es de uno de tres tipos:
--   permuta     de grupo a grupo: los marcados del turno A pasan al turno B y
--               los marcados del B pasan al A —uno por uno, dos por dos, o los
--               turnos completos—. Un movimiento por persona en cada turno.
--               Los turnos pueden ser de la misma fecha y hora o de otra.
--   sustitucion un movimiento: en el turno A sale X y entra Y, sin devolución.
--   agregado    un movimiento: en el turno A entra Y y no sale nadie.
--
-- `sale_id` / `entra_id` apuntan a mesc_ministros (o a proclamadores): se
-- llaman igual en los dos módulos porque los dos los atiende el mismo código
-- (core/CambiosTurno.php). Cualquiera de los dos puede ir vacío —nunca los
-- dos—: sin `sale_id` es un refuerzo, y sin `entra_id` es el sobrante de una
-- permuta dispar (dos de un turno por uno del otro).
--
-- Solicitudes: un laico (rol Consulta) no registra el cambio, lo SOLICITA.
-- Su fila nace con `estado` = 'pendiente' y no cuenta para el calendario hasta
-- que una coordinación de la pastoral la aprueba ('aprobado') o la rechaza
-- ('rechazado', con `motivo_rechazo` opcional). `resuelto_por`/`resuelto_at`
-- dicen quién y cuándo; con `resuelto_por` vacío y estado aprobado, el cambio
-- lo registró la coordinación directamente. `visto_solicitante` = 0 mantiene
-- la respuesta en la campanita del laico hasta que la abre.
--
-- Nada se borra: es la bitácora de movimientos. Deshacer un cambio aplicado lo
-- deja en 'deshecho' (y, si venía de una solicitud, se le avisa a quien la
-- pidió); que el laico retire su solicitud pendiente la deja en 'retirado'.
-- `cancelado_por`/`cancelado_at`/`motivo_cancelacion` dicen quién, cuándo y
-- por qué. Solo 'aprobado' cuenta para el calendario. Y nada de esto se hace
-- sobre turnos que ya pasaron: ni registrar, ni aprobar, ni deshacer, ni
-- retirar (lo hace cumplir core/CambiosTurnoAcciones.php).
--
-- Aplicar con:
--     C:\xampp\mysql\bin\mysql.exe -uroot parroquia_nsdlp < docs/migraciones/2026-11-15-cambios-de-turno.sql
--
-- Solo crea tablas nuevas: no modifica ni borra nada existente.

CREATE TABLE IF NOT EXISTS mesc_cambios (
    id          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    pastoral_id TINYINT UNSIGNED NOT NULL,
    tipo        ENUM('permuta', 'sustitucion', 'agregado') NOT NULL,
    notas       VARCHAR(255)     NULL,
    usuario_id  INT UNSIGNED     NULL,
    estado      ENUM('pendiente', 'aprobado', 'rechazado', 'deshecho', 'retirado') NOT NULL DEFAULT 'aprobado',
    resuelto_por INT UNSIGNED    NULL,
    resuelto_at DATETIME         NULL,
    motivo_rechazo VARCHAR(255)  NULL,
    cancelado_por INT UNSIGNED   NULL,
    cancelado_at DATETIME        NULL,
    motivo_cancelacion VARCHAR(255) NULL,
    visto_solicitante TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_mca_pastoral (pastoral_id, estado),
    CONSTRAINT fk_mca_pastoral FOREIGN KEY (pastoral_id)  REFERENCES pastorales(id) ON DELETE CASCADE,
    CONSTRAINT fk_mca_usuario  FOREIGN KEY (usuario_id)   REFERENCES usuarios(id)   ON DELETE SET NULL,
    CONSTRAINT fk_mca_resolvio FOREIGN KEY (resuelto_por) REFERENCES usuarios(id)   ON DELETE SET NULL,
    CONSTRAINT fk_mca_cancelo  FOREIGN KEY (cancelado_por) REFERENCES usuarios(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mesc_cambio_movimientos (
    id        INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    cambio_id INT UNSIGNED      NOT NULL,
    turno_id  INT UNSIGNED      NOT NULL,
    sale_id   SMALLINT UNSIGNED NULL,
    entra_id  SMALLINT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_mcm_turno (turno_id),
    CONSTRAINT fk_mcm_cambio FOREIGN KEY (cambio_id) REFERENCES mesc_cambios(id)   ON DELETE CASCADE,
    CONSTRAINT fk_mcm_turno  FOREIGN KEY (turno_id)  REFERENCES mesc_turnos(id)    ON DELETE CASCADE,
    CONSTRAINT fk_mcm_sale   FOREIGN KEY (sale_id)   REFERENCES mesc_ministros(id) ON DELETE CASCADE,
    CONSTRAINT fk_mcm_entra  FOREIGN KEY (entra_id)  REFERENCES mesc_ministros(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proclamadores_cambios (
    id          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    pastoral_id TINYINT UNSIGNED NOT NULL,
    tipo        ENUM('permuta', 'sustitucion', 'agregado') NOT NULL,
    notas       VARCHAR(255)     NULL,
    usuario_id  INT UNSIGNED     NULL,
    estado      ENUM('pendiente', 'aprobado', 'rechazado', 'deshecho', 'retirado') NOT NULL DEFAULT 'aprobado',
    resuelto_por INT UNSIGNED    NULL,
    resuelto_at DATETIME         NULL,
    motivo_rechazo VARCHAR(255)  NULL,
    cancelado_por INT UNSIGNED   NULL,
    cancelado_at DATETIME        NULL,
    motivo_cancelacion VARCHAR(255) NULL,
    visto_solicitante TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pca_pastoral (pastoral_id, estado),
    CONSTRAINT fk_pca_pastoral FOREIGN KEY (pastoral_id)  REFERENCES pastorales(id) ON DELETE CASCADE,
    CONSTRAINT fk_pca_usuario  FOREIGN KEY (usuario_id)   REFERENCES usuarios(id)   ON DELETE SET NULL,
    CONSTRAINT fk_pca_resolvio FOREIGN KEY (resuelto_por) REFERENCES usuarios(id)   ON DELETE SET NULL,
    CONSTRAINT fk_pca_cancelo  FOREIGN KEY (cancelado_por) REFERENCES usuarios(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proclamadores_cambio_movimientos (
    id        INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    cambio_id INT UNSIGNED      NOT NULL,
    turno_id  INT UNSIGNED      NOT NULL,
    sale_id   SMALLINT UNSIGNED NULL,
    entra_id  SMALLINT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_pcm_turno (turno_id),
    CONSTRAINT fk_pcm_cambio FOREIGN KEY (cambio_id) REFERENCES proclamadores_cambios(id) ON DELETE CASCADE,
    CONSTRAINT fk_pcm_turno  FOREIGN KEY (turno_id)  REFERENCES proclamadores_turnos(id)  ON DELETE CASCADE,
    CONSTRAINT fk_pcm_sale   FOREIGN KEY (sale_id)   REFERENCES proclamadores(id)         ON DELETE CASCADE,
    CONSTRAINT fk_pcm_entra  FOREIGN KEY (entra_id)  REFERENCES proclamadores(id)         ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
