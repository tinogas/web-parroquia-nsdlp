<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Elige con cuál entrar — <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= e(url_activo('assets/css/app.css')) ?>?v=<?= e(APP_VERSION) ?>">
</head>
<body class="pantalla-acceso">

<div class="caja-acceso">
    <div class="card shadow-lg border-0">
        <div class="card-body p-4 p-md-5">

            <div class="text-center mb-4">
                <div class="icono-acceso"><i class="bi bi-person-badge"></i></div>
                <h1 class="h5 fw-bold mt-3 mb-1"><?= e($usuario['nombre']) ?></h1>
                <p class="text-muted small mb-0">¿Con cuál entras hoy?</p>
            </div>

            <?php foreach ($flash as $tipo => $mensajes): ?>
                <?php foreach ($mensajes as $mensaje): ?>
                <div class="alert <?= $tipo === 'error' ? 'alert-danger' : 'alert-info' ?> py-2 small">
                    <i class="bi <?= $tipo === 'error' ? 'bi-x-circle' : 'bi-info-circle' ?> me-1"></i>
                    <?= e($mensaje) ?>
                </div>
                <?php endforeach; ?>
            <?php endforeach; ?>

            <form method="POST" accept-charset="UTF-8" action="<?= e(url_post('admin', 'auth', 'elegir_perfil')) ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

                <div class="list-group mb-4">
                    <?php /* Sin ninguna pastoral asignada, el rol principal no lleva a nada
                             -Auth::rolPrincipalTieneAlcance()-: no se ofrece como opción. */ ?>
                    <?php if ($rolPrincipalUtil): ?>
                    <label class="list-group-item">
                        <input class="form-check-input me-2" type="radio" name="perfil_id" value="" checked>
                        <span class="fw-semibold"><?= e(ROLES_NOMBRES[$usuario['rol']] ?? $usuario['rol']) ?></span>
                        <div class="text-muted small">Tu cuenta de siempre.</div>
                    </label>
                    <?php endif; ?>
                    <?php foreach ($perfiles as $i => $perfil): ?>
                    <label class="list-group-item">
                        <input class="form-check-input me-2" type="radio" name="perfil_id" value="<?= (int) $perfil['id'] ?>"
                               <?= (!$rolPrincipalUtil && $i === 0) ? 'checked' : '' ?>>
                        <span class="fw-semibold"><?= e($perfil['nombre']) ?></span>
                        <div class="text-muted small">
                            <?= e(ROLES_NOMBRES[$perfil['rol']] ?? $perfil['rol']) ?>
                            de <?= e($perfil['pastoral_nombre']) ?><?= $perfil['centro_nombre'] ? ' · ' . e($perfil['centro_nombre']) : '' ?>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>

                <button type="submit" class="btn btn-primary w-100 fw-semibold">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Entrar
                </button>
            </form>

        </div>
    </div>
</div>

</body>
</html>
