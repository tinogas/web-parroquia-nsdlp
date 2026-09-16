<?php
$porTipo = [];
foreach ($horarios as $horario) {
    $porTipo[$horario['tipo']][] = $horario;
}
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1">Horarios</h1>
        <p class="text-muted mb-0 small">
            Misas, confesiones, adoración eucarística y horario de oficina.
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= e(url_admin('horarios', 'horarios_imprimir')) ?>" target="_blank"
           class="btn btn-outline-secondary">
            <i class="bi bi-printer me-1"></i>Imprimir horarios
        </a>
        <a href="<?= e(url_admin('horarios', 'nuevo')) ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Nuevo horario
        </a>
    </div>
</div>

<?php if (count($centros) > 1): /* Con una sola sede el selector no elige nada. */ ?>
<form method="GET" action="<?= e(url_admin('horarios')) ?>" class="row g-2 align-items-end mb-3">
    <?php if (!URLS_AMIGABLES): ?>
    <?php /* Sin URLs amigables la ruta va en la cadena de consulta, y un GET
             descarta la del action: hay que repetirla como campos. */ ?>
    <input type="hidden" name="area" value="admin">
    <input type="hidden" name="modulo" value="horarios">
    <?php endif; ?>
    <div class="col-auto">
        <label for="centro" class="form-label small fw-semibold mb-1">Sede o centro</label>
        <select name="centro" id="centro" class="form-select form-select-sm">
            <option value="">Todas</option>
            <?php foreach ($centros as $centro): ?>
            <option value="<?= (int) $centro['id'] ?>"
                    <?= $filtroCentro === (string) $centro['id'] ? 'selected' : '' ?>>
                <?= e($centro['nombre']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-primary">
            <i class="bi bi-funnel me-1"></i>Filtrar
        </button>
    </div>
    <?php if ($filtroCentro !== ''): ?>
    <div class="col-auto">
        <a href="<?= e(url_admin('horarios')) ?>" class="btn btn-sm btn-outline-secondary">Quitar</a>
    </div>
    <?php endif; ?>
</form>
<?php endif; ?>

<?php if (!$horarios): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5">
        <div class="display-6 text-body-tertiary mb-2"><i class="bi bi-clock"></i></div>
        <p class="text-muted mb-0">Todavía no hay horarios registrados.</p>
    </div>
</div>
<?php endif; ?>

<?php foreach (HorarioModel::TIPOS as $tipo => [$nombreTipo, $icono]): ?>
    <?php if (empty($porTipo[$tipo])) { continue; } ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom py-3">
            <h2 class="h6 fw-bold mb-0"><i class="bi <?= e($icono) ?> text-primary me-1"></i><?= e($nombreTipo) ?></h2>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Día</th>
                        <th>Hora</th>
                        <th class="d-none d-md-table-cell">Lugar</th>
                        <th class="d-none d-lg-table-cell">Vigencia</th>
                        <th class="d-none d-md-table-cell">Estado</th>
                        <th class="text-end">&nbsp;</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($porTipo[$tipo] as $horario): ?>
                    <tr>
                        <td class="text-capitalize"><?= e(nombre_dia((int) $horario['dia_semana'])) ?></td>
                        <td>
                            <?= e(hora_corta($horario['hora'])) ?>
                            <?php if ($horario['hora_fin']): ?> – <?= e(hora_corta($horario['hora_fin'])) ?><?php endif; ?>
                            <?php if ($horario['tiene_coro']): ?>
                            <span class="badge bg-info-subtle text-info-emphasis ms-1">
                                <i class="bi bi-music-note-beamed me-1"></i>Con coro
                            </span>
                            <?php endif; ?>
                            <?php if ($horario['nota']): ?>
                            <div class="text-muted small"><?= e($horario['nota']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="d-none d-md-table-cell"><?= e($horario['lugar'] ?: '—') ?></td>
                        <td class="d-none d-lg-table-cell small text-muted">
                            <?php if ($horario['vigente_desde'] || $horario['vigente_hasta']): ?>
                                <?= e($horario['vigente_desde'] ?: '…') ?> – <?= e($horario['vigente_hasta'] ?: '…') ?>
                            <?php else: ?>
                                Todo el año
                            <?php endif; ?>
                        </td>
                        <td class="d-none d-md-table-cell">
                            <?php if ($horario['activo']): ?>
                                <span class="badge bg-success-subtle text-success-emphasis">Visible</span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary-emphasis">Oculto</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="<?= e(url_admin('horarios', 'editar', ['id' => $horario['id']])) ?>"
                               class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                            <button type="button" class="btn btn-sm btn-outline-danger"
                                    data-bs-toggle="modal" data-bs-target="#borrar<?= (int) $horario['id'] ?>">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endforeach; ?>

<?php foreach ($horarios as $horario): ?>
    <div class="modal fade" id="borrar<?= (int) $horario['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h2 class="h6 modal-title fw-bold">Eliminar este horario</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">
                        Se eliminará <?= e(nombre_dia((int) $horario['dia_semana'])) ?> a las
                        <?= e(hora_corta($horario['hora'])) ?>. Esta acción no se puede deshacer.
                    </p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" accept-charset="UTF-8"
                          action="<?= e(url_post('admin', 'horarios', 'eliminar')) ?>" class="m-0">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="id" value="<?= (int) $horario['id'] ?>">
                        <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i>Eliminar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
