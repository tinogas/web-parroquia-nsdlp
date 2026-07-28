<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1">Mensajes</h1>
        <p class="text-muted mb-0 small">
            <?= $noLeidos > 0 ? e((string) $noLeidos) . ' sin leer' : 'No hay mensajes sin leer' ?>
        </p>
    </div>
    <div class="btn-group btn-group-sm" role="group">
        <a href="<?= e(url_admin('mensajes')) ?>"
           class="btn <?= $filtro === 'todos' ? 'btn-primary' : 'btn-outline-secondary' ?>">Todos</a>
        <a href="<?= e(url_admin('mensajes', '', ['filtro' => 'no_leidos'])) ?>"
           class="btn <?= $filtro === 'no_leidos' ? 'btn-primary' : 'btn-outline-secondary' ?>">Sin leer</a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <?php if (!$listado['filas']): ?>
        <div class="card-body text-center py-5">
            <div class="display-6 text-body-tertiary mb-2"><i class="bi bi-envelope"></i></div>
            <p class="text-muted mb-0">No hay mensajes que mostrar.</p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>De</th>
                    <th class="d-none d-md-table-cell">Asunto</th>
                    <th class="d-none d-lg-table-cell">Recibido</th>
                    <th>&nbsp;</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($listado['filas'] as $mensaje): ?>
                <tr>
                    <td>
                        <?php if (!$mensaje['leido']): ?>
                        <i class="bi bi-circle-fill text-primary me-1" style="font-size:.5rem" aria-label="Sin leer"></i>
                        <?php endif; ?>
                        <span class="<?= !$mensaje['leido'] ? 'fw-semibold' : '' ?>"><?= e($mensaje['nombre']) ?></span>
                        <div class="text-muted small"><?= e($mensaje['email'] ?: $mensaje['telefono'] ?: '') ?></div>
                    </td>
                    <td class="d-none d-md-table-cell"><?= e($mensaje['asunto'] ?: '(Sin asunto)') ?></td>
                    <td class="d-none d-lg-table-cell small text-muted"><?= e(fecha_larga($mensaje['created_at'])) ?></td>
                    <td class="text-end">
                        <a href="<?= e(url_admin('mensajes', 'ver', ['id' => $mensaje['id']])) ?>"
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
$paginaBase = url_admin('mensajes', '', $filtro !== 'todos' ? ['filtro' => $filtro] : []);
require BASE_PATH . '/shared/views/parciales/paginacion.php';
?>
