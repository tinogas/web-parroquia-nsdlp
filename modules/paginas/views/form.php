<?php
$esNueva   = $pagina === null;
$protegida = !$esNueva && in_array($pagina['slug'], PaginaModel::PROTEGIDAS, true);
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <nav aria-label="Ubicación">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item">
                    <a href="<?= e(url_admin('paginas')) ?>" class="text-decoration-none">Páginas</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">
                    <?= $esNueva ? 'Nueva' : 'Editar' ?>
                </li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0"><?= $esNueva ? 'Nueva página' : e($pagina['titulo']) ?></h1>
    </div>
    <a href="<?= e(url_admin('paginas')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<form method="POST" accept-charset="UTF-8" action="<?= e(url_post('admin', 'paginas', 'guardar')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="id" value="<?= $esNueva ? 0 : (int) $pagina['id'] ?>">

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">

                    <div class="mb-3">
                        <label for="titulo" class="form-label fw-semibold">Título</label>
                        <input type="text" name="titulo" id="titulo" class="form-control"
                               value="<?= e($esNueva ? '' : $pagina['titulo']) ?>" maxlength="160" required>
                    </div>

                    <?php
                    $eh_nombre   = 'contenido';
                    $eh_etiqueta = 'Contenido';
                    $eh_valor    = $esNueva ? '' : (string) $pagina['contenido'];
                    $eh_ayuda    = '';
                    require BASE_PATH . '/shared/views/parciales/editor_html.php';
                    ?>

                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">

                    <div class="mb-3">
                        <label for="slug" class="form-label fw-semibold">Dirección</label>
                        <div class="input-group">
                            <span class="input-group-text">/</span>
                            <input type="text" name="slug" id="slug" class="form-control"
                                   value="<?= e($esNueva ? '' : $pagina['slug']) ?>" maxlength="120"
                                   <?= $protegida ? 'readonly' : '' ?>
                                   placeholder="<?= $esNueva ? 'se genera del título' : '' ?>">
                        </div>
                        <div class="form-text">
                            <?php if ($protegida): ?>
                                El sitio enlaza a esta dirección, así que no se puede cambiar.
                            <?php else: ?>
                                Si la cambias, los enlaces que ya se compartieron dejarán de funcionar.
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="meta_descripcion" class="form-label fw-semibold">Resumen para buscadores</label>
                        <textarea name="meta_descripcion" id="meta_descripcion" class="form-control"
                                  rows="2" maxlength="200"><?= e($esNueva ? '' : (string) $pagina['meta_descripcion']) ?></textarea>
                        <div class="form-text">Dos líneas. Es lo que Google muestra bajo el título.</div>
                    </div>

                    <div class="mb-3">
                        <label for="orden" class="form-label fw-semibold">Orden</label>
                        <input type="number" name="orden" id="orden" class="form-control"
                               value="<?= (int) ($esNueva ? 0 : $pagina['orden']) ?>" min="0" max="999">
                    </div>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch"
                               name="publicada" id="publicada" value="1"
                               <?= !$esNueva && $pagina['publicada'] ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="publicada">Publicada</label>
                        <div class="form-text">Mientras esté sin publicar, la dirección responde «no encontrada».</div>
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               name="en_menu" id="en_menu" value="1"
                               <?= !$esNueva && $pagina['en_menu'] ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="en_menu">Mostrar en el menú</label>
                    </div>

                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-check-lg me-1"></i>Guardar
                </button>
                <a href="<?= e(url_admin('paginas')) ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </div>
    </div>
</form>
