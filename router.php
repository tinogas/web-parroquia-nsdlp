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

// Detrás de un túnel (devtunnel, cloudflared) la petición llega desde esta
// misma máquina con «Host: localhost:8080», y el nombre con el que la abrió el
// visitante viene aparte, en X-Forwarded-Host. Se toma ese, y HTTPS si el túnel
// lo era, para que las URL absolutas (canonical, el enlace personal) y la
// vuelta de redirectBack() usen la liga del túnel y no localhost. Solo se cree
// cuando la conexión viene de 127.0.0.1 o ::1, que es por donde entra el túnel;
// el relevo de Microsoft además pisa el X-Forwarded-Host que mande el visitante.
// Va antes que todo porque los formularios apuntan a /index.php, que es un
// archivo y sale por el bloque de abajo. En Apache esto no existe: allá manda
// el Host verdadero.
$deLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
$reenviado = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? '';
if ($deLocal && preg_match('/^[a-z0-9.-]+(:\d{1,5})?$/i', $reenviado)) {
    $_SERVER['HTTP_HOST'] = $reenviado;
    if (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
        $_SERVER['HTTPS'] = 'on';
    }
}

// Los archivos que existen en disco se sirven tal cual (assets, uploads).
$archivo = __DIR__ . $ruta;
if ($ruta !== '/' && is_file($archivo)) {
    // Salvo lo que en Apache bloquean .htaccess y uploads/.htaccess: el código
    // del proyecto y cualquier script dentro de uploads. Se replica aquí para
    // que el entorno de desarrollo se comporte como el servidor real, y se
    // cierra además lo que en el servidor real no está porque no se sube
    // —herramientas/, backups/, .git/ y todo lo que empieza con punto—: este
    // servidor a veces se comparte (un túnel, la red de la casa) para que otros
    // revisen el sitio, y entonces queda tan expuesto como el de verdad.
    //
    // Las reglas miran la ruta real del archivo, en minúsculas. En Windows
    // «/BACKUPS/x.sql», «/backups/x.sql.» —con punto al final— o «/.GIT/HEAD»
    // abren el mismo archivo que la ruta bien escrita, y comparando la ruta tal
    // como llega se colaban.
    $raiz = realpath(__DIR__);
    $real = realpath($archivo);
    if ($real === false || !str_starts_with($real, $raiz . DIRECTORY_SEPARATOR)) {
        http_response_code(403);
        exit('403');
    }
    $relativa = strtolower(str_replace('\\', '/', substr($real, strlen($raiz))));
    if (preg_match('#^/(config|core|modules|shared|docs|cli|backups|herramientas)/#', $relativa)
        || preg_match('#/\.#', $relativa)
        || preg_match('#\.(sql|log|env|md|json|lock|sh|bak|ini)$#', $relativa)
        || preg_match('#^/uploads/.*\.(php|phtml|phar|cgi|pl|py|sh)$#', $relativa)) {
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
