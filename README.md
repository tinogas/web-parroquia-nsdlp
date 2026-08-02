# Parroquia Nuestra Señora de la Paz — Sitio web y CMS

Sitio web público y sistema de administración de contenido a la medida para la Parroquia
Nuestra Señora de la Paz. Permite al equipo parroquial publicar avisos, eventos, horarios
de misas, información de sacramentos y actividades de las pastorales, y recibir
inscripciones a cursos, todo desde un panel de administración con usuarios y roles.

## Estado

Las diez etapas del plan original están implementadas y probadas en local, y sobre ellas se
ha trabajado un segundo bloque de cambios pedidos por la parroquia:

- **Sedes, centros y pastorales con módulo propio**
  ([issue #3](https://github.com/tinogas/web-parroquia-nsdlp/issues/3)): catálogo de centros,
  pastorales ligadas a su sede, contenido y documentos propios de
  cada pastoral, y tres módulos dedicados —MESC (visitas a enfermos, rutas y calendario de
  turnos), Catequesis (catequistas, periodos y grado) y Lector (turnos)—. El formulario de
  solicitud de sacramentos en línea se retiró por decisión del administrador: esa sección
  queda como información de requisitos.
- **Respaldos con restauración desde el panel** e **impersonación** ("Usar como…"), los dos a
  petición del administrador.
- **Revisión de módulos**: roles de administrador y consulta por pastoral, botones que no se
  dibujan cuando falta el permiso, horarios públicos agrupados por tipo con filtro de sede,
  eventos de varios días marcados en todos sus días, organigrama responsivo con vista de
  impresión en árbol, y datos de tutor en las inscripciones a cursos.
- **Agenda parroquial 2026 cargada**: 467 eventos y 22 actividades semanales, con el
  calendario público en vistas de día, semana, mes y año, y filtros de fecha en el panel.
- **Agenda interna y filtrado por pastoral**: un calendario propio del equipo en el panel
  (`/admin/agenda`) que reúne eventos y cursos, publicados y en borrador, en las mismas
  cuatro vistas. Ahí todas las pastorales ven la agenda completa; en los listados de
  eventos y cursos, en cambio, cada una trabaja sobre lo suyo y sobre lo general de la
  parroquia. Los cursos pasaron a administrarse por pastoral, igual que los eventos, y una
  y otros los publica la propia pastoral que los organiza —los avisos y la galería siguen
  pasando por revisión de un editor—. El calendario del sitio público no cambió.
- **Los roles se simplificaron y las cuentas salen del equipo pastoral**: se retiraron los
  seis roles con la pastoral en el nombre (Administrador MESC, Consulta Catequesis…) y
  quedan Administrador, Editor, Coordinador, Coordinador general, Consulta y Secretaría —el
  rol dice qué puede hacer y la asignación sobre qué pastoral y sede—. Y cada cuenta se
  crea eligiendo a alguien del equipo pastoral, de donde vienen su nombre, su teléfono y su
  foto: antes la misma coordinadora estaba escrita de tres formas distintas entre las dos
  listas. Coordinador general, además, ya puede dar de alta y editar las cuentas de
  Coordinador y Consulta de su propia pastoral, sin depender del administrador para eso.
- **El responsable de cada pastoral se elige del equipo pastoral**, no se escribe a mano:
  su nombre viene de su ficha y, si tiene cuenta, el correo de contacto de la pastoral se
  toma del correo de acceso de esa cuenta —corrige el caso real de MESC, donde los dos
  correos llevaban una letra distinta.
- **La portada muestra lo último publicado en Facebook**, con el Page Plugin oficial
  (un iframe, sin token ni API propia) sobre la misma URL que ya se usaba para el icono
  del pie.
- **El alcance pasó a tener dos mitades, la pastoral y la sede**: eventos y cursos guardan
  ahora en qué sede ocurren, y una cuenta administra *sus pastorales* × *sus sedes*. Así se
  distinguen las tres coordinadoras de catequesis —la de la parroquia, la de San Pío y la
  de Jesús el Señor— sin duplicar el catálogo de pastorales; sin sedes marcadas, la
  coordinación es general. De paso se retiró la herencia por centro, que le entregaba a
  quien administraba un centro todas las pastorales ligadas a él: la administradora de
  MESC, marcada en las tres sedes, había acabado pudiendo editar los cursos de catequesis.
- **El panel ya no muestra módulos ajenos**: las tarjetas de acceso rápido del dashboard
  (`/admin/panel`) filtraban solo por permiso, y `mesc.ver`/`catequesis.ver`/`lector.ver`
  los llevan todos los coordinadores a propósito —lo mismo que ya resolvía el menú
  lateral, pero le faltaba a esta pantalla—. La pastoral de Lectores, además, pasó a
  llamarse **Liturgia** en todo el panel (el slug ya lo decía el usuario real, el código
  seguía diciendo el nombre viejo).
- **Ministro de MESC, catequista y lector también se eligen del equipo pastoral**, mismo
  patrón que el responsable de pastoral y las cuentas: se encontró el mismo problema de
  fondo por tercera vez —una coordinadora estaba escrita como "Zulema" en un catálogo,
  "Zulema Alvarez" en otro y con su nombre completo en su ficha—. Con persona elegida,
  nombre y teléfono se toman de su ficha y se mantienen al día solos; sin persona, sigue
  funcionando el nombre libre de siempre.

Falta el resto del contenido real y el despliegue a producción — ver
[`docs/DESPLIEGUE.md`](docs/DESPLIEGUE.md). La fase 2 (aula virtual con tareas y
calificaciones sobre el catálogo de cursos) sigue fuera de este alcance. Ver
[`docs/PLAN.md`](docs/PLAN.md) para el detalle de cada etapa y del trabajo posterior.

## Stack

| Componente | Elección |
|---|---|
| Lenguaje | PHP 8.1 o superior, orientado a objetos |
| Base de datos | MySQL 5.7+ / MariaDB 10.3+ |
| Acceso a datos | PDO con sentencias preparadas |
| Framework | Ninguno — arquitectura MVC propia |
| Dependencias PHP | Ninguna. Sin Composer, sin `vendor/` |
| Frontend | Bootstrap 5.3.3 y Bootstrap Icons 1.11.3 por CDN, JavaScript vanilla. Leaflet 1.9.4 y OpenStreetMap solo en el mapa de MESC |
| Servidor | Apache con `mod_rewrite` (XAMPP en local, cPanel en producción) |

La decisión de no usar framework ni dependencias es deliberada: el sitio debe poder
instalarse en un hosting compartido de cPanel sin acceso SSH, copiando archivos e
importando un `.sql`. Ver [`docs/ARQUITECTURA.md`](docs/ARQUITECTURA.md).

El repositorio versiona un `package.json` que declara `bootstrap-icons`, pero **el sitio no
sirve esa copia**: las plantillas cargan los iconos del CDN, `node_modules/` está ignorado y
`npm` no hace falta ni para instalar ni para desplegar. Es constancia de la dependencia, no
un paso de compilación. El detalle —y la discrepancia de versiones que arrastra— está en
[`docs/ARQUITECTURA.md`](docs/ARQUITECTURA.md#dependencias-lo-que-se-sirve-y-lo-que-solo-está-declarado).

## Requisitos

- PHP 8.1 o superior con las extensiones `pdo_mysql`, `fileinfo`, `dom` y `mbstring`.
  `gd` es opcional (habilita el redimensionado de imágenes).
- MySQL 5.7 o superior, o MariaDB 10.3 o superior.
- Apache con `mod_rewrite` activo. Sin él, el sitio funciona igual con URLs de tipo
  `?area=publico&modulo=avisos` (ver la constante `URLS_AMIGABLES`).

## Instalación en local (XAMPP)

1. Clonar el repositorio dentro de `C:\xampp\htdocs\`:

   ```
   git clone https://github.com/tinogas/web-parroquia-nsdlp.git WebParroquia
   ```

2. Crear la base de datos en phpMyAdmin con cotejamiento `utf8mb4_unicode_ci`:

   ```sql
   CREATE DATABASE parroquia_nsdlp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. Importar `install.sql` en esa base de datos.

4. Copiar la configuración de conexión y editarla con las credenciales locales:

   ```
   copy config\database.example.php config\database.php
   ```

5. Abrir `http://localhost/WebParroquia/setup.php`, crear la cuenta de administrador y
   **borrar `setup.php`** cuando termine.

6. El sitio queda en `http://localhost/WebParroquia/` y el panel en
   `http://localhost/WebParroquia/admin/`.

No hace falta tocar `APP_URL`: se deduce de dónde está `index.php`, así que funciona
igual en una subcarpeta de XAMPP que en la raíz de un dominio. Lo que sí conviene
revisar antes de publicar es `APP_DEBUG`, que debe quedar en `false`.

`config/database.php` y el contenido de `uploads/` están excluidos del control de
versiones a propósito.

### Levantarlo sin Apache

Para probar sin montar XAMPP, el proyecto trae un router para el servidor integrado de
PHP que reproduce las reglas de reescritura del `.htaccess`:

```
php -S localhost:8080 router.php
```

`router.php` es solo una herramienta de desarrollo; en Apache y en cPanel no interviene.

## Estructura del proyecto

```
WebParroquia/
├── index.php            Front controller único
├── router.php           Reglas de reescritura para `php -S` (solo desarrollo)
├── setup.php            Instalador de un solo uso (se borra tras instalar)
├── install.sql          Esquema completo y datos semilla
├── .htaccess            Seguridad, cabeceras y URLs amigables
├── docs/                Documentación del proyecto
├── config/              Configuración de la aplicación y de la base de datos
├── core/                Núcleo: Router, Controller, Model, Auth, Session, Config…
├── shared/views/        Los dos layouts y sus parciales
├── modules/             Un directorio por sección (admin + público + modelo)
├── assets/              CSS y JavaScript propios
├── uploads/             Archivos subidos desde el panel
├── backups/             Respaldos .sql generados desde el panel (su contenido no se versiona)
└── herramientas/        Carga masiva por línea de órdenes; no se despliega
```

Cada módulo agrupa su controlador de administración, su controlador público, su modelo
y sus vistas. Ver [`docs/ARQUITECTURA.md`](docs/ARQUITECTURA.md).

## Herramientas de línea de órdenes

`herramientas/` no forma parte del sitio: nada de lo que hay ahí se ejecuta durante una
petición HTTP, y no se sube al servidor. Hoy contiene el par de scripts con los que se cargó
la agenda parroquial de 2026 —467 eventos y 22 actividades semanales— desde el `.xlsx` en el
que se transcribió la agenda impresa:

```
python herramientas/extraer_agenda.py                        # saca la hoja revisable
…se revisa y corrige agenda-2026-extraida.xlsx en Excel…
python herramientas/extraer_agenda.py --a-json agenda-2026.json
php herramientas/importar_agenda.php --dry-run               # dice qué haría
php herramientas/importar_agenda.php                         # lo hace
```

`extraer_agenda.py` necesita Python 3 con `openpyxl`, que es una dependencia **de la máquina
de desarrollo, no del proyecto**: el sitio no la usa. El importador es PHP puro y solo corre
desde la línea de órdenes; por HTTP responde 403.

Dos cosas que conviene saber antes de usarlos: la extracción marca en una hoja `Revisar` todo
lo que no pudo resolver sola, y ese repaso en Excel es parte del proceso, no un extra
opcional; y la importación es idempotente pero **no queda registrada en la auditoría**, así
que lo que da constancia de la carga es el respaldo previo. Las hojas de cálculo de origen y
el JSON intermedio están excluidos del control de versiones; los scripts, no. El porqué de
cada decisión está en
[`docs/ARQUITECTURA.md`](docs/ARQUITECTURA.md#carga-de-la-agenda-parroquial-2026-herramientas).

## Documentación

| Documento | Contenido |
|---|---|
| [`docs/PLAN.md`](docs/PLAN.md) | Alcance de la fase 1, lo excluido y por qué, etapas de implementación |
| [`docs/ARQUITECTURA.md`](docs/ARQUITECTURA.md) | Patrón MVC, routing de dos áreas, layouts, contenido editable, decisiones y sus trade-offs |
| [`docs/BASE-DE-DATOS.md`](docs/BASE-DE-DATOS.md) | Diccionario de datos completo y convenciones SQL |
| [`docs/PRIVACIDAD.md`](docs/PRIVACIDAD.md) | Obligaciones legales, qué nunca se publica, retención de datos |
| [`docs/DESPLIEGUE.md`](docs/DESPLIEGUE.md) | Puesta en producción en cPanel |

## Antes de tocar el código

Cuatro reglas que el proyecto no negocia:

1. **Cero dependencias en el servidor.** Nada de Composer, nada de `vendor/`, ninguna
   librería de PHP descargada: si algo hace falta, se escribe con las funciones nativas. Lo
   que el navegador carga de un CDN —Bootstrap, sus iconos, y Leaflet solo en el mapa de
   MESC— está autorizado explícitamente en la CSP del `.htaccess`; sumar un origen nuevo ahí
   es una decisión que se justifica, no un trámite.
2. **Nada se imprime sin escapar.** Todo eco de una vista usa `e()`, el atajo de
   `htmlspecialchars()` definido en `core/helpers.php`. La única excepción son los
   campos de contenido enriquecido, que pasan por `core/SanitizadorHtml.php` al
   guardarse; están señalados con un comentario en el código.
3. **Ninguna URL se escribe a mano.** Se construyen con `url_publica()`, `url_admin()`
   y `url_post()`. Es lo que permite que el sitio funcione con o sin `mod_rewrite`, y
   en subcarpeta o en la raíz de un dominio.
4. **Los datos personales de menores no se publican nunca.** Ver
   [`docs/PRIVACIDAD.md`](docs/PRIVACIDAD.md) antes de agregar cualquier vista pública
   que toque inscripciones, mensajes o fotografías.

## Licencia

Uso interno de la Parroquia Nuestra Señora de la Paz.
