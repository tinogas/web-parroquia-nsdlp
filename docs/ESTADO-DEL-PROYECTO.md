# Estado del proyecto

Fotografía de dónde va el sistema hoy, para quien llegue nuevo o retome el trabajo después
de un tiempo. No repite lo que ya cuentan bien los demás documentos de `docs/`: aquí solo
el panorama completo y un mapa de dónde está cada cosa.

- **[`PLAN.md`](PLAN.md)** — el plan original por etapas, con esfuerzo estimado y qué se
  pidió después de cerrarlas.
- **[`ARQUITECTURA.md`](ARQUITECTURA.md)** — por qué cada pieza está construida como está,
  con las decisiones de diseño y sus motivos.
- **[`BASE-DE-DATOS.md`](BASE-DE-DATOS.md)** — cada tabla, columna por columna.
- **[`DESPLIEGUE.md`](DESPLIEGUE.md)** — checklist de salida a producción y lo que falta
  resolver que no depende del código.

## En una frase

Sitio web público y sistema de administración a la medida para la Parroquia Nuestra
Señora de la Paz: el equipo pastoral publica contenido desde un panel con roles, y
cualquier visitante lo consulta sin necesitar cuenta.

## Las diez etapas del plan original están hechas

`docs/PLAN.md` las marca todas **Hecha**, de "Andamiaje" (etapa 1) a "SEO y despliegue"
(etapa 10). Sobre esa base se ha seguido trabajando en bloques pedidos por el uso real del
panel — la tabla "Después de la fase 1" de ese mismo documento los lista todos, el más
reciente es la publicación de avisos y cursos en dos escalones (interno/público) y el
panel básico automático por pastoral.

**Lo único marcado como pendiente dentro del propio código** es el aula virtual: el
catálogo de cursos (`modules/cursos/CursoModel.php`) está preparado para que
`curso_sesiones` sea el ancla de tareas, entregas y calificaciones, pero eso es "fase 2" y
no se ha empezado.

## Qué hay para un visitante del sitio (sin cuenta)

| Sección | Qué encuentra |
|---|---|
| Inicio | Próximas misas, eventos y cursos, avisos recientes, lo último en Facebook |
| Quiénes somos | Historia, misión y visión, equipo pastoral, organigrama |
| Horarios | Misas, confesiones, adoración y oficina, agrupados por tipo y por sede |
| Sacramentos | Requisitos y documentos por sacramento (informativo; el trámite es en oficina) |
| Pastorales | Fichas de cada pastoral, agrupadas por la Comisión que las coordina |
| Cursos | Catálogo con temario e inscripción en línea, con cupo y lista de espera |
| Avisos | Boletín y noticias — solo lo que cada pastoral decidió sacar a la página |
| Eventos | Calendario en cuatro vistas: día, semana, mes y año |
| Galería | Álbumes de fotos |
| Contacto | Formulario con antispam propio, sin depender de un servicio externo |

Además, `sitemap.xml` se genera en cada visita (no es un archivo estático que se pueda
desactualizar) y cada página trae sus etiquetas Open Graph para compartirse bien en redes.

## Quién hace qué en el panel

| Rol | Qué puede hacer |
|---|---|
| **Administrador** | Todo: usuarios, configuración, respaldos, auditoría, sin límite |
| **Editor** | Publica y modera todo el contenido del sitio; no toca usuarios ni configuración |
| **Coordinador general de pastoral** | Administra su pastoral en varias sedes o en toda la parroquia; además da de alta y edita cuentas de su propia pastoral |
| **Coordinador de pastoral** | Administra su pastoral en una sola sede: sus eventos, sus cursos y sus avisos (con el escalón interno y el salto a la página) |
| **Consulta** | Solo mira: su calendario, sus documentos y los avisos internos de su pastoral. No crea ni edita nada |
| **Secretaría** | Solo inscripciones a cursos y mensajes de contacto. No toca el sitio — existe por una separación legal de quién ve datos de menores |

Ningún rol lleva el nombre de una pastoral en sí (no hay "Administrador MESC"): la
pastoral y la sede se asignan por separado, en la cuenta.

## Los módulos del panel, por tema

**Contenido del sitio** — `bloques` (textos editables), `paginas`, `carrusel`, `galeria`.

**La parroquia** — `centros` (sedes), `personas` (equipo pastoral), `organigrama`,
`horarios`, `pastorales` (con su jerarquía de Comisiones y su panel básico por pastoral).

**Comunicación** — `avisos`, `eventos`, `agenda` (calendario interno, ve borradores de
toda la parroquia a propósito, para que nadie reserve el salón dos veces).

**Trámites** — `sacramentos` (informativo), `cursos` e `inscripciones`.

**Tres pastorales con módulo propio**, sobre el mismo patrón: `mesc` (ministros, visitas a
enfermos, rutas y turnos), `catequesis` (catequistas, periodos) y `lector` (turnos y color
litúrgico). Las tres tienen su pastoral fija por configuración, no por selector, y además
cuentan ya con el panel básico genérico de cualquier pastoral.

**Administración** — `usuarios`, `configuracion`, `auditoria` (solo lectura),
`respaldos` (con restauración desde el panel), `contacto` (bandeja de mensajes), `auth`
(incluye impersonación, "Usar como…").

## Los números

- **28 módulos** en `modules/`, 12 con contraparte pública y 16 exclusivos del panel.
- **42 tablas** en `install.sql`, agrupadas por tema al final de `docs/BASE-DE-DATOS.md`.
- **6 roles**, sin ninguno atado al nombre de una pastoral.

## Lo que falta, y por qué se dejó fuera a propósito

- **Producción.** Todo corre hoy en local (XAMPP). `docs/DESPLIEGUE.md` lista sin resolver
  el dominio, el hosting, y quién tiene cada acceso — son decisiones de la parroquia, no
  de código.
- **Aula virtual** (tareas, entregas, calificaciones sobre los cursos): fase 2, pospuesta
  desde el plan original.
- **Donaciones en línea**: se retiró por decisión del párroco.
- **Notificaciones por correo**: no existen. En su lugar, el panel avisa con el tablón de
  novedades y las marcas "Nuevo" de la sesión de cada quien.
- **Retención y anonimización de datos personales**: no hay mecanismo. Si la parroquia
  decide que hace falta uno para inscripciones o mensajes de contacto, hay que construirlo
  — ver `docs/PRIVACIDAD.md`.

## Para probarlo en este momento

El sitio corre en `http://localhost/WebParroquia/` con XAMPP (Apache + MariaDB). No hay
todavía una URL de producción — ver la nota anterior. La base de datos local ya tiene
contenido real cargado (la agenda 2026 completa, pastorales, equipo pastoral), así que
reinstalar desde `install.sql` sin respaldar antes borra ese trabajo — el propio
`docs/PLAN.md` lo advierte.
