<h1 class="titulo-pagina mb-2">Pastorales</h1>

<?php if (!empty($bloques['pastorales_intro']['contenido'])): ?>
<div class="contenido-editorial mb-4">
    <?= $bloques['pastorales_intro']['contenido'] ?>
</div>
<?php endif; ?>

<?php if (!$comisiones && !$sueltas): ?>
<p class="text-muted fst-italic">Estamos actualizando el listado de pastorales.</p>
<?php endif; ?>

<?php
/** Una tarjeta de pastoral, igual para hijas de Comisión y para pastorales sueltas. */
$dibujarTarjeta = static function (array $pastoral): void {
    ?>
    <div class="col-md-6 col-lg-4">
        <a href="<?= e(url_publica('pastorales', ['slug' => $pastoral['slug']])) ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 tarjeta-aviso">
                <div class="card-body p-4">
                    <div class="fs-2 text-dorado mb-2"><i class="bi <?= e($pastoral['icono'] ?: 'bi-people') ?>"></i></div>
                    <h3 class="h6 fw-bold mb-2 text-body"><?= e($pastoral['nombre']) ?></h3>
                    <?php if ($pastoral['descripcion_corta']): ?>
                    <p class="small text-muted mb-0"><?= e($pastoral['descripcion_corta']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </a>
    </div>
    <?php
};
?>

<?php foreach ($comisiones as $grupo): ?>
<section class="mb-5">
    <h2 class="h5 fw-bold mb-3">
        <a href="<?= e(url_publica('pastorales', ['slug' => $grupo['padre']['slug']])) ?>" class="text-decoration-none text-body">
            <i class="bi <?= e($grupo['padre']['icono'] ?: 'bi-people') ?> text-dorado me-1"></i>
            <?= e($grupo['padre']['nombre']) ?>
        </a>
    </h2>
    <?php if ($grupo['padre']['descripcion_corta']): ?>
    <p class="text-muted small mb-3"><?= e($grupo['padre']['descripcion_corta']) ?></p>
    <?php endif; ?>
    <div class="row g-4">
        <?php foreach ($grupo['hijas'] as $pastoral): ?>
        <?php $dibujarTarjeta($pastoral); ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endforeach; ?>

<?php if ($sueltas): ?>
<section class="mb-4">
    <div class="row g-4">
        <?php foreach ($sueltas as $pastoral): ?>
        <?php $dibujarTarjeta($pastoral); ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
