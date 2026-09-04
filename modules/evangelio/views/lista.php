<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1">Evangelio del día</h1>
        <p class="text-muted mb-0 small">
            El evangelio de cada día y la reflexión del párroco, publicados por separado.
        </p>
    </div>
    <a href="<?= e(url_admin('evangelio', 'nuevo')) ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nueva entrada
    </a>
</div>

<div class="card border-0 shadow-sm">
    <?php if (!$listado['filas']): ?>
        <div class="card-body text-center py-5">
            <div class="display-6 text-body-tertiary mb-2"><i class="bi bi-book-half"></i></div>
            <p class="text-muted mb-0">Todavía no hay ninguna entrada.</p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Fecha</th>
                    <th class="d-none d-md-table-cell">Estado</th>
                    <th class="d-none d-lg-table-cell">Última edición</th>
                    <th class="text-end">&nbsp;</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($listado['filas'] as $entrada): ?>
                <tr>
                    <td>
                        <div class="fw-semibold text-capitalize"><?= e(fecha_con_dia($entrada['fecha'])) ?></div>
                        <div class="text-muted small">
                            <?= e(resumen($entrada['evangelio'], 80)) ?>
                        </div>
                    </td>
                    <td class="d-none d-md-table-cell">
                        <?php if ($entrada['publicado']): ?>
                            <span class="badge bg-success-subtle text-success-emphasis">Publicado</span>
                        <?php else: ?>
                            <span class="badge bg-warning-subtle text-warning-emphasis">Borrador</span>
                        <?php endif; ?>
                    </td>
                    <td class="d-none d-lg-table-cell small text-muted">
                        <?php if (!empty($entrada['updated_at'])): ?>
                            <?= e(fecha_larga($entrada['updated_at'])) ?>
                            <?php if (!empty($entrada['editor'])): ?>
                            <br><span class="text-body-tertiary"><?= e($entrada['editor']) ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="<?= e(url_admin('evangelio', 'editar', ['id' => $entrada['id']])) ?>"
                           class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-pencil me-1"></i>Editar
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-danger"
                                data-bs-toggle="modal" data-bs-target="#borrar<?= (int) $entrada['id'] ?>"
                                title="Eliminar">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php $paginacion = $listado; $paginaBase = url_admin('evangelio'); ?>
<?php require BASE_PATH . '/shared/views/parciales/paginacion.php'; ?>

<?php /* Confirmaciones de borrado, fuera de la tabla para no anidar formularios. */ ?>
<?php foreach ($listado['filas'] as $entrada): ?>
    <div class="modal fade" id="borrar<?= (int) $entrada['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h2 class="h6 modal-title fw-bold">Eliminar la entrada</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">
                        Se eliminará el evangelio del <strong><?= e(fecha_con_dia($entrada['fecha'])) ?></strong>.
                        Esta acción no se puede deshacer.
                    </p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" accept-charset="UTF-8"
                          action="<?= e(url_post('admin', 'evangelio', 'eliminar')) ?>" class="m-0">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="id" value="<?= (int) $entrada['id'] ?>">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash me-1"></i>Eliminar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
