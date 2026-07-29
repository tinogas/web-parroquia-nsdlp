<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <nav aria-label="Ubicación">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item"><a href="<?= e(url_admin('mesc')) ?>" class="text-decoration-none">MESC</a></li>
                <li class="breadcrumb-item active" aria-current="page">Rutas</li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0">Rutas de visita</h1>
    </div>
    <?php if (Auth::tienePermiso('mesc.crear')): ?>
    <a href="<?= e(url_admin('mesc', 'ruta_nueva')) ?>" class="btn btn-primary">
        <i class="bi bi-signpost-2 me-1"></i>Generar ruta
    </a>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm">
    <?php if (!$rutas): ?>
        <div class="card-body text-center py-5">
            <div class="display-6 text-body-tertiary mb-2"><i class="bi bi-signpost-2"></i></div>
            <p class="text-muted mb-0">Todavía no se ha generado ninguna ruta.</p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Ruta</th>
                    <th class="d-none d-md-table-cell">Pastoral</th>
                    <th class="d-none d-lg-table-cell">Visitas</th>
                    <th class="d-none d-lg-table-cell">Generada</th>
                    <th class="text-end">&nbsp;</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rutas as $ruta): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e($ruta['nombre']) ?></div>
                        <div class="small text-muted"><?= e($ruta['autor'] ?? '—') ?></div>
                    </td>
                    <td class="d-none d-md-table-cell small"><?= e($ruta['pastoral_nombre']) ?></td>
                    <td class="d-none d-lg-table-cell"><?= (int) $ruta['num_visitas'] ?></td>
                    <td class="d-none d-lg-table-cell small text-muted"><?= e(fecha_larga($ruta['created_at'])) ?></td>
                    <td class="text-end text-nowrap">
                        <a href="<?= e(url_admin('mesc', 'ruta_editar', ['id' => $ruta['id']])) ?>"
                           class="btn btn-sm btn-outline-primary" title="Ver / reordenar">
                            <i class="bi bi-list-ol"></i>
                        </a>
                        <a href="<?= e(url_admin('mesc', 'ruta_exportar', ['id' => $ruta['id']])) ?>"
                           class="btn btn-sm btn-outline-secondary" title="Descargar CSV">
                            <i class="bi bi-download"></i>
                        </a>
                        <?php if (Auth::tienePermiso('mesc.eliminar')): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger"
                                data-bs-toggle="modal" data-bs-target="#borrar<?= (int) $ruta['id'] ?>">
                            <i class="bi bi-trash"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php foreach ($rutas as $ruta): ?>
    <div class="modal fade" id="borrar<?= (int) $ruta['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h2 class="h6 modal-title fw-bold">Eliminar ruta</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">
                        Se eliminará «<?= e($ruta['nombre']) ?>». Las visitas que contiene no se borran, solo esta ruta.
                    </p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" accept-charset="UTF-8"
                          action="<?= e(url_post('admin', 'mesc', 'ruta_eliminar')) ?>" class="m-0">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="id" value="<?= (int) $ruta['id'] ?>">
                        <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i>Eliminar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
