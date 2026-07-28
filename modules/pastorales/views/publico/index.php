<h1 class="titulo-pagina mb-2">Pastorales</h1>

<?php if (!empty($bloques['pastorales_intro']['contenido'])): ?>
<div class="contenido-editorial mb-4">
    <?= $bloques['pastorales_intro']['contenido'] ?>
</div>
<?php endif; ?>

<?php if (!$pastorales): ?>
<p class="text-muted fst-italic">Estamos actualizando el listado de pastorales.</p>
<?php endif; ?>

<div class="row g-4">
    <?php foreach ($pastorales as $pastoral): ?>
    <div class="col-md-6 col-lg-4">
        <a href="<?= e(url_publica('pastorales', ['slug' => $pastoral['slug']])) ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 tarjeta-aviso">
                <div class="card-body p-4">
                    <div class="fs-2 text-dorado mb-2"><i class="bi <?= e($pastoral['icono'] ?: 'bi-people') ?>"></i></div>
                    <h2 class="h6 fw-bold mb-2 text-body"><?= e($pastoral['nombre']) ?></h2>
                    <?php if ($pastoral['descripcion_corta']): ?>
                    <p class="small text-muted mb-0"><?= e($pastoral['descripcion_corta']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>
