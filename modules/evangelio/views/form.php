<?php $esNueva = $entrada === null; ?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <nav aria-label="Ubicación">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item">
                    <a href="<?= e(url_admin('evangelio')) ?>" class="text-decoration-none">Evangelio del día</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">
                    <?= $esNueva ? 'Nueva' : 'Editar' ?>
                </li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0"><?= $esNueva ? 'Nueva entrada' : e($titulo) ?></h1>
    </div>
    <a href="<?= e(url_admin('evangelio')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<form method="POST" accept-charset="UTF-8" action="<?= e(url_post('admin', 'evangelio', 'guardar')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="id" value="<?= $esNueva ? 0 : (int) $entrada['id'] ?>">

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">

                    <div class="mb-3">
                        <label for="fecha" class="form-label fw-semibold">Fecha</label>
                        <input type="date" name="fecha" id="fecha" class="form-control"
                               style="max-width:220px" value="<?= e($fechaSugerida) ?>" required>
                        <div class="form-text">Si ya existe una entrada para esa fecha, se actualiza en vez de crear otra.</div>
                    </div>

                    <?php
                    $eh_nombre   = 'evangelio';
                    $eh_etiqueta = 'Evangelio';
                    $eh_valor    = $esNueva ? '' : (string) $entrada['evangelio'];
                    $eh_ayuda    = 'Cita y texto del evangelio de hoy. No hace falta encabezarlo: la portada pone la etiqueta «EVANGELIO» sola.';
                    require BASE_PATH . '/shared/views/parciales/editor_html.php';
                    ?>

                    <?php
                    $eh_nombre   = 'reflexion';
                    $eh_etiqueta = 'Reflexión';
                    $eh_valor    = $esNueva ? '' : (string) $entrada['reflexion'];
                    $eh_ayuda    = 'Palabras del párroco sobre la lectura de hoy (opcional). Tampoco hace falta escribir «REFLEXIÓN» arriba: la portada la pone sola, con el mismo aspecto que la del evangelio.';
                    require BASE_PATH . '/shared/views/parciales/editor_html.php';
                    ?>

                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               name="publicado" id="publicado" value="1"
                               <?= !$esNueva && $entrada['publicado'] ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="publicado">Publicado</label>
                        <div class="form-text">Mientras esté sin publicar, no aparece en el sitio.</div>
                    </div>

                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-check-lg me-1"></i>Guardar
                </button>
                <a href="<?= e(url_admin('evangelio')) ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </div>
    </div>
</form>
