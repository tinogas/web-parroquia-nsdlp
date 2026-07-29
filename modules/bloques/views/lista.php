<div class="mb-4">
    <h1 class="h4 fw-bold mb-1">Textos del sitio</h1>
    <p class="text-muted mb-0 small">
        Cada texto tiene un lugar fijo en el sitio. Se puede cambiar su contenido y
        desactivarlo, pero no borrarlo: las páginas cuentan con que exista.
    </p>
</div>

<?php if (!$porZona): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-1"></i>
        No hay textos registrados. Vuelve a importar <code>install.sql</code>.
    </div>
<?php endif; ?>

<?php foreach ($porZona as $zona => $bloques): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom py-3">
        <h2 class="h6 fw-bold mb-0">
            <span class="text-primary me-1"><?= BloqueModel::iconoZona($zona) ?></span>
            <?= e(BloqueModel::nombreZona($zona)) ?>
        </h2>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Texto</th>
                    <th class="d-none d-md-table-cell">Estado</th>
                    <th class="d-none d-lg-table-cell">Última edición</th>
                    <th class="text-end">&nbsp;</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($bloques as $bloque): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e($bloque['titulo'] ?: $bloque['clave']) ?></div>
                        <?php if (!empty($bloque['descripcion'])): ?>
                        <div class="text-muted small"><?= e($bloque['descripcion']) ?></div>
                        <?php endif; ?>
                        <?php if (empty($bloque['contenido'])): ?>
                        <span class="badge bg-warning-subtle text-warning-emphasis mt-1">
                            <i class="bi bi-exclamation-triangle me-1"></i>Sin contenido
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="d-none d-md-table-cell">
                        <?php if ($bloque['activo']): ?>
                            <span class="badge bg-success-subtle text-success-emphasis">Visible</span>
                        <?php else: ?>
                            <span class="badge bg-secondary-subtle text-secondary-emphasis">Oculto</span>
                        <?php endif; ?>
                    </td>
                    <td class="d-none d-lg-table-cell small text-muted">
                        <?php if (!empty($bloque['updated_at'])): ?>
                            <?= e(fecha_larga($bloque['updated_at'])) ?>
                            <?php if (!empty($bloque['editor'])): ?>
                            <br><span class="text-body-tertiary"><?= e($bloque['editor']) ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a href="<?= e(url_admin('bloques', 'editar', ['id' => $bloque['id']])) ?>"
                           class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-pencil me-1"></i>Editar
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>
