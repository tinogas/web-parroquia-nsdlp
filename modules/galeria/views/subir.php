<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <nav aria-label="Ubicación">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item">
                    <a href="<?= e(url_admin('galeria')) ?>" class="text-decoration-none">Galería</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Subir fotografías</li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0">Subir fotografías</h1>
    </div>
    <a href="<?= e(url_admin('galeria')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <form method="POST" accept-charset="UTF-8" action="<?= e(url_post('admin', 'galeria', 'subir')) ?>"
              enctype="multipart/form-data" class="card border-0 shadow-sm">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

            <div class="card-body p-4">
                <div class="mb-3">
                    <label for="fotos" class="form-label fw-semibold">Fotografías</label>
                    <input type="file" name="fotos[]" id="fotos" class="form-control" multiple
                           accept="image/jpeg,image/png,image/webp,image/gif" required>
                    <div class="form-text">Puedes elegir varias a la vez. Máximo 4 MB cada una.</div>
                </div>

                <?php
                $sp_valorActual = null;
                require BASE_PATH . '/shared/views/parciales/selector_pastoral.php';
                ?>

                <div class="alert alert-light border">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="autorizacion_imagen"
                               id="autorizacion_imagen" value="1">
                        <label class="form-check-label fw-semibold" for="autorizacion_imagen">
                            Cuento con autorización para usar estas fotografías en el sitio
                        </label>
                    </div>
                    <p class="small text-muted mb-0 mt-2">
                        Se aplica a todo el lote que subas ahora. Sin marcar esta casilla, las fotos se
                        guardan pero nunca se muestran en el sitio público; puedes corregirlo una por una
                        después si el lote es mixto. Ver docs/PRIVACIDAD.md sobre fotografías de menores.
                    </p>
                </div>

                <p class="small text-muted mb-0">
                    Las fotos se suben sin publicar: revísalas y actívalas una por una desde el listado.
                </p>
            </div>

            <div class="card-footer bg-white border-top-0 pb-4 px-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-cloud-upload me-1"></i>Subir
                </button>
            </div>
        </form>
    </div>
</div>
