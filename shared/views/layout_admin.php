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
<body<?= Auth::estaImpersonando() ? ' class="impersonando"' : '' ?>>

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

        <?php
        // Perfiles adicionales propios: no aplica mientras se impersona -eso
        // sigue entrando siempre con el principal, ver AuthController::
        // cambiarPerfil()-. cuentaBase trae el rol REAL de usuarios.rol, no
        // el del perfil activo en sesión, para poder ofrecerlo como opción
        // cuando no es el que ya está en uso.
        $perfilesPropios = [];
        $cuentaBase      = null;
        if (Auth::estaAutenticado() && !Auth::estaImpersonando()) {
            require_once BASE_PATH . '/modules/usuarios/UsuarioPerfilModel.php';
            $perfilesPropios = (new UsuarioPerfilModel())->activosDeUsuario((int) $usuario['id']);
            if ($perfilesPropios) {
                require_once BASE_PATH . '/modules/usuarios/UsuarioModel.php';
                $cuentaBase = (new UsuarioModel())->porId((int) $usuario['id']);
            }
        }
        ?>
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
                <?php if (!empty($usuario['perfil_nombre'])): ?>
                <?php /* Con cuál de sus perfiles adicionales entró hoy — ver
                         docs/ARQUITECTURA.md, "Perfiles adicionales". */ ?>
                <li>
                    <span class="dropdown-item-text small">
                        <span class="badge bg-info-subtle text-info-emphasis">
                            <i class="bi bi-person-badge me-1"></i><?= e($usuario['perfil_nombre']) ?>
                        </span>
                    </span>
                </li>
                <?php endif; ?>
                <?php if ($perfilesPropios): ?>
                <li>
                    <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modalCambiarPerfil">
                        <i class="bi bi-arrow-left-right me-1"></i> Cambiar de perfil
                    </button>
                </li>
                <?php endif; ?>
                <li><hr class="dropdown-divider"></li>
                <?php if (Auth::estaImpersonando()): ?>
                <li>
                    <form method="POST" accept-charset="UTF-8"
                          action="<?= e(url_post('admin', 'auth', 'terminar_impersonacion')) ?>" class="m-0">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <button type="submit" class="dropdown-item">
                            <i class="bi bi-arrow-return-left me-1"></i> Volver a Admin
                        </button>
                    </form>
                </li>
                <?php elseif (Auth::tienePermiso('usuarios.impersonar')): ?>
                <li>
                    <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modalUsarComo">
                        <i class="bi bi-people me-1"></i> Usar como…
                    </button>
                </li>
                <?php endif; ?>
                <li>
                    <form method="POST" accept-charset="UTF-8" action="<?= e(url_post('admin', 'auth', 'logout')) ?>" class="m-0">
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

<?php if (Auth::estaImpersonando()): ?>
<div class="banner-impersonando">
    <i class="bi bi-person-badge me-1"></i>
    Actuando como <strong><?= e($usuario['nombre'] ?? '') ?></strong>
    <span class="badge bg-dark ms-1"><?= e(Auth::nombreRol()) ?></span>
    <form method="POST" accept-charset="UTF-8" action="<?= e(url_post('admin', 'auth', 'terminar_impersonacion')) ?>"
          class="d-inline m-0 ms-2">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <button type="submit" class="btn btn-sm btn-dark">Volver a Admin</button>
    </form>
</div>
<?php endif; ?>

<?php require BASE_PATH . '/shared/views/parciales/admin_sidebar.php'; ?>

<div id="main-content" class="main-content">
    <?php require BASE_PATH . '/shared/views/parciales/flash.php'; ?>
    <?php require $vistaPath; ?>
</div>

<?php if ($perfilesPropios): ?>
<div class="modal fade" id="modalCambiarPerfil" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h2 class="h6 modal-title fw-bold">Cambiar de perfil</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">Elige con cuál sigues, sin cerrar sesión.</p>
                <div class="list-group">
                    <?php if ($usuario['perfil_id'] !== null && Auth::rolPrincipalTieneAlcance($cuentaBase)): ?>
                    <?php /* El principal no está activo ahora mismo y sí tiene alguna
                             pastoral asignada: se ofrece de vuelta. Sin pastorales, no
                             lleva a nada -Auth::rolPrincipalTieneAlcance()-, así que no
                             se ofrece. */ ?>
                    <form method="POST" accept-charset="UTF-8" action="<?= e(url_post('admin', 'auth', 'cambiar_perfil')) ?>"
                          class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-2 py-2">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="perfil_id" value="">
                        <span class="fw-semibold"><?= e(ROLES_NOMBRES[$cuentaBase['rol']] ?? $cuentaBase['rol']) ?></span>
                        <button type="submit" class="btn btn-sm btn-outline-primary flex-shrink-0">Usar</button>
                    </form>
                    <?php endif; ?>
                    <?php foreach ($perfilesPropios as $perfil): ?>
                        <?php if ((int) $perfil['id'] === (int) ($usuario['perfil_id'] ?? 0)) continue; ?>
                        <form method="POST" accept-charset="UTF-8" action="<?= e(url_post('admin', 'auth', 'cambiar_perfil')) ?>"
                              class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-2 py-2">
                            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                            <input type="hidden" name="perfil_id" value="<?= (int) $perfil['id'] ?>">
                            <span>
                                <span class="fw-semibold"><?= e($perfil['nombre']) ?></span>
                                <span class="text-muted small d-block">
                                    <?= e(ROLES_NOMBRES[$perfil['rol']] ?? $perfil['rol']) ?> de
                                    <?= e($perfil['pastoral_nombre']) ?>
                                </span>
                            </span>
                            <button type="submit" class="btn btn-sm btn-outline-primary flex-shrink-0">Usar</button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (Auth::tienePermiso('usuarios.impersonar') && !Auth::estaImpersonando()): ?>
<?php
require_once BASE_PATH . '/modules/usuarios/UsuarioModel.php';
$candidatosImpersonar = (new UsuarioModel())->paraImpersonar();
?>
<div class="modal fade" id="modalUsarComo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h2 class="h6 modal-title fw-bold">Usar como…</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">
                    Vas a operar el panel con la sesión de esa cuenta, sin conocer su contraseña. Queda
                    registrado en la auditoría quién eres tú en realidad.
                </p>
                <input type="text" class="form-control form-control-sm mb-3" id="buscarUsarComo"
                       placeholder="Buscar por nombre o correo…" autocomplete="off">
                <?php if (!$candidatosImpersonar): ?>
                <p class="text-muted small mb-0">No hay otras cuentas activas.</p>
                <?php else: ?>
                <div class="list-group">
                    <?php foreach ($candidatosImpersonar as $candidato): ?>
                    <form method="POST" accept-charset="UTF-8" action="<?= e(url_post('admin', 'auth', 'impersonar')) ?>"
                          class="list-group-item list-group-item-action d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 py-2 fila-usar-como"
                          data-texto="<?= e(mb_strtolower($candidato['nombre'] . ' ' . $candidato['email'])) ?>">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="usuario_id" value="<?= (int) $candidato['id'] ?>">
                        <span class="fila-usar-como-nombre">
                            <?= e($candidato['nombre']) ?>
                            <span class="text-muted small d-block"><?= e($candidato['email']) ?></span>
                        </span>
                        <span class="d-flex align-items-center gap-2 flex-shrink-0">
                            <span class="badge bg-secondary-subtle text-secondary-emphasis"><?= e(ROLES_NOMBRES[$candidato['rol']] ?? $candidato['rol']) ?></span>
                            <button type="submit" class="btn btn-sm btn-outline-primary">Usar</button>
                        </span>
                    </form>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var buscador = document.getElementById('buscarUsarComo');
    if (!buscador) { return; }
    buscador.addEventListener('input', function () {
        var texto = this.value.trim().toLowerCase();
        document.querySelectorAll('.fila-usar-como').forEach(function (fila) {
            fila.style.display = fila.dataset.texto.includes(texto) ? '' : 'none';
        });
    });
})();
</script>
<?php endif; ?>

<script>const APP_URL = <?= json_encode(url_base(), JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(url_activo('assets/js/app.js')) ?>?v=<?= e(APP_VERSION) ?>"></script>
<?php if (isset($scriptExtra)) { echo $scriptExtra; } ?>
</body>
</html>
