<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1">Avisos</h1>
        <p class="text-muted mb-0 small">Boletín semanal y noticias parroquiales.</p>
    </div>
    <?php if (Auth::tienePermiso('avisos.crear')): ?>
    <a href="<?= e(url_admin('avisos', 'nuevo')) ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nuevo aviso
    </a>
    <?php endif; ?>
</div>

<div class="btn-group btn-group-sm mb-3" role="group">
    <a href="<?= e(url_admin('avisos')) ?>"
       class="btn <?= $filtro === 'todos' ? 'btn-primary' : 'btn-outline-secondary' ?>">Todos</a>
    <a href="<?= e(url_admin('avisos', '', ['filtro' => 'publicados'])) ?>"
       class="btn <?= $filtro === 'publicados' ? 'btn-primary' : 'btn-outline-secondary' ?>">Publicados</a>
    <a href="<?= e(url_admin('avisos', '', ['filtro' => 'borradores'])) ?>"
       class="btn <?= $filtro === 'borradores' ? 'btn-primary' : 'btn-outline-secondary' ?>">Borradores</a>
</div>

<div class="card border-0 shadow-sm">
    <?php if (!$listado['filas']): ?>
        <div class="card-body text-center py-5">
            <div class="display-6 text-body-tertiary mb-2"><i class="bi bi-megaphone"></i></div>
            <p class="text-muted mb-0">No hay avisos que mostrar.</p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Título</th>
                    <th class="d-none d-md-table-cell">Tipo</th>
                    <th class="d-none d-lg-table-cell">Fecha</th>
                    <th class="d-none d-md-table-cell">Estado</th>
                    <th class="text-end">&nbsp;</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($listado['filas'] as $aviso): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e($aviso['titulo']) ?></div>
                        <?php if ($aviso['destacado']): ?>
                        <span class="badge bg-warning-subtle text-warning-emphasis">Destacado</span>
                        <?php endif; ?>
                        <span class="badge bg-light text-secondary border">
                            <?= e($aviso['pastoral_nombre'] ?? 'General') ?>
                        </span>
                    </td>
                    <td class="d-none d-md-table-cell"><?= e(AvisoModel::TIPOS[$aviso['tipo']] ?? $aviso['tipo']) ?></td>
                    <td class="d-none d-lg-table-cell small text-muted"><?= e(fecha_larga($aviso['fecha_publicacion'])) ?></td>
                    <td class="d-none d-md-table-cell">
                        <?php if ($aviso['publicado']): ?>
                            <span class="badge bg-success-subtle text-success-emphasis">Publicado</span>
                        <?php else: ?>
                            <span class="badge bg-warning-subtle text-warning-emphasis">Borrador</span>
                        <?php endif; ?>
                        <?php if ($aviso['vigente_hasta'] && $aviso['vigente_hasta'] < date('Y-m-d')): ?>
                            <span class="badge bg-secondary-subtle text-secondary-emphasis" title="Ya pasó su fecha 'Visible hasta'; no se muestra en el sitio.">Vencido</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <?php if ($aviso['publicado']): ?>
                        <a href="<?= e(url_publica('avisos', ['slug' => $aviso['slug']])) ?>"
                           class="btn btn-sm btn-outline-secondary" target="_blank" title="Ver en el sitio">
                            <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                        <?php endif; ?>
                        <?php if (Auth::tienePermiso('avisos.editar')): ?>
                        <a href="<?= e(url_admin('avisos', 'editar', ['id' => $aviso['id']])) ?>"
                           class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        <?php endif; ?>
                        <?php if (Auth::tienePermiso('avisos.eliminar')): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger"
                                data-bs-toggle="modal" data-bs-target="#borrar<?= (int) $aviso['id'] ?>">
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
$paginaBase = url_admin('avisos', '', $filtro !== 'todos' ? ['filtro' => $filtro] : []);
require BASE_PATH . '/shared/views/parciales/paginacion.php';
?>

<?php foreach ($listado['filas'] as $aviso): ?>
    <div class="modal fade" id="borrar<?= (int) $aviso['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h2 class="h6 modal-title fw-bold">Eliminar aviso</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Se eliminará «<?= e($aviso['titulo']) ?>». Esta acción no se puede deshacer.</p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" accept-charset="UTF-8"
                          action="<?= e(url_post('admin', 'avisos', 'eliminar')) ?>" class="m-0">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="id" value="<?= (int) $aviso['id'] ?>">
                        <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i>Eliminar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
