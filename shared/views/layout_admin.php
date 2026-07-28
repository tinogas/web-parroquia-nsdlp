<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= isset($titulo) ? e($titulo) . ' — ' : '' ?>Panel · <?= e($appName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= e(url_activo('assets/css/app.css')) ?>?v=<?= e(APP_VERSION) ?>">
</head>
<body>

<nav class="navbar navbar-dark bg-dark px-3 fixed-top" style="z-index:1040">
    <div class="d-flex align-items-center gap-2">
        <button class="btn btn-sm btn-outline-secondary" id="sidebarToggle" aria-label="Mostrar u ocultar el menú">
            <i class="bi bi-list fs-5"></i>
        </button>
        <a class="navbar-brand fw-bold mb-0" href="<?= e(url_admin('panel')) ?>">
            <i class="bi bi-house-heart text-warning me-1"></i>
            <span class="d-none d-sm-inline"><?= e(APP_CORTO) ?></span>
            <span class="d-sm-none">Panel</span>
        </a>
    </div>

    <div class="d-flex align-items-center gap-3">
        <a href="<?= e(url_publica('inicio')) ?>" class="text-white-50 text-decoration-none small" target="_blank"
           title="Abrir el sitio en una pestaña nueva">
            <i class="bi bi-box-arrow-up-right me-1"></i><span class="d-none d-md-inline">Ver el sitio</span>
        </a>

        <div class="dropdown">
            <button class="btn btn-sm btn-outline-light dropdown-toggle d-flex align-items-center gap-2"
                    data-bs-toggle="dropdown" aria-expanded="false">
                <img src="<?= e(foto_o_avatar($usuario['foto'] ?? null, $usuario['nombre'] ?? '', 52)) ?>" alt=""
                     class="rounded-circle" style="width:26px;height:26px;object-fit:cover">
                <span class="d-none d-sm-inline"><?= e($usuario['nombre'] ?? '') ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><span class="dropdown-item-text text-muted small"><?= e($usuario['email'] ?? '') ?></span></li>
                <li><span class="dropdown-item-text text-muted small"><?= e(Auth::nombreRol()) ?></span></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="<?= e(url_post('admin', 'auth', 'logout')) ?>" class="m-0">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <button type="submit" class="dropdown-item text-danger">
                            <i class="bi bi-box-arrow-right me-1"></i> Cerrar sesión
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</nav>

<?php require BASE_PATH . '/shared/views/parciales/admin_sidebar.php'; ?>

<div id="main-content" class="main-content">
    <?php require BASE_PATH . '/shared/views/parciales/flash.php'; ?>
    <?php require $vistaPath; ?>
</div>

<script>const APP_URL = <?= json_encode(url_base(), JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(url_activo('assets/js/app.js')) ?>?v=<?= e(APP_VERSION) ?>"></script>
<?php if (isset($scriptExtra)) { echo $scriptExtra; } ?>
</body>
</html>
