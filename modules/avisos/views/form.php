<?php
$esNuevo       = $aviso === null;
$puedePublicar = Auth::tienePermiso('avisos.publicar');
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <nav aria-label="Ubicación">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item">
                    <a href="<?= e(url_admin('avisos')) ?>" class="text-decoration-none">Avisos</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page"><?= $esNuevo ? 'Nuevo' : 'Editar' ?></li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0"><?= $esNuevo ? 'Nuevo aviso' : e($aviso['titulo']) ?></h1>
    </div>
    <a href="<?= e(url_admin('avisos')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<form method="POST" accept-charset="UTF-8" action="<?= e(url_post('admin', 'avisos', 'guardar')) ?>"
      enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="id" value="<?= $esNuevo ? 0 : (int) $aviso['id'] ?>">

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">

                    <div class="mb-3">
                        <label for="titulo" class="form-label fw-semibold">Título</label>
                        <input type="text" name="titulo" id="titulo" class="form-control"
                               value="<?= e($esNuevo ? '' : $aviso['titulo']) ?>" maxlength="200" required>
                    </div>

                    <div class="mb-3">
                        <label for="resumen" class="form-label fw-semibold">Resumen</label>
                        <textarea name="resumen" id="resumen" class="form-control" rows="2"
                                  maxlength="300"><?= e($esNuevo ? '' : (string) $aviso['resumen']) ?></textarea>
                        <div class="form-text">Aparece en el listado y al compartir el aviso. Si lo dejas en blanco, se usa el inicio del contenido.</div>
                    </div>

                    <?php
                    $eh_nombre   = 'contenido';
                    $eh_etiqueta = 'Contenido';
                    $eh_valor    = $esNuevo ? '' : (string) $aviso['contenido'];
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
                    $sp_valorActual = $esNuevo ? ($pastoralIdPreseleccionado ?? null) : $aviso['pastoral_id'];
                    require BASE_PATH . '/shared/views/parciales/selector_pastoral.php';
                    ?>

                    <div class="mb-3">
                        <label for="tipo" class="form-label fw-semibold">Tipo</label>
                        <select name="tipo" id="tipo" class="form-select">
                            <?php foreach (AvisoModel::TIPOS as $valor => $etiqueta): ?>
                            <option value="<?= e($valor) ?>"
                                <?= (!$esNuevo && $aviso['tipo'] === $valor) ? 'selected' : '' ?>>
                                <?= e($etiqueta) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="fecha_publicacion" class="form-label fw-semibold">Visible desde</label>
                            <input type="date" name="fecha_publicacion" id="fecha_publicacion" class="form-control"
                                   value="<?= e($esNuevo ? date('Y-m-d') : (string) $aviso['fecha_publicacion']) ?>">
                            <div class="form-text">Una fecha futura no se mostrará en el sitio hasta llegar ese día.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="vigente_hasta" class="form-label fw-semibold">
                                Visible hasta <span class="text-muted fw-normal">(opcional)</span>
                            </label>
                            <input type="date" name="vigente_hasta" id="vigente_hasta" class="form-control"
                                   value="<?= e($esNuevo ? '' : (string) $aviso['vigente_hasta']) ?>">
                            <div class="form-text">Pasada esta fecha, deja de mostrarse solo, sin despublicarlo a mano.</div>
                        </div>
                    </div>

                    <?php
                    $ci_nombre   = 'imagen';
                    $ci_etiqueta = 'Imagen';
                    $ci_actual   = $esNuevo ? '' : (string) $aviso['imagen'];
                    $ci_ayuda    = '';
                    require BASE_PATH . '/shared/views/parciales/campo_imagen.php';
                    ?>

                    <?php if ((!$esNuevo && $aviso['tipo'] === 'boletin') || $esNuevo): ?>
                    <div class="mb-3">
                        <label for="archivo_pdf" class="form-label fw-semibold">Boletín en PDF</label>
                        <input type="file" name="archivo_pdf" id="archivo_pdf" class="form-control form-control-sm" accept="application/pdf">
                        <?php if (!$esNuevo && $aviso['archivo_pdf']): ?>
                        <div class="mt-2 small">
                            <a href="<?= e(url_activo($aviso['archivo_pdf'])) ?>" target="_blank">
                                <i class="bi bi-file-earmark-pdf me-1"></i>Ver el PDF actual
                            </a>
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" name="archivo_pdf_quitar" id="archivo_pdf_quitar" value="1">
                                <label class="form-check-label small text-danger" for="archivo_pdf_quitar">Quitar el PDF</label>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch"
                               name="destacado" id="destacado" value="1"
                               <?= (!$esNuevo && $aviso['destacado']) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="destacado">Destacado</label>
                    </div>

                    <?php if ($puedePublicar): ?>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               name="publicado" id="publicado" value="1"
                               <?= ($esNuevo || $aviso['publicado']) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="publicado">Publicado</label>
                    </div>
                    <?php else: ?>
                    <p class="small text-muted mb-0">
                        <i class="bi bi-info-circle me-1"></i>Este aviso se enviará como borrador para que un editor lo publique.
                    </p>
                    <?php endif; ?>

                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-check-lg me-1"></i>Guardar
                </button>
                <a href="<?= e(url_admin('avisos')) ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </div>
    </div>
</form>
