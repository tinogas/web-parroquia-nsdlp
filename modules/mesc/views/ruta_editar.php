<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <nav aria-label="Ubicación">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item"><a href="<?= e(url_admin('mesc')) ?>" class="text-decoration-none">MESC</a></li>
                <li class="breadcrumb-item"><a href="<?= e(url_admin('mesc', 'rutas')) ?>" class="text-decoration-none">Rutas</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= e($ruta['nombre']) ?></li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0"><?= e($ruta['nombre']) ?></h1>
        <p class="text-muted small mb-0"><?= e($ruta['pastoral_nombre']) ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= e(url_admin('mesc', 'ruta_exportar', ['id' => $ruta['id']])) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-download me-1"></i>Descargar CSV
        </a>
        <a href="<?= e(url_admin('mesc', 'rutas')) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
    </div>
</div>

<div class="alert alert-light border small">
    <i class="bi bi-info-circle text-primary me-1"></i>
    El orden sugerido es una aproximación por cercanía en línea recta, no una ruta real por calles. Ajusta los
    números y guarda si conoces mejor el camino.
</div>

<form method="POST" accept-charset="UTF-8" action="<?= e(url_post('admin', 'mesc', 'ruta_reordenar')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="ruta_id" value="<?= (int) $ruta['id'] ?>">

    <div class="card border-0 shadow-sm mb-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:90px">Orden</th>
                        <th>Enfermo</th>
                        <th class="d-none d-md-table-cell">Dirección</th>
                        <th class="d-none d-lg-table-cell">Teléfono</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($visitas as $i => $visita): ?>
                    <tr>
                        <td>
                            <input type="number" class="form-control form-control-sm"
                                   name="orden[<?= (int) $visita['id'] ?>]" value="<?= $i + 1 ?>" min="1" max="999">
                        </td>
                        <td class="fw-semibold"><?= e($visita['nombre_enfermo']) ?></td>
                        <td class="d-none d-md-table-cell small"><?= e($visita['direccion']) ?></td>
                        <td class="d-none d-lg-table-cell small text-muted"><?= e($visita['telefono']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">
        <i class="bi bi-check-lg me-1"></i>Guardar orden
    </button>
</form>
