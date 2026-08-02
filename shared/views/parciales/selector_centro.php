<?php
/**
 * Selector de sede/centro para los formularios de eventos y cursos, gemelo de
 * selector_pastoral.php. Los datos los prepara Controller::opcionesCentro():
 * quien tiene sedes asignadas no ve una lista abierta, y si trabaja en una
 * sola ni siquiera elige, va fija en un campo oculto.
 *
 * Variables esperadas:
 *   $sc_valorActual   int|null  centro_id ya guardado (al editar)
 *   $sc_opciones      array     [['id'=>, 'tipo'=>, 'nombre'=>], ...] ya filtradas al alcance
 *   $sc_fija          int|null  si solo puede usar una, su id
 *   $sc_permiteVacio  bool      true si puede dejarse como "toda la parroquia"
 */
?>
<?php if ($sc_fija !== null): ?>
<input type="hidden" name="centro_id" value="<?= (int) $sc_fija ?>">
<?php else: ?>
<div class="mb-3">
    <label for="centro_id" class="form-label fw-semibold">Sede o centro</label>
    <select name="centro_id" id="centro_id" class="form-select" <?= $sc_permiteVacio ? '' : 'required' ?>>
        <?php if ($sc_permiteVacio): ?>
        <option value="">— Toda la parroquia —</option>
        <?php endif; ?>
        <?php foreach ($sc_opciones as $c): ?>
        <option value="<?= (int) $c['id'] ?>" <?= (int) $sc_valorActual === (int) $c['id'] ? 'selected' : '' ?>>
            <?= e($c['nombre']) ?>
        </option>
        <?php endforeach; ?>
    </select>
</div>
<?php endif; ?>
