<?php
/**
 * Un evento dentro de una lista de día. Espera $ev (fila de eventos) y, si se
 * quiere la hora en columna aparte, $conHora = true.
 *
 * Lo usan las vistas de día y de semana; la de mes usa el punto de color y la
 * de año solo marca el día, porque ahí no cabe el título.
 */
$color = $ev['color'] ?: '#1e4d8b';
$dura  = !empty($ev['fecha_fin']) && substr((string) $ev['fecha_fin'], 0, 10) !== substr((string) $ev['fecha_inicio'], 0, 10);
?>
<a href="<?= e(url_publica('eventos', ['slug' => $ev['slug']])) ?>"
   class="evento-linea d-flex gap-2 text-decoration-none" style="border-left-color:<?= e($color) ?>">
    <span class="evento-linea-hora">
        <?= $ev['todo_el_dia'] ? 'Todo el día' : e(hora_corta(substr((string) $ev['fecha_inicio'], 11))) ?>
    </span>
    <span class="evento-linea-texto">
        <span class="evento-linea-titulo"><?= e($ev['titulo']) ?></span>
        <?php if ($ev['lugar'] || $dura): ?>
        <span class="evento-linea-detalle">
            <?php if ($ev['lugar']): ?><i class="bi bi-geo-alt me-1"></i><?= e($ev['lugar']) ?><?php endif; ?>
            <?php if ($dura): ?>
            <span class="ms-1"><i class="bi bi-arrow-left-right me-1"></i>hasta el
                <?= e(fecha_larga(substr((string) $ev['fecha_fin'], 0, 10))) ?></span>
            <?php endif; ?>
        </span>
        <?php endif; ?>
    </span>
</a>
