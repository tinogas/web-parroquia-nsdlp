<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <nav aria-label="Ubicación">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item"><a href="<?= e(url_admin('catequesis', 'periodos')) ?>" class="text-decoration-none">Periodos</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= e($periodo['nombre']) ?></li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0">
            <?= e($periodo['nombre']) ?>
            <?php if ($periodo['activo']): ?>
            <span class="badge bg-success-subtle text-success-emphasis align-middle">Vigente</span>
            <?php endif; ?>
        </h1>
        <p class="text-muted mb-0 small"><?= e($periodo['fecha_inicio']) ?> – <?= e($periodo['fecha_fin']) ?></p>
    </div>
    <a href="<?= e(url_admin('catequesis', 'periodos')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h2 class="h6 fw-bold mb-3">Catequistas de este periodo</h2>

        <?php $puedeEditar = Auth::tienePermiso('catequesis.editar'); ?>

        <?php if (!$asignados): ?>
        <p class="text-muted small mb-3">Todavía no hay catequistas asignados a este periodo.</p>
        <?php else: ?>
        <div class="table-responsive mb-3">
            <table class="table table-sm align-middle">
                <thead class="table-light">
                    <tr><th>Catequista</th><th>Grado</th><th>&nbsp;</th></tr>
                </thead>
                <tbody>
                <?php foreach ($asignados as $catequista): ?>
                    <tr>
                        <td><?= e($catequista['nombre']) ?></td>
                        <td>
                            <span class="badge bg-secondary-subtle text-secondary-emphasis">
                                <?= e(CatequesisModel::GRADOS[$catequista['grado']] ?? $catequista['grado']) ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <?php if ($puedeEditar): ?>
                            <form method="POST" accept-charset="UTF-8" class="d-inline m-0"
                                  action="<?= e(url_post('admin', 'catequesis', 'periodo_desasignar')) ?>"
                                  onsubmit="return confirm('¿Quitar a este catequista del periodo?');">
                                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                <input type="hidden" name="periodo_id" value="<?= (int) $periodo['id'] ?>">
                                <input type="hidden" name="catequista_id" value="<?= (int) $catequista['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($puedeEditar): ?>
        <?php if (!$disponibles): ?>
        <p class="text-muted small mb-0">
            No hay más catequistas activos por asignar.
            <a href="<?= e(url_admin('catequesis')) ?>">Agregar uno nuevo</a>.
        </p>
        <?php else: ?>
        <form method="POST" accept-charset="UTF-8" class="row g-2 align-items-end"
              action="<?= e(url_post('admin', 'catequesis', 'periodo_asignar')) ?>">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="periodo_id" value="<?= (int) $periodo['id'] ?>">
            <div class="col-sm-5">
                <label class="form-label small fw-semibold">Catequista</label>
                <select name="catequista_id" class="form-select form-select-sm" required>
                    <option value="">Elige uno…</option>
                    <?php foreach ($disponibles as $catequista): ?>
                    <option value="<?= (int) $catequista['id'] ?>"><?= e($catequista['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-5">
                <label class="form-label small fw-semibold">Grado</label>
                <select name="grado" class="form-select form-select-sm" required>
                    <option value="">Elige uno…</option>
                    <?php foreach (CatequesisModel::GRADOS as $valor => $etiqueta): ?>
                    <option value="<?= e($valor) ?>"><?= e($etiqueta) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-plus-lg me-1"></i>Agregar
                </button>
            </div>
        </form>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
