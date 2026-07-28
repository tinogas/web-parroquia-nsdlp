<?php $esNueva = $diapositiva === null; ?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <nav aria-label="Ubicación">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item">
                    <a href="<?= e(url_admin('carrusel')) ?>" class="text-decoration-none">Carrusel</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page"><?= $esNueva ? 'Nueva' : 'Editar' ?></li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0"><?= $esNueva ? 'Nueva diapositiva' : 'Editar diapositiva' ?></h1>
    </div>
    <a href="<?= e(url_admin('carrusel')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <form method="POST" accept-charset="UTF-8" action="<?= e(url_post('admin', 'carrusel', 'guardar')) ?>"
              enctype="multipart/form-data" class="card border-0 shadow-sm">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="id" value="<?= $esNueva ? 0 : (int) $diapositiva['id'] ?>">

            <div class="card-body p-4">

                <?php
                $ci_nombre   = 'imagen';
                $ci_etiqueta = 'Imagen';
                $ci_actual   = $esNueva ? '' : (string) $diapositiva['imagen'];
                $ci_ayuda    = 'Horizontal, ancha. Se recomienda al menos 1600 px de ancho.';
                require BASE_PATH . '/shared/views/parciales/campo_imagen.php';
                ?>

                <div class="mb-3">
                    <label for="titulo" class="form-label fw-semibold">Título</label>
                    <input type="text" name="titulo" id="titulo" class="form-control"
                           value="<?= e($esNueva ? '' : (string) $diapositiva['titulo']) ?>" maxlength="120">
                </div>
                <div class="mb-3">
                    <label for="subtitulo" class="form-label fw-semibold">Subtítulo</label>
                    <input type="text" name="subtitulo" id="subtitulo" class="form-control"
                           value="<?= e($esNueva ? '' : (string) $diapositiva['subtitulo']) ?>" maxlength="200">
                </div>
                <div class="mb-3">
                    <label for="enlace" class="form-label fw-semibold">Enlace (opcional)</label>
                    <input type="text" name="enlace" id="enlace" class="form-control"
                           value="<?= e($esNueva ? '' : (string) $diapositiva['enlace']) ?>"
                           placeholder="/avisos/mi-noticia o https://...">
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <label for="orden" class="form-label fw-semibold">Orden</label>
                        <input type="number" name="orden" id="orden" class="form-control"
                               value="<?= (int) ($esNueva ? 0 : $diapositiva['orden']) ?>" min="0" max="999">
                    </div>
                    <div class="col-6 d-flex align-items-end">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   name="activo" id="activo" value="1"
                                   <?= ($esNueva || $diapositiva['activo']) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="activo">Visible</label>
                        </div>
                    </div>
                </div>

            </div>
            <div class="card-footer bg-white border-top-0 pb-4 px-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Guardar
                </button>
                <a href="<?= e(url_admin('carrusel')) ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
