<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <nav aria-label="Ubicación">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item"><a href="<?= e(url_admin('mesc')) ?>" class="text-decoration-none">MESC</a></li>
                <li class="breadcrumb-item active" aria-current="page">Colores litúrgicos</li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0">Colores litúrgicos</h1>
        <p class="text-muted mb-0 small">Referencia para etiquetar los turnos según el tiempo o fiesta del día.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= e(url_admin('mesc', 'turnos')) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-calendar3 me-1"></i>Turnos
        </a>
        <?php if (Auth::tienePermiso('mesc.crear')): ?>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#colorNuevo">
            <i class="bi bi-plus-lg me-1"></i>Nuevo color
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="alert alert-light border small mb-4">
    <i class="bi bi-info-circle text-primary me-1"></i>
    Los colores de las casullas de los sacerdotes muestran el significado de cada tiempo o fiesta de la Iglesia,
    destacando principalmente el blanco, el verde y el morado. Cada color se usa en momentos especiales para
    ayudar a los fieles a entender la misa del día.
</div>

<?php if (!$colores): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5">
        <p class="text-muted mb-0">Todavía no hay colores litúrgicos registrados.</p>
    </div>
</div>
<?php endif; ?>

<div class="row g-3">
    <?php foreach ($colores as $color): ?>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="rounded-circle border" style="width:28px;height:28px;display:inline-block;background:<?= e($color['color_hex']) ?>"></span>
                        <h2 class="h6 fw-bold mb-0"><?= e($color['nombre']) ?></h2>
                    </div>
                    <?php if (Auth::tienePermiso('mesc.editar')): ?>
                    <button type="button" class="btn btn-sm btn-outline-primary"
                            data-bs-toggle="modal" data-bs-target="#color<?= (int) $color['id'] ?>">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <?php endif; ?>
                </div>
                <p class="small text-muted mb-0"><?= e($color['significado']) ?></p>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php
$dibujarModalColor = static function (string $idModal, ?array $color, string $csrf) {
    $vacio = $color === null;
    ?>
    <div class="modal fade" id="<?= e($idModal) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" accept-charset="UTF-8"
                  action="<?= e(url_post('admin', 'mesc', 'color_guardar')) ?>" class="modal-content">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="id" value="<?= $vacio ? 0 : (int) $color['id'] ?>">

                <div class="modal-header border-0 pb-0">
                    <h2 class="h6 modal-title fw-bold"><?= $vacio ? 'Nuevo color litúrgico' : 'Editar color' ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-2">
                        <div class="col-8">
                            <label class="form-label small fw-semibold">Nombre</label>
                            <input type="text" name="nombre" class="form-control form-control-sm"
                                   value="<?= e($vacio ? '' : $color['nombre']) ?>" maxlength="30" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label small fw-semibold">Tono</label>
                            <input type="color" name="color_hex" class="form-control form-control-sm form-control-color w-100"
                                   value="<?= e($vacio ? '#ffffff' : $color['color_hex']) ?>" required>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Significado</label>
                        <textarea name="significado" class="form-control form-control-sm" rows="4" maxlength="400" required
                                  ><?= e($vacio ? '' : $color['significado']) ?></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Orden</label>
                        <input type="number" name="orden" class="form-control form-control-sm"
                               value="<?= $vacio ? 0 : (int) $color['orden'] ?>" min="0" max="999">
                    </div>
                </div>
                <div class="modal-footer border-0 justify-content-between">
                    <?php if (!$vacio): ?>
                    <button type="submit" formaction="<?= e(url_post('admin', 'mesc', 'color_eliminar')) ?>"
                            class="btn btn-outline-danger btn-sm"
                            onclick="return confirm('¿Eliminar este color? Los turnos que lo usaban quedan sin color asignado.');">
                        <i class="bi bi-trash me-1"></i>Eliminar
                    </button>
                    <?php else: ?>
                    <span></span>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-check-lg me-1"></i>Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php
};

$dibujarModalColor('colorNuevo', null, $csrf);
foreach ($colores as $color) {
    $dibujarModalColor('color' . (int) $color['id'], $color, $csrf);
}
?>
