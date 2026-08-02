<?php
/**
 * Vista de semana: los siete días en columnas, apilados en pantalla angosta.
 * Cada cabecera enlaza a la vista de día, conservando el filtro de pastoral.
 */
?>
<div class="calendario-semana">
    <?php foreach ($dias as $d): ?>
    <div class="calendario-semana-dia <?= $d['hoy'] ? 'es-hoy' : '' ?>">
        <a href="<?= e(url_admin('agenda', '', ['vista' => 'dia', 'fecha' => $d['fecha']] + $comunes)) ?>"
           class="calendario-semana-cabecera text-decoration-none">
            <span class="nombre-dia"><?= e(ucfirst($d['nombreDia'])) ?></span>
            <span class="numero-dia-grande"><?= (int) $d['dia'] ?></span>
        </a>
        <?php if (!$d['eventos']): ?>
        <p class="calendario-semana-vacio">—</p>
        <?php else: ?>
        <?php foreach ($d['eventos'] as $it): ?>
        <?php require BASE_PATH . '/modules/agenda/views/item_linea.php'; ?>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
