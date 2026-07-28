<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1">Galería</h1>
        <p class="text-muted mb-0 small">
            Solo se muestran en el sitio las fotos publicadas <strong>y</strong> con autorización de uso.
        </p>
    </div>
    <?php if (Auth::tienePermiso('galeria.crear')): ?>
    <a href="<?= e(url_admin('galeria', 'subir')) ?>" class="btn btn-primary">
        <i class="bi bi-cloud-upload me-1"></i>Subir fotografías
    </a>
    <?php endif; ?>
</div>

<div class="btn-group btn-group-sm mb-3" role="group">
    <a href="<?= e(url_admin('galeria')) ?>"
       class="btn <?= $filtro === 'todas' ? 'btn-primary' : 'btn-outline-secondary' ?>">Todas</a>
    <a href="<?= e(url_admin('galeria', '', ['filtro' => 'publicadas'])) ?>"
       class="btn <?= $filtro === 'publicadas' ? 'btn-primary' : 'btn-outline-secondary' ?>">Publicadas</a>
    <a href="<?= e(url_admin('galeria', '', ['filtro' => 'ocultas'])) ?>"
       class="btn <?= $filtro === 'ocultas' ? 'btn-primary' : 'btn-outline-secondary' ?>">Ocultas</a>
</div>

<?php if (!$listado['filas']): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5">
        <div class="display-6 text-body-tertiary mb-2"><i class="bi bi-images"></i></div>
        <p class="text-muted mb-0">No hay fotografías que mostrar.</p>
    </div>
</div>
<?php else: ?>
<div class="row g-3">
    <?php foreach ($listado['filas'] as $imagen): ?>
    <div class="col-6 col-md-4 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <img src="<?= e(url_activo($imagen['archivo'])) ?>" class="card-img-top" alt=""
                 style="height:140px;object-fit:cover">
            <div class="card-body p-2">
                <div class="d-flex flex-wrap gap-1 mb-2">
                    <?php if ($imagen['publicada']): ?>
                    <span class="badge bg-success-subtle text-success-emphasis">Publicada</span>
                    <?php else: ?>
                    <span class="badge bg-secondary-subtle text-secondary-emphasis">Oculta</span>
                    <?php endif; ?>
                    <?php if ($imagen['autorizacion_imagen']): ?>
                    <span class="badge bg-info-subtle text-info-emphasis">Autorizada</span>
                    <?php else: ?>
                    <span class="badge bg-warning-subtle text-warning-emphasis">Sin autorizar</span>
                    <?php endif; ?>
                </div>
                <?php if (Auth::tienePermiso('galeria.editar')): ?>
                <button type="button" class="btn btn-sm btn-outline-primary w-100"
                        data-bs-toggle="modal" data-bs-target="#editar<?= (int) $imagen['id'] ?>">
                    <i class="bi bi-pencil me-1"></i>Editar
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php
$paginacion = $listado;
$paginaBase = url_admin('galeria', '', $filtro !== 'todas' ? ['filtro' => $filtro] : []);
require BASE_PATH . '/shared/views/parciales/paginacion.php';
?>

<?php foreach ($listado['filas'] as $imagen): ?>
<div class="modal fade" id="editar<?= (int) $imagen['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" accept-charset="UTF-8" action="<?= e(url_post('admin', 'galeria', 'guardar')) ?>" class="modal-content">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="id" value="<?= (int) $imagen['id'] ?>">

            <div class="modal-header border-0 pb-0">
                <h2 class="h6 modal-title fw-bold">Editar fotografía</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <img src="<?= e(url_activo($imagen['archivo'])) ?>" class="img-fluid rounded mb-3" alt="">

                <div class="mb-2">
                    <label class="form-label small fw-semibold">Título</label>
                    <input type="text" name="titulo" class="form-control form-control-sm"
                           value="<?= e((string) $imagen['titulo']) ?>" maxlength="140">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Texto alternativo</label>
                    <input type="text" name="alt_texto" class="form-control form-control-sm"
                           value="<?= e((string) $imagen['alt_texto']) ?>" maxlength="160">
                    <div class="form-text">Describe la foto para quien usa lector de pantalla.</div>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Orden</label>
                    <input type="number" name="orden" class="form-control form-control-sm"
                           value="<?= (int) $imagen['orden'] ?>" min="0" max="999">
                </div>

                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" name="autorizacion_imagen" value="1"
                           id="aut<?= (int) $imagen['id'] ?>" <?= $imagen['autorizacion_imagen'] ? 'checked' : '' ?>>
                    <label class="form-check-label small fw-semibold" for="aut<?= (int) $imagen['id'] ?>">
                        Autorización de uso
                    </label>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" name="publicada" value="1"
                           id="pub<?= (int) $imagen['id'] ?>" <?= $imagen['publicada'] ? 'checked' : '' ?>>
                    <label class="form-check-label small fw-semibold" for="pub<?= (int) $imagen['id'] ?>">
                        Publicada en el sitio
                    </label>
                </div>
            </div>
            <div class="modal-footer border-0 justify-content-between">
                <?php if (Auth::tienePermiso('galeria.eliminar')): ?>
                <button type="submit" formaction="<?= e(url_post('admin', 'galeria', 'eliminar')) ?>"
                        class="btn btn-outline-danger btn-sm"
                        onclick="return confirm('¿Eliminar esta fotografía? No se puede deshacer.');">
                    <i class="bi bi-trash me-1"></i>Eliminar
                </button>
                <?php else: ?>
                <span></span>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-check-lg me-1"></i>Guardar
                </button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>
