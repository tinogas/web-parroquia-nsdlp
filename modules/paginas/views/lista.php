<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1">Páginas</h1>
        <p class="text-muted mb-0 small">
            Para contenido que no cabe en las secciones fijas del sitio. Cada página
            tiene su propia dirección.
        </p>
    </div>
    <a href="<?= e(url_admin('paginas', 'nueva')) ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nueva página
    </a>
</div>

<div class="card border-0 shadow-sm">
    <?php if (!$paginas): ?>
        <div class="card-body text-center py-5">
            <div class="display-6 text-body-tertiary mb-2"><i class="bi bi-file-earmark-text"></i></div>
            <p class="text-muted mb-0">Todavía no hay páginas.</p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Página</th>
                    <th class="d-none d-md-table-cell">Estado</th>
                    <th class="d-none d-lg-table-cell">Última edición</th>
                    <th class="text-end">&nbsp;</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($paginas as $pagina): ?>
                <?php $protegida = in_array($pagina['slug'], PaginaModel::PROTEGIDAS, true); ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e($pagina['titulo']) ?></div>
                        <div class="text-muted small">
                            <code>/<?= e($pagina['slug']) ?></code>
                            <?php if ($pagina['en_menu']): ?>
                            <span class="badge bg-info-subtle text-info-emphasis ms-1">En el menú</span>
                            <?php endif; ?>
                            <?php if ($protegida): ?>
                            <span class="badge bg-secondary-subtle text-secondary-emphasis ms-1">
                                <i class="bi bi-lock"></i> Requerida
                            </span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="d-none d-md-table-cell">
                        <?php if ($pagina['publicada']): ?>
                            <span class="badge bg-success-subtle text-success-emphasis">Publicada</span>
                        <?php else: ?>
                            <span class="badge bg-warning-subtle text-warning-emphasis">Borrador</span>
                        <?php endif; ?>
                    </td>
                    <td class="d-none d-lg-table-cell small text-muted">
                        <?php if (!empty($pagina['updated_at'])): ?>
                            <?= e(fecha_larga($pagina['updated_at'])) ?>
                            <?php if (!empty($pagina['editor'])): ?>
                            <br><span class="text-body-tertiary"><?= e($pagina['editor']) ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <?php if ($pagina['publicada']): ?>
                        <a href="<?= e(url_publica('pagina', ['slug' => $pagina['slug']])) ?>"
                           class="btn btn-sm btn-outline-secondary" target="_blank" title="Ver en el sitio">
                            <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                        <?php endif; ?>
                        <a href="<?= e(url_admin('paginas', 'editar', ['id' => $pagina['id']])) ?>"
                           class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-pencil me-1"></i>Editar
                        </a>
                        <?php if (!$protegida): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger"
                                data-bs-toggle="modal" data-bs-target="#borrar<?= (int) $pagina['id'] ?>"
                                title="Eliminar">
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

<?php /* Confirmaciones de borrado, fuera de la tabla para no anidar formularios. */ ?>
<?php foreach ($paginas as $pagina): ?>
    <?php if (in_array($pagina['slug'], PaginaModel::PROTEGIDAS, true)) { continue; } ?>
    <div class="modal fade" id="borrar<?= (int) $pagina['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h2 class="h6 modal-title fw-bold">Eliminar la página</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">
                        Se eliminará <strong><?= e($pagina['titulo']) ?></strong> y su dirección
                        <code>/<?= e($pagina['slug']) ?></code> dejará de funcionar.
                        Esta acción no se puede deshacer.
                    </p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" accept-charset="UTF-8"
                          action="<?= e(url_post('admin', 'paginas', 'eliminar')) ?>" class="m-0">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="id" value="<?= (int) $pagina['id'] ?>">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash me-1"></i>Eliminar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
