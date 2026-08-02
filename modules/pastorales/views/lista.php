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
                <div class="d-flex gap-1 flex-wrap mb-2">
                    <?php if ($pastoral['activa']): ?>
                    <span class="badge bg-success-subtle text-success-emphasis">Visible</span>
                    <?php else: ?>
                    <span class="badge bg-secondary-subtle text-secondary-emphasis">Oculta</span>
                    <?php endif; ?>
                    <?php if ($pastoral['visible_en_menu']): ?>
                    <span class="badge bg-primary-subtle text-primary-emphasis">
                        <i class="bi bi-grid-1x2 me-1"></i>En el menú
                    </span>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2 mt-2 flex-wrap">
                    <a href="<?= e(url_admin('pastorales', 'panel', ['id' => $pastoral['id']])) ?>"
                       class="btn btn-sm btn-outline-secondary flex-grow-1">
                        <i class="bi bi-grid-1x2 me-1"></i>Panel básico
                    </a>
                    <a href="<?= e(url_admin('pastorales', 'editar', ['id' => $pastoral['id']])) ?>"
                       class="btn btn-sm btn-outline-primary flex-grow-1">
                        <i class="bi bi-pencil me-1"></i>Editar
                    </a>
                    <?php if (Auth::esAdmin() && !$pastoral['visible_en_menu']): ?>
                    <button type="button" class="btn btn-sm btn-outline-success"
                            data-bs-toggle="modal" data-bs-target="#activarMenu<?= (int) $pastoral['id'] ?>"
                            title="Crear menú y grupo">
                        <i class="bi bi-plus-circle"></i>
                    </button>
                    <?php endif; ?>
                    <?php if (Auth::esAdmin()): ?>
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
<?php foreach ($todasParaModales as $pastoral):
    $mcp_idModal       = 'borrar' . (int) $pastoral['id'];
    $mcp_titulo        = 'Eliminar «' . $pastoral['nombre'] . '»';
    $mcp_mensaje       = 'Se eliminará <strong>permanentemente</strong> a «' . e($pastoral['nombre']) . '», sus '
                       . 'actividades y sus documentos. Los avisos, eventos y cursos que ya le pertenecían '
                       . 'quedarán como contenido parroquial general (sin pastoral), no se borran.'
                       . ($pastoral['pastoral_padre_id'] === null
                           ? ' Si agrupaba otras pastorales, esas quedarán sueltas, sin Comisión.' : '')
                       . ' Esta acción no se puede deshacer.';
    $mcp_accionUrl     = url_post('admin', 'pastorales', 'eliminar');
    $mcp_camposOcultos = ['id' => (int) $pastoral['id']];
    $mcp_csrf          = $csrf;
    $mcp_textoBoton    = 'Eliminar';
    $mcp_claseBoton    = 'btn-danger';
    require BASE_PATH . '/shared/views/parciales/modal_confirmar_password.php';
endforeach; ?>

<?php if (Auth::esAdmin()):
    foreach ($todasParaModales as $pastoral):
        if ($pastoral['visible_en_menu']) { continue; }
        $mcp_idModal       = 'activarMenu' . (int) $pastoral['id'];
        $mcp_titulo        = 'Crear menú y grupo de «' . $pastoral['nombre'] . '»';
        $mcp_mensaje       = 'Esto hará visible a <strong>' . e($pastoral['nombre']) . '</strong> en el menú del '
                           . 'panel, agrupada bajo su Comisión, con acceso a avisos, eventos, cursos y documentos.';
        $mcp_accionUrl     = url_post('admin', 'pastorales', 'menuActivar');
        $mcp_camposOcultos = ['id' => (int) $pastoral['id']];
        $mcp_csrf          = $csrf;
        $mcp_textoBoton    = 'Crear menú y grupo';
        $mcp_claseBoton    = 'btn-success';
        require BASE_PATH . '/shared/views/parciales/modal_confirmar_password.php';
    endforeach;
endif; ?>
