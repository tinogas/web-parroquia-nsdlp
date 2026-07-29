<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1">Solicitudes de sacramentos</h1>
        <p class="text-muted mb-0 small">Contienen datos personales. Cada consulta queda registrada.</p>
    </div>
    <div class="d-flex gap-2">
        <?php if (Auth::tienePermiso('solicitudes.exportar')): ?>
        <a href="<?= e(url_admin('solicitudes', 'exportar', array_filter([
                'estado' => $estado !== 'todos' ? $estado : null,
                'sacramento_id' => $sacramentoId,
            ]))) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i>Exportar CSV
        </a>
        <?php endif; ?>
        <?php if (Auth::esAdmin()): ?>
        <form method="POST" accept-charset="UTF-8" action="<?= e(url_post('admin', 'solicitudes', 'purgar')) ?>"
              onsubmit="return confirm('¿Anonimizar las solicitudes cerradas que ya cumplieron el plazo de retención? No se puede deshacer.');">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <button type="submit" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-shield-check me-1"></i>Purgar vencidas
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>

<div class="d-flex flex-wrap gap-2 mb-3">
    <div class="btn-group btn-group-sm" role="group">
        <a href="<?= e(url_admin('solicitudes')) ?>"
           class="btn <?= $estado === 'todos' ? 'btn-primary' : 'btn-outline-secondary' ?>">Todas</a>
        <?php foreach (SolicitudModel::ESTADOS as $valor => $etiqueta): ?>
        <a href="<?= e(url_admin('solicitudes', '', ['estado' => $valor])) ?>"
           class="btn <?= $estado === $valor ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= e($etiqueta) ?></a>
        <?php endforeach; ?>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <?php if (!$listado['filas']): ?>
        <div class="card-body text-center py-5">
            <div class="display-6 text-body-tertiary mb-2"><i class="bi bi-file-earmark-text"></i></div>
            <p class="text-muted mb-0">No hay solicitudes que mostrar.</p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Folio</th>
                    <th>Solicitante</th>
                    <th class="d-none d-md-table-cell">Sacramento</th>
                    <th class="d-none d-lg-table-cell">Recibida</th>
                    <th>Estado</th>
                    <th>&nbsp;</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($listado['filas'] as $solicitud): ?>
                <tr>
                    <td class="font-monospace small"><?= e($solicitud['folio']) ?></td>
                    <td>
                        <?= e($solicitud['nombre_solicitante']) ?>
                        <?php if ($solicitud['es_menor']): ?>
                        <span class="badge bg-info-subtle text-info-emphasis">Menor</span>
                        <?php endif; ?>
                    </td>
                    <td class="d-none d-md-table-cell"><?= e($solicitud['sacramento_nombre']) ?></td>
                    <td class="d-none d-lg-table-cell small text-muted"><?= e(fecha_larga($solicitud['created_at'])) ?></td>
                    <td>
                        <?php
                        $clase = match ($solicitud['estado']) {
                            'aprobada', 'completada' => 'bg-success-subtle text-success-emphasis',
                            'rechazada', 'cancelada' => 'bg-secondary-subtle text-secondary-emphasis',
                            'en_revision'            => 'bg-info-subtle text-info-emphasis',
                            default                  => 'bg-warning-subtle text-warning-emphasis',
                        };
                        ?>
                        <span class="badge <?= $clase ?>"><?= e(SolicitudModel::ESTADOS[$solicitud['estado']]) ?></span>
                    </td>
                    <td class="text-end">
                        <a href="<?= e(url_admin('solicitudes', 'ver', ['id' => $solicitud['id']])) ?>"
                           class="btn btn-sm btn-outline-primary">Ver</a>
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
$paginaBase = url_admin('solicitudes', '', array_filter([
    'estado' => $estado !== 'todos' ? $estado : null,
    'sacramento_id' => $sacramentoId,
]));
require BASE_PATH . '/shared/views/parciales/paginacion.php';
?>
