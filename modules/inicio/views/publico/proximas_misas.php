<?php
/**
 * Las tres próximas misas en fila, la primera en azul noche. La usan la
 * portada editorial (InicioController::heroEditorial) y, cuando hay
 * carrusel, el cuerpo de la portada. Espera $proximasMisas con 'cuando' ya
 * calculado (InicioController::conCuando).
 *
 * La hora va primero y en grande; cuándo y dónde, debajo, en una sola línea.
 * Con el día encima de la hora, cada casilla se leía como una etiqueta sobre
 * un título.
 */
?>
<div class="misas-proximas">
    <h2 class="misas-proximas-titulo">Próximas misas</h2>
    <ol class="misas-proximas-lista">
        <?php foreach ($proximasMisas as $i => $misa): ?>
        <li class="misa-proxima <?= $i === 0 ? 'primera' : '' ?>">
            <span class="hora"><?= e(hora_corta($misa['hora'])) ?></span>
            <span class="lugar">
                <span class="cuando"><?= e($misa['cuando']) ?></span>
                · <?= e(($misa['lugar'] ?: 'Parroquia') . ($misa['nota'] ? ' · ' . $misa['nota'] : '')) ?>
            </span>
        </li>
        <?php endforeach; ?>
    </ol>
    <p class="misas-proximas-nota">Hora de Hermosillo.</p>
</div>
