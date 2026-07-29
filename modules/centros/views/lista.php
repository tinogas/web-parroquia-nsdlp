<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1">Sede y centros</h1>
        <p class="text-muted mb-0 small">La sede parroquial y los centros que dependen de ella.</p>
    </div>
    <a href="<?= e(url_admin('centros', 'nuevo')) ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nuevo centro
    </a>
</div>

<div class="card border-0 shadow-sm">
    <?php if (!$centros): ?>
        <div class="card-body text-center py-5">
            <div class="display-6 text-body-tertiary mb-2"><i class="bi bi-buildings"></i></div>
            <p class="text-muted mb-0">Todavía no hay nada registrado.</p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Nombre</th>
                    <th class="d-none d-md-table-cell">Tipo</th>
                    <th class="d-none d-lg-table-cell">Dirección</th>
                    <th class="d-none d-md-table-cell">Estado</th>
                    <th class="text-end">&nbsp;</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($centros as $centro): ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <img src="<?= e(foto_o_avatar($centro['imagen'], $centro['nombre'], 40)) ?>"
                                 class="rounded" style="width:32px;height:32px;object-fit:cover" alt="">
                            <div class="fw-semibold"><?= e($centro['nombre']) ?></div>
                        </div>
                    </td>
                    <td class="d-none d-md-table-cell">
                        <span class="badge <?= $centro['tipo'] === 'sede' ? 'bg-primary-subtle text-primary-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' ?>">
                            <?= e(CentroModel::TIPOS[$centro['tipo']] ?? $centro['tipo']) ?>
                        </span>
                    </td>
                    <td class="d-none d-lg-table-cell small text-muted"><?= e($centro['direccion'] ?: '—') ?></td>
                    <td class="d-none d-md-table-cell">
                        <?php if ($centro['activo']): ?>
                            <span class="badge bg-success-subtle text-success-emphasis">Activo</span>
                        <?php else: ?>
                            <span class="badge bg-secondary-subtle text-secondary-emphasis">Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="<?= e(url_admin('centros', 'editar', ['id' => $centro['id']])) ?>"
                           class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-pencil me-1"></i>Editar
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-danger"
                                data-bs-toggle="modal" data-bs-target="#borrar<?= (int) $centro['id'] ?>" title="Eliminar">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php foreach ($centros as $centro): ?>
    <div class="modal fade" id="borrar<?= (int) $centro['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h2 class="h6 modal-title fw-bold">Eliminar <?= e($centro['nombre']) ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">
                        Si ya no está en uso, es mejor <strong>desactivarlo</strong> desde su ficha: conserva su
                        historial si alguna pastoral o registro lo referencia. Elimínalo solo si el registro se
                        creó por error.
                    </p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" accept-charset="UTF-8"
                          action="<?= e(url_post('admin', 'centros', 'eliminar')) ?>" class="m-0">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="id" value="<?= (int) $centro['id'] ?>">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash me-1"></i>Eliminar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
