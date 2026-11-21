# Pendientes para el servidor

Lo que hay que hacer en el servidor en línea para que funcione lo que ya está en `main`.
Este archivo se actualiza junto con el código: **cada migración nueva se agrega aquí**, en la
tabla del paso 2 y en la consulta del paso 1. Lo que ya se aplicó se anota al final, en el
registro.

> **Revisado el 21 de noviembre de 2026**, con `main` en `cfdace2`. Se comprobó así: el
> `install.sql` de agosto más las 16 migraciones de entonces, aplicadas en este orden sobre
> una base desechable, dan exactamente el mismo esquema que la base local (tablas, columnas,
> índices y llaves foráneas). La 17 se agregó ese mismo día después: solo suma dos columnas
> a `eventos`.

## Antes de empezar

- [ ] **Respaldo de la base del servidor**, descargado a tu equipo: desde el panel
      (Administración → Respaldos) o desde phpMyAdmin (Exportar).
- [ ] **Copia de los archivos que en el servidor son distintos**: `config/app.php`,
      `config/database.php` y `.htaccess`.
- [ ] Hacerlo a una hora sin uso: mientras la base ya está al día y el código todavía no (o
      al revés), algunas pantallas fallan.

## 1. Qué le falta a la base del servidor

En phpMyAdmin, elige la base del sitio en la columna izquierda y pega esto en la pestaña
**SQL**. Solo lee: no cambia nada.

```sql
SELECT m.orden AS `#`, m.archivo AS `Migración`,
       IF(COUNT(c.column_name) > 0, 'ya está', 'FALTA') AS `En esta base`
  FROM (
                  SELECT  1 AS orden, '2026-09-04-proclamadores.sql' AS archivo, 'proclamadores' AS tabla, 'id' AS columna
        UNION ALL SELECT  2, '2026-09-04-tablero-y-documentos-compartidos.sql', 'pastoral_tablero', 'id'
        UNION ALL SELECT  3, '2026-09-04-avisos-sin-leer.sql',                  'aviso_lecturas',   'aviso_id'
        UNION ALL SELECT  4, '2026-09-04-evangelio-del-dia.sql',                'evangelios_dia',   'id'
        UNION ALL SELECT  5, '2026-09-04-coros.sql',                            'coros',            'id'
        UNION ALL SELECT  6, '2026-09-04-coros-voz-e-instrumento.sql',          'coristas',         'voz'
        UNION ALL SELECT  7, '2026-09-05-parejas-de-la-parroquia.sql',          'parejas',          'persona_b_id'
        UNION ALL SELECT  8, '2026-09-13-directorio-telefonico.sql',            'personas',         'en_directorio'
        UNION ALL SELECT  9, '2026-09-15-horarios-tiene-coro.sql',              'horarios',         'tiene_coro'
        UNION ALL SELECT 10, '2026-11-02-menu-del-sitio.sql',                   'menu_sitio',       'id'
        UNION ALL SELECT 11, '2026-11-02-pastorales-grupo-whatsapp.sql',        'pastorales',       'whatsapp_grupo'
        UNION ALL SELECT 12, '2026-11-15-cambios-de-turno.sql',                 'mesc_cambios',     'visto_solicitante'
        UNION ALL SELECT 13, '2026-11-15-enlace-personal.sql',                  'persona_enlaces',  'token'
        UNION ALL SELECT 14, '2026-11-16-varios-coordinadores.sql',             'pastoral_coordinadores', 'persona_id'
        UNION ALL SELECT 15, '2026-11-16-cargo-por-pastoral.sql',               'persona_pastorales', 'cargo'
        UNION ALL SELECT 16, '2026-11-20-cargo-general-aparte.sql',             'personas',         'cargo_general'
        UNION ALL SELECT 17, '2026-11-21-eventos-destacado-y-cuenta-regresiva.sql', 'eventos',      'cuenta_regresiva'
       ) m
  LEFT JOIN information_schema.columns c
         ON c.table_schema = DATABASE() AND c.table_name = m.tabla AND c.column_name = m.columna
 GROUP BY m.orden, m.archivo
 ORDER BY m.orden;
```

- **Todas dicen «ya está»:** la base está al día; sigue con el paso 3.
- **Algunas dicen «FALTA»:** aplica solo esas, en el orden del paso 2.
- **Las 17 dicen «FALTA»:** la base del servidor es de antes de septiembre, cuando todavía
  no había migraciones, y estas no alcanzan para ponerla al día. No la toques; hay que
  revisarla antes.

## 2. Las migraciones, en este orden

Cada una se aplica en phpMyAdmin: la base del sitio → **Importar** → el archivo de
`docs/migraciones/` → Continuar. Los archivos no nombran ninguna base: entran en la que esté
seleccionada. El comando `mysql.exe -uroot parroquia_nsdlp` que traen en su encabezado es el
de la base local; en el servidor no sirve.

| # | Archivo | Qué agrega | Ojo |
|---|---|---|---|
| 1 | `2026-09-04-proclamadores.sql` | Lectores pasa a Proclamadores, con sus datos | No se puede repetir: renombra y borra tablas. Si falla a medias, restaura el respaldo |
| 2 | `2026-09-04-tablero-y-documentos-compartidos.sql` | Tablero y documentos de cada pastoral | Tampoco se repite: pasa los de Catequesis y borra sus tablas viejas |
| 3 | `2026-09-04-avisos-sin-leer.sql` | Quién ya leyó cada aviso | |
| 4 | `2026-09-04-evangelio-del-dia.sql` | Evangelio del día y reflexión del párroco | |
| 5 | `2026-09-04-coros.sql` | Coros y sus integrantes | |
| 6 | `2026-09-04-coros-voz-e-instrumento.sql` | Voz e instrumento de cada integrante | **Después de la 5**, aunque en la carpeta aparezca antes |
| 7 | `2026-09-05-parejas-de-la-parroquia.sql` | Parejas, y la pareja que coordina | |
| 8 | `2026-09-13-directorio-telefonico.sql` | Quién sale en el directorio | |
| 9 | `2026-09-15-horarios-tiene-coro.sql` | Qué misas llevan coro | |
| 10 | `2026-11-02-menu-del-sitio.sql` | Menú del sitio administrable | Sin ella, Contenido → Menú del sitio da error |
| 11 | `2026-11-02-pastorales-grupo-whatsapp.sql` | Enlace al grupo de WhatsApp de cada pastoral | **Su archivo no está en `main`**: ver abajo |
| 12 | `2026-11-15-cambios-de-turno.sql` | Cambios de turno y solicitudes | Va la versión actual del archivo: se corrigió varias veces el 15 y 16 de noviembre |
| 13 | `2026-11-15-enlace-personal.sql` | Enlace personal de los integrantes | **Después de la 12**: modifica sus tablas |
| 14 | `2026-11-16-varios-coordinadores.sql` | Una pastoral la coordinan una o varias personas | **Borra dos columnas**: sin el respaldo no hay vuelta atrás. Con el código en seguida: ver abajo |
| 15 | `2026-11-16-cargo-por-pastoral.sql` | Un cargo distinto en cada pastoral | Solo agrega una columna: el código anterior la ignora. En la carpeta aparece antes que la 14; el orden entre las dos no importa |
| 16 | `2026-11-20-cargo-general-aparte.sql` | El cargo general, aparte; el que se muestra sale solo | **Con el código en seguida**, y después la mudanza de cargos: ver abajo |
| 17 | `2026-11-21-eventos-destacado-y-cuenta-regresiva.sql` | Eventos destacados y con cuenta regresiva en la portada | **Antes que el código**: la portada nueva lee esas columnas y sin ellas da error. Solo agrega columnas, así que el código anterior las ignora |

**La 11 es obligatoria aunque su archivo no esté en `main`.** La página del enlace personal
(`/mi`) lee esa columna y sin ella da error. El archivo solo existe en la rama
`whatsapp-check`, que todavía no se une a `main`; mientras tanto se aplica pegando esto en la
pestaña SQL:

```sql
ALTER TABLE pastorales
    ADD COLUMN whatsapp_grupo VARCHAR(255) NULL AFTER contacto_telefono;
```

Por la misma razón, una instalación nueva desde el `install.sql` de `main` tampoco tiene esa
columna. Se arregla uniendo `whatsapp-check` a `main` (trae además el botón «Copiar y abrir
grupo») o trayendo a `main` solo la migración; cuando se haga, esta nota se borra.

**Sin la 12 y la 13 fallan** las listas de integrantes de MESC, Proclamadores, Coros y
Catequesis, el panel de cada pastoral y la pantalla de Cambios de turno, porque el código
nuevo las consulta siempre. Por eso la base va antes que el código.

**La 14 deja al código viejo sin dos columnas.** Pasa a una tabla nueva a quien coordina
cada pastoral —la pareja de antes, como sus dos personas— y borra `responsable_persona_id` y
`responsable_pareja_id`. Entre aplicarla y subir el código, el sitio deja de decir quién
coordina cada pastoral y guardar una pastoral da error; con el código nuevo sin ella falla
todo lo que muestra pastorales. Por eso va justo antes de subir el código, sin pausa entre
las dos cosas. Si se aplica dos veces, la segunda falla sin tocar nada.

**La 16 va con el código, y después la mudanza de cargos.** Aparta el cargo que se escribe
en la ficha (`cargo_general`) del que se muestra (`cargo`, que desde ahí calcula el código).
El código anterior seguiría escribiendo el de la ficha en `cargo`, así que una ficha guardada
entre la migración y el código nuevo quedaría con el general viejo: van juntas.

Después, en la base local se pasó el cargo general de cada persona a la pastoral donde lo
ejerce, con `herramientas/mover_cargo_general.php` (21 de noviembre de 2026: 64 personas,
cada una con su renglón en la bitácora). Si la base del servidor se va a reemplazar con la
local, ya viene hecho. Si no, hay que correrla allá: primero sin `--aplicar`, para ver qué
haría, y luego con `--aplicar` y las mismas decisiones a mano que se tomaron aquí (entre
comillas las que llevan espacio, o la terminal las parte en dos):

    "--asignar=9:30=Coordinadora General" --asignar=9:16=Coordinadora --asignar=9:21=Proclamadora
    --asignar=11:34 --asignar=73:36 --asignar=73:24 "--asignar=106:38=Coordinadora General"

La última corrige de paso «Cordinadora General». Aparte, en la ficha del diácono se
corrigió «Diacono» por «Diácono»; allá se hace igual, desde su ficha.

- [ ] Al terminar, vuelve a correr la consulta del paso 1: las 17 deben decir «ya está».

## 3. El código

- [ ] Subir los archivos de `main`, sin lo que excluye el paso 3 de
      [`DESPLIEGUE.md`](DESPLIEGUE.md#pasos-previstos).
- [ ] **`config/app.php` sí cambió**: trae los permisos del menú del sitio, de las
      solicitudes de cambio de turno, del enlace personal y del cargo por pastoral
      (`personas.cargo`). Súbelo y vuelve a ponerle lo que
      solo tiene el servidor: `APP_DEBUG` en `false` y `URLS_AMIGABLES` como estaba.
- [ ] `config/database.php` no se sube: no está en git y el del servidor tiene sus propias
      credenciales.
- [ ] `.htaccess` no ha cambiado desde agosto. No lo sobrescribas: el del servidor lleva
      `RewriteBase /`.

## 4. Comprobar en el sitio

- [ ] Contenido → Menú del sitio abre.
- [ ] MESC → Ministros y Proclamadores abren, y cada quien tiene su botón de enlace.
- [ ] Coros → Integrantes, Catequesis → Catequistas y el panel de cualquier pastoral abren.
- [ ] MESC → Cambios de turno y Proclamadores → Cambios de turno abren.
- [ ] En el cuadro del enlace personal, el enlace empieza con `https://` y el dominio del
      sitio.
- [ ] Ese enlace, abierto en un celular, saluda por su nombre y muestra sus turnos.
- [ ] Parroquia → Pastorales: cada tarjeta dice quién coordina, y Matrimonios lleva su
      corazón. Al editar cualquiera, «Quién la coordina» tiene sus renglones y el botón
      «Agregar a alguien más».
- [ ] La portada abre, y al editar un evento como administrador aparece el bloque «En la
      portada» con «Destacado» y «Cuenta regresiva».

## Si el servidor no tiene base todavía

O si la del servidor se va a reemplazar con la de tu equipo: no hace falta ninguna
migración, porque la base local ya tiene las 17. Genera un respaldo en local
(Administración → Respaldos), impórtalo en el servidor con phpMyAdmin y copia también la
carpeta `uploads/`: el respaldo no lleva las imágenes ni los documentos subidos. Después
sigue con los pasos 3 y 4.

## Avisos

- **Los enlaces personales se mandan desde el panel del servidor.** El enlace toma la
  dirección del sitio desde donde se genera: uno copiado en tu equipo lleva `localhost` y no
  le abre a nadie más.
- **Correcciones de datos del 2 de noviembre, hechas solo en la base local:** los nombres
  de tres pastorales («Fiananzas», «Visitadores de Enermos», «Sabado Comunitario»),
  «Miercoles» en el día de reunión de tres pastorales, «SAlon San Judas», «Sabados» en el
  horario de oficina, la nota de un coro y un apellido mal escrito en cinco registros. Si la
  base del servidor tiene datos propios, capturados allá, esas faltas siguen ahí. Si es una
  copia de la local hecha después del 2 de noviembre, ya van corregidas.

## Registro de lo aplicado en el servidor

| Fecha | Qué se aplicó | Quién | Notas |
|---|---|---|---|
| | | | |
