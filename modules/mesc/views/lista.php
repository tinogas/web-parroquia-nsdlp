<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1">MESC — Visitas a enfermos</h1>
        <p class="text-muted mb-0 small">Registro para llevar la comunión. Datos de salud: visibles solo aquí.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= e(url_admin('mesc', 'turnos')) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-calendar3 me-1"></i>Turnos
        </a>
        <a href="<?= e(url_admin('mesc', 'ministros')) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-people me-1"></i>Ministros
        </a>
        <a href="<?= e(url_admin('mesc', 'rutas')) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-signpost-2 me-1"></i>Rutas
        </a>
        <?php if (Auth::tienePermiso('mesc.crear')): ?>
        <a href="<?= e(url_admin('mesc', 'nueva')) ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Nueva visita
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="btn-group btn-group-sm mb-3" role="group">
    <a href="<?= e(url_admin('mesc', '', ['filtro' => 'activas'])) ?>"
       class="btn <?= $filtro === 'activas' ? 'btn-primary' : 'btn-outline-secondary' ?>">Activas</a>
    <a href="<?= e(url_admin('mesc', '', ['filtro' => 'inactivas'])) ?>"
       class="btn <?= $filtro === 'inactivas' ? 'btn-primary' : 'btn-outline-secondary' ?>">Dadas de baja</a>
</div>

<div class="card border-0 shadow-sm">
    <?php if (!$listado['filas']): ?>
        <div class="card-body text-center py-5">
            <div class="display-6 text-body-tertiary mb-2"><i class="bi bi-heart-pulse"></i></div>
            <p class="text-muted mb-0">No hay visitas que mostrar.</p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Enfermo</th>
                    <th class="d-none d-md-table-cell">Dirección</th>
                    <th class="d-none d-lg-table-cell">Pastoral</th>
                    <th class="text-end">&nbsp;</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($listado['filas'] as $visita): ?>
                <tr class="<?= $visita['activo'] ? '' : 'text-muted' ?>">
                    <td>
                        <div class="fw-semibold"><?= e($visita['nombre_enfermo']) ?></div>
                        <?php if ($visita['telefono']): ?>
                        <div class="small text-muted"><?= e($visita['telefono']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="d-none d-md-table-cell small">
                        <?= e($visita['direccion']) ?>
                        <?php if ($visita['latitud'] !== null): ?>
                        <i class="bi bi-geo-alt-fill text-primary ms-1" title="Con ubicación en mapa"></i>
                        <?php endif; ?>
                    </td>
                    <td class="d-none d-lg-table-cell small text-muted"><?= e($visita['pastoral_nombre']) ?></td>
                    <td class="text-end text-nowrap">
                        <?php if (Auth::tienePermiso('mesc.editar')): ?>
                        <a href="<?= e(url_admin('mesc', 'editar', ['id' => $visita['id']])) ?>"
                           class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        <?php endif; ?>
                        <?php if (Auth::tienePermiso('mesc.eliminar')): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger"
                                data-bs-toggle="modal" data-bs-target="#borrar<?= (int) $visita['id'] ?>">
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

<?php
$paginacion = $listado;
$paginaBase = url_admin('mesc', '', ['filtro' => $filtro]);
require BASE_PATH . '/shared/views/parciales/paginacion.php';
?>

<?php foreach ($listado['filas'] as $visita): ?>
    <div class="modal fade" id="borrar<?= (int) $visita['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h2 class="h6 modal-title fw-bold">Eliminar visita</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Se eliminará el registro de «<?= e($visita['nombre_enfermo']) ?>». Esta acción no se puede deshacer.</p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" accept-charset="UTF-8"
                          action="<?= e(url_post('admin', 'mesc', 'eliminar')) ?>" class="m-0">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="id" value="<?= (int) $visita['id'] ?>">
                        <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i>Eliminar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
