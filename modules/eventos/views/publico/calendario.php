<?php
/**
 * Calendario navegable: selector de vista, cabecera con el periodo y la
 * cuadrícula o la lista que corresponda.
 *
 * Es también lo que devuelve EventoPublicoController::fragmento(), así que este
 * archivo tiene que valerse por sí solo: calendario.js sustituye justo este
 * bloque al cambiar de periodo o de vista.
 */
?>
<div class="card border-0 shadow-sm mb-5" id="calendario">
    <div class="card-body p-3 p-md-4">

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div class="btn-group btn-group-sm" role="group" aria-label="Cómo ver el calendario">
                <?php foreach ($vistas as $clave => $etiqueta): ?>
                <a href="<?= e($urlsVista[$clave]) ?>" data-calendario-vista="<?= e($clave) ?>"
                   class="btn <?= $vista === $clave ? 'btn-primary' : 'btn-outline-secondary' ?>"
                   <?= $vista === $clave ? 'aria-current="true"' : '' ?>>
                    <?= e($etiqueta) ?>
                </a>
                <?php endforeach; ?>
            </div>

            <div class="d-flex align-items-center gap-2">
                <a href="<?= e($urlAnterior) ?>" class="btn btn-sm btn-outline-secondary"
                   data-calendario-nav="anterior" aria-label="Periodo anterior">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <?php if (!$incluyeHoy): ?>
                <a href="<?= e($urlHoy) ?>" class="btn btn-sm btn-outline-secondary" data-calendario-nav="hoy">Hoy</a>
                <?php endif; ?>
                <a href="<?= e($urlSiguiente) ?>" class="btn btn-sm btn-outline-secondary"
                   data-calendario-nav="siguiente" aria-label="Periodo siguiente">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </div>
        </div>

        <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-2 mb-3">
            <h2 class="h5 fw-bold mb-0" data-calendario-titulo><?= e($titulo) ?></h2>
            <span class="small text-muted">
                <?= $totalEventos === 1 ? '1 evento' : (int) $totalEventos . ' eventos' ?>
            </span>
        </div>

        <?php require BASE_PATH . '/modules/eventos/views/publico/vista_' . $vista . '.php'; ?>
    </div>
</div>
