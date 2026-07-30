<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1">Catequesis — Periodos</h1>
        <p class="text-muted mb-0 small">Ciclos de catecismo (ej. agosto a junio) y qué catequistas dieron clase en cada uno.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= e(url_admin('catequesis')) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-person-badge me-1"></i>Catequistas
        </a>
        <a href="<?= e(url_admin('catequesis', 'actividades')) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-calendar3 me-1"></i>Actividades
        </a>
        <a href="<?= e(url_admin('catequesis', 'documentos')) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-file-earmark-pdf me-1"></i>Documentos
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <?php if (!$periodos): ?>
        <p class="text-muted small mb-3">Todavía no hay periodos registrados.</p>
        <?php else: ?>
        <div class="table-responsive mb-3">
            <table class="table table-sm align-middle">
                <thead class="table-light">
                    <tr><th>Periodo</th><th class="d-none d-md-table-cell">Vigencia</th><th>Estado</th><th>&nbsp;</th></tr>
                </thead>
                <tbody>
                <?php foreach ($periodos as $periodo): ?>
                    <tr>
                        <td>
                            <a href="<?= e(url_admin('catequesis', 'periodo_ver', ['id' => $periodo['id']])) ?>">
                                <?= e($periodo['nombre']) ?>
                            </a>
                        </td>
                        <td class="d-none d-md-table-cell small text-muted">
                            <?= e($periodo['fecha_inicio']) ?> – <?= e($periodo['fecha_fin']) ?>
                        </td>
                        <td>
                            <?php if ($periodo['activo']): ?>
                                <span class="badge bg-success-subtle text-success-emphasis">Vigente</span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary-emphasis">Cerrado</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <a href="<?= e(url_admin('catequesis', 'periodo_ver', ['id' => $periodo['id']])) ?>"
                               class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-people"></i>
                            </a>
                            <?php if (Auth::tienePermiso('catequesis.editar')): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal" data-bs-target="#periodo<?= (int) $periodo['id'] ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if (Auth::tienePermiso('catequesis.crear')): ?>
        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#periodoNuevo">
            <i class="bi bi-plus-lg me-1"></i>Agregar periodo
        </button>
        <?php endif; ?>
    </div>
</div>

<?php
$dibujarModalPeriodo = static function (string $idModal, ?array $periodo, string $csrf) {
    $vacio = $periodo === null;
    ?>
    <div class="modal fade" id="<?= e($idModal) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" accept-charset="UTF-8"
                  action="<?= e(url_post('admin', 'catequesis', 'periodo_guardar')) ?>" class="modal-content">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="id" value="<?= $vacio ? 0 : (int) $periodo['id'] ?>">

                <div class="modal-header border-0 pb-0">
                    <h2 class="h6 modal-title fw-bold"><?= $vacio ? 'Nuevo periodo' : 'Editar periodo' ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Nombre</label>
                        <input type="text" name="nombre" class="form-control form-control-sm"
                               value="<?= e($vacio ? '' : $periodo['nombre']) ?>" maxlength="60"
                               placeholder="Ej. 2026-2027" required>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Desde</label>
                            <input type="date" name="fecha_inicio" class="form-control form-control-sm"
                                   value="<?= e($vacio ? '' : (string) $periodo['fecha_inicio']) ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Hasta</label>
                            <input type="date" name="fecha_fin" class="form-control form-control-sm"
                                   value="<?= e($vacio ? '' : (string) $periodo['fecha_fin']) ?>" required>
                        </div>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" name="activo" value="1"
                               id="act<?= e($idModal) ?>" <?= ($vacio || $periodo['activo']) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="act<?= e($idModal) ?>">Vigente</label>
                    </div>
                </div>
                <div class="modal-footer border-0 justify-content-between">
                    <?php if (!$vacio && Auth::tienePermiso('catequesis.eliminar')): ?>
                    <button type="submit" formaction="<?= e(url_post('admin', 'catequesis', 'periodo_eliminar')) ?>"
                            class="btn btn-outline-danger btn-sm"
                            onclick="return confirm('¿Eliminar este periodo? También se borran sus asignaciones de catequistas.');">
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

$dibujarModalPeriodo('periodoNuevo', null, $csrf);
foreach ($periodos as $periodo) {
    $dibujarModalPeriodo('periodo' . (int) $periodo['id'], $periodo, $csrf);
}
?>
