# Plan de implementación

Documento de referencia del alcance y el orden de trabajo. Nace del
[issue #1](https://github.com/tinogas/web-parroquia-nsdlp/issues/1) y de las decisiones
acordadas al revisarlo.

## Por qué este proyecto

La parroquia no tiene presencia web propia. Quien busca el horario de la misa dominical,
los requisitos de un bautizo o cómo contactar a la oficina parroquial no encuentra una
fuente oficial. Al mismo tiempo, las pastorales generan actividad constante —avisos,
eventos, cursos— que hoy solo circula de boca en boca o en redes sociales dispersas.

El objetivo es un sitio que resuelva esas dos cosas: información confiable para el
visitante, y un panel donde el equipo parroquial publique sin depender de un
desarrollador.

## Decisiones de alcance

### Sitio tradicional, no landing page

El issue preguntaba cuál convenía. Una landing page sirve para una sola conversión; aquí
hay ocho secciones informativas, un catálogo de cursos, formularios de inscripción y un
panel de administración. Es un sitio multi-página. Lo que sí se toma prestado de las
landing pages es la portada: carrusel, próximas misas, próximos eventos y cursos, y avisos
recientes, todo sin bajar.

(Al principio la portada repetía además los datos de contacto. Se quitaron: el pie de
página los lleva en todas las pantallas del sitio, así que ese bloque solo servía en una
landing page, donde no hay otra página a la que ir. Ver
[`ARQUITECTURA.md`](ARQUITECTURA.md#la-portada).)

### CMS a medida, no WordPress ni un motor tipo Joomla

Se descartaron tres caminos:

| Opción | Por qué no |
|---|---|
| WordPress | Rápido de levantar, pero obliga a un ciclo permanente de actualizaciones de núcleo y plugins, y amplía mucho la superficie de ataque en un hosting compartido sin quien lo vigile. |
| Motor CMS genérico propio (tipos de contenido, menús y plantillas configurables) | Es un proyecto de meses antes de mostrar la primera página, y nadie en la parroquia va a crear tipos de contenido. |
| **CMS a medida acotado** ← elegida | Módulos concretos para lo que la parroquia realmente administra. El contenido es 100 % editable; la estructura del sitio no se puede romper desde el panel. |

Ver [`ARQUITECTURA.md`](ARQUITECTURA.md#contenido-editable-tres-mecanismos-no-uno) para
cómo se logra que todo sea editable sin ser un motor genérico.

### Qué queda fuera de la fase 1

**Aula virtual (LMS).** El issue pedía cursos con "tareas, asignaciones, calendario".
Eso es un sistema de gestión de aprendizaje completo: entregas de alumnos,
calificaciones, control de avance. Es aproximadamente el 40 % del esfuerzo total del
proyecto y bloquearía la salida del sitio durante meses. En fase 1 los cursos se publican
como catálogo con temario e inscripción; el modelo de datos queda diseñado para
extenderse sin rehacer nada.

**Donaciones y ofrendas en línea.** Retirado del alcance por decisión del párroco.
Cuando se retome, la regla técnica es que el servidor nunca procese datos de tarjeta: se
redirige a una pasarela externa. Cobrar en línea a nombre de una asociación religiosa
además tiene implicaciones fiscales que hay que resolver antes que las técnicas.

**Notificaciones por correo.** La función `mail()` en hosting compartido cae en spam con
frecuencia, y usar un servicio SMTP externo implicaría romper la regla de cero
dependencias. La fase 1 no depende del correo: las solicitudes y mensajes nuevos se
avisan con contadores en el panel.

### Correcciones al issue original

- Donde el issue dice "grupos parroquiales" el nombre correcto es **pastorales**. Así se
  llaman en el código, en la base de datos y en la interfaz.
- Se añade un **formulario de inscripción en línea a los sacramentos**. El issue solo
  pedía publicar los requisitos; recibir la solicitud por la web ahorra visitas a la
  oficina parroquial y es la pieza de más valor operativo del sistema.

  > **Revertido después.** Se construyó en la etapa 7 y se eliminó por completo en el
  > issue #3, a petición explícita del administrador: la parroquia prefiere que el trámite
  > empiece en la oficina. La sección quedó como información de requisitos, y con eso el
  > issue original tenía razón en este punto. Ver
  > [`ARQUITECTURA.md`](ARQUITECTURA.md#sacramentos-catálogo-puramente-informativo).
- El **organigrama** se muestra en "Quiénes somos" y no en la portada. En la portada
  competiría con lo que el visitante realmente busca: horario de misa y ubicación.

## Secciones del sitio

| Sección | Contenido |
|---|---|
| Inicio | Carrusel, bienvenida del párroco, próximas misas, próximos eventos y próximos cursos, últimos avisos, ligas de interés |
| Quiénes somos | Historia, misión, visión y valores; sacerdote, diáconos y equipo pastoral; organigrama |
| Horarios | Misas dominicales y entre semana, confesiones, adoración eucarística, horario de oficina |
| Sacramentos | Requisitos, documentos y aportación de bautizo, primera comunión, confirmación, matrimonio, confesión y unción. Puramente informativo: el trámite se hace en la oficina parroquial |
| Pastorales | Coro, catequesis, caridad, jóvenes, ministros MESC y demás, con sus actividades comunitarias y de apoyo social |
| Cursos | Catálogo con temario e inscripción |
| Avisos | Boletín semanal y noticias parroquiales |
| Eventos | Calendario mensual y detalle de cada celebración |
| Galería | Álbumes de fotografías |
| Contacto | Mapa, teléfono, correo, redes sociales, horario de oficina y formulario |
| Aviso de privacidad | Obligatorio; ver [`PRIVACIDAD.md`](PRIVACIDAD.md) |

## Quién administra qué

| Rol | Alcance |
|---|---|
| Administrador | Todo, incluidos usuarios, configuración, respaldos y auditoría |
| Editor | Todo el contenido del sitio; publica y modera. Sin acceso a usuarios ni configuración |
| Coordinador de pastoral | Contenido de su o sus pastorales, en las sedes que se le marquen (sin marcar ninguna, en todas). Publica sus eventos, sus cursos y sus avisos —estos dos últimos en dos escalones: primero hacia dentro, para su pastoral, y luego, si quiere, también al sitio—; su galería queda en borrador hasta que un editor la publica |
| Secretaría | Inscripciones a cursos y mensajes de contacto. No edita el sitio |

El rol de secretaría existe por una razón legal, no organizativa: separa a quien ve datos
personales de menores de quien edita la web. Detalle en
[`ARQUITECTURA.md`](ARQUITECTURA.md#roles-y-permisos).

A estos cuatro se sumaron después seis roles con la pastoral en el nombre (Administrador
MESC, Consulta Catequesis…) y se retiraron al aparecer tres coordinadoras de catequesis, una
por comunidad, que ese esquema no sabía distinguir. En su lugar quedan dos: **Coordinador
general de pastoral**, que administra la suya en varias sedes o en todas, y **Consulta**, de
solo lectura, para que un ministro, catequista o lector entre a ver su calendario y sus
documentos sin poder cambiar nada. Los tres acotados usan el mismo mecanismo: se les asigna
la pastoral y la sede. Ver
[`ARQUITECTURA.md`](ARQUITECTURA.md#administrador-y-consulta-por-pastoral-revisión-de-módulos).

## Etapas

El esfuerzo es relativo: 1 unidad equivale aproximadamente a un CRUD completo con sus
vistas de administración.

| # | Etapa | Contenido | Esfuerzo | Estado |
|---|---|---|---|---|
| 0 | Documentación | Este documento y los demás de `docs/`, más el README | 0.5 | **Hecha** |
| 1 | Andamiaje | `.gitignore`, `.htaccess`, `config/`, `core/`, Router de dos áreas, sesión perezosa, `index.php`, layouts esqueleto, `install.sql` inicial, `setup.php` | 1.0 | **Hecha** |
| 2 | Configuración y bloques | Módulos `configuracion` y `bloques`, sanitizador de HTML, editor de texto, subida de archivos extendida | 1.0 | **Hecha** |
| 3 | Sitio público estructural | Layout público real, hoja de estilos, `inicio`, `nosotros`, `contacto` con antispam, páginas libres y aviso de privacidad | 2.0 | **Hecha** |
| 4 | Horarios, personas y organigrama | Tres CRUD y sus secciones públicas | 1.5 | **Hecha** |
| 5 | Avisos, eventos, galería y carrusel | Publicación con borrador, calendario mensual, álbumes | 2.5 | **Hecha** |
| 6 | Pastorales | CRUD con actividades, fichas públicas, y activación del rol coordinador con su alcance | 2.0 | **Hecha** |
| 7 | Sacramentos y solicitudes | Catálogo, campos configurables, formulario público, bandeja con estados y bitácora | 2.5 | **Hecha** |
| 8 | Cursos e inscripciones | Catálogo, temario, inscripción, cupo y lista de espera | 1.5 | **Hecha** |
| 9 | Usuarios, roles y auditoría | CRUD de usuarios, asignación de pastorales, auditoría con filtros, repaso de seguridad | 1.0 | **Hecha** |
| 10 | SEO y despliegue | `sitemap.xml`, Open Graph, datos estructurados, guía de cPanel, checklist final | 1.0 | **Hecha** |
| | | **Total** | **16.5** | |

### Hitos

- **Al terminar la etapa 3** hay un sitio presentable al párroco: portada, quiénes somos
  y contacto, con textos editables desde el panel.
- **Al terminar la etapa 5** el sitio está vivo: la parroquia ya puede publicar avisos y
  eventos sin ayuda.
- **Al terminar la etapa 7** está la pieza de más valor operativo: las solicitudes de
  sacramentos llegan por la web.

### Reglas de secuenciación

1. La etapa 1 no se recorta. Todo lo demás asume el Router de dos áreas y el arranque
   perezoso de sesión; parchearlos después obliga a tocar cada controlador.
2. La etapa 2 va antes que cualquier vista pública, porque toda vista lee de
   `configuracion`.
3. El rol coordinador no se implementa hasta la etapa 6, cuando ya existe la entidad
   sobre la que limita el alcance. Adelantarlo obliga a rehacerlo.
4. Ninguna etapa se cierra sin que su DDL esté en `install.sql` y sin haber reinstalado
   desde cero en una base de datos limpia. No hay migraciones: `install.sql` es un
   archivo único acumulativo hasta que el sitio salga a producción.

**Salvedad sobre la regla 4, a partir de la carga de la agenda 2026.** La base local ya
contiene contenido real que `install.sql` no siembra: los 467 eventos de la agenda, los
centros, las pastorales con sus ministros, catequistas y lectores, y el equipo pastoral.
Reimportar `install.sql` desde cero sigue siendo la forma de comprobar que el esquema está
completo, pero **ya no es gratis**: borra ese trabajo. Desde ahora, antes de reinstalar se
genera un respaldo desde el panel (Administración → Respaldos) y se restaura después.
`backups/antes-agenda-2026.sql` es el precedente. El punto de no retorno formal sigue siendo el despliegue —ahí empiezan las
migraciones `ALTER TABLE`, ver [`DESPLIEGUE.md`](DESPLIEGUE.md#actualizaciones-posteriores)—,
pero en local ya conviene tratar la base como algo que cuesta reconstruir.

## Después de la fase 1

Las diez etapas de arriba cerraron el alcance acordado. Lo que sigue son bloques de trabajo
pedidos después, cada uno con su origen; todos están implementados y probados en local.

| Bloque | Qué trajo | Origen |
|---|---|---|
| Respaldos y restauración | Módulo de respaldos en PHP puro, con restauración desde el panel y respaldo de seguridad automático antes de reemplazar nada | Petición del párroco |
| Impersonación | "Usar como…": el administrador opera el panel con la sesión de otra cuenta, y la auditoría distingue quién lo hizo de verdad | Petición del administrador |
| Sedes, centros y pastorales | Catálogo de centros, pastorales ligadas a su sede (el alcance heredado por centro se implementó aquí y se sustituyó después por el alcance pastoral × sede, ver ARQUITECTURA.md), contenido y documentos propios de cada pastoral, vigencia de avisos, horarios por sede, y el módulo MESC (visitas a enfermos, rutas y calendario de turnos). Se retiró el formulario de solicitud de sacramentos en línea | [Issue #3](https://github.com/tinogas/web-parroquia-nsdlp/issues/3) |
| Catequesis y Lector | Dos módulos más de pastoral dedicada sobre el patrón de MESC: catequistas con periodos y grado, y turnos de lectores | Issue #3 |
| Revisión de módulos | Roles de administrador y consulta por pastoral, botones que no se dibujan sin permiso, horarios públicos agrupados por tipo con filtro de sede, eventos de varios días, datos de tutor en inscripciones, organigrama responsivo y su vista de impresión en árbol | Uso real del panel |
| Calendario y agenda 2026 | Vistas de día, semana, mes y año; filtros de fecha en el listado del panel; y la carga de la agenda impresa completa con los scripts de `herramientas/` | Necesidad de publicar la agenda del año |
| Jerarquía de pastorales y menú dinámico | Las pastorales se agrupan en Comisiones (Litúrgica, Profética...); cada pastoral gana un panel básico con acceso ya filtrado a avisos, eventos, cursos y documentos, sin necesitar un módulo dedicado; publicarla en el menú es un paso aparte, exclusivo del Administrador y con confirmación de contraseña | Uso real del panel |
| Publicación en dos escalones | Avisos y cursos ganan un peldaño intermedio entre borrador y sitio público: publicado hacia dentro, visible a la pastoral (y a sus hijas si es una Comisión) desde el panel de inicio, marcado como nuevo hasta que se revisa | Uso real del panel |

El detalle de cada decisión —y de los errores que estos bloques encontraron en el código de
la fase 1— está en [`ARQUITECTURA.md`](ARQUITECTURA.md).

### Lo que queda abierto

- **Desplegar en producción.** Todo el trabajo sigue siendo local. Los pendientes que no
  dependen de nosotros (dominio, hosting, quién tiene cada acceso) están listados en
  [`DESPLIEGUE.md`](DESPLIEGUE.md#pendiente-de-resolver).
- **El resto del contenido real**: lo de la lista de abajo que aún no ha llegado.
- **No hay mecanismo de retención ni anonimización** de datos personales. El que existía
  murió con el formulario de solicitud de sacramentos; si la parroquia decide que
  `inscripciones_curso` o `mensajes_contacto` necesitan uno, hay que construirlo de nuevo.
  Ver [`PRIVACIDAD.md`](PRIVACIDAD.md#retención).
- **Dos incoherencias menores anotadas, no resueltas**: la lista de carpetas que el
  `.htaccess` bloquea nombra `cli/`, que ya no existe, y no nombra `herramientas/`; y
  `package.json` declara una versión de los iconos distinta de la que el sitio carga del CDN.
  Las dos están explicadas en [`ARQUITECTURA.md`](ARQUITECTURA.md).
- **La fase 2 (aula virtual) sigue fuera de alcance**, con el modelo de datos de cursos ya
  preparado para extenderse.

## Qué hace falta de la parroquia

El sitio no sirve sin contenido real. Esto es lo que se pidió al empezar y cómo va, revisado
sobre la base local el **30 de julio de 2026**:

**Ya cargado**

- Dirección, ciudad, código postal, teléfono, WhatsApp, correo, Facebook, mapa y horario de
  oficina.
- Logotipo.
- Horarios de misas, confesiones y demás: 42 registros, repartidos entre la sede y los dos
  centros.
- Párroco, vicario y equipo pastoral: 8 personas y un organigrama de 10 nodos.
- Las seis pastorales activas, con los ministros de MESC (13), los catequistas (5) y los
  lectores dados de alta.
- La agenda del año: 467 eventos y 22 actividades semanales.
- Bienvenida del párroco, misión, visión, ligas de interés e introducción de horarios.

**Sigue faltando**

- **Fotografías.** Es lo más notorio: el carrusel de la portada está vacío, la galería tiene
  una sola imagen, y de las 8 personas del equipo pastoral solo el párroco tiene foto.
- **Historia de la parroquia y sus valores.** Los bloques existen y están en blanco.
- **Requisitos y documentos de cuatro de los seis sacramentos**: hoy solo bautismo y
  matrimonio los tienen escritos.
- **Textos de entrada** de inicio, contacto, cursos, pastorales y sacramentos, todos en
  blanco.
- **Semblanza** de la mayoría del equipo pastoral.
- **Instagram y YouTube**, si la parroquia los usa; el favicon; y la descripción para
  buscadores y la imagen de Open Graph, que hoy caen en el valor por omisión.

Nada de esto bloquea el despliegue —una sección sin contenido no se dibuja en vez de
aparecer vacía—, pero la portada sin fotografías es lo primero que se nota.

## Riesgos

| Riesgo | Impacto | Mitigación |
|---|---|---|
| Portar el Router de `inventario` tal cual y dejar el sitio público detrás del login | Alto | Punto de revisión explícito en la etapa 1 y prueba manual de las rutas públicas sin sesión |
| XSS almacenado: habrá varios editores semi-confiables escribiendo HTML | Alto | Sanitizado con whitelist al guardar, más una CSP restrictiva |
| Ejecución de PHP subido a `uploads/` | Crítico | `.htaccess` que apaga el motor, validación de MIME real y nombres generados |
| Fuga de datos personales de menores | Crítico y legal | Separación de roles, filtro de autorización en la galería, auditoría de lecturas y anonimizado por retención |
| Un coordinador publica contenido inapropiado | Reputacional | Sus avisos y su galería entran como borrador y los revisa un editor. Sus eventos y cursos sí se publican solos —una fecha del calendario no espera revisión—, con la auditoría y el respaldo como red |
| `mod_rewrite` ausente o distinto en el hosting | Medio | Interruptor `URLS_AMIGABLES` y helpers de URL desde el primer día |
| `install.sql` se desincroniza del esquema real | Medio | Reinstalación limpia al cerrar cada etapa; desde la carga de la agenda 2026, con respaldo y restauración alrededor (ver la salvedad de la regla 4) |
| Falta de contenido real al momento de publicar | Alto para la entrega | Solicitarlo desde ahora; datos de ejemplo marcados mientras tanto |
| El alcance es grande y abarca varias sesiones de trabajo | Medio | Etapas independientes y acumulativas, con hitos presentables |
