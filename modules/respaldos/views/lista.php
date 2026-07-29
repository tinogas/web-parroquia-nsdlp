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
    Descárgalo y guárdalo en un lugar seguro, fuera del hosting. Para restaurarlo,
    impórtalo desde phpMyAdmin: este panel no reescribe la base de datos automáticamente.
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
                <?php $existe = $respaldo['estado'] === 'completado' && is_file(BASE_PATH . '/backups/' . $respaldo['archivo']); ?>
                <tr>
                    <td class="small text-nowrap"><?= e(fecha_con_dia($respaldo['created_at'])) ?></td>
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
                        <?php if ($existe): ?>
                        <a href="<?= e(url_admin('respaldos', 'descargar', ['id' => $respaldo['id']])) ?>"
                           class="btn btn-sm btn-outline-success" title="Descargar">
                            <i class="bi bi-download"></i>
                        </a>
                        <?php elseif ($respaldo['estado'] === 'completado'): ?>
                        <span class="badge bg-secondary-subtle text-secondary-emphasis">sin archivo</span>
                        <?php endif; ?>
                        <?php if (Auth::tienePermiso('respaldos.eliminar')): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger" title="Eliminar"
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

<?php if (Auth::tienePermiso('respaldos.eliminar')): ?>
<?php foreach ($respaldos as $respaldo): ?>
    <div class="modal fade" id="borrar<?= (int) $respaldo['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h2 class="h6 modal-title fw-bold">Eliminar respaldo</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">
                        Se borrará el archivo <strong><?= e($respaldo['archivo']) ?></strong> y su registro en el
                        historial. No se puede deshacer.
                    </p>
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
<?php endforeach; ?>
<?php endif; ?>
