<?php
/**
 * router.php — Solo para el servidor de desarrollo de PHP:
 *
 *     php -S localhost:8080 router.php
 *
 * El servidor embebido no lee .htaccess, así que este archivo reproduce sus
 * reglas de reescritura para poder probar las URLs amigables sin montar Apache.
 * En XAMPP y en cPanel no interviene: manda el .htaccess.
 */

$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$ruta = '/' . trim($ruta, '/');

// Los archivos que existen en disco se sirven tal cual (assets, uploads).
$archivo = __DIR__ . $ruta;
if ($ruta !== '/' && is_file($archivo)) {
    // Salvo lo que en Apache bloquean .htaccess y uploads/.htaccess: el código
    // del proyecto y cualquier script dentro de uploads. Se replica aquí para
    // que el entorno de desarrollo se comporte como el servidor real.
    if (preg_match('#^/(config|core|modules|shared|docs)/#', $ruta)
        || preg_match('#\.(sql|log|env|md|json|lock|sh|bak|ini)$#', $ruta)
        || preg_match('#^/uploads/.*\.(php|phtml|phar|cgi|pl|py|sh)$#i', $ruta)) {
        http_response_code(403);
        exit('403');
    }
    return false;
}

// El instalador se abre directamente.
if ($ruta === '/setup.php') {
    require __DIR__ . '/setup.php';
    return true;
}

// ---- Mismas reglas que el .htaccess ----
if (preg_match('#^/admin/?$#', $ruta)) {
    $_GET['area'] = 'admin';
    $_GET['modulo'] = 'panel';
} elseif (preg_match('#^/admin/([a-z_]+)(?:/([a-z_]+))?/?$#', $ruta, $m)) {
    $_GET['area']   = 'admin';
    $_GET['modulo'] = $m[1];
    if (!empty($m[2])) {
        $_GET['accion'] = $m[2];
    }
} elseif ($ruta === '/sitemap.xml') {
    $_GET['area']   = 'publico';
    $_GET['modulo'] = 'sitemap';
} elseif (preg_match('#^/([a-z0-9-]+)(?:/([a-z0-9-]+))?(?:/([a-z0-9-]+))?/?$#', $ruta, $m)) {
    $_GET['area']   = 'publico';
    $_GET['modulo'] = $m[1];
    if (!empty($m[2])) { $_GET['slug']   = $m[2]; }
    if (!empty($m[3])) { $_GET['accion'] = $m[3]; }
}

require __DIR__ . '/index.php';
