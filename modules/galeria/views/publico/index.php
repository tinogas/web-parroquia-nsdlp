<h1 class="titulo-pagina mb-4">Galería</h1>

<?php if (!$listado['filas']): ?>
<p class="text-muted fst-italic">Todavía no hay fotografías publicadas.</p>
<?php endif; ?>

<div class="row g-3">
    <?php foreach ($listado['filas'] as $imagen): ?>
    <div class="col-6 col-md-4 col-lg-3">
        <a href="<?= e(url_activo($imagen['archivo'])) ?>" target="_blank" rel="noopener"
           class="d-block tarjeta-galeria" title="<?= e($imagen['titulo'] ?: 'Ver en tamaño completo') ?>">
            <img src="<?= e(url_activo($imagen['archivo'])) ?>"
                 alt="<?= e($imagen['alt_texto'] ?: $imagen['titulo'] ?: 'Fotografía de la parroquia') ?>"
                 loading="lazy">
        </a>
    </div>
    <?php endforeach; ?>
</div>

<?php
$paginacion = $listado;
$paginaBase = url_publica('galeria');
require BASE_PATH . '/shared/views/parciales/paginacion.php';
?>
