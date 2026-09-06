-- ============================================================
-- 2026-09-05 — Quién con quién: parejas dentro de una pastoral
-- ============================================================
-- Matrimonios y AMA no trabajan con personas sueltas sino con parejas, y hasta
-- hoy el sistema solo sabía que Jorge y Zulema están los dos en la pastoral,
-- no que están el uno con el otro. Esto guarda esa liga.
--
-- ── Por qué una tabla y no una columna en persona_pastorales ───────────────
--
-- La tentación era `persona_pastorales.pareja_persona_id`, una columna que
-- apunta a la otra persona. Se descartó porque obliga a escribir dos filas
-- para un solo hecho —la de Jorge apuntando a Zulema y la de Zulema apuntando
-- a Jorge— y nada impide que una de las dos se quede sin actualizar: la base
-- acabaría diciendo que Jorge está con Zulema y Zulema con nadie. Aquí la
-- pareja es una fila y existe o no existe.
--
-- ── Por qué cuelga de la pastoral y no de la persona ───────────────────────
--
-- El mismo matrimonio puede estar en Matrimonios y no en AMA, y a la pastoral
-- de la Salud puede ir solo uno de los dos. La liga es de la pastoral, igual
-- que la pertenencia (`persona_pastorales`), no un dato de la ficha personal.
-- Por eso tampoco toca `personas`: quien deje la pastoral pierde su pareja
-- ahí y conserva su ficha intacta.
--
-- ── Emparejar no es obligatorio ────────────────────────────────────────────
--
-- Nadie tiene que estar en pareja. Quien no lo esté aparece en la lista como
-- cualquier otro integrante, sin marca de "le falta algo": en las dos
-- pastorales hay personas que participan solas y eso es normal, no un
-- registro a medias.
--
-- ── Qué guarda: solo el vínculo ────────────────────────────────────────────
--
-- Ni fecha de matrimonio ni notas. Se decidió el mínimo a propósito: es lo que
-- se pidió, y agregarle columnas después no obliga a rehacer nada de esto.
--
-- ── organiza_parejas: qué pastorales lo usan se marca, no se programa ──────
--
-- La casilla vive en el formulario de cada pastoral, junto a "Acepta
-- voluntarios". Se eligió así en vez de una constante con los slugs
-- ('matrimonios', 'ama') en config/app.php —que es como MODULO_POR_PASTORAL
-- resuelve otra cosa parecida— porque esto no necesita código detrás: el día
-- que la Pastoral Familiar quiera organizarse igual, lo activa quien la
-- coordina y no hace falta tocar el sistema. Abajo se deja activada en las dos
-- que la pidieron.
--
-- ── Una persona, una pareja por pastoral ───────────────────────────────────
--
-- Las dos UNIQUE de abajo cubren el caso directo (la misma persona repetida en
-- la misma columna). Lo que ninguna clave puede cubrir en MySQL es que alguien
-- esté en la columna A de una fila y en la B de otra, así que esa comprobación
-- la hace PastoralModel::personaEmparejada() antes de insertar, y el
-- controlador rechaza el intento con un mensaje. Además se guarda siempre el
-- id menor primero, para que "Jorge con Zulema" y "Zulema con Jorge" no puedan
-- ser dos filas distintas.
--
-- Aplicar con:
--     C:\xampp\mysql\bin\mysql.exe -uroot parroquia_nsdlp < docs/migraciones/2026-09-05-parejas-por-pastoral.sql
--
-- Agrega una columna con valor por omisión y una tabla nueva; no modifica
-- ninguna fila existente salvo el UPDATE final, que solo enciende la casilla
-- en Matrimonios y AMA. Aun así, respaldar antes.

ALTER TABLE pastorales
    ADD COLUMN organiza_parejas TINYINT(1) NOT NULL DEFAULT 0 AFTER acepta_voluntarios;

CREATE TABLE IF NOT EXISTS pastoral_parejas (
    id           SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pastoral_id  TINYINT UNSIGNED  NOT NULL,
    persona_a_id SMALLINT UNSIGNED NOT NULL,
    persona_b_id SMALLINT UNSIGNED NOT NULL,
    created_at   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ppj_a (pastoral_id, persona_a_id),
    UNIQUE KEY uq_ppj_b (pastoral_id, persona_b_id),
    CONSTRAINT fk_ppj_pastoral FOREIGN KEY (pastoral_id)  REFERENCES pastorales(id) ON DELETE CASCADE,
    CONSTRAINT fk_ppj_a        FOREIGN KEY (persona_a_id) REFERENCES personas(id)   ON DELETE CASCADE,
    CONSTRAINT fk_ppj_b        FOREIGN KEY (persona_b_id) REFERENCES personas(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Las dos que lo pidieron. Por slug y no por id: los ids son los de esta base
-- y no tienen por qué ser los mismos en otra instalación.
UPDATE pastorales SET organiza_parejas = 1 WHERE slug IN ('matrimonios', 'ama');
