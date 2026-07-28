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
landing pages es la portada: carrusel, próximas misas, avisos recientes y contacto
visible sin bajar.

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
- El **organigrama** se muestra en "Quiénes somos" y no en la portada. En la portada
  competiría con lo que el visitante realmente busca: horario de misa y ubicación.

## Secciones del sitio

| Sección | Contenido |
|---|---|
| Inicio | Carrusel, bienvenida del párroco, horarios destacados, próximos eventos, últimos avisos, contacto rápido |
| Quiénes somos | Historia, misión, visión y valores; sacerdote, diáconos y equipo pastoral; organigrama |
| Horarios | Misas dominicales y entre semana, confesiones, adoración eucarística, horario de oficina |
| Sacramentos | Requisitos y documentos de bautizo, primera comunión, confirmación, matrimonio, confesión y unción; formulario de solicitud |
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
| Administrador | Todo, incluidos usuarios, configuración y auditoría |
| Editor | Todo el contenido del sitio; publica y modera. Sin acceso a usuarios ni configuración |
| Coordinador | Contenido de su o sus pastorales. Lo que escribe queda en borrador hasta que un editor lo publica |
| Secretaría | Solicitudes de sacramentos, inscripciones a cursos y mensajes de contacto. No edita el sitio |

El rol de secretaría existe por una razón legal, no organizativa: separa a quien ve datos
personales de menores de quien edita la web. Detalle en
[`ARQUITECTURA.md`](ARQUITECTURA.md#roles-y-permisos).

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
| 6 | Pastorales | CRUD con actividades, fichas públicas, y activación del rol coordinador con su alcance | 2.0 | Pendiente |
| 7 | Sacramentos y solicitudes | Catálogo, campos configurables, formulario público, bandeja con estados y bitácora | 2.5 | Pendiente |
| 8 | Cursos e inscripciones | Catálogo, temario, inscripción, cupo y lista de espera | 1.5 | Pendiente |
| 9 | Usuarios, roles y auditoría | CRUD de usuarios, asignación de pastorales, auditoría con filtros, repaso de seguridad | 1.0 | Pendiente |
| 10 | SEO y despliegue | `sitemap.xml`, Open Graph, datos estructurados, guía de cPanel, checklist final | 1.0 | Pendiente |
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

## Qué hace falta de la parroquia

El sitio no sirve sin contenido real. Conviene ir reuniendo, en paralelo al desarrollo:

- Fotografías del templo, del interior y de las celebraciones, en buena resolución.
- Historia de la parroquia, y su misión, visión y valores.
- Nombre, cargo, fotografía y semblanza breve del párroco, vicarios, diáconos y equipo
  pastoral.
- Horarios exactos de misas, confesiones, adoración y oficina parroquial.
- Requisitos y documentos que se piden hoy para cada sacramento.
- Listado de pastorales con su responsable, día y lugar de reunión.
- Dirección exacta, teléfono, correo y enlaces a redes sociales.
- Logotipo o escudo, si existe.

Mientras tanto se trabaja con datos de ejemplo, claramente marcados como tales.

## Riesgos

| Riesgo | Impacto | Mitigación |
|---|---|---|
| Portar el Router de `inventario` tal cual y dejar el sitio público detrás del login | Alto | Punto de revisión explícito en la etapa 1 y prueba manual de las rutas públicas sin sesión |
| XSS almacenado: habrá varios editores semi-confiables escribiendo HTML | Alto | Sanitizado con whitelist al guardar, más una CSP restrictiva |
| Ejecución de PHP subido a `uploads/` | Crítico | `.htaccess` que apaga el motor, validación de MIME real y nombres generados |
| Fuga de datos personales de menores | Crítico y legal | Separación de roles, filtro de autorización en la galería, auditoría de lecturas y anonimizado por retención |
| Un coordinador publica contenido inapropiado | Reputacional | Los coordinadores no tienen permiso de publicar; todo entra como borrador |
| `mod_rewrite` ausente o distinto en el hosting | Medio | Interruptor `URLS_AMIGABLES` y helpers de URL desde el primer día |
| `install.sql` se desincroniza del esquema real | Medio | Reinstalación limpia al cerrar cada etapa |
| Falta de contenido real al momento de publicar | Alto para la entrega | Solicitarlo desde ahora; datos de ejemplo marcados mientras tanto |
| El alcance es grande y abarca varias sesiones de trabajo | Medio | Etapas independientes y acumulativas, con hitos presentables |
