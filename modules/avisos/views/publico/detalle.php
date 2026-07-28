<div class="row justify-content-center">
    <div class="col-lg-8">

        <nav aria-label="Ubicación" class="mb-3">
            <a href="<?= e(url_publica('avisos')) ?>" class="small text-decoration-none">
                <i class="bi bi-arrow-left me-1"></i>Todos los avisos
            </a>
        </nav>

        <span class="badge bg-secondary-subtle text-secondary-emphasis mb-2">
            <?= e(AvisoModel::TIPOS[$aviso['tipo']] ?? $aviso['tipo']) ?>
        </span>
        <h1 class="titulo-pagina mb-2"><?= e($aviso['titulo']) ?></h1>
        <p class="text-muted mb-4"><?= e(fecha_larga($aviso['fecha_publicacion'])) ?></p>

        <?php if ($aviso['imagen']): ?>
        <img src="<?= e(url_activo($aviso['imagen'])) ?>" alt="" class="img-fluid rounded mb-4">
        <?php endif; ?>

        <?php if ($aviso['archivo_pdf']): ?>
        <a href="<?= e(url_activo($aviso['archivo_pdf'])) ?>" target="_blank"
           class="btn btn-outline-primary mb-4">
            <i class="bi bi-file-earmark-pdf me-1"></i>Descargar el boletín en PDF
        </a>
        <?php endif; ?>

        <?php if (!empty($aviso['contenido'])): ?>
        <div class="contenido-editorial">
            <?= $aviso['contenido'] ?>
        </div>
        <?php endif; ?>

    </div>
</div>
