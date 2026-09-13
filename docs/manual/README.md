# Manual de usuario

Cómo se usa el sitio de la Parroquia Nuestra Señora de la Paz y su panel de
administración, escrito para el equipo parroquial. Lo que se entrega es un
documento de **Word**; lo que se versiona aquí son sus capítulos en Markdown y
las capturas, que es lo que se puede revisar en un diff y corregir sin abrir
nada.

## Cómo se arma el Word

```sh
python herramientas/manual_a_word.py          # docs/manual/Manual-de-usuario.docx
python herramientas/manual_a_word.py --pdf    # y además el PDF, para repartir
```

Junta los capítulos en orden, les pone portada con el escudo de la parroquia,
índice con números de página, y pie numerado. El .docx y el PDF **no se
versionan** —se rehacen con ese comando—, así que las correcciones van siempre
a los `.md`: editar el Word a mano funciona una vez, hasta que alguien vuelva a
generar y lo pise.

## Capítulos

El orden es el del número en el nombre del archivo.

### El sitio y el panel

- [00 — Introducción](00-introduccion.md)
- [01 — El sitio público](01-el-sitio-publico.md)
- [02 — Entrar al panel](02-entrar-al-panel.md)
- [03 — El panel por dentro](03-el-panel-por-dentro.md)
- [04 — Quién puede hacer qué: roles, pastoral y sede](04-quien-puede-hacer-que.md)

### Comunicación

- 05 — Avisos *(en preparación)*
- 06 — Eventos *(en preparación)*
- 07 — Cursos e inscripciones *(en preparación)*
- 08 — Agenda *(en preparación)*
- 09 — Galería y carrusel *(en preparación)*
- 10 — Mensajes de contacto *(en preparación)*

### La parroquia

- 11 — Horarios y sedes *(en preparación)*
- 12 — Equipo pastoral y organigrama *(en preparación)*
- 13 — Pastorales *(en preparación)*
- 14 — Sacramentos *(en preparación)*

### Las pastorales con módulo propio

- 15 — MESC: visitas a enfermos, rutas y turnos *(en preparación)*
- 16 — Catequesis: catequistas y periodos *(en preparación)*
- 17 — Proclamadores: quién proclama y su calendario *(en preparación)*
- 18 — Coros: un coro por misa *(en preparación)*

### Contenido del sitio

- 19 — Páginas, bloques y evangelio del día *(en preparación)*

### Administración

- 20 — Usuarios *(en preparación)*
- 21 — Configuración *(en preparación)*
- 22 — Respaldos y bitácora *(en preparación)*

## Las capturas

No se toman a mano: las rehace
[`herramientas/capturas_manual.js`](../../herramientas/capturas_manual.js)
contra el XAMPP local, con Apache y MySQL arriba.

```sh
node herramientas/capturas_manual.js --lista        # qué pantallas hay
node herramientas/capturas_manual.js                # todas
node herramientas/capturas_manual.js avisos-*       # solo un capítulo
```

La lista de qué se fotografía está en
[`herramientas/capturas_manual_pantallas.js`](../../herramientas/capturas_manual_pantallas.js),
y el nombre de cada imagen es el `id` de su entrada: para rehacer una captura
concreta basta con leer el nombre del archivo en el Markdown.

Para entrar al panel, ese guion lee `herramientas/.env` (que no se versiona):

```
MANUAL_EMAIL=admin@parroquiansdlp.org
MANUAL_PASSWORD=…
```

Las capturas se guardan en WebP, que pesa la quinta parte que un PNG y se ve
igual; al armar el Word se convierten solas, porque Word no lee ese formato.

**El guion es también el que tapa los datos personales antes de disparar.** Una
captura tomada por fuera de él —un recorte de pantalla pegado a mano— se lleva
a un repositorio público los teléfonos y los correos de la gente de la
parroquia. Si hace falta una pantalla que todavía no cubre, se agrega a la
lista; no se pega una captura suelta.
