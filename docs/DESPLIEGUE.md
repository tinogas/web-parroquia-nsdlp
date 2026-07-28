# Despliegue en cPanel

> **Estado del documento.** Esqueleto. Se completa en la etapa 10, cuando el hosting esté
> contratado y se conozcan sus características reales. Lo que sigue es la guía prevista y
> las decisiones de diseño que ya se tomaron pensando en este momento.

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
   para archivos. Nunca 777.

9. **Ejecutar `setup.php`**, crear la cuenta de administrador y **borrar el archivo**.
   Mientras exista, cualquiera puede crear un administrador.

10. **Instalar el certificado SSL** —cPanel ofrece Let's Encrypt gratuito— y activar la
    redirección forzada a HTTPS.

11. **Cargar el contenido real** desde el panel: datos de la parroquia, horarios, equipo
    pastoral, pastorales, y los textos de los bloques.

## Verificación posterior al despliegue

- [ ] La portada carga en el dominio, sin errores y sin avisos de PHP visibles.
- [ ] `APP_DEBUG` está en `false` y ningún error se muestra al visitante.
- [ ] Las once rutas públicas responden sin exigir sesión.
- [ ] El panel exige contraseña y la sesión se mantiene.
- [ ] Una imagen subida desde el panel se ve en el sitio.
- [ ] `https://dominio/uploads/prueba.php` devuelve 403 y no ejecuta nada.
- [ ] `setup.php` ya no existe.
- [ ] `config/database.php` no es accesible por HTTP.
- [ ] `sitemap.xml` se genera y `robots.txt` lo referencia.
- [ ] El certificado SSL es válido y HTTP redirige a HTTPS.
- [ ] El formulario de contacto envía y el mensaje aparece en el panel.
- [ ] Los enlaces del footer, incluido el aviso de privacidad, funcionan.

## Respaldos

Sin acceso SSH no hay `cron` con `mysqldump` cómodo, pero cPanel tiene su propio
programador de tareas y su asistente de respaldos.

Como mínimo:

- Respaldo completo semanal de la cuenta desde el asistente de cPanel.
- Exportación mensual de la base de datos desde phpMyAdmin, guardada fuera del hosting.
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
