<?php
/**
 * Las tres próximas misas en fila, la primera en azul noche. La usan la
 * portada editorial (InicioController::heroEditorial) y, cuando hay
 * carrusel, el cuerpo de la portada. Espera $proximasMisas con 'cuando' ya
 * calculado (InicioController::conCuando).
 */
?>
<div class="misas-proximas">
    <div class="misas-proximas-cabecera">
        <i class="bi bi-clock text-dorado" aria-hidden="true"></i>
        <h2 class="misas-proximas-titulo">Próximas misas</h2>
        <span class="ms-auto small">Hora de Hermosillo</span>
    </div>
    <ol class="misas-proximas-lista">
        <?php foreach ($proximasMisas as $i => $misa): ?>
        <li class="misa-proxima <?= $i === 0 ? 'primera' : '' ?>">
            <span class="cuando"><?= e($misa['cuando']) ?></span>
            <span class="hora"><?= e(hora_corta($misa['hora'])) ?></span>
            <span class="lugar">
                <i class="bi bi-geo-alt" aria-hidden="true"></i>
                <span><?= e(($misa['lugar'] ?: 'Parroquia') . ($misa['nota'] ? ' · ' . $misa['nota'] : '')) ?></span>
            </span>
        </li>
        <?php endforeach; ?>
    </ol>
</div>
