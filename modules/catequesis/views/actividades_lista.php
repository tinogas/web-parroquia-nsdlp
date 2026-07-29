<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1">Catequesis — Actividades</h1>
        <p class="text-muted mb-0 small">Tablero de actividades, con vigencia y publicación propias.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= e(url_admin('catequesis')) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-person-badge me-1"></i>Maestros
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
$lista = $actividades[$pid]['filas'] ?? [];
?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <?php if (count($pastorales) > 1): ?>
        <h2 class="h6 fw-bold mb-3"><?= e($pastoral['nombre']) ?></h2>
        <?php endif; ?>

        <?php if (!$lista): ?>
        <p class="text-muted small mb-3">Todavía no hay actividades registradas.</p>
        <?php else: ?>
        <div class="table-responsive mb-3">
            <table class="table table-sm align-middle">
                <thead class="table-light">
                    <tr><th>Título</th><th class="d-none d-md-table-cell">Vigencia</th><th>Estado</th><th>&nbsp;</th></tr>
                </thead>
                <tbody>
                <?php foreach ($lista as $actividad): ?>
                    <tr>
                        <td><?= e($actividad['titulo']) ?></td>
                        <td class="d-none d-md-table-cell small text-muted">
                            <?= e($actividad['fecha_inicio']) ?><?= $actividad['fecha_fin'] ? ' – ' . e($actividad['fecha_fin']) : '' ?>
                        </td>
                        <td>
                            <?php if ($actividad['publicado']): ?>
                                <span class="badge bg-success-subtle text-success-emphasis">Publicado</span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary-emphasis">Borrador</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal" data-bs-target="#actividad<?= (int) $actividad['id'] ?>">
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
                data-bs-target="#actividadNueva<?= $pid ?>">
            <i class="bi bi-plus-lg me-1"></i>Agregar actividad
        </button>
    </div>
</div>
<?php endforeach; ?>

<?php
$dibujarModalActividad = static function (string $idModal, ?array $actividad, int $pastoralId, string $csrf) {
    $vacio = $actividad === null;
    ?>
    <div class="modal fade" id="<?= e($idModal) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" accept-charset="UTF-8"
                  action="<?= e(url_post('admin', 'catequesis', 'actividad_guardar')) ?>" class="modal-content">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="pastoral_id" value="<?= $pastoralId ?>">
                <input type="hidden" name="id" value="<?= $vacio ? 0 : (int) $actividad['id'] ?>">

                <div class="modal-header border-0 pb-0">
                    <h2 class="h6 modal-title fw-bold"><?= $vacio ? 'Nueva actividad' : 'Editar actividad' ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Título</label>
                        <input type="text" name="titulo" class="form-control form-control-sm"
                               value="<?= e($vacio ? '' : $actividad['titulo']) ?>" maxlength="160" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Descripción</label>
                        <textarea name="descripcion" class="form-control form-control-sm" rows="2"><?= e($vacio ? '' : (string) $actividad['descripcion']) ?></textarea>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Fecha de inicio</label>
                            <input type="date" name="fecha_inicio" class="form-control form-control-sm"
                                   value="<?= e($vacio ? date('Y-m-d') : (string) $actividad['fecha_inicio']) ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Fecha de término</label>
                            <input type="date" name="fecha_fin" class="form-control form-control-sm"
                                   value="<?= e($vacio ? '' : (string) $actividad['fecha_fin']) ?>">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Orden</label>
                        <input type="number" name="orden" class="form-control form-control-sm"
                               value="<?= (int) ($vacio ? 0 : $actividad['orden']) ?>" min="0" max="999">
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" name="publicado" value="1"
                               id="pub<?= e($idModal) ?>" <?= (!$vacio && $actividad['publicado']) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="pub<?= e($idModal) ?>">Publicado</label>
                    </div>
                </div>
                <div class="modal-footer border-0 justify-content-between">
                    <?php if (!$vacio): ?>
                    <button type="submit" formaction="<?= e(url_post('admin', 'catequesis', 'actividad_eliminar')) ?>"
                            class="btn btn-outline-danger btn-sm"
                            onclick="return confirm('¿Eliminar esta actividad?');">
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
    $dibujarModalActividad('actividadNueva' . $pid, null, $pid, $csrf);
    foreach (($actividades[$pid]['filas'] ?? []) as $actividad) {
        $dibujarModalActividad('actividad' . (int) $actividad['id'], $actividad, $pid, $csrf);
    }
}
?>
