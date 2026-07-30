<?php
/**
 * Vista de año: los doce meses como mini-cuadrículas. Aquí no cabe ningún
 * título, así que el día con eventos se marca con un punto de color y enlaza a
 * su vista de día; el nombre del mes enlaza a la vista de mes.
 */
$paramsPastoral = $pastoral ? ['pastoral' => $pastoral['slug']] : [];
?>
<div class="calendario-anio-marco">
<div class="calendario-anio">
    <?php foreach ($meses as $m): ?>
    <div class="calendario-anio-mes">
        <a class="calendario-anio-titulo text-decoration-none"
           href="<?= e(url_publica('eventos', ['vista' => 'mes', 'fecha' => sprintf('%s-%02d-01', substr($fecha, 0, 4), $m['mes'])] + $paramsPastoral)) ?>"
           data-calendario-ir="<?= e(sprintf('%s-%02d-01', substr($fecha, 0, 4), $m['mes'])) ?>"
           data-calendario-vista-destino="mes">
            <?= e($m['nombre']) ?>
            <?php if ($m['total']): ?><span class="cuantos"><?= (int) $m['total'] ?></span><?php endif; ?>
        </a>
        <table class="calendario-mini">
            <thead>
                <tr>
                    <?php foreach (['D', 'L', 'M', 'M', 'J', 'V', 'S'] as $inicial): ?>
                    <th><?= $inicial ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($m['semanas'] as $semana): ?>
                <tr>
                    <?php foreach ($semana as $celda): ?>
                    <td>
                        <?php if ($celda && $celda['cuantos']): ?>
                        <a href="<?= e(url_publica('eventos', ['vista' => 'dia', 'fecha' => $celda['fecha']] + $paramsPastoral)) ?>"
                           class="mini-dia con-eventos <?= $celda['hoy'] ? 'es-hoy' : '' ?>"
                           style="--punto:<?= e($celda['color']) ?>"
                           data-calendario-ir="<?= e($celda['fecha']) ?>"
                           title="<?= (int) $celda['cuantos'] ?> <?= $celda['cuantos'] === 1 ? 'evento' : 'eventos' ?> el <?= e(fecha_larga($celda['fecha'])) ?>">
                            <?= (int) $celda['dia'] ?>
                        </a>
                        <?php elseif ($celda): ?>
                        <span class="mini-dia <?= $celda['hoy'] ? 'es-hoy' : '' ?>"><?= (int) $celda['dia'] ?></span>
                        <?php endif; ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>
</div>
</div>
