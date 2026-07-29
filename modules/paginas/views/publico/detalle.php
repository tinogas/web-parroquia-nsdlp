<div class="row justify-content-center">
    <div class="col-lg-9">

        <h1 class="titulo-pagina mb-4"><?= e($pagina['titulo']) ?></h1>

        <?php if (!empty($pagina['contenido'])): ?>
        <div class="contenido-editorial">
            <?php /* Saneado con lista blanca al guardarse: se imprime sin escapar a propósito. */ ?>
            <?= $pagina['contenido'] ?>
        </div>
        <?php else: ?>
        <p class="text-muted fst-italic">Esta página aún no tiene contenido.</p>
        <?php endif; ?>

        <?php if (!empty($pagina['updated_at'])): ?>
        <p class="text-body-tertiary small mt-5 mb-0">
            Última actualización: <?= e(fecha_larga($pagina['updated_at'])) ?>
        </p>
        <?php endif; ?>

    </div>
</div>
