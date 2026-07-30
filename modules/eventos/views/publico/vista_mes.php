<?php
/**
 * Vista de mes: la cuadrícula de siempre, domingo primero. El número del día
 * enlaza a su vista de día, que es donde caben los detalles —en el móvil los
 * eventos se reducen a puntos de color y esa es la forma de leerlos—.
 */
$paramsPastoral = $pastoral ? ['pastoral' => $pastoral['slug']] : [];
?>
<div class="table-responsive">
    <table class="table table-bordered calendario-tabla mb-0">
        <thead>
            <tr class="text-center small text-uppercase">
                <?php foreach (['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'] as $dia): ?>
                <th><?= $dia ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody data-calendario-cuerpo>
            <?php foreach ($semanas as $semana): ?>
            <tr>
                <?php foreach ($semana as $celda): ?>
                <td class="<?= $celda && $celda['hoy'] ? 'dia-hoy' : '' ?>">
                    <?php if ($celda): ?>
                    <a class="numero-dia text-decoration-none"
                       href="<?= e(url_publica('eventos', ['vista' => 'dia', 'fecha' => $celda['fecha']] + $paramsPastoral)) ?>"
                       data-calendario-ir="<?= e($celda['fecha']) ?>"><?= (int) $celda['dia'] ?></a>
                    <?php foreach ($celda['eventos'] as $evento): ?>
                    <a href="<?= e(url_publica('eventos', ['slug' => $evento['slug']])) ?>"
                       class="evento-punto" style="background:<?= e($evento['color'] ?: '#1e4d8b') ?>"
                       title="<?= e($evento['titulo']) ?>">
                        <?= e($evento['titulo']) ?>
                    </a>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
