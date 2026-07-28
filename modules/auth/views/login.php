<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Acceso — <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= e(url_activo('assets/css/app.css')) ?>?v=<?= e(APP_VERSION) ?>">
</head>
<body class="pantalla-acceso">

<div class="caja-acceso">
    <div class="card shadow-lg border-0">
        <div class="card-body p-4 p-md-5">

            <div class="text-center mb-4">
                <div class="icono-acceso"><i class="bi bi-house-heart"></i></div>
                <h1 class="h5 fw-bold mt-3 mb-1"><?= e(Config::get('parroquia_nombre', APP_NAME)) ?></h1>
                <p class="text-muted small mb-0">Panel de administración</p>
            </div>

            <?php foreach ($flash as $tipo => $mensajes): ?>
                <?php foreach ($mensajes as $mensaje): ?>
                <div class="alert <?= $tipo === 'error' ? 'alert-danger' : 'alert-info' ?> py-2 small">
                    <i class="bi <?= $tipo === 'error' ? 'bi-x-circle' : 'bi-info-circle' ?> me-1"></i>
                    <?= e($mensaje) ?>
                </div>
                <?php endforeach; ?>
            <?php endforeach; ?>

            <form method="POST" action="<?= e(url_post('admin', 'auth', 'login')) ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

                <div class="mb-3">
                    <label for="email" class="form-label fw-semibold">Correo electrónico</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" name="email" id="email" class="form-control"
                               value="<?= e($_POST['email'] ?? '') ?>"
                               autocomplete="username" autofocus required>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label fw-semibold">Contraseña</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" id="password" class="form-control"
                               autocomplete="current-password" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 fw-semibold">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Entrar
                </button>
            </form>

            <div class="text-center mt-4">
                <a href="<?= e(url_publica('inicio')) ?>" class="small text-decoration-none text-muted">
                    <i class="bi bi-arrow-left me-1"></i>Volver al sitio
                </a>
            </div>

        </div>
    </div>
</div>

</body>
</html>
