<h1 class="titulo-pagina mb-4">Avisos</h1>

<?php if (!$listado['filas']): ?>
<p class="text-muted fst-italic">Todavía no hay avisos publicados.</p>
<?php endif; ?>

<div class="row g-4">
    <?php foreach ($listado['filas'] as $aviso): ?>
    <div class="col-md-6 col-lg-4">
        <a href="<?= e(url_publica('avisos', ['slug' => $aviso['slug']])) ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 tarjeta-aviso">
                <img src="<?= e(imagen_o_placeholder($aviso['imagen'], $aviso['titulo'], 400, 220)) ?>"
                     class="card-img-top" alt="" style="height:160px;object-fit:cover">
                <div class="card-body p-3">
                    <span class="badge bg-secondary-subtle text-secondary-emphasis mb-2">
                        <?= e(AvisoModel::TIPOS[$aviso['tipo']] ?? $aviso['tipo']) ?>
                    </span>
                    <h2 class="h6 fw-bold mb-1 text-body"><?= e($aviso['titulo']) ?></h2>
                    <p class="small text-muted mb-2"><?= e(fecha_larga($aviso['fecha_publicacion'])) ?></p>
                    <p class="small text-body-secondary mb-0">
                        <?= e(resumen($aviso['resumen'] ?: $aviso['contenido'], 110)) ?>
                    </p>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<?php
$paginacion = $listado;
$paginaBase = url_publica('avisos');
require BASE_PATH . '/shared/views/parciales/paginacion.php';
?>
