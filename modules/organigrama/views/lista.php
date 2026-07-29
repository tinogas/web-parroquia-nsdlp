<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1">Organigrama</h1>
        <p class="text-muted mb-0 small">
            Se muestra en "Quiénes somos", salvo que subas una imagen propia en
            Configuración → Identidad → Organigrama en imagen.
        </p>
    </div>
    <a href="<?= e(url_admin('organigrama', 'nuevo')) ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nuevo nodo
    </a>
</div>

<?php if (Config::tiene('organigrama_imagen')): ?>
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle me-1"></i>
    Hay una imagen cargada en Configuración: el sitio muestra esa imagen en lugar de este
    árbol. Lo de aquí abajo sigue siendo el organigrama real, por si se quita la imagen.
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <?php
        $oa_nodos = $arbol;
        $oa_admin = true;
        $oa_csrf  = $csrf;
        require BASE_PATH . '/shared/views/parciales/organigrama_arbol.php';
        ?>
    </div>
</div>
