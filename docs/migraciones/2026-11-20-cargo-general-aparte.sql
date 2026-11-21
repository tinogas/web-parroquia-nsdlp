-- ============================================================
-- 2026-11-20 — El cargo general, aparte; el que se muestra sale solo
-- ============================================================
-- Desde la migración del 16 (cargo-por-pastoral) cada quien puede tener un
-- cargo en cada pastoral, y el cargo de la ficha quedó como «cargo general».
-- Con eso el general empezó a sobrar: quien ya tiene su cargo en sus
-- pastorales lo repetía arriba («Coordinadora» arriba y «Coordinadora en
-- Catequesis» abajo), y si se dejaba vacío, esa persona salía sin cargo en las
-- listas para elegir personas, en el panel y en el directorio.
--
-- ── Qué cambia ─────────────────────────────────────────────────────────────
--
-- `personas.cargo_general` (nueva) guarda lo que se escribe a mano en la ficha,
-- en «Cargo general». Es opcional.
--
-- `personas.cargo` pasa a ser el cargo que SE MUESTRA, y ya no se escribe a
-- mano: es el general si lo hay y, si no, el más alto de los que la persona
-- tiene en sus pastorales —primero los de coordinación general, luego los de
-- coordinación, luego el resto; a igual rango, por nombre de la pastoral—. El
-- código lo recalcula cada vez que cambia la ficha o un cargo por pastoral
-- (PersonaModel::recalcularCargo()). Se guarda en vez de calcularse al leer
-- porque lo leen muchas pantallas —las listas para elegir personas, el
-- organigrama, las cuentas, el directorio— y así ninguna tiene que saberlo.
--
-- El rango es el de PersonaModel::rangoCargo(), escrito aquí sin barras
-- invertidas para que valga igual en cualquier servidor: (*UCP) hace que
-- [[:alpha:]] acepte letras con acento, como \pL en PHP.
--
-- Aplicar con:
--     C:\xampp\mysql\bin\mysql.exe -uroot parroquia_nsdlp < docs/migraciones/2026-11-20-cargo-general-aparte.sql
--
-- Va JUNTO con el código que la usa: el código anterior escribe el cargo de la
-- ficha en `cargo` y no sabe de `cargo_general`, así que una ficha guardada
-- entre la migración y el código nuevo quedaría con el general viejo. No se
-- puede repetir: la segunda vez falla en el ALTER porque la columna ya existe,
-- sin tocar nada.

ALTER TABLE personas
    ADD COLUMN cargo_general VARCHAR(100) NULL AFTER cargo;

-- Lo que hoy dice la ficha es el general que alguien escribió.
UPDATE personas SET cargo_general = NULLIF(TRIM(cargo), '');

-- Quien no tiene general pero sí cargos en sus pastorales: el más alto.
UPDATE personas p
   SET p.cargo = (
       SELECT TRIM(pp.cargo)
         FROM persona_pastorales pp
         JOIN pastorales pa ON pa.id = pp.pastoral_id
        WHERE pp.persona_id = p.id
          AND COALESCE(TRIM(pp.cargo), '') <> ''
        ORDER BY CASE
                     WHEN LOWER(pp.cargo) REGEXP '(*UCP)(^|[^[:alpha:]])co[[:alpha:]]*rd[[:alpha:]]*(dor|aci)[[:alpha:]]*[[:space:]]+general' THEN 0
                     WHEN LOWER(pp.cargo) REGEXP '(*UCP)(^|[^[:alpha:]])co[[:alpha:]]*rd[[:alpha:]]*(dor|aci)' THEN 1
                     ELSE 2
                 END,
                 pa.nombre
        LIMIT 1)
 WHERE p.cargo_general IS NULL;
