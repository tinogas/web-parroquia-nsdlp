<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1">Auditoría</h1>
        <p class="text-muted mb-0 small">Quién hizo qué, y cuándo. Incluye las consultas de datos personales.</p>
    </div>
    <?php if (Auth::tienePermiso('auditoria.exportar')): ?>
    <a href="<?= e(url_admin('auditoria', 'exportar', array_filter($filtros))) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-download me-1"></i>Exportar CSV
    </a>
    <?php endif; ?>
</div>

<form method="GET" accept-charset="UTF-8" action="<?= e(url_admin('auditoria')) ?>" class="card border-0 shadow-sm mb-3">
    <?php if (!URLS_AMIGABLES): ?>
    <input type="hidden" name="area" value="admin">
    <input type="hidden" name="modulo" value="auditoria">
    <?php endif; ?>
    <div class="card-body p-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-3 col-6">
                <label for="usuario_id" class="form-label small fw-semibold mb-1">Usuario</label>
                <select name="usuario_id" id="usuario_id" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach ($usuarios as $u): ?>
                    <option value="<?= (int) $u['id'] ?>" <?= (int) ($filtros['usuario_id'] ?? 0) === (int) $u['id'] ? 'selected' : '' ?>>
                        <?= e($u['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 col-6">
                <label for="tipo_accion" class="form-label small fw-semibold mb-1">Acción</label>
                <select name="tipo_accion" id="tipo_accion" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    <?php foreach ($acciones as $a): ?>
                    <option value="<?= e($a) ?>" <?= ($filtros['tipo_accion'] ?? '') === $a ? 'selected' : '' ?>><?= e($a) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 col-6">
                <label for="tabla_ref" class="form-label small fw-semibold mb-1">Tabla</label>
                <select name="tabla_ref" id="tabla_ref" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    <?php foreach ($tablas as $t): ?>
                    <option value="<?= e($t) ?>" <?= ($filtros['tabla_ref'] ?? '') === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1 col-6">
                <label for="desde" class="form-label small fw-semibold mb-1">Desde</label>
                <input type="date" name="desde" id="desde" class="form-control form-control-sm"
                       value="<?= e((string) ($filtros['desde'] ?? '')) ?>">
            </div>
            <div class="col-md-1 col-6">
                <label for="hasta" class="form-label small fw-semibold mb-1">Hasta</label>
                <input type="date" name="hasta" id="hasta" class="form-control form-control-sm"
                       value="<?= e((string) ($filtros['hasta'] ?? '')) ?>">
            </div>
            <div class="col-md-1 col-12 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1" title="Filtrar">
                    <i class="bi bi-funnel"></i>
                </button>
                <a href="<?= e(url_admin('auditoria')) ?>" class="btn btn-outline-secondary btn-sm" title="Limpiar">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <?php if (!$listado['filas']): ?>
        <div class="card-body text-center py-5">
            <div class="display-6 text-body-tertiary mb-2"><i class="bi bi-journal-text"></i></div>
            <p class="text-muted mb-0">No hay movimientos que coincidan con el filtro.</p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="text-nowrap">Fecha</th>
                    <th>Usuario</th>
                    <th>Acción</th>
                    <th class="d-none d-md-table-cell">Tabla</th>
                    <th class="d-none d-lg-table-cell">Descripción</th>
                    <th class="d-none d-lg-table-cell">IP</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($listado['filas'] as $fila): ?>
                <tr>
                    <td class="text-nowrap small text-muted"><?= e(fecha_con_dia($fila['created_at'])) ?></td>
                    <td>
                        <?= e($fila['usuario_nombre'] ?? 'Sistema') ?>
                        <?php if (!empty($fila['admin_real_nombre'])): ?>
                        <div class="small text-muted" title="Acción hecha durante una impersonación (&quot;Usar como…&quot;)">
                            <i class="bi bi-person-badge me-1"></i>admin real: <?= e($fila['admin_real_nombre']) ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $clase = match ($fila['accion']) {
                            'crear'                 => 'bg-success-subtle text-success-emphasis',
                            'editar', 'cambiar_estado' => 'bg-info-subtle text-info-emphasis',
                            'eliminar'              => 'bg-danger-subtle text-danger-emphasis',
                            'consultar', 'exportar' => 'bg-secondary-subtle text-secondary-emphasis',
                            default                 => 'bg-warning-subtle text-warning-emphasis',
                        };
                        ?>
                        <span class="badge <?= $clase ?>"><?= e($fila['accion']) ?></span>
                    </td>
                    <td class="d-none d-md-table-cell small font-monospace">
                        <?= e($fila['tabla_ref'] ?? '') ?>
                        <?php if ($fila['registro_id']): ?> #<?= (int) $fila['registro_id'] ?><?php endif; ?>
                    </td>
                    <td class="d-none d-lg-table-cell small"><?= e($fila['descripcion'] ?? '') ?></td>
                    <td class="d-none d-lg-table-cell small text-muted"><?= e($fila['ip'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php
$paginacion = $listado;
$paginaBase = url_admin('auditoria', '', array_filter($filtros));
require BASE_PATH . '/shared/views/parciales/paginacion.php';
?>
