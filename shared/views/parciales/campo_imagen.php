<?php
/**
 * Campo de imagen con vista previa y casilla para quitarla.
 *
 * La casilla evita tener un formulario aparte por cada imagen, que además no
 * podría anidarse dentro del formulario principal. Al guardar, el controlador
 * comprueba «<nombre>_quitar».
 *
 * Variables esperadas:
 *   $ci_nombre    nombre del input file
 *   $ci_etiqueta  etiqueta visible
 *   $ci_actual    ruta guardada, o cadena vacía
 *   $ci_ayuda     texto de ayuda (opcional)
 */
$ci_id     = 'img_' . preg_replace('/[^a-z0-9_]/', '', strtolower($ci_nombre));
$ci_actual = $ci_actual ?? '';
$ci_ayuda  = $ci_ayuda  ?? '';
?>
<div class="mb-4">
    <label for="<?= e($ci_id) ?>" class="form-label fw-semibold"><?= e($ci_etiqueta) ?></label>

    <div class="d-flex align-items-start gap-3 flex-wrap">
        <img id="<?= e($ci_id) ?>_vista"
             src="<?= e($ci_actual !== '' ? url_activo($ci_actual) : placeholder_rect('Sin imagen', 160, 110)) ?>"
             alt="" class="vista-imagen">

        <div class="flex-grow-1" style="min-width:220px">
            <input type="file" name="<?= e($ci_nombre) ?>" id="<?= e($ci_id) ?>"
                   class="form-control form-control-sm"
                   accept="image/jpeg,image/png,image/webp,image/gif"
                   data-preview="<?= e($ci_id) ?>_vista">

            <?php if ($ci_ayuda !== ''): ?>
            <div class="form-text"><?= e($ci_ayuda) ?></div>
            <?php endif; ?>

            <?php if ($ci_actual !== ''): ?>
            <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" value="1"
                       name="<?= e($ci_nombre) ?>_quitar" id="<?= e($ci_id) ?>_quitar">
                <label class="form-check-label small text-danger" for="<?= e($ci_id) ?>_quitar">
                    Quitar la imagen actual
                </label>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
