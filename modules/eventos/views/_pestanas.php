<?php
/**
 * Cabecera común de Eventos: título, botón de nuevo evento y las tres formas
 * de mirarlos —Lista, Calendario, Propuestas—.
 *
 * Variables:
 *   $pestana — 'lista' | 'calendario' | 'propuestas'
 *   $subtitulo — texto bajo el título (opcional)
 */
$proximoAnio = (int) date('Y') + 1;
$pestanas = [
    'lista'      => ['Lista',      'bi-list-ul',        url_admin('eventos')],
    'calendario' => ['Calendario', 'bi-calendar3',      url_admin('eventos', 'calendario')],
    'propuestas' => ['Propuestas ' . $proximoAnio, 'bi-calendar2-range', url_admin('eventos', 'propuestas')],
];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 fw-bold mb-1">Eventos</h1>
        <p class="text-muted mb-0 small"><?= e($subtitulo ?? 'Celebraciones y actividades con fecha concreta.') ?></p>
    </div>
    <?php if (Auth::tienePermiso('eventos.crear')): ?>
    <a href="<?= e(url_admin('eventos', 'nuevo')) ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nuevo evento
    </a>
    <?php endif; ?>
</div>

<ul class="nav nav-tabs mb-4">
    <?php foreach ($pestanas as $clave => [$etiqueta, $icono, $url]): ?>
    <li class="nav-item">
        <a class="nav-link <?= $pestana === $clave ? 'active fw-semibold' : '' ?>"
           href="<?= e($url) ?>" <?= $pestana === $clave ? 'aria-current="page"' : '' ?>>
            <i class="bi <?= e($icono) ?> me-1"></i><?= e($etiqueta) ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>
