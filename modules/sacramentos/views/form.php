<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <nav aria-label="Ubicación">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item">
                    <a href="<?= e(url_admin('sacramentos')) ?>" class="text-decoration-none">Sacramentos</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Editar</li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0"><?= e($sacramento['nombre']) ?></h1>
    </div>
    <a href="<?= e(url_admin('sacramentos')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<form method="POST" accept-charset="UTF-8" action="<?= e(url_post('admin', 'sacramentos', 'guardar')) ?>"
      enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="id" value="<?= (int) $sacramento['id'] ?>">

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">

                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label for="nombre" class="form-label fw-semibold">Nombre</label>
                            <input type="text" name="nombre" id="nombre" class="form-control"
                                   value="<?= e($sacramento['nombre']) ?>" maxlength="80" required>
                        </div>
                        <div class="col-md-4">
                            <label for="aportacion" class="form-label fw-semibold">Aportación</label>
                            <input type="text" name="aportacion" id="aportacion" class="form-control"
                                   value="<?= e((string) $sacramento['aportacion']) ?>" maxlength="80"
                                   placeholder="Ej. $500 o Libre">
                        </div>
                    </div>

                    <?php
                    $eh_nombre   = 'descripcion';
                    $eh_etiqueta = 'Descripción';
                    $eh_valor    = (string) $sacramento['descripcion'];
                    $eh_ayuda    = '';
                    require BASE_PATH . '/shared/views/parciales/editor_html.php';
                    ?>
                    <?php
                    $eh_nombre   = 'requisitos';
                    $eh_etiqueta = 'Requisitos';
                    $eh_valor    = (string) $sacramento['requisitos'];
                    $eh_ayuda    = 'Lo que debe cumplir quien lo lleva a cabo.';
                    require BASE_PATH . '/shared/views/parciales/editor_html.php';
                    ?>
                    <?php
                    $eh_nombre   = 'documentos';
                    $eh_etiqueta = 'Documentos a presentar';
                    $eh_valor    = (string) $sacramento['documentos'];
                    $eh_ayuda    = '';
                    require BASE_PATH . '/shared/views/parciales/editor_html.php';
                    ?>

                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <?php
                    $ci_nombre   = 'imagen';
                    $ci_etiqueta = 'Imagen';
                    $ci_actual   = (string) $sacramento['imagen'];
                    $ci_ayuda    = '';
                    require BASE_PATH . '/shared/views/parciales/campo_imagen.php';
                    ?>

                    <div class="mb-3">
                        <label for="orden" class="form-label fw-semibold">Orden</label>
                        <input type="number" name="orden" id="orden" class="form-control"
                               value="<?= (int) $sacramento['orden'] ?>" min="0" max="999">
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               name="activo" id="activo" value="1" <?= $sacramento['activo'] ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="activo">Visible en el sitio</label>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-check-lg me-1"></i>Guardar
                </button>
                <a href="<?= e(url_admin('sacramentos')) ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </div>
    </div>
</form>
