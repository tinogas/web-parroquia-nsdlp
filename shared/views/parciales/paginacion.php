<?php
/**
 * Controles de paginación.
 *
 * Variables esperadas:
 *   $paginacion  arreglo devuelto por Model::paginar() (usa 'pagina' y 'total_paginas')
 *   $paginaBase  URL del listado sin el parámetro de página; se le añade &pagina=N
 */
if (($paginacion['total_paginas'] ?? 1) <= 1) {
    return;
}

$actual    = (int) $paginacion['pagina'];
$total     = (int) $paginacion['total_paginas'];
$separador = str_contains($paginaBase, '?') ? '&' : '?';
$enlace    = static fn (int $p): string => $paginaBase . $separador . 'pagina=' . $p;
?>
<nav aria-label="Paginación" class="mt-4">
    <ul class="pagination justify-content-center mb-0">
        <li class="page-item <?= $actual <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= $actual > 1 ? e($enlace($actual - 1)) : '#' ?>" aria-label="Anterior">
                <i class="bi bi-chevron-left"></i>
            </a>
        </li>
        <?php for ($p = max(1, $actual - 2); $p <= min($total, $actual + 2); $p++): ?>
        <li class="page-item <?= $p === $actual ? 'active' : '' ?>">
            <a class="page-link" href="<?= e($enlace($p)) ?>"><?= $p ?></a>
        </li>
        <?php endfor; ?>
        <li class="page-item <?= $actual >= $total ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= $actual < $total ? e($enlace($actual + 1)) : '#' ?>" aria-label="Siguiente">
                <i class="bi bi-chevron-right"></i>
            </a>
        </li>
    </ul>
</nav>
