<?php
/**
 * Cabecera común del calendario interno —selector de vista, navegación de
 * periodo y contador— más la plantilla que toque.
 *
 * Es la contraparte de eventos/publico/calendario.php, con dos diferencias:
 * las URLs son del panel y aquí no hay AJAX, así que cada enlace recarga.
 */
?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-3 p-md-4">

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div class="btn-group btn-group-sm" role="group" aria-label="Cómo ver la agenda">
                <?php foreach ($vistas as $clave => $etiqueta): ?>
                <a href="<?= e($urlsVista[$clave]) ?>"
                   class="btn <?= $vista === $clave ? 'btn-primary' : 'btn-outline-secondary' ?>"
                   <?= $vista === $clave ? 'aria-current="true"' : '' ?>>
                    <?= e($etiqueta) ?>
                </a>
                <?php endforeach; ?>
            </div>

            <div class="d-flex align-items-center gap-2">
                <a href="<?= e($urlAnterior) ?>" class="btn btn-sm btn-outline-secondary"
                   aria-label="Periodo anterior"><i class="bi bi-chevron-left"></i></a>
                <?php if (!$incluyeHoy): ?>
                <a href="<?= e($urlHoy) ?>" class="btn btn-sm btn-outline-secondary">Hoy</a>
                <?php endif; ?>
                <a href="<?= e($urlSiguiente) ?>" class="btn btn-sm btn-outline-secondary"
                   aria-label="Periodo siguiente"><i class="bi bi-chevron-right"></i></a>
            </div>
        </div>

        <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-2 mb-3">
            <h2 class="h5 fw-bold mb-0"><?= e($periodo) ?></h2>
            <span class="small text-muted">
                <?= $total === 1 ? '1 registro' : (int) $total . ' registros' ?>
            </span>
        </div>

        <?php require BASE_PATH . '/modules/agenda/views/vista_' . $vista . '.php'; ?>
    </div>
</div>
