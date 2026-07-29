<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <nav aria-label="Ubicación">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item"><a href="<?= e(url_admin('mesc')) ?>" class="text-decoration-none">MESC</a></li>
                <li class="breadcrumb-item"><a href="<?= e(url_admin('mesc', 'rutas')) ?>" class="text-decoration-none">Rutas</a></li>
                <li class="breadcrumb-item active" aria-current="page">Generar</li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0">Generar ruta de visitas</h1>
    </div>
    <a href="<?= e(url_admin('mesc', 'rutas')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<?php if (!$visitas): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5">
        <p class="text-muted mb-0">No hay visitas activas disponibles. Registra al menos una antes de generar una ruta.</p>
    </div>
</div>
<?php else: ?>

<div class="alert alert-light border small">
    <i class="bi bi-info-circle text-primary me-1"></i>
    El orden se calcula automáticamente por cercanía, partiendo de la parroquia si su ubicación está configurada.
    Las visitas sin pin en el mapa se agregan al final. Podrás reordenar todo a mano después de generar la ruta.
</div>

<form method="POST" accept-charset="UTF-8" action="<?= e(url_post('admin', 'mesc', 'ruta_generar')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="mb-3">
                <label for="nombre" class="form-label fw-semibold">Nombre de la ruta</label>
                <input type="text" name="nombre" id="nombre" class="form-control" maxlength="150"
                       placeholder="Ej. Ruta del sábado 2 de agosto">
            </div>

            <label class="form-label fw-semibold">Visitas a incluir</label>
            <div class="d-flex justify-content-end mb-2">
                <button type="button" class="btn btn-sm btn-link p-0" id="marcarTodas">Marcar todas</button>
            </div>
            <div class="list-group">
                <?php foreach ($visitas as $visita): ?>
                <label class="list-group-item d-flex gap-3 align-items-start">
                    <input class="form-check-input mt-1" type="checkbox" name="visitas[]" value="<?= (int) $visita['id'] ?>">
                    <span>
                        <span class="fw-semibold d-block"><?= e($visita['nombre_enfermo']) ?></span>
                        <span class="small text-muted">
                            <?= e($visita['direccion']) ?>
                            <?php if ($visita['latitud'] !== null): ?>
                            <i class="bi bi-geo-alt-fill text-primary ms-1" title="Con ubicación en mapa"></i>
                            <?php else: ?>
                            <span class="text-warning-emphasis">(sin ubicación en mapa)</span>
                            <?php endif; ?>
                        </span>
                    </span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">
        <i class="bi bi-signpost-2 me-1"></i>Generar ruta
    </button>
</form>

<script>
document.getElementById('marcarTodas').addEventListener('click', function () {
    var casillas = document.querySelectorAll('input[name="visitas[]"]');
    var faltanPorMarcar = Array.prototype.some.call(casillas, function (c) { return !c.checked; });
    casillas.forEach(function (c) { c.checked = faltanPorMarcar; });
});
</script>

<?php endif; ?>
