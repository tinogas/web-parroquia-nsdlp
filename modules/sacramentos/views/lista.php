<div class="mb-4">
    <h1 class="h4 fw-bold mb-1">Sacramentos</h1>
    <p class="text-muted mb-0 small">
        Requisitos, documentos y formulario de solicitud de cada sacramento.
    </p>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Sacramento</th>
                    <th class="d-none d-md-table-cell">Solicitudes en línea</th>
                    <th class="d-none d-md-table-cell">Estado</th>
                    <th class="text-end">&nbsp;</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($sacramentos as $sacramento): ?>
                <tr>
                    <td class="fw-semibold"><?= e($sacramento['nombre']) ?></td>
                    <td class="d-none d-md-table-cell">
                        <?php if ($sacramento['acepta_solicitudes']): ?>
                            <span class="badge bg-success-subtle text-success-emphasis">Activas</span>
                        <?php else: ?>
                            <span class="badge bg-secondary-subtle text-secondary-emphasis">Desactivadas</span>
                        <?php endif; ?>
                    </td>
                    <td class="d-none d-md-table-cell">
                        <?php if ($sacramento['activo']): ?>
                            <span class="badge bg-success-subtle text-success-emphasis">Visible</span>
                        <?php else: ?>
                            <span class="badge bg-secondary-subtle text-secondary-emphasis">Oculto</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a href="<?= e(url_admin('sacramentos', 'editar', ['id' => $sacramento['id']])) ?>"
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

<p class="text-muted small mt-3">
    <i class="bi bi-info-circle me-1"></i>
    El catálogo de sacramentos es fijo: se edita cada uno, pero no se crean ni se borran
    desde aquí.
</p>
