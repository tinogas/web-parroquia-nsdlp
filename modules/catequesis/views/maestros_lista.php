<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1">Catequesis — Maestros</h1>
        <p class="text-muted mb-0 small">Catequistas de primera comunión y confirmación.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= e(url_admin('catequesis', 'actividades')) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-calendar3 me-1"></i>Actividades
        </a>
        <a href="<?= e(url_admin('catequesis', 'documentos')) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-file-earmark-pdf me-1"></i>Documentos
        </a>
    </div>
</div>

<?php if (!$pastorales): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5">
        <p class="text-muted mb-0">No administras la pastoral de Catequesis todavía.</p>
    </div>
</div>
<?php endif; ?>

<?php foreach ($pastorales as $pastoral): ?>
<?php
$pid   = (int) $pastoral['id'];
$lista = $maestros[$pid]['filas'] ?? [];
?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <?php if (count($pastorales) > 1): ?>
        <h2 class="h6 fw-bold mb-3"><?= e($pastoral['nombre']) ?></h2>
        <?php endif; ?>

        <?php if (!$lista): ?>
        <p class="text-muted small mb-3">Todavía no hay maestros registrados.</p>
        <?php else: ?>
        <div class="table-responsive mb-3">
            <table class="table table-sm align-middle">
                <thead class="table-light">
                    <tr><th>Nombre</th><th>Sacramento</th><th class="d-none d-md-table-cell">Contacto</th><th>&nbsp;</th></tr>
                </thead>
                <tbody>
                <?php foreach ($lista as $maestro): ?>
                    <tr class="<?= $maestro['activo'] ? '' : 'text-muted' ?>">
                        <td><?= e($maestro['nombre']) ?><?= $maestro['activo'] ? '' : ' (inactivo)' ?></td>
                        <td>
                            <span class="badge bg-secondary-subtle text-secondary-emphasis">
                                <?= e(CatequesisModel::SACRAMENTOS[$maestro['sacramento']] ?? $maestro['sacramento']) ?>
                            </span>
                        </td>
                        <td class="d-none d-md-table-cell small">
                            <?= e(trim(($maestro['telefono'] ?? '') . ($maestro['telefono'] && $maestro['email'] ? ' · ' : '') . ($maestro['email'] ?? ''))) ?>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal" data-bs-target="#maestro<?= (int) $maestro['id'] ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                data-bs-target="#maestroNuevo<?= $pid ?>">
            <i class="bi bi-plus-lg me-1"></i>Agregar maestro
        </button>
    </div>
</div>
<?php endforeach; ?>

<?php
$dibujarModalMaestro = static function (string $idModal, ?array $maestro, int $pastoralId, string $csrf) {
    $vacio = $maestro === null;
    ?>
    <div class="modal fade" id="<?= e($idModal) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" accept-charset="UTF-8"
                  action="<?= e(url_post('admin', 'catequesis', 'maestro_guardar')) ?>" class="modal-content">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="pastoral_id" value="<?= $pastoralId ?>">
                <input type="hidden" name="id" value="<?= $vacio ? 0 : (int) $maestro['id'] ?>">

                <div class="modal-header border-0 pb-0">
                    <h2 class="h6 modal-title fw-bold"><?= $vacio ? 'Nuevo maestro' : 'Editar maestro' ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Nombre</label>
                        <input type="text" name="nombre" class="form-control form-control-sm"
                               value="<?= e($vacio ? '' : $maestro['nombre']) ?>" maxlength="140" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Sacramento</label>
                        <select name="sacramento" class="form-select form-select-sm" required>
                            <option value="">Elige uno…</option>
                            <?php foreach (CatequesisModel::SACRAMENTOS as $valor => $etiqueta): ?>
                            <option value="<?= e($valor) ?>"
                                <?= (!$vacio && $maestro['sacramento'] === $valor) ? 'selected' : '' ?>>
                                <?= e($etiqueta) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Teléfono</label>
                        <input type="tel" name="telefono" class="form-control form-control-sm"
                               value="<?= e($vacio ? '' : (string) $maestro['telefono']) ?>" maxlength="20">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Correo</label>
                        <input type="email" name="email" class="form-control form-control-sm"
                               value="<?= e($vacio ? '' : (string) $maestro['email']) ?>" maxlength="150">
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" name="activo" value="1"
                               id="act<?= e($idModal) ?>" <?= ($vacio || $maestro['activo']) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="act<?= e($idModal) ?>">Activo</label>
                    </div>
                </div>
                <div class="modal-footer border-0 justify-content-between">
                    <?php if (!$vacio): ?>
                    <button type="submit" formaction="<?= e(url_post('admin', 'catequesis', 'maestro_eliminar')) ?>"
                            class="btn btn-outline-danger btn-sm"
                            onclick="return confirm('¿Eliminar este maestro?');">
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

foreach ($pastorales as $pastoral) {
    $pid = (int) $pastoral['id'];
    $dibujarModalMaestro('maestroNuevo' . $pid, null, $pid, $csrf);
    foreach (($maestros[$pid]['filas'] ?? []) as $maestro) {
        $dibujarModalMaestro('maestro' . (int) $maestro['id'], $maestro, $pid, $csrf);
    }
}
?>
