<h1 class="titulo-pagina mb-2">Sacramentos</h1>

<?php if (!empty($bloques['sacramentos_intro']['contenido'])): ?>
<div class="contenido-editorial mb-4">
    <?= $bloques['sacramentos_intro']['contenido'] ?>
</div>
<?php endif; ?>

<?php if (!$sacramentos): ?>
<p class="text-muted fst-italic">Estamos actualizando esta sección.</p>
<?php endif; ?>

<div class="row g-4">
    <?php foreach ($sacramentos as $sacramento): ?>
    <div class="col-md-6 col-lg-4">
        <a href="<?= e(url_publica('sacramentos', ['slug' => $sacramento['slug']])) ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 tarjeta-aviso text-center">
                <div class="card-body p-4">
                    <div class="fs-2 text-dorado mb-2"><i class="bi bi-droplet"></i></div>
                    <h2 class="h6 fw-bold mb-0 text-body"><?= e($sacramento['nombre']) ?></h2>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>
