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

<?php
// Los filtros —estado, pastoral y sede— se combinan, así que cada uno arrastra
// el estado de los otros.
$porEstado   = $filtro !== 'todos' ? ['filtro' => $filtro] : [];
$porPastoral = $filtroPastoral !== '' ? ['pastoral' => $filtroPastoral] : [];
$porCentro   = $filtroCentro !== '' ? ['centro' => $filtroCentro] : [];
$porAmbito   = $porPastoral + $porCentro;
?>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
    <div class="btn-group btn-group-sm" role="group" aria-label="Filtrar por estado">
        <a href="<?= e(url_admin('cursos', '', $porAmbito)) ?>"
           class="btn <?= $filtro === 'todos' ? 'btn-primary' : 'btn-outline-secondary' ?>">Todos</a>
        <a href="<?= e(url_admin('cursos', '', ['filtro' => 'publicados'] + $porAmbito)) ?>"
           class="btn <?= $filtro === 'publicados' ? 'btn-primary' : 'btn-outline-secondary' ?>">Publicados</a>
        <a href="<?= e(url_admin('cursos', '', ['filtro' => 'borradores'] + $porAmbito)) ?>"
           class="btn <?= $filtro === 'borradores' ? 'btn-primary' : 'btn-outline-secondary' ?>">Borradores</a>
    </div>

    <form method="GET" action="<?= e(url_admin('cursos')) ?>" class="row g-2 align-items-end">
        <?php if (!URLS_AMIGABLES): ?>
        <?php /* Sin URLs amigables la ruta va en la cadena de consulta, y un GET
                 descarta la del action: hay que repetirla como campos. */ ?>
        <input type="hidden" name="area" value="admin">
        <input type="hidden" name="modulo" value="cursos">
        <?php endif; ?>
        <?php if ($filtro !== 'todos'): ?>
        <input type="hidden" name="filtro" value="<?= e($filtro) ?>">
        <?php endif; ?>
        <div class="col-auto">
            <label for="pastoral" class="form-label small fw-semibold mb-1">Pastoral</label>
            <select name="pastoral" id="pastoral" class="form-select form-select-sm">
                <?php /* Igual que en eventos: sin alcance global el listado trae
                         lo suyo y lo general, y «Todas» prometería de más. */ ?>
                <option value=""><?= Auth::tieneAlcanceGlobal() ? 'Todas' : 'Las mías y las generales' ?></option>
                <?php if ($tieneAlcance): ?>
                <option value="mias" <?= $filtroPastoral === 'mias' ? 'selected' : '' ?>>Solo las mías</option>
                <?php endif; ?>
                <?php foreach ($pastorales as $unaPastoral): ?>
                <option value="<?= (int) $unaPastoral['id'] ?>"
                        <?= $filtroPastoral === (string) $unaPastoral['id'] ? 'selected' : '' ?>>
                    <?= e($unaPastoral['nombre']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if (count($centros) > 1): ?>
        <div class="col-auto">
            <label for="centro" class="form-label small fw-semibold mb-1">Sede</label>
            <select name="centro" id="centro" class="form-select form-select-sm">
                <option value="">Todas</option>
                <?php foreach ($centros as $unCentro): ?>
                <option value="<?= (int) $unCentro['id'] ?>"
                        <?= $filtroCentro === (string) $unCentro['id'] ? 'selected' : '' ?>>
                    <?= e($unCentro['nombre']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="col-auto">
            <button type="submit" class="btn btn-sm btn-primary">
                <i class="bi bi-funnel me-1"></i>Filtrar
            </button>
        </div>
        <?php if ($porAmbito): ?>
        <div class="col-auto">
            <a href="<?= e(url_admin('cursos', '', $porEstado)) ?>"
               class="btn btn-sm btn-outline-secondary">Quitar</a>
        </div>
        <?php endif; ?>
    </form>
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
                <?php
                // Suyo es lo de su pastoral y su sede, igual que en eventos.
                $suyo = Auth::puedeSobrePastoral($curso['pastoral_id'] !== null ? (int) $curso['pastoral_id'] : null)
                     && Auth::puedeSobreCentro($curso['centro_id'] !== null ? (int) $curso['centro_id'] : null);
                $puedeEditar = $suyo && Auth::tienePermiso('cursos.editar');
                $puedeBorrar = $suyo && Auth::tienePermiso('cursos.eliminar');
                ?>
                <tr>
                    <td>
                        <div class="fw-semibold">
                            <?= e($curso['titulo']) ?>
                            <span class="badge bg-light text-secondary border">
                                <?= e($curso['pastoral_nombre'] ?? 'General') ?>
                            </span>
                            <?php if ($curso['centro_nombre']): ?>
                            <span class="badge bg-light text-secondary border">
                                <i class="bi bi-geo-alt me-1"></i><?= e($curso['centro_nombre']) ?>
                            </span>
                            <?php endif; ?>
                        </div>
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
                        <?php if ($puedeEditar): ?>
                        <a href="<?= e(url_admin('cursos', 'editar', ['id' => $curso['id']])) ?>"
                           class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        <?php endif; ?>
                        <?php if ($puedeBorrar): ?>
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
$paginaBase = url_admin('cursos', '', $porEstado + $porAmbito);
require BASE_PATH . '/shared/views/parciales/paginacion.php';
?>

<?php foreach ($listado['filas'] as $curso): ?>
    <?php // El modal solo existe si su botón existe: ver un curso ajeno no da acceso a borrarlo.
    if (!Auth::tienePermiso('cursos.eliminar')
        || !Auth::puedeSobrePastoral($curso['pastoral_id'] !== null ? (int) $curso['pastoral_id'] : null)
        || !Auth::puedeSobreCentro($curso['centro_id'] !== null ? (int) $curso['centro_id'] : null)) {
        continue;
    } ?>
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
