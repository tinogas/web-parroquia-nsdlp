<?php
/**
 * Layout del sitio público.
 *
 * Variables que puede definir el controlador:
 *   $titulo           encabezado de la página
 *   $metaTitulo       título del navegador y de los buscadores
 *   $metaDescripcion  resumen para buscadores y redes
 *   $ogImagen         imagen de vista previa al compartir
 *   $urlCanonica      dirección oficial de la página
 *   $sinIndexar       true para pedir a los buscadores que no la registren
 *   $hero             HTML de la cabecera destacada, si la hay
 */
$nombreSitio  = Config::get('parroquia_nombre', APP_NAME);
$tituloPagina = isset($metaTitulo) && $metaTitulo !== ''
    ? $metaTitulo . ' — ' . $nombreSitio
    : $nombreSitio;
$descripcion  = $metaDescripcion ?? Config::get('meta_descripcion');
$imagenSocial = $ogImagen ?? Config::get('og_imagen');
$canonica     = $urlCanonica ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($tituloPagina) ?></title>

    <?php if ($descripcion !== ''): ?>
    <meta name="description" content="<?= e($descripcion) ?>">
    <?php endif; ?>
    <?php if (!empty($sinIndexar)): ?>
    <meta name="robots" content="noindex, follow">
    <?php endif; ?>
    <?php if ($canonica): ?>
    <link rel="canonical" href="<?= e($canonica) ?>">
    <?php endif; ?>

    <meta property="og:type"        content="website">
    <meta property="og:site_name"   content="<?= e($nombreSitio) ?>">
    <meta property="og:title"       content="<?= e($tituloPagina) ?>">
    <?php if ($descripcion !== ''): ?>
    <meta property="og:description" content="<?= e($descripcion) ?>">
    <?php endif; ?>
    <?php if ($imagenSocial !== ''): ?>
    <meta property="og:image"       content="<?= e(url_activo($imagenSocial)) ?>">
    <?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">

    <?php if (Config::tiene('favicon')): ?>
    <link rel="icon" href="<?= e(url_activo(Config::get('favicon'))) ?>">
    <?php endif; ?>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= e(url_activo('assets/css/publico.css')) ?>?v=<?= e(APP_VERSION) ?>">
</head>
<body class="sitio-publico">

<a class="visually-hidden-focusable salto-contenido" href="#contenido">Saltar al contenido</a>

<?php require BASE_PATH . '/shared/views/parciales/publico_navbar.php'; ?>

<?php if (!empty($hero)) { echo $hero; /* HTML compuesto por el controlador */ } ?>

<main id="contenido" class="py-4 py-md-5">
    <div class="container">
        <?php if (!empty($flash)) { require BASE_PATH . '/shared/views/parciales/flash.php'; } ?>
        <?php require $vistaPath; ?>
    </div>
</main>

<?php require BASE_PATH . '/shared/views/parciales/publico_footer.php'; ?>

<script>const APP_URL = <?= json_encode(url_base(), JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(url_activo('assets/js/publico.js')) ?>?v=<?= e(APP_VERSION) ?>"></script>
<?php if (isset($scriptExtra)) { echo $scriptExtra; } ?>
</body>
</html>
