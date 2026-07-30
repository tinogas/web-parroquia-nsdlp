<?php
/**
 * Vista de una semana: los siete días en columnas, uno debajo de otro en el
 * móvil. Cada día enlaza a su propia vista de día.
 */
?>
<div class="calendario-semana">
    <?php foreach ($dias as $d): ?>
    <div class="calendario-semana-dia <?= $d['hoy'] ? 'es-hoy' : '' ?>">
        <a href="<?= e(url_publica('eventos', ['vista' => 'dia', 'fecha' => $d['fecha']]
                + ($pastoral ? ['pastoral' => $pastoral['slug']] : []))) ?>"
           class="calendario-semana-cabecera text-decoration-none" data-calendario-ir="<?= e($d['fecha']) ?>">
            <span class="nombre-dia"><?= e(ucfirst($d['nombreDia'])) ?></span>
            <span class="numero-dia-grande"><?= (int) $d['dia'] ?></span>
        </a>
        <?php if (!$d['eventos']): ?>
        <p class="calendario-semana-vacio">—</p>
        <?php else: ?>
        <?php foreach ($d['eventos'] as $ev): ?>
        <?php require BASE_PATH . '/modules/eventos/views/publico/evento_linea.php'; ?>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
