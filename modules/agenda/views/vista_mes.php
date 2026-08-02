<?php
/**
 * Vista de mes: la cuadrícula de siempre, domingo primero. El número del día
 * enlaza a su vista de día, que es donde caben los detalles; en la casilla solo
 * cabe el título, y en pantalla angosta se reduce a un punto de color.
 */
?>
<div class="table-responsive">
    <table class="table table-bordered calendario-tabla mb-0">
        <thead>
            <tr class="text-center small text-uppercase">
                <?php foreach (['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'] as $inicial): ?>
                <th><?= $inicial ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($semanas as $semana): ?>
            <tr>
                <?php foreach ($semana as $celda): ?>
                <td class="<?= $celda && $celda['hoy'] ? 'dia-hoy' : '' ?>">
                    <?php if ($celda): ?>
                    <a class="numero-dia text-decoration-none"
                       href="<?= e(url_admin('agenda', '', ['vista' => 'dia', 'fecha' => $celda['fecha']] + $comunes)) ?>">
                        <?= (int) $celda['dia'] ?>
                    </a>
                    <?php foreach ($celda['eventos'] as $it): ?>
                    <?php
                    $titulo = $it['titulo'] . ' · ' . ($it['pastoral_nombre'] ?? 'General')
                            . (empty($it['centro_nombre']) ? '' : ' · ' . $it['centro_nombre'])
                            . ($it['publicado'] ? '' : ' (borrador)');
                    ?>
                    <?php if ($it['url']): ?>
                    <a href="<?= e($it['url']) ?>"
                       class="evento-punto <?= $it['publicado'] ? '' : 'es-borrador' ?>"
                       style="background:<?= e($it['color']) ?>" title="<?= e($titulo) ?>">
                        <?= e($it['titulo']) ?>
                    </a>
                    <?php else: ?>
                    <span class="evento-punto <?= $it['publicado'] ? '' : 'es-borrador' ?>"
                          style="background:<?= e($it['color']) ?>" title="<?= e($titulo) ?>">
                        <?= e($it['titulo']) ?>
                    </span>
                    <?php endif; ?>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
