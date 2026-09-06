<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1">Coros</h1>
        <p class="text-muted mb-0 small">Un coro por misa de domingo, con su encargado y sus integrantes.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php $navActiva = ''; require __DIR__ . '/_nav.php'; ?>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <?php if (!$horarios): ?>
        <div class="text-center py-4">
            <i class="bi bi-clock d-block display-6 text-muted mb-2"></i>
            <p class="text-muted small mb-2">No hay ninguna misa de domingo capturada en Horarios.</p>
            <?php if (Auth::tienePermiso('horarios.ver')): ?>
            <a href="<?= e(url_admin('horarios')) ?>" class="btn btn-sm btn-outline-primary">Ir a Horarios</a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Misa</th>
                        <th class="d-none d-md-table-cell">Sede</th>
                        <th>Encargado</th>
                        <th class="text-center">Integrantes</th>
                        <th>&nbsp;</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($horarios as $horario): ?>
                <?php
                $coro   = $coros[(int) $horario['id']] ?? null;
                $activo = $coro && $coro['activo'];
                $misIntegrantes = $coro ? ($integrantes[(int) $coro['id']] ?? []) : [];
                ?>
                    <tr class="<?= ($coro === null || $activo) ? '' : 'text-muted' ?>">
                        <td>
                            <span class="fw-semibold"><?= e(hora_corta($horario['hora'])) ?></span>
                            <?php if ($horario['nota']): ?>
                            <span class="text-muted small d-block"><?= e($horario['nota']) ?></span>
                            <?php endif; ?>
                            <?php if ($coro && !$activo): ?>
                            <span class="badge bg-light text-dark border fw-normal">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td class="d-none d-md-table-cell small"><?= e($horario['centro_nombre'] ?? '—') ?></td>
                        <td class="small">
                            <?php if (!$coro): ?>
                            <span class="text-muted">Sin coro</span>
                            <?php elseif ($coro['encargado_nombre']): ?>
                            <?= e($coro['encargado_nombre']) ?>
                            <?php else: ?>
                            <span class="text-muted">Sin encargado</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center small">
                            <?php /* El número es el botón: despliega la fila de abajo con quién canta
                                     en esta misa, que era el dato que obligaba a abrir el formulario
                                     de cada coro para verlo. Sin coro o sin nadie marcado no hay nada
                                     que desplegar, así que ahí va el número —o una raya— sin un botón
                                     que no lleve a ninguna parte. */ ?>
                            <?php if ($coro && $misIntegrantes): ?>
                            <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none collapsed toggle-integrantes"
                                    data-bs-toggle="collapse" data-bs-target="#integrantes<?= (int) $coro['id'] ?>"
                                    aria-expanded="false" aria-controls="integrantes<?= (int) $coro['id'] ?>"
                                    title="Ver quiénes cantan en esta misa">
                                <i class="bi bi-plus-square icono-mas"></i>
                                <i class="bi bi-dash-square icono-menos"></i>
                                <span class="ms-1"><?= count($misIntegrantes) ?></span>
                            </button>
                            <?php else: ?>
                            <?= $coro ? (int) $coro['total_coristas'] : '<span class="text-muted">—</span>' ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <?php if ($coro && Auth::tienePermiso('coros.editar')): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal" data-bs-target="#coro<?= (int) $coro['id'] ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php elseif (!$coro && Auth::tienePermiso('coros.crear')): ?>
                            <form method="POST" accept-charset="UTF-8" class="d-inline"
                                  action="<?= e(url_post('admin', 'coros', 'coro_guardar')) ?>">
                                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                <input type="hidden" name="id" value="0">
                                <input type="hidden" name="horario_id" value="<?= (int) $horario['id'] ?>">
                                <input type="hidden" name="activo" value="1">
                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-plus-lg me-1"></i>Crear coro
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($coro && $misIntegrantes): ?>
                    <?php /* El desplegable vive dentro de una celda y no en la fila misma: una
                             <tr class="collapse"> se anima cambiándole el alto, y en una tabla eso
                             da tirones. Así la fila siempre está y lo que se abre y se cierra es el
                             div de dentro. */ ?>
                    <tr class="border-0">
                        <td colspan="5" class="p-0 border-0">
                            <div class="collapse" id="integrantes<?= (int) $coro['id'] ?>">
                                <div class="px-3 pb-3 pt-1">
                                    <?php $encargadoId = (int) ($coro['encargado_id'] ?? 0); ?>
                                    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-1">
                                        <?php foreach ($misIntegrantes as $integrante): ?>
                                        <div class="col small">
                                            <?php $esEncargado = $encargadoId === (int) $integrante['id']; ?>
                                            <i class="bi <?= $esEncargado ? 'bi-star-fill text-dorado' : 'bi-music-note text-muted' ?> me-1"
                                               title="<?= $esEncargado ? 'Encargado del coro' : 'Integrante' ?>"></i>
                                            <span class="<?= $integrante['activo'] ? '' : 'text-muted' ?>"><?= e($integrante['nombre']) ?></span>
                                            <?php if ($esEncargado): ?>
                                            <span class="badge bg-warning-subtle text-warning-emphasis fw-normal">Encargado</span>
                                            <?php endif; ?>
                                            <?php if (!$integrante['activo']): ?>
                                            <span class="badge bg-light text-dark border fw-normal">Inactivo</span>
                                            <?php endif; ?>
                                            <?php $hace = trim((string) $integrante['voz']
                                                . ($integrante['voz'] && $integrante['instrumento'] ? ' · ' : '')
                                                . (string) $integrante['instrumento']); ?>
                                            <?php if ($hace !== ''): ?>
                                            <span class="text-muted">— <?= e($hace) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!$coristas && $coros): ?>
<p class="text-muted small">
    Todavía no hay nadie en el catálogo, así que ningún coro puede tener integrantes.
    <a href="<?= e(url_admin('coros', 'coristas')) ?>">Agrega integrantes</a>.
</p>
<?php endif; ?>

<?php
/*
 * Los modales de edición, fuera de la tabla para no anidar formularios —el
 * mismo criterio que siguen el resto de los listados del panel—. Solo se
 * generan si quien mira puede editar: dibujar el modal y esconder el botón
 * sería teatro, aunque el límite de verdad esté en el controlador.
 */
$puedeEditar = Auth::tienePermiso('coros.editar');
foreach ($horarios as $horario):
    $coro = $coros[(int) $horario['id']] ?? null;
    if (!$coro || !$puedeEditar) {
        continue;
    }
    $coroId     = (int) $coro['id'];
    // `integrantes` trae ahora la ficha de cada quien —hace falta para la lista
    // desplegable—, así que aquí se sacan solo los ids, que es lo que la casilla
    // necesita comparar.
    $suyos      = array_map('intval', array_column($integrantes[$coroId] ?? [], 'id'));
    $encargadoId = (int) ($coro['encargado_id'] ?? 0);
?>
<div class="modal fade" id="coro<?= $coroId ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form method="POST" accept-charset="UTF-8"
              action="<?= e(url_post('admin', 'coros', 'coro_guardar')) ?>" class="modal-content">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="id" value="<?= $coroId ?>">

            <div class="modal-header border-0 pb-0">
                <h2 class="h6 modal-title fw-bold">
                    Coro de la misa de <?= e(hora_corta($horario['hora'])) ?>
                    <span class="d-block text-muted fw-normal small">
                        <?= e(CoroController::etiquetaDeCoro($horario)) ?>
                    </span>
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Integrantes</label>
                    <?php if (!$coristas): ?>
                    <p class="text-muted small mb-0">
                        El catálogo está vacío.
                        <a href="<?= e(url_admin('coros', 'coristas')) ?>">Agrega integrantes</a> y vuelve aquí.
                    </p>
                    <?php else: ?>
                    <?php foreach ($coristas as $corista): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="coristas[]"
                               value="<?= (int) $corista['id'] ?>" id="cc<?= $coroId ?>_<?= (int) $corista['id'] ?>"
                               <?= in_array((int) $corista['id'], $suyos, true) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="cc<?= $coroId ?>_<?= (int) $corista['id'] ?>">
                            <?= e($corista['nombre']) ?>
                            <?php $hace = trim((string) $corista['voz']
                                . ($corista['voz'] && $corista['instrumento'] ? ' · ' : '')
                                . (string) $corista['instrumento']); ?>
                            <?php if ($hace !== ''): ?>
                            <span class="text-muted">— <?= e($hace) ?></span>
                            <?php endif; ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                    <div class="form-text">
                        Solo aparece quien está activo en el catálogo; tras el guión, su voz y su
                        instrumento, que es con lo que se reparte lo que se canta.
                    </div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold" for="enc<?= $coroId ?>">Encargado</label>
                    <select name="encargado_id" id="enc<?= $coroId ?>" class="form-select form-select-sm">
                        <option value="">— Sin encargado —</option>
                        <?php foreach ($coristas as $corista): ?>
                        <option value="<?= (int) $corista['id'] ?>"
                            <?= $encargadoId === (int) $corista['id'] ? 'selected' : '' ?>>
                            <?= e($corista['nombre']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Tiene que ser alguien marcado arriba; si no, el coro se guarda sin encargado.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold" for="nota<?= $coroId ?>">Nota</label>
                    <input type="text" name="nota" id="nota<?= $coroId ?>" class="form-control form-control-sm"
                           value="<?= e((string) $coro['nota']) ?>" maxlength="160"
                           placeholder="Ej. Ensaya los jueves a las 19:00">
                </div>

                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" name="activo" value="1"
                           id="act<?= $coroId ?>" <?= $coro['activo'] ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="act<?= $coroId ?>">Activo</label>
                </div>
            </div>
            <div class="modal-footer border-0 justify-content-between">
                <?php if (Auth::tienePermiso('coros.eliminar')): ?>
                <button type="submit" formaction="<?= e(url_post('admin', 'coros', 'coro_eliminar')) ?>"
                        class="btn btn-outline-danger btn-sm"
                        onclick="return confirm('¿Eliminar este coro? Quienes cantaban en él siguen en el catálogo.');">
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
<?php endforeach; ?>
