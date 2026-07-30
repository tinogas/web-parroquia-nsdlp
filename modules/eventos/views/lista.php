<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1">Eventos</h1>
        <p class="text-muted mb-0 small">Celebraciones y actividades con fecha concreta.</p>
    </div>
    <?php if (Auth::tienePermiso('eventos.crear')): ?>
    <a href="<?= e(url_admin('eventos', 'nuevo')) ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nuevo evento
    </a>
    <?php endif; ?>
</div>

<?php
// Los filtros se combinan, así que cada uno tiene que arrastrar el estado de los
// otros: los botones de estado conservan mes y año, y el formulario de fecha
// conserva el estado.
$porFecha  = array_filter(
    ['anio' => $anio, 'mes' => $mes, 'dia' => $dia],
    static fn ($v) => $v !== null
);
$porEstado = $filtro !== 'todos' ? ['filtro' => $filtro] : [];
$MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
          'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
?>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
    <div class="btn-group btn-group-sm" role="group" aria-label="Filtrar por estado">
        <a href="<?= e(url_admin('eventos', '', $porFecha)) ?>"
           class="btn <?= $filtro === 'todos' ? 'btn-primary' : 'btn-outline-secondary' ?>">Todos</a>
        <a href="<?= e(url_admin('eventos', '', ['filtro' => 'publicados'] + $porFecha)) ?>"
           class="btn <?= $filtro === 'publicados' ? 'btn-primary' : 'btn-outline-secondary' ?>">Publicados</a>
        <a href="<?= e(url_admin('eventos', '', ['filtro' => 'borradores'] + $porFecha)) ?>"
           class="btn <?= $filtro === 'borradores' ? 'btn-primary' : 'btn-outline-secondary' ?>">Borradores</a>
    </div>

    <?php if ($anios): ?>
    <form method="GET" action="<?= e(url_admin('eventos')) ?>" class="row g-2 align-items-end">
        <?php if (!URLS_AMIGABLES): ?>
        <?php /* Sin URLs amigables la ruta va en la cadena de consulta, y un GET
                 descarta la del action: hay que repetirla como campos. */ ?>
        <input type="hidden" name="area" value="admin">
        <input type="hidden" name="modulo" value="eventos">
        <?php endif; ?>
        <?php if ($filtro !== 'todos'): ?>
        <input type="hidden" name="filtro" value="<?= e($filtro) ?>">
        <?php endif; ?>
        <div class="col-auto">
            <label for="dia" class="form-label small fw-semibold mb-1">Día</label>
            <select name="dia" id="dia" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php for ($d = 1; $d <= $diasDelMes; $d++): ?>
                <option value="<?= $d ?>" <?= $dia === $d ? 'selected' : '' ?>><?= $d ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="col-auto">
            <label for="mes" class="form-label small fw-semibold mb-1">Mes</label>
            <select name="mes" id="mes" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach ($MESES as $i => $nombre): ?>
                <option value="<?= $i + 1 ?>" <?= $mes === $i + 1 ? 'selected' : '' ?>>
                    <?= e(ucfirst($nombre)) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <label for="anio" class="form-label small fw-semibold mb-1">Año</label>
            <select name="anio" id="anio" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach ($anios as $unAnio): ?>
                <option value="<?= (int) $unAnio ?>" <?= $anio === $unAnio ? 'selected' : '' ?>>
                    <?= (int) $unAnio ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-sm btn-primary">
                <i class="bi bi-funnel me-1"></i>Filtrar
            </button>
        </div>
        <?php if ($porFecha): ?>
        <div class="col-auto">
            <a href="<?= e(url_admin('eventos', '', $porEstado)) ?>"
               class="btn btn-sm btn-outline-secondary">Quitar</a>
        </div>
        <?php endif; ?>
    </form>
    <?php endif; ?>
</div>

<?php if ($porFecha): ?>
<p class="small text-muted mb-3">
    <?= (int) $listado['total'] ?>
    <?= (int) $listado['total'] === 1 ? 'evento' : 'eventos' ?>
    con fecha de inicio <?= e($descripcionFecha) ?>.
</p>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <?php if (!$listado['filas']): ?>
        <div class="card-body text-center py-5">
            <div class="display-6 text-body-tertiary mb-2"><i class="bi bi-calendar-event"></i></div>
            <?php if ($porFecha || $filtro !== 'todos'): ?>
            <p class="text-muted mb-2">Ningún evento coincide con el filtro.</p>
            <a href="<?= e(url_admin('eventos')) ?>" class="btn btn-sm btn-outline-secondary">
                Ver todos los eventos
            </a>
            <?php else: ?>
            <p class="text-muted mb-0">No hay eventos que mostrar.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Evento</th>
                    <th class="d-none d-md-table-cell">Fecha</th>
                    <th class="d-none d-lg-table-cell">Lugar</th>
                    <th class="d-none d-md-table-cell">Estado</th>
                    <th class="text-end">&nbsp;</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($listado['filas'] as $evento): ?>
                <tr>
                    <td>
                        <span class="badge rounded-pill me-1" style="background:<?= e($evento['color'] ?: '#1e4d8b') ?>;width:.6rem;height:.6rem;padding:0"></span>
                        <span class="fw-semibold"><?= e($evento['titulo']) ?></span>
                        <span class="badge bg-light text-secondary border">
                            <?= e($evento['pastoral_nombre'] ?? 'General') ?>
                        </span>
                    </td>
                    <td class="d-none d-md-table-cell small text-muted">
                        <?= e(fecha_larga(substr($evento['fecha_inicio'], 0, 10))) ?>
                        <?php if (!$evento['todo_el_dia']): ?>, <?= e(hora_corta(substr($evento['fecha_inicio'], 11))) ?><?php endif; ?>
                    </td>
                    <td class="d-none d-lg-table-cell small text-muted"><?= e($evento['lugar'] ?: '—') ?></td>
                    <td class="d-none d-md-table-cell">
                        <?php if ($evento['publicado']): ?>
                            <span class="badge bg-success-subtle text-success-emphasis">Publicado</span>
                        <?php else: ?>
                            <span class="badge bg-warning-subtle text-warning-emphasis">Borrador</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <?php if ($evento['publicado']): ?>
                        <a href="<?= e(url_publica('eventos', ['slug' => $evento['slug']])) ?>"
                           class="btn btn-sm btn-outline-secondary" target="_blank" title="Ver en el sitio">
                            <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                        <?php endif; ?>
                        <?php if (Auth::tienePermiso('eventos.editar')): ?>
                        <a href="<?= e(url_admin('eventos', 'editar', ['id' => $evento['id']])) ?>"
                           class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        <?php endif; ?>
                        <?php if (Auth::tienePermiso('eventos.eliminar')): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger"
                                data-bs-toggle="modal" data-bs-target="#borrar<?= (int) $evento['id'] ?>">
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
$paginaBase = url_admin('eventos', '', $porEstado + $porFecha);
require BASE_PATH . '/shared/views/parciales/paginacion.php';
?>

<?php foreach ($listado['filas'] as $evento): ?>
    <div class="modal fade" id="borrar<?= (int) $evento['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h2 class="h6 modal-title fw-bold">Eliminar evento</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Se eliminará «<?= e($evento['titulo']) ?>». Esta acción no se puede deshacer.</p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" accept-charset="UTF-8"
                          action="<?= e(url_post('admin', 'eventos', 'eliminar')) ?>" class="m-0">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="id" value="<?= (int) $evento['id'] ?>">
                        <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i>Eliminar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
