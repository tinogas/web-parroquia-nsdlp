<?php
/**
 * Selector de pastoral para los formularios de avisos, eventos y galería.
 * Los datos los prepara Controller::opcionesPastoral(): un coordinador nunca
 * ve una lista abierta, y si tiene una sola pastoral asignada ni siquiera
 * elige, va fija en un campo oculto.
 *
 * Variables esperadas:
 *   $sp_valorActual   int|null  pastoral_id ya guardado (al editar)
 *   $sp_opciones      array     [['id'=>, 'nombre'=>], ...] ya filtradas al alcance
 *   $sp_fija          int|null  si solo puede usar una, su id
 *   $sp_permiteVacio  bool      true si puede dejarse como contenido parroquial general
 */
?>
<?php if ($sp_fija !== null): ?>
<input type="hidden" name="pastoral_id" value="<?= (int) $sp_fija ?>">
<?php else: ?>
<div class="mb-3">
    <label for="pastoral_id" class="form-label fw-semibold">Pastoral</label>
    <select name="pastoral_id" id="pastoral_id" class="form-select" <?= $sp_permiteVacio ? '' : 'required' ?>>
        <?php if ($sp_permiteVacio): ?>
        <option value="">— Contenido parroquial general —</option>
        <?php endif; ?>
        <?php foreach ($sp_opciones as $p): ?>
        <option value="<?= (int) $p['id'] ?>" <?= (int) $sp_valorActual === (int) $p['id'] ? 'selected' : '' ?>>
            <?= e($p['nombre']) ?>
        </option>
        <?php endforeach; ?>
    </select>
</div>
<?php endif; ?>
