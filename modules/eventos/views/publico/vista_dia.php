<?php
/**
 * Vista de un día: la lista de lo que pasa ese día, con su hora.
 * $dias trae un único elemento.
 */
$hoy = $dias[0] ?? null;
?>
<?php if (!$hoy || !$hoy['eventos']): ?>
<p class="text-muted fst-italic mb-0">No hay nada anotado este día.</p>
<?php else: ?>
<div class="calendario-dia <?= $hoy['hoy'] ? 'es-hoy' : '' ?>">
    <?php foreach ($hoy['eventos'] as $ev): ?>
    <?php require BASE_PATH . '/modules/eventos/views/publico/evento_linea.php'; ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>
