<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1">Carrusel de portada</h1>
        <p class="text-muted mb-0 small">Las imágenes grandes que rotan en el inicio del sitio.</p>
    </div>
    <a href="<?= e(url_admin('carrusel', 'nuevo')) ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nueva diapositiva
    </a>
</div>

<?php if (!$diapositivas): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5">
        <div class="display-6 text-body-tertiary mb-2"><i class="bi bi-images"></i></div>
        <p class="text-muted mb-0">
            Todavía no hay diapositivas. Mientras tanto, la portada muestra el saludo de bienvenida.
        </p>
    </div>
</div>
<?php endif; ?>

<div class="row g-3">
    <?php foreach ($diapositivas as $diapositiva): ?>
    <div class="col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <img src="<?= e(url_activo($diapositiva['imagen'])) ?>" class="card-img-top" alt=""
                 style="height:160px;object-fit:cover">
            <div class="card-body p-3">
                <h2 class="h6 fw-bold mb-1"><?= e($diapositiva['titulo'] ?: '(Sin título)') ?></h2>
                <?php if ($diapositiva['activo']): ?>
                <span class="badge bg-success-subtle text-success-emphasis mb-2">Visible</span>
                <?php else: ?>
                <span class="badge bg-secondary-subtle text-secondary-emphasis mb-2">Oculta</span>
                <?php endif; ?>
                <div class="d-flex gap-2">
                    <a href="<?= e(url_admin('carrusel', 'editar', ['id' => $diapositiva['id']])) ?>"
                       class="btn btn-sm btn-outline-primary flex-grow-1">
                        <i class="bi bi-pencil me-1"></i>Editar
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-danger"
                            data-bs-toggle="modal" data-bs-target="#borrar<?= (int) $diapositiva['id'] ?>">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php foreach ($diapositivas as $diapositiva): ?>
    <div class="modal fade" id="borrar<?= (int) $diapositiva['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h2 class="h6 modal-title fw-bold">Eliminar diapositiva</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Esta acción no se puede deshacer.</p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" accept-charset="UTF-8"
                          action="<?= e(url_post('admin', 'carrusel', 'eliminar')) ?>" class="m-0">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="id" value="<?= (int) $diapositiva['id'] ?>">
                        <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i>Eliminar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
