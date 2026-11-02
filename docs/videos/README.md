# Videos del manual

Un video por capítulo del [manual de usuario](../manual/README.md), narrado y
con subtítulos, para quien prefiere ver la pantalla moverse a leer el capítulo.
Cada uno abre con una cortinilla —la ilustración del sitio y el título del
capítulo—, recorre las mismas capturas del manual acercándose a la parte de la
que habla la voz, y cierra con el nombre de la parroquia.

Lo que se versiona aquí son los **guiones** (`guiones/*.json`); los videos se
rehacen con un comando y no se suben al repositorio, igual que el Word del
manual.

## Cómo se arman

```sh
python herramientas/videos_manual.py                 # todos
python herramientas/videos_manual.py 05 13           # solo esos capítulos
python herramientas/videos_manual.py --lista         # qué guiones hay, y si están bien
```

Cada video deja tres archivos en esta carpeta:

| Archivo | Qué es |
|---|---|
| `NN-capitulo.mp4` | El video, 1920×1080, con los subtítulos ya puestos en la imagen. |
| `NN-capitulo.srt` | Los mismos subtítulos aparte, para subirlos a YouTube o Facebook. |
| `NN-capitulo.vtt` | Igual, en el formato que usan las páginas web. |

Al terminar deja también `index.html`, una página con todos los videos armados,
para verlos desde la carpeta —una memoria USB, la computadora de la oficina—
sin subirlos a ningún lado.

Los subtítulos quemados en la imagen se ven en cualquier reproductor, también
en el teléfono y en un grupo de WhatsApp; los `.srt` y `.vtt` son para cuando
el video se publica en una plataforma que deja encenderlos y apagarlos.

Necesita internet para la voz y estas librerías:

```sh
pip install Pillow edge-tts imageio-ffmpeg opencv-python-headless numpy
```

La voz es **es-MX-DaliaNeural**. Solo se le manda el texto de la narración, que
es el mismo del manual. Cada frase se pide una vez y se guarda en `.voz/`, así
que corregir una escena y volver a armar el video solo pide esa escena.

## Los capítulos

Los guiones llevan el mismo nombre que el capítulo que cuentan:

| Video | Capítulo |
|---|---|
| `00-introduccion` | Presentación del manual |
| `01-el-sitio-publico` | El sitio público |
| `02-entrar-al-panel` | Entrar al panel |
| `03-el-panel-por-dentro` | El panel por dentro |
| `04-quien-puede-hacer-que` | Quién puede hacer qué |
| `05-avisos` | Avisos |
| `06-eventos` | Eventos |
| `07-cursos-e-inscripciones` | Cursos e inscripciones |
| `08-agenda` | Agenda |
| `09-galeria-y-carrusel` | Galería y carrusel |
| `10-mensajes-de-contacto` | Mensajes de contacto |
| `11-horarios-y-sedes` | Horarios y sedes |
| `12-equipo-pastoral-y-organigrama` | Equipo pastoral y organigrama |
| `13-pastorales` | Pastorales |
| `14-sacramentos` | Sacramentos |
| `15-mesc` | MESC |
| `16-catequesis` | Catequesis |
| `17-proclamadores` | Proclamadores |
| `18-coros` | Coros |
| `19-paginas-bloques-y-evangelio` | Páginas, bloques y evangelio |
| `20-usuarios` | Usuarios |
| `21-configuracion` | Configuración |
| `22-respaldos-y-bitacora` | Respaldos y bitácora |

## Cómo se escribe o se corrige un guion

Un guion es una lista de escenas. Cada escena es una captura del manual y lo
que dice la voz mientras se ve:

```json
{
  "capitulo": 5,
  "titulo": "Avisos",
  "subtitulo": "Publicar un aviso no es una sola cosa",
  "intro": "Capítulo cinco: avisos. En este video verás…",
  "escenas": [
    {
      "imagen": "avisos-form",
      "texto": "A la derecha, lo que decide dónde aparece…",
      "foco": [0.72, 0.13, 0.98, 0.45]
    }
  ]
}
```

- **imagen** es el nombre de una captura de `docs/manual/img/`, sin extensión.
- **texto** es a la vez la narración y el subtítulo. Se escribe para el oído:
  oraciones cortas, sin flechas ni paréntesis largos, y cada una terminada en
  punto, porque el subtítulo se parte por oración.
- **foco**, opcional, es el rectángulo al que se acerca la cámara, en
  fracciones de la captura: izquierda, arriba, derecha, abajo. Para leerlo,
  `python herramientas/videos_manual.py --cuadricula avisos-form` deja en
  `.cuadricula/` la captura con una rejilla rotulada cada décimo. Sin foco, la
  cámara recorre la pantalla de arriba abajo.

Si una palabra suena mal —una sigla, sobre todo—, se corrige en el diccionario
`DICCION` del generador, no en el guion: así el subtítulo conserva la
ortografía y la voz la pronuncia bien en todos los videos.

**Cuando cambia una pantalla**, primero se rehace su captura con
`capturas_manual.js` y después el video: los dos toman la misma imagen. Y por
la misma razón que en el manual, **no se graba la pantalla a mano**: las
capturas del catálogo ya llevan tapados los teléfonos, los correos y los
cumpleaños, y una grabación suelta se llevaría todo eso a internet.
