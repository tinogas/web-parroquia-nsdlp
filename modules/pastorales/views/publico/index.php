<?php
/**
 * Pastorales en rejilla tipo "bento": una tarjeta por Comisión con sus
 * pastorales dentro, y las pastorales sueltas como tarjetas propias. Las
 * Comisiones con cuatro o más pastorales ocupan dos columnas para que su
 * lista respire; grid-auto-flow: dense rellena los huecos que eso deja.
 *
 * Quién coordina se sigue leyendo sin abrir la ficha —una silueta si es una
 * persona, dos si es un matrimonio—; correo y teléfono, en cambio, siguen
 * solo en la ficha de cada pastoral, que es donde tiene sentido escribirle.
 */

/** "Miércoles, 8:00 p. m." o '' si la pastoral no tiene reunión fija. */
$reunion = static function (array $p): string {
    $partes = array_filter([
        $p['dia_reunion'] ? ucfirst(mb_strtolower($p['dia_reunion'])) : '',
        $p['hora_reunion'] ? hora_corta($p['hora_reunion']) : '',
    ]);
    return implode(', ', $partes);
};

$responsable = static function (array $p): string {
    if (!$p['responsable_nombre']) {
        return '';
    }
    $icono = $p['responsable_pareja_id'] ? 'bi-people' : 'bi-person';
    return '<i class="bi ' . $icono . ' me-1" aria-hidden="true"></i>' . e($p['responsable_nombre']);
};

$total = count($comisiones) + count($sueltas);

// Se resalta la Comisión más grande, que es la que más gente busca y la que
// más se beneficia de llamar la atención; con empate, la primera.
$masGrande = null;
foreach ($comisiones as $i => $grupo) {
    if ($masGrande === null || count($grupo['hijas']) > count($comisiones[$masGrande]['hijas'])) {
        $masGrande = $i;
    }
}
?>

<div class="encabezado-seccion">
    <span class="antetitulo">Pastorales</span>
    <h1 class="titulo-pagina mb-3">Hay un lugar para ti</h1>
    <?php if (!empty($bloques['pastorales_intro']['contenido'])): ?>
    <div class="contenido-editorial text-tinta-suave">
        <?= $bloques['pastorales_intro']['contenido'] ?>
    </div>
    <?php else: ?>
    <p>Las pastorales se agrupan en comisiones. Entra a la que te interese para ver cuándo se reúne y cómo contactar a quien la coordina.</p>
    <?php endif; ?>
</div>

<?php if ($total === 0): ?>
<p class="text-muted fst-italic">Estamos actualizando el listado de pastorales.</p>
<?php else: ?>

<div class="bento">
    <?php foreach ($comisiones as $i => $grupo): ?>
    <?php
    $padre = $grupo['padre'];
    $hijas = $grupo['hijas'];
    $ancha = count($hijas) >= 4;
    ?>
    <section class="bento-tarjeta <?= $ancha ? 'ancha' : '' ?> <?= $i === $masGrande ? 'destacada' : '' ?>"
             aria-labelledby="comision-<?= (int) $padre['id'] ?>">
        <div class="bento-cabecera">
            <span class="bento-icono"><i class="bi <?= e($padre['icono'] ?: 'bi-people') ?>" aria-hidden="true"></i></span>
            <span class="bento-cuenta"><?= count($hijas) ?> <?= count($hijas) === 1 ? 'pastoral' : 'pastorales' ?></span>
        </div>
        <div>
            <h2 class="bento-titulo" id="comision-<?= (int) $padre['id'] ?>">
                <a href="<?= e(url_publica('pastorales', ['slug' => $padre['slug']])) ?>"><?= e($padre['nombre']) ?></a>
            </h2>
            <?php if ($padre['responsable_nombre']): ?>
            <p class="bento-responsable mt-1"><?= $responsable($padre) ?></p>
            <?php endif; ?>
            <?php if ($padre['descripcion_corta']): ?>
            <p class="bento-descripcion mt-1"><?= e($padre['descripcion_corta']) ?></p>
            <?php endif; ?>
        </div>
        <?php if ($hijas): ?>
        <ul class="bento-lista">
            <?php foreach ($hijas as $hija): ?>
            <?php $detalle = $reunion($hija) ?: $hija['descripcion_corta']; ?>
            <li>
                <a href="<?= e(url_publica('pastorales', ['slug' => $hija['slug']])) ?>">
                    <span class="nombre"><?= e($hija['nombre']) ?></span>
                    <?php if ($hija['responsable_nombre']): ?>
                    <span class="detalle"><?= $responsable($hija) ?></span>
                    <?php endif; ?>
                    <?php if ($detalle): ?>
                    <span class="detalle"><?= e($detalle) ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>
    <?php endforeach; ?>

    <?php foreach ($sueltas as $pastoral): ?>
    <?php $cuando = $reunion($pastoral); ?>
    <section class="bento-tarjeta" aria-labelledby="pastoral-<?= (int) $pastoral['id'] ?>">
        <div class="bento-cabecera">
            <span class="bento-icono"><i class="bi <?= e($pastoral['icono'] ?: 'bi-people') ?>" aria-hidden="true"></i></span>
            <?php if ($cuando): ?>
            <span class="bento-cuenta"><?= e($cuando) ?></span>
            <?php endif; ?>
        </div>
        <div>
            <h2 class="bento-titulo" id="pastoral-<?= (int) $pastoral['id'] ?>">
                <a href="<?= e(url_publica('pastorales', ['slug' => $pastoral['slug']])) ?>"><?= e($pastoral['nombre']) ?></a>
            </h2>
            <?php if ($pastoral['responsable_nombre']): ?>
            <p class="bento-responsable mt-1"><?= $responsable($pastoral) ?></p>
            <?php endif; ?>
            <?php if ($pastoral['descripcion_corta']): ?>
            <p class="bento-descripcion mt-1"><?= e($pastoral['descripcion_corta']) ?></p>
            <?php endif; ?>
        </div>
        <div class="bento-pie">
            <a href="<?= e(url_publica('pastorales', ['slug' => $pastoral['slug']])) ?>" class="enlace-flecha small">
                Conocer más <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    </section>
    <?php endforeach; ?>
</div>

<?php endif; ?>
