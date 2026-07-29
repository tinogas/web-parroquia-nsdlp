<?php
/**
 * Bytes a una unidad legible. Cierre local, no función global: esta vista no
 * debe fallar con "Cannot redeclare" si alguna vez se incluyera dos veces en
 * el mismo request.
 */
$formatoTamano = function (?int $bytes): string {
    if ($bytes === null) {
        return '—';
    }
    $unidades = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($unidades) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, $i === 0 ? 0 : 1) . ' ' . $unidades[$i];
};
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1">Respaldos de la base de datos</h1>
        <p class="text-muted mb-0 small">Un archivo .sql con la estructura y los datos completos.</p>
    </div>
    <?php if (Auth::tienePermiso('respaldos.crear')): ?>
    <form method="POST" accept-charset="UTF-8" action="<?= e(url_post('admin', 'respaldos', 'crear')) ?>"
          onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').innerHTML='<span class=\'spinner-border spinner-border-sm me-1\'></span>Generando…';">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-database-add me-1"></i>Generar respaldo ahora
        </button>
    </form>
    <?php endif; ?>
</div>

<div class="alert alert-info">
    <i class="bi bi-info-circle me-1"></i>
    El respaldo incluye la estructura y todos los datos de la base <code><?= e(DB_NAME) ?></code>.
    Descárgalo y guárdalo en un lugar seguro, fuera del hosting. También puedes restaurarlo
    directamente desde este panel: antes de tocar nada se genera un respaldo de seguridad
    del estado actual, por si hay que volver atrás.
</div>

<div class="card border-0 shadow-sm">
    <?php if (!$respaldos): ?>
        <div class="card-body text-center py-5">
            <div class="display-6 text-body-tertiary mb-2"><i class="bi bi-database"></i></div>
            <p class="text-muted mb-0">Todavía no se ha generado ningún respaldo.</p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th class="d-none d-md-table-cell">Archivo</th>
                    <th class="d-none d-lg-table-cell text-end">Tamaño</th>
                    <th class="d-none d-lg-table-cell text-end">Tablas</th>
                    <th class="d-none d-lg-table-cell text-end">Registros</th>
                    <th class="d-none d-md-table-cell">Usuario</th>
                    <th>Estado</th>
                    <th class="text-end">&nbsp;</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($respaldos as $respaldo): ?>
                <?php
                $esRespaldo = $respaldo['tipo'] === 'respaldo';
                $existe     = $respaldo['estado'] === 'completado'
                    && is_file(BASE_PATH . '/backups/' . $respaldo['archivo']);
                ?>
                <tr>
                    <td class="small text-nowrap"><?= e(fecha_con_dia($respaldo['created_at'])) ?></td>
                    <td>
                        <?php if ($esRespaldo): ?>
                        <span class="badge bg-primary-subtle text-primary-emphasis">Respaldo</span>
                        <?php else: ?>
                        <span class="badge bg-warning-subtle text-warning-emphasis">Restauración</span>
                        <?php endif; ?>
                    </td>
                    <td class="d-none d-md-table-cell small font-monospace"><?= e($respaldo['archivo']) ?></td>
                    <td class="d-none d-lg-table-cell text-end small"><?= e($formatoTamano($respaldo['tamano_bytes'] !== null ? (int) $respaldo['tamano_bytes'] : null)) ?></td>
                    <td class="d-none d-lg-table-cell text-end small"><?= $respaldo['num_tablas'] !== null ? (int) $respaldo['num_tablas'] : '—' ?></td>
                    <td class="d-none d-lg-table-cell text-end small"><?= $respaldo['num_registros'] !== null ? number_format((int) $respaldo['num_registros']) : '—' ?></td>
                    <td class="d-none d-md-table-cell small"><?= e($respaldo['usuario_nombre'] ?? 'Sistema') ?></td>
                    <td>
                        <?php if ($respaldo['estado'] === 'completado'): ?>
                        <span class="badge bg-success-subtle text-success-emphasis"><i class="bi bi-check-circle me-1"></i>Listo</span>
                        <?php else: ?>
                        <span class="badge bg-danger-subtle text-danger-emphasis" title="<?= e($respaldo['notas'] ?? '') ?>">
                            <i class="bi bi-x-circle me-1"></i>Error
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <?php if ($esRespaldo && $existe): ?>
                        <a href="<?= e(url_admin('respaldos', 'descargar', ['id' => $respaldo['id']])) ?>"
                           class="btn btn-sm btn-outline-success" title="Descargar">
                            <i class="bi bi-download"></i>
                        </a>
                        <?php if (Auth::tienePermiso('respaldos.restaurar')): ?>
                        <button type="button" class="btn btn-sm btn-outline-warning" title="Restaurar"
                                data-bs-toggle="modal" data-bs-target="#restaurar<?= (int) $respaldo['id'] ?>">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </button>
                        <?php endif; ?>
                        <?php elseif ($esRespaldo && $respaldo['estado'] === 'completado'): ?>
                        <span class="badge bg-secondary-subtle text-secondary-emphasis">sin archivo</span>
                        <?php endif; ?>
                        <?php if (Auth::tienePermiso('respaldos.eliminar')): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger" title="Eliminar del historial"
                                data-bs-toggle="modal" data-bs-target="#borrar<?= (int) $respaldo['id'] ?>">
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

<?php foreach ($respaldos as $respaldo): ?>
    <?php $esRespaldo = $respaldo['tipo'] === 'respaldo'; ?>
    <?php $existe = $respaldo['estado'] === 'completado' && is_file(BASE_PATH . '/backups/' . $respaldo['archivo']); ?>

    <?php if ($esRespaldo && $existe && Auth::tienePermiso('respaldos.restaurar')): ?>
    <div class="modal fade" id="restaurar<?= (int) $respaldo['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h2 class="h6 modal-title fw-bold">Restaurar respaldo</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger mb-3">
                        Esto <strong>reemplaza todos los datos actuales</strong> con los de
                        <strong><?= e($respaldo['archivo']) ?></strong>. No se puede deshacer a mano, aunque el
                        sistema genera automáticamente un respaldo de seguridad del estado presente antes de
                        empezar, por si hay que volver atrás.
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="confirmo<?= (int) $respaldo['id'] ?>"
                               onchange="document.getElementById('btnRestaurar<?= (int) $respaldo['id'] ?>').disabled = !this.checked;">
                        <label class="form-check-label" for="confirmo<?= (int) $respaldo['id'] ?>">
                            Entiendo que esto reemplaza todos los datos actuales y quiero continuar.
                        </label>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" accept-charset="UTF-8"
                          action="<?= e(url_post('admin', 'respaldos', 'restaurar')) ?>" class="m-0"
                          onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').innerHTML='<span class=\'spinner-border spinner-border-sm me-1\'></span>Restaurando…';">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="id" value="<?= (int) $respaldo['id'] ?>">
                        <input type="hidden" name="confirmo" value="1">
                        <button type="submit" id="btnRestaurar<?= (int) $respaldo['id'] ?>" class="btn btn-warning" disabled>
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Restaurar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (Auth::tienePermiso('respaldos.eliminar')): ?>
    <div class="modal fade" id="borrar<?= (int) $respaldo['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h2 class="h6 modal-title fw-bold">Eliminar del historial</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <?php if ($esRespaldo): ?>
                    <p class="mb-0">
                        Se borrará el archivo <strong><?= e($respaldo['archivo']) ?></strong> y su registro en el
                        historial. No se puede deshacer.
                    </p>
                    <?php else: ?>
                    <p class="mb-0">
                        Se borrará este registro del historial. El archivo <strong><?= e($respaldo['archivo']) ?></strong>
                        pertenece a otro respaldo y no se toca.
                    </p>
                    <?php endif; ?>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" accept-charset="UTF-8"
                          action="<?= e(url_post('admin', 'respaldos', 'eliminar')) ?>" class="m-0">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="id" value="<?= (int) $respaldo['id'] ?>">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash me-1"></i>Eliminar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
<?php endforeach; ?>
