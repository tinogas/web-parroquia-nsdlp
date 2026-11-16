<?php
/**
 * Casillas de «quiénes dejan este turno» de un lado de la permuta, dentro de
 * cambios_turno.php. Se pintan todas las personas del módulo y el script de
 * esa vista deja visibles solo las del turno elegido; sin JavaScript se ven
 * todas y el servidor rechaza a quien no esté en el turno.
 *
 *   $lado      'a' (primer turno) | 'b' (segundo turno)
 *   $personas  todas las personas del módulo
 */
?>
<div class="d-flex justify-content-between align-items-baseline">
    <span class="form-label fw-semibold">Quiénes dejan ese turno</span>
    <button type="button" class="btn btn-link btn-sm p-0" data-marcar-todo="turno_<?= e($lado) ?>">Todo el turno</button>
</div>
<div class="border rounded px-2 py-1" data-rol-de="turno_<?= e($lado) ?>" data-mostrar="dentro">
    <p class="small text-muted mb-1" data-vacio>Elige primero el turno.</p>
    <?php foreach ($personas as $p): ?>
    <div class="form-check" data-persona="<?= (int) $p['id'] ?>">
        <input class="form-check-input" type="checkbox" name="salen_<?= e($lado) ?>[]"
               value="<?= (int) $p['id'] ?>" id="salen-<?= e($lado) ?>-<?= (int) $p['id'] ?>">
        <label class="form-check-label small" for="salen-<?= e($lado) ?>-<?= (int) $p['id'] ?>"><?= e($p['nombre']) ?></label>
    </div>
    <?php endforeach; ?>
</div>
