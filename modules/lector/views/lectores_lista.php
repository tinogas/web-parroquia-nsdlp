<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <nav aria-label="Ubicación">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item"><a href="<?= e(url_admin('lector')) ?>" class="text-decoration-none">Lectores</a></li>
                <li class="breadcrumb-item active" aria-current="page">Catálogo</li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0">Catálogo de lectores</h1>
    </div>
    <a href="<?= e(url_admin('lector')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver al calendario
    </a>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <?php if (!$lectores): ?>
        <p class="text-muted small mb-3">Todavía no hay lectores registrados.</p>
        <?php else: ?>
        <div class="table-responsive mb-3">
            <table class="table table-sm align-middle">
                <thead class="table-light">
                    <tr><th>Nombre</th><th class="d-none d-md-table-cell">Contacto</th><th>&nbsp;</th></tr>
                </thead>
                <tbody>
                <?php foreach ($lectores as $lector): ?>
                    <tr class="<?= $lector['activo'] ? '' : 'text-muted' ?>">
                        <td><?= e($lector['nombre']) ?><?= $lector['activo'] ? '' : ' (inactivo)' ?></td>
                        <td class="d-none d-md-table-cell small">
                            <?= e(trim(($lector['telefono'] ?? '') . ($lector['telefono'] && $lector['email'] ? ' · ' : '') . ($lector['email'] ?? ''))) ?>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal" data-bs-target="#lector<?= (int) $lector['id'] ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#lectorNuevo">
            <i class="bi bi-plus-lg me-1"></i>Agregar lector
        </button>
    </div>
</div>

<?php
$dibujarModalLector = static function (string $idModal, ?array $lector, int $pastoralId, string $csrf) {
    $vacio = $lector === null;
    ?>
    <div class="modal fade" id="<?= e($idModal) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" accept-charset="UTF-8"
                  action="<?= e(url_post('admin', 'lector', 'lector_guardar')) ?>" class="modal-content">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="pastoral_id" value="<?= $pastoralId ?>">
                <input type="hidden" name="id" value="<?= $vacio ? 0 : (int) $lector['id'] ?>">

                <div class="modal-header border-0 pb-0">
                    <h2 class="h6 modal-title fw-bold"><?= $vacio ? 'Nuevo lector' : 'Editar lector' ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Nombre</label>
                        <input type="text" name="nombre" class="form-control form-control-sm"
                               value="<?= e($vacio ? '' : $lector['nombre']) ?>" maxlength="140" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Teléfono</label>
                        <input type="tel" name="telefono" class="form-control form-control-sm"
                               value="<?= e($vacio ? '' : (string) $lector['telefono']) ?>" maxlength="20">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Correo</label>
                        <input type="email" name="email" class="form-control form-control-sm"
                               value="<?= e($vacio ? '' : (string) $lector['email']) ?>" maxlength="150">
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" name="activo" value="1"
                               id="act<?= e($idModal) ?>" <?= ($vacio || $lector['activo']) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="act<?= e($idModal) ?>">Activo</label>
                    </div>
                </div>
                <div class="modal-footer border-0 justify-content-between">
                    <?php if (!$vacio): ?>
                    <button type="submit" formaction="<?= e(url_post('admin', 'lector', 'lector_eliminar')) ?>"
                            class="btn btn-outline-danger btn-sm"
                            onclick="return confirm('¿Eliminar este lector?');">
                        <i class="bi bi-trash me-1"></i>Eliminar
                    </button>
                    <?php else: ?>
                    <span></span>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-check-lg me-1"></i>Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php
};

$dibujarModalLector('lectorNuevo', null, $pastoralId, $csrf);
foreach ($lectores as $lector) {
    $dibujarModalLector('lector' . (int) $lector['id'], $lector, $pastoralId, $csrf);
}
?>
