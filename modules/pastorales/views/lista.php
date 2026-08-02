<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1">Pastorales</h1>
        <p class="text-muted mb-0 small">
            <?= Auth::tieneAlcanceGlobal() ? 'Todos los grupos y pastorales de la parroquia.' : 'Las pastorales que coordinas.' ?>
        </p>
    </div>
    <?php if (Auth::tienePermiso('pastorales.crear')): ?>
    <a href="<?= e(url_admin('pastorales', 'nueva')) ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nueva pastoral
    </a>
    <?php endif; ?>
</div>

<?php if (!$comisiones && !$sueltas): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5">
        <div class="display-6 text-body-tertiary mb-2"><i class="bi bi-people"></i></div>
        <p class="text-muted mb-0">No hay pastorales que mostrar.</p>
    </div>
</div>
<?php endif; ?>

<?php
/** Una tarjeta de pastoral, igual para hijas de Comisión y para pastorales sueltas. */
$dibujarTarjeta = static function (array $pastoral): void {
    ?>
    <div class="col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="bi <?= e($pastoral['icono'] ?: 'bi-people') ?> fs-4 text-primary"></i>
                    <h3 class="h6 fw-bold mb-0"><?= e($pastoral['nombre']) ?></h3>
                </div>
                <?php if ($pastoral['descripcion_corta']): ?>
                <p class="small text-muted mb-2"><?= e($pastoral['descripcion_corta']) ?></p>
                <?php endif; ?>
                <?php if ($pastoral['activa']): ?>
                <span class="badge bg-success-subtle text-success-emphasis mb-2">Visible</span>
                <?php else: ?>
                <span class="badge bg-secondary-subtle text-secondary-emphasis mb-2">Oculta</span>
                <?php endif; ?>
                <div class="d-flex gap-2 mt-2">
                    <a href="<?= e(url_admin('pastorales', 'editar', ['id' => $pastoral['id']])) ?>"
                       class="btn btn-sm btn-outline-primary flex-grow-1">
                        <i class="bi bi-pencil me-1"></i>Editar
                    </a>
                    <?php if (Auth::tienePermiso('pastorales.eliminar')): ?>
                    <button type="button" class="btn btn-sm btn-outline-danger"
                            data-bs-toggle="modal" data-bs-target="#borrar<?= (int) $pastoral['id'] ?>">
                        <i class="bi bi-trash"></i>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
};
?>

<?php foreach ($comisiones as $grupo): ?>
<div class="mb-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="h6 fw-bold text-uppercase text-muted mb-0">
            <i class="bi <?= e($grupo['padre']['icono'] ?: 'bi-people') ?> me-1"></i>
            <?= e($grupo['padre']['nombre']) ?>
        </h2>
        <a href="<?= e(url_admin('pastorales', 'editar', ['id' => $grupo['padre']['id']])) ?>"
           class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-pencil me-1"></i>Editar Comisión
        </a>
    </div>
    <div class="row g-3">
        <?php foreach ($grupo['hijas'] as $pastoral): ?>
        <?php $dibujarTarjeta($pastoral); ?>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<?php if ($sueltas): ?>
<div class="mb-4">
    <?php if ($comisiones): ?>
    <h2 class="h6 fw-bold text-uppercase text-muted mb-3">Otras pastorales</h2>
    <?php endif; ?>
    <div class="row g-3">
        <?php foreach ($sueltas as $pastoral): ?>
        <?php $dibujarTarjeta($pastoral); ?>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php
// Los modales de borrado cubren toda pastoral visible: Comisiones, sus hijas y las sueltas.
$todasParaModales = $sueltas;
foreach ($comisiones as $grupo) {
    $todasParaModales[] = $grupo['padre'];
    $todasParaModales   = array_merge($todasParaModales, $grupo['hijas']);
}
?>
<?php foreach ($todasParaModales as $pastoral): ?>
    <div class="modal fade" id="borrar<?= (int) $pastoral['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h2 class="h6 modal-title fw-bold">Eliminar «<?= e($pastoral['nombre']) ?>»</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">
                        Se eliminará la pastoral y sus actividades. Los avisos, eventos y fotos que ya
                        le pertenecían quedarán como contenido parroquial general, no se borran.
                        <?php if ($pastoral['pastoral_padre_id'] === null): ?>
                        Si agrupaba otras pastorales, esas quedarán sueltas, sin Comisión.
                        <?php endif; ?>
                    </p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" accept-charset="UTF-8"
                          action="<?= e(url_post('admin', 'pastorales', 'eliminar')) ?>" class="m-0">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="id" value="<?= (int) $pastoral['id'] ?>">
                        <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i>Eliminar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
