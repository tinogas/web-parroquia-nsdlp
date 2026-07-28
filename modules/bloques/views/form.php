<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <nav aria-label="Ubicación">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item">
                    <a href="<?= e(url_admin('bloques')) ?>" class="text-decoration-none">Textos del sitio</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">
                    <?= e(BloqueModel::nombreZona($bloque['zona'])) ?>
                </li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-1"><?= e($bloque['titulo'] ?: $bloque['clave']) ?></h1>
        <?php if (!empty($bloque['descripcion'])): ?>
        <p class="text-muted mb-0 small"><?= e($bloque['descripcion']) ?></p>
        <?php endif; ?>
    </div>
    <a href="<?= e(url_admin('bloques')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<form method="POST" accept-charset="UTF-8" action="<?= e(url_post('admin', 'bloques', 'guardar')) ?>"
      enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="id" value="<?= (int) $bloque['id'] ?>">

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">

                    <div class="mb-3">
                        <label for="titulo" class="form-label fw-semibold">Título</label>
                        <input type="text" name="titulo" id="titulo" class="form-control"
                               value="<?= e($bloque['titulo']) ?>" maxlength="160">
                        <div class="form-text">Encabezado que se muestra sobre el texto en el sitio.</div>
                    </div>

                    <?php
                    $eh_nombre   = 'contenido';
                    $eh_etiqueta = 'Contenido';
                    $eh_valor    = (string) $bloque['contenido'];
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
                    $ci_actual   = (string) $bloque['imagen'];
                    $ci_ayuda    = 'Opcional. Acompaña al texto en el sitio.';
                    require BASE_PATH . '/shared/views/parciales/campo_imagen.php';
                    ?>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               name="activo" id="activo" value="1"
                               <?= $bloque['activo'] ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="activo">Visible en el sitio</label>
                        <div class="form-text">Si lo desactivas, la sección no aparece en la página.</div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-check-lg me-1"></i>Guardar
                </button>
                <a href="<?= e(url_admin('bloques')) ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>

            <p class="text-muted small mt-3 mb-0">
                <i class="bi bi-key me-1"></i>Clave interna: <code><?= e($bloque['clave']) ?></code>
            </p>
        </div>
    </div>
</form>
