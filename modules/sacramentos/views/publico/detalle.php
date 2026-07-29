<div class="row justify-content-center">
    <div class="col-lg-8">

        <nav aria-label="Ubicación" class="mb-3">
            <a href="<?= e(url_publica('sacramentos')) ?>" class="small text-decoration-none">
                <i class="bi bi-arrow-left me-1"></i>Todos los sacramentos
            </a>
        </nav>

        <h1 class="titulo-pagina mb-3"><?= e($sacramento['nombre']) ?></h1>

        <?php if ($sacramento['imagen']): ?>
        <img src="<?= e(url_activo($sacramento['imagen'])) ?>" alt="" class="img-fluid rounded mb-4">
        <?php endif; ?>

        <?php if (!empty($sacramento['descripcion'])): ?>
        <div class="contenido-editorial mb-4">
            <?= $sacramento['descripcion'] ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($sacramento['requisitos'])): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h2 class="h6 fw-bold mb-3"><i class="bi bi-check2-square text-primary me-1"></i>Requisitos</h2>
                <div class="contenido-editorial">
                    <?= $sacramento['requisitos'] ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($sacramento['documentos'])): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h2 class="h6 fw-bold mb-3"><i class="bi bi-file-earmark-text text-primary me-1"></i>Documentos a presentar</h2>
                <div class="contenido-editorial">
                    <?= $sacramento['documentos'] ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($sacramento['aportacion']): ?>
        <p class="text-muted small">
            <i class="bi bi-cash-coin me-1"></i>Aportación sugerida: <?= e($sacramento['aportacion']) ?>
        </p>
        <?php endif; ?>

        <?php if ($sacramento['acepta_solicitudes']): ?>
        <a href="<?= e(url_publica('sacramentos', ['slug' => $sacramento['slug'], 'accion' => 'solicitar'])) ?>"
           class="btn btn-primary btn-lg">
            <i class="bi bi-send me-1"></i>Solicitar en línea
        </a>
        <?php else: ?>
        <div class="alert alert-light border">
            <i class="bi bi-info-circle me-1"></i>
            Para este sacramento, acércate directamente a la oficina parroquial.
        </div>
        <?php endif; ?>

    </div>
</div>
