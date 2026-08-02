<?php
/**
 * Vista de un día: la lista de lo que hay ese día. $dias trae un solo elemento.
 */
$jornada = $dias[0] ?? null;
?>
<?php if (!$jornada || !$jornada['eventos']): ?>
<p class="text-muted fst-italic mb-0">No hay nada anotado este día.</p>
<?php else: ?>
<div class="calendario-dia">
    <?php foreach ($jornada['eventos'] as $it): ?>
    <?php require BASE_PATH . '/modules/agenda/views/item_linea.php'; ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>
