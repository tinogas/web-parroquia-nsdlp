# Despliegue en cPanel

> **Estado del documento.** Completo en lo que no depende del hosting real: los pasos, la
> checklist y el respaldo ya están pensados para un cPanel genérico y no deberían cambiar.
> Lo que falta —marcado en "Pendiente de resolver"— es información que solo se conoce
> cuando el hosting esté contratado: dominio, límites reales del plan y quién tendrá cada
> acceso.

## Supuestos de partida

Hasta conocer el hosting real, el desarrollo asume el escenario más restrictivo:

- PHP 8.1 o superior, seleccionable desde el gestor de cPanel.
- MySQL 5.7 o MariaDB 10.3 o superior.
- **Sin acceso SSH.** Todo se hace por el gestor de archivos y phpMyAdmin.
- **Sin Composer.** Por eso el proyecto no tiene dependencias.
- Apache con `mod_rewrite`, aunque el sitio funciona igual si no lo tuviera.

Si el hosting resulta más generoso, mejor. Nada de lo construido lo impide.

## Diferencias entre el entorno local y producción

| Aspecto | XAMPP local | cPanel |
|---|---|---|
| `APP_URL` | `/WebParroquia` | `''` (raíz del dominio) — **se deduce sola** |
| `APP_DEBUG` | `true` | **`false`** ← hay que cambiarlo a mano |
| Raíz web | `htdocs/WebParroquia/` | `public_html/` |
| HTTPS | No | Obligatorio, forzado |
| `RewriteBase` | `/WebParroquia/` | `/` ← hay que cambiarlo a mano |

`APP_URL` se calcula a partir de la ubicación de `index.php`, así que el cambio de
subcarpeta a raíz de dominio no requiere editar nada. Ninguna ruta se escribe a mano en
el código: todas salen de los helpers `url_publica()`, `url_admin()`, `url_post()` y
`url_activo()`. Aun así conviene probar el sitio **en una subcarpeta y en la raíz de un
vhost** antes de publicar, porque ahí es donde aparecen las rutas olvidadas.

## Pasos previstos

1. **Crear la base de datos** en cPanel, con cotejamiento `utf8mb4_unicode_ci`, y un
   usuario con todos los privilegios sobre ella. Anotar el nombre real: cPanel antepone un
   prefijo de cuenta.

2. **Seleccionar la versión de PHP** en *MultiPHP Manager* y verificar en *Select PHP
   Version* que estén activas las extensiones `pdo_mysql`, `fileinfo`, `dom` y `mbstring`.
   Activar `gd` si está disponible.

3. **Subir los archivos** a `public_html/`. Excluir del paquete: `.git/`, `docs/` y
   `config/database.php`.

4. **Importar `install.sql`** desde phpMyAdmin.

5. **Crear `config/database.php`** a partir del ejemplo, con las credenciales reales.

6. **Ajustar `config/app.php`**: `APP_DEBUG` a `false`, y `URLS_AMIGABLES` según si
   `mod_rewrite` responde. `APP_URL` no se toca: se deduce sola.

7. **Ajustar `RewriteBase`** en `.htaccess` a `/`.

8. **Permisos de escritura** en `uploads/` y sus subcarpetas: 755 para directorios y 644
   para archivos. Nunca 777. `backups/` la crea sola el módulo de respaldos en el primer
   uso, con los mismos permisos.

9. **Ejecutar `setup.php`**, crear la cuenta de administrador y **borrar el archivo**.
   Mientras exista, cualquiera puede crear un administrador.

10. **Instalar el certificado SSL** —cPanel ofrece Let's Encrypt gratuito— y activar la
    redirección forzada a HTTPS.

11. **Programar la purga de solicitudes vencidas** en *Cron Jobs* de cPanel (no requiere
    SSH: esa herramienta existe también en las cuentas sin acceso a terminal). Una vez al
    día basta:
    ```
    php -f /home/usuario/public_html/cli/purgar_solicitudes.php
    ```
    Sustituir la ruta por la real de la cuenta. Sin este paso, el plazo de retención de
    `docs/PRIVACIDAD.md` depende de que alguien entre al panel y pulse "Purgar vencidas" a
    mano — el botón sigue ahí como respaldo si el hosting no ofreciera *Cron Jobs*.

12. **Sustituir el dominio de ejemplo** en `robots.txt` (la línea `Sitemap:`) por el
    dominio real: es un archivo estático, no se genera solo como `sitemap.xml`.

13. **Cargar el contenido real** desde el panel: datos de la parroquia, horarios, equipo
    pastoral, pastorales, y los textos de los bloques. Los datos de contacto y redes
    sociales alimentan también los datos estructurados `Church` de la portada.

## Verificación posterior al despliegue

- [ ] La portada carga en el dominio, sin errores y sin avisos de PHP visibles.
- [ ] `APP_DEBUG` está en `false` y ningún error se muestra al visitante.
- [ ] Las once rutas públicas responden sin exigir sesión.
- [ ] El panel exige contraseña y la sesión se mantiene.
- [ ] Una imagen subida desde el panel se ve en el sitio.
- [ ] `https://dominio/uploads/prueba.php` devuelve 403 y no ejecuta nada.
- [ ] `https://dominio/cli/purgar_solicitudes.php` devuelve 403 y no ejecuta nada.
- [ ] `https://dominio/backups/algo.sql` devuelve 403, aunque no se conozca el nombre real.
- [ ] Generar un respaldo desde el panel funciona y el archivo se puede descargar.
- [ ] Restaurar ese mismo respaldo funciona y deja además un respaldo de seguridad nuevo
      (probar esto en un momento sin visitas reales al sitio, no en horas pico).
- [ ] `setup.php` ya no existe.
- [ ] `config/database.php` no es accesible por HTTP.
- [ ] `sitemap.xml` se genera, incluye el contenido publicado y usa el dominio real.
- [ ] `robots.txt` referencia el sitemap con el dominio real.
- [ ] El certificado SSL es válido y HTTP redirige a HTTPS.
- [ ] El formulario de contacto envía y el mensaje aparece en el panel.
- [ ] Los enlaces del footer, incluido el aviso de privacidad, funcionan.
- [ ] La tarea programada de purga aparece en *Cron Jobs* y corrió al menos una vez sin error.

## Checklist de seguridad

Repaso final antes de anunciar el sitio. La mayoría de estos puntos ya están resueltos por
diseño desde etapas anteriores (ver `docs/ARQUITECTURA.md`); esta lista es para
**verificarlos en el entorno real**, no para construirlos de nuevo.

**Configuración del entorno**
- [ ] `APP_DEBUG` en `false`. Con `true`, un error de PHP muestra rutas del servidor y,
      alguna vez, datos de la consulta que falló.
- [ ] `setup.php` borrado. Mientras exista, cualquiera que lo encuentre crea un
      administrador.
- [ ] `config/database.php` fuera de `.git` (revisar `.gitignore`) y no accesible por HTTP.
- [ ] `cli/purgar_solicitudes.php` no accesible por HTTP (403) — solo debe poder invocarse
      desde el cron.
- [ ] `backups/` no accesible por HTTP (403), y los `.sql` que contenga no terminan nunca
      en un repositorio público ni en un correo sin cifrar.
- [ ] HTTPS forzado y certificado válido; `session.cookie_samesite=Strict` y
      `cookie_httponly` activos (ya vienen en `.htaccess`, confirmar que el hosting no los
      sobrescribe).

**Superficie de ataque**
- [ ] `uploads/archivo.php` (o cualquier `.php*` dentro de `uploads/`) devuelve 403: el
      `.htaccess` de esa carpeta desactiva la ejecución de PHP por completo.
- [ ] Subir un archivo renombrado a `.jpg` que en realidad es un script falla por MIME real
      (`finfo`), no por extensión.
- [ ] Guardar `<script>alert(1)</script>` en un campo de contenido enriquecido (bloques,
      avisos, eventos, pastorales, sacramentos, cursos, páginas) se guarda sin la etiqueta:
      `core/SanitizadorHtml.php` la retira al guardar, no al mostrar.
- [ ] Un POST sin `_csrf` válido responde 403 en cualquier formulario del panel y en los
      formularios públicos con envío (contacto, solicitudes, inscripciones).
- [ ] Las cabeceras `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` y
      `Content-Security-Policy` llegan en la respuesta (`curl -I` a cualquier página).

**Datos personales (LFPDPPP) — ver `docs/PRIVACIDAD.md`**
- [ ] El aviso de privacidad (`/aviso-de-privacidad`) está publicado y con los datos reales
      de la parroquia, no el texto de plantilla con corchetes.
- [ ] Ninguna foto de la galería con menores está marcada `autorizacion_imagen` sin el
      permiso firmado correspondiente.
- [ ] La tarea de purga (paso 11 de arriba) está programada y corriendo.

**Cuentas y accesos**
- [ ] Cada persona con acceso al panel tiene su propia cuenta; nadie comparte la de
      administrador.
- [ ] Los roles asignados corresponden a la función real de cada quien: `secretaria` para
      quien atiende trámites, `coordinador` solo con sus pastorales, `editor` sin acceso a
      usuarios ni configuración.
- [ ] La contraseña de la cuenta de administrador no es la que trae este documento como
      ejemplo en ningún paso previo.

## Respaldos

Sin acceso SSH no hay `cron` con `mysqldump` cómodo, pero cPanel tiene su propio
programador de tareas y su asistente de respaldos. El panel además trae su propio módulo
de respaldos (**Administración → Respaldos**, solo administrador): genera un `.sql` con
estructura y datos vía PDO —sin `mysqldump`, por la misma razón de siempre—, se descarga en
un clic, y **también se puede restaurar directamente desde ahí** (antes de reemplazar nada,
genera solo un respaldo de seguridad del estado actual, por si hay que volver atrás). Es
cómodo para un respaldo o una reversión puntual antes de un cambio grande, pero no sustituye
lo de abajo: no cubre `uploads/` ni corre solo.

Como mínimo:

- Respaldo completo semanal de la cuenta desde el asistente de cPanel.
- Exportación mensual de la base de datos desde phpMyAdmin, guardada fuera del hosting (o
  un respaldo generado desde **Administración → Respaldos** y descargado a un lugar seguro).
- Copia de `uploads/` junto con la base de datos: un respaldo del contenido sin las
  imágenes no sirve de nada.

## Actualizaciones posteriores

Mientras no haya datos reales, `install.sql` se reimporta desde cero. **En cuanto el sitio
esté en producción eso deja de ser una opción**: a partir de ahí, cada cambio de esquema
necesita su propio script `ALTER TABLE`, guardado en `docs/migraciones/` con la fecha en
el nombre y aplicado por phpMyAdmin.

Conviene marcar ese momento con claridad. Es el punto en el que el proyecto deja de ser
desechable.

## Pendiente de resolver

- Nombre del dominio definitivo.
- Proveedor de hosting y sus límites reales: espacio, versión de PHP, correo saliente.
- Si se usará correo del dominio y con qué cuenta.
- Quién tendrá la cuenta de administrador y quién los accesos de cPanel.
