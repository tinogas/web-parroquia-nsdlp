<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1">Cursos</h1>
        <p class="text-muted mb-0 small">Catálogo de cursos y capacitaciones.</p>
    </div>
    <?php if (Auth::tienePermiso('cursos.crear')): ?>
    <a href="<?= e(url_admin('cursos', 'nuevo')) ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nuevo curso
    </a>
    <?php endif; ?>
</div>

<div class="btn-group btn-group-sm mb-3" role="group">
    <a href="<?= e(url_admin('cursos')) ?>"
       class="btn <?= $filtro === 'todos' ? 'btn-primary' : 'btn-outline-secondary' ?>">Todos</a>
    <a href="<?= e(url_admin('cursos', '', ['filtro' => 'publicados'])) ?>"
       class="btn <?= $filtro === 'publicados' ? 'btn-primary' : 'btn-outline-secondary' ?>">Publicados</a>
    <a href="<?= e(url_admin('cursos', '', ['filtro' => 'borradores'])) ?>"
       class="btn <?= $filtro === 'borradores' ? 'btn-primary' : 'btn-outline-secondary' ?>">Borradores</a>
</div>

<div class="card border-0 shadow-sm">
    <?php if (!$listado['filas']): ?>
        <div class="card-body text-center py-5">
            <div class="display-6 text-body-tertiary mb-2"><i class="bi bi-mortarboard"></i></div>
            <p class="text-muted mb-0">No hay cursos que mostrar.</p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Curso</th>
                    <th class="d-none d-md-table-cell">Modalidad</th>
                    <th class="d-none d-lg-table-cell">Inicio</th>
                    <th class="d-none d-md-table-cell">Estado</th>
                    <th class="text-end">&nbsp;</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($listado['filas'] as $curso): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e($curso['titulo']) ?></div>
                        <?php if ($curso['instructor_nombre']): ?>
                        <div class="text-muted small"><?= e($curso['instructor_nombre']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="d-none d-md-table-cell"><?= e(CursoModel::MODALIDADES[$curso['modalidad']] ?? $curso['modalidad']) ?></td>
                    <td class="d-none d-lg-table-cell small text-muted">
                        <?= $curso['fecha_inicio'] ? e(fecha_larga($curso['fecha_inicio'])) : '—' ?>
                    </td>
                    <td class="d-none d-md-table-cell">
                        <?php if ($curso['publicado']): ?>
                            <span class="badge bg-success-subtle text-success-emphasis">Publicado</span>
                        <?php else: ?>
                            <span class="badge bg-warning-subtle text-warning-emphasis">Borrador</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <?php if ($curso['publicado']): ?>
                        <a href="<?= e(url_publica('cursos', ['slug' => $curso['slug']])) ?>"
                           class="btn btn-sm btn-outline-secondary" target="_blank" title="Ver en el sitio">
                            <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                        <?php endif; ?>
                        <a href="<?= e(url_admin('cursos', 'editar', ['id' => $curso['id']])) ?>"
                           class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        <?php if (Auth::tienePermiso('cursos.eliminar')): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger"
                                data-bs-toggle="modal" data-bs-target="#borrar<?= (int) $curso['id'] ?>">
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
$paginaBase = url_admin('cursos', '', $filtro !== 'todos' ? ['filtro' => $filtro] : []);
require BASE_PATH . '/shared/views/parciales/paginacion.php';
?>

<?php foreach ($listado['filas'] as $curso): ?>
    <div class="modal fade" id="borrar<?= (int) $curso['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h2 class="h6 modal-title fw-bold">Eliminar curso</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">
                        Se eliminará «<?= e($curso['titulo']) ?>», su temario y sus inscripciones.
                        Esta acción no se puede deshacer.
                    </p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" accept-charset="UTF-8"
                          action="<?= e(url_post('admin', 'cursos', 'eliminar')) ?>" class="m-0">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="id" value="<?= (int) $curso['id'] ?>">
                        <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i>Eliminar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
