<div class="row justify-content-center">
    <div class="col-lg-7 text-center">
        <div class="display-4 text-success mb-3"><i class="bi bi-check-circle"></i></div>
        <h1 class="titulo-pagina mb-3">Inscripción recibida</h1>
        <p class="lead text-muted mb-4">
            Gracias por inscribirte a <?= e($curso['titulo']) ?>. Te contactaremos para
            confirmar los detalles.
        </p>

        <div class="card border-0 shadow-sm d-inline-block mb-4">
            <div class="card-body p-4">
                <p class="text-muted small mb-1">Tu folio</p>
                <p class="font-monospace fs-4 fw-bold mb-0"><?= e($folio) ?></p>
            </div>
        </div>

        <a href="<?= e(url_publica('cursos')) ?>" class="btn btn-outline-primary mt-2">
            <i class="bi bi-arrow-left me-1"></i>Ver más cursos
        </a>
    </div>
</div>
