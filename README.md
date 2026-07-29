# Parroquia Nuestra Señora de la Paz — Sitio web y CMS

Sitio web público y sistema de administración de contenido a la medida para la Parroquia
Nuestra Señora de la Paz. Permite al equipo parroquial publicar avisos, eventos, horarios
de misas, información de sacramentos y actividades de las pastorales, y recibir
inscripciones a cursos, todo desde un panel de administración con usuarios y roles.

## Estado

Fase 1 completa: sitio público y panel de administración con las diez etapas del plan
implementadas y probadas en local. Pendiente de contenido real y de desplegarse a
producción — ver [`docs/DESPLIEGUE.md`](docs/DESPLIEGUE.md). La fase 2 (aula virtual con
tareas y calificaciones sobre el catálogo de cursos) queda fuera de este alcance.
Ver [`docs/PLAN.md`](docs/PLAN.md) para el detalle de cada etapa.

## Stack

| Componente | Elección |
|---|---|
| Lenguaje | PHP 8.1 o superior, orientado a objetos |
| Base de datos | MySQL 5.7+ / MariaDB 10.3+ |
| Acceso a datos | PDO con sentencias preparadas |
| Framework | Ninguno — arquitectura MVC propia |
| Dependencias PHP | Ninguna. Sin Composer, sin `vendor/` |
| Frontend | Bootstrap 5.3.3 y Bootstrap Icons por CDN, JavaScript vanilla |
| Servidor | Apache con `mod_rewrite` (XAMPP en local, cPanel en producción) |

La decisión de no usar framework ni dependencias es deliberada: el sitio debe poder
instalarse en un hosting compartido de cPanel sin acceso SSH, copiando archivos e
importando un `.sql`. Ver [`docs/ARQUITECTURA.md`](docs/ARQUITECTURA.md).

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
├── assets/              CSS, JavaScript e imágenes propias
├── uploads/             Archivos subidos desde el panel
└── cli/                 Scripts de línea de comandos (cron), sin acceso por HTTP
```

Cada módulo agrupa su controlador de administración, su controlador público, su modelo
y sus vistas. Ver [`docs/ARQUITECTURA.md`](docs/ARQUITECTURA.md).

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

1. **Cero dependencias.** Nada de Composer, nada de `vendor/`, nada de librerías
   descargadas. Si algo hace falta, se escribe con las funciones nativas de PHP.
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
