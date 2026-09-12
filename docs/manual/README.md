# Manual de usuario

Cómo se usa el sitio de la Parroquia Nuestra Señora de la Paz y su panel de
administración. Está escrito para el equipo parroquial —quien coordina una
pastoral, quien atiende la oficina, quien publica los avisos—, no para quien
programa: no hace falta saber nada de computación más allá de abrir una página
y llenar un formulario.

Si lo que buscas es cómo está hecho por dentro, eso vive en
[`docs/ARQUITECTURA.md`](../ARQUITECTURA.md) y en
[`docs/BASE-DE-DATOS.md`](../BASE-DE-DATOS.md).

## Antes de empezar

El sistema tiene **dos caras**:

- **El sitio**, que ve cualquiera que entre a la dirección de la parroquia. No
  pide contraseña.
- **El panel**, que está detrás de una contraseña y es donde el equipo captura
  lo que después aparece en el sitio.

Casi todo este manual es del panel. Lo que ahí se guarda es lo que el visitante
acaba viendo, así que conviene leer primero el capítulo 1, que enseña el sitio
terminado, y después los capítulos que explican de dónde sale cada pedazo.

**No todos ven lo mismo.** El menú del panel se dibuja según lo que cada cuenta
tiene permitido: si un capítulo describe una pantalla que tú no encuentras, lo
más probable es que tu cuenta no la tenga. El capítulo 4 explica por qué.

## Capítulos

### El sitio y el panel

1. [El sitio público](01-el-sitio-publico.md) — lo que ve cualquiera, sección
   por sección, y desde qué parte del panel se edita cada una.
2. Entrar al panel *(en preparación)*
3. El panel por dentro *(en preparación)*
4. Quién puede hacer qué: roles, pastoral y sede *(en preparación)*

### Comunicación

5. Avisos *(en preparación)*
6. Eventos *(en preparación)*
7. Cursos e inscripciones *(en preparación)*
8. Agenda *(en preparación)*
9. Galería y carrusel *(en preparación)*
10. Mensajes de contacto *(en preparación)*

### La parroquia

11. Horarios y sedes *(en preparación)*
12. Equipo pastoral y organigrama *(en preparación)*
13. Pastorales *(en preparación)*
14. Sacramentos *(en preparación)*

### Las pastorales con módulo propio

15. MESC — visitas a enfermos, rutas y turnos *(en preparación)*
16. Catequesis — catequistas y periodos *(en preparación)*
17. Proclamadores — quién proclama y su calendario *(en preparación)*
18. Coros — un coro por misa *(en preparación)*

### Contenido del sitio

19. Páginas, bloques y evangelio del día *(en preparación)*

### Administración

20. Usuarios *(en preparación)*
21. Configuración *(en preparación)*
22. Respaldos y bitácora *(en preparación)*

## Cómo leer las capturas

Las imágenes son del sistema real, tomadas de la instalación de la parroquia.
Dos cosas que conviene saber al mirarlas:

- **Los datos personales están cambiados.** Teléfonos, correos particulares y
  los datos de quien escribe por el formulario de contacto o se inscribe a un
  curso aparecen sustituidos por ejemplos. Lo que sí es real es todo lo demás:
  los nombres de las pastorales, quién coordina cada una —que el propio sitio
  publica— y la forma de cada pantalla.
- **Un recuadro rosa** señala el botón o la zona de la que habla el párrafo de
  al lado.

## Para quien mantenga este manual

Las capturas no se toman a mano: las rehace
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

Ese guion es también el que tapa los datos personales antes de disparar. **Una
captura que se tome por fuera de él —un recorte de pantalla pegado a mano— se
lleva a un repositorio público los teléfonos y los correos de la gente de la
parroquia.** Si hace falta una pantalla que el guion todavía no cubre, se
agrega a la lista; no se pega una captura suelta.
