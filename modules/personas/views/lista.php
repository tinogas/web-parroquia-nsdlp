<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1">Equipo pastoral</h1>
        <p class="text-muted mb-0 small">
            Sacerdotes, diáconos y personal que aparecen en "Quiénes somos" y pueden
            asignarse al organigrama.
        </p>
    </div>
    <a href="<?= e(url_admin('personas', 'nueva')) ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nueva persona
    </a>
</div>

<div class="card border-0 shadow-sm">
    <?php if (!$personas): ?>
        <div class="card-body text-center py-5">
            <div class="display-6 text-body-tertiary mb-2"><i class="bi bi-person-badge"></i></div>
            <p class="text-muted mb-0">Todavía no hay nadie registrado.</p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Nombre</th>
                    <th class="d-none d-md-table-cell">Tipo</th>
                    <th class="d-none d-md-table-cell">Estado</th>
                    <th class="text-end">&nbsp;</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($personas as $persona): ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <img src="<?= e(foto_o_avatar($persona['foto'], $persona['nombre'], 40)) ?>"
                                 class="rounded-circle" style="width:32px;height:32px;object-fit:cover" alt="">
                            <div>
                                <div class="fw-semibold"><?= e($persona['nombre']) ?></div>
                                <?php if ($persona['cargo']): ?>
                                <div class="text-muted small"><?= e($persona['cargo']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($persona['pastorales_nombres'])): ?>
                                <div class="text-muted small"><i class="bi bi-people me-1"></i><?= e($persona['pastorales_nombres']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="d-none d-md-table-cell"><?= e(PersonaModel::TIPOS[$persona['tipo']] ?? $persona['tipo']) ?></td>
                    <td class="d-none d-md-table-cell">
                        <?php if ($persona['activo']): ?>
                            <span class="badge bg-success-subtle text-success-emphasis">Activo</span>
                        <?php else: ?>
                            <span class="badge bg-secondary-subtle text-secondary-emphasis">Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="<?= e(url_admin('personas', 'editar', ['id' => $persona['id']])) ?>"
                           class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-pencil me-1"></i>Editar
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-danger"
                                data-bs-toggle="modal" data-bs-target="#borrar<?= (int) $persona['id'] ?>" title="Eliminar">
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

<?php foreach ($personas as $persona): ?>
    <div class="modal fade" id="borrar<?= (int) $persona['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h2 class="h6 modal-title fw-bold">Eliminar a <?= e($persona['nombre']) ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">
                        Si ya no forma parte del equipo, es mejor <strong>desactivarla</strong> desde su ficha:
                        conserva el historial y su lugar en el organigrama. Elimínala solo si el registro se
                        creó por error. Si aparecía en el organigrama, ese lugar quedará sin asignar.
                    </p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" accept-charset="UTF-8"
                          action="<?= e(url_post('admin', 'personas', 'eliminar')) ?>" class="m-0">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="id" value="<?= (int) $persona['id'] ?>">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash me-1"></i>Eliminar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
