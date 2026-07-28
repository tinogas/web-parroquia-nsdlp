<?php
/**
 * Mensajes de una sola vista. Los emite Session::flash() y el layout los
 * recoge una única vez.
 */
foreach ($flash as $tipo => $mensajes):
    $clase = match ($tipo) {
        'success' => 'alert-success',
        'error'   => 'alert-danger',
        'warning' => 'alert-warning',
        default   => 'alert-info',
    };
    $icono = match ($tipo) {
        'success' => 'bi-check-circle',
        'error'   => 'bi-x-circle',
        'warning' => 'bi-exclamation-triangle',
        default   => 'bi-info-circle',
    };
    foreach ($mensajes as $mensaje): ?>
        <div class="alert <?= $clase ?> alert-dismissible fade show mb-3" role="alert">
            <i class="bi <?= $icono ?> me-1"></i>
            <?= e($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endforeach;
endforeach;
