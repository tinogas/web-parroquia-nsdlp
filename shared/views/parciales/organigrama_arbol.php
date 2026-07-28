<?php
/**
 * Dibuja el organigrama como una lista anidada. Es HTML plano y CSS: sin él,
 * la estructura sigue siendo legible y navegable.
 *
 * Variables esperadas:
 *   $oa_nodos  árbol construido por OrganigramaModel::arbolAdmin()/arbolPublico()
 *   $oa_admin  true para mostrar los controles de edición (opcional, false por defecto)
 *   $oa_csrf   token CSRF; solo hace falta si $oa_admin es true
 */
if (!function_exists('organigrama_render_nodo')) {
    function organigrama_render_nodo(array $nodo, bool $admin, string $csrf): void
    {
        // En el sitio público, una persona inactiva no se nombra: el nodo
        // conserva su título ("Párroco") aunque el puesto esté vacante.
        $mostrarPersona = !empty($nodo['persona_id']) && ($admin || !empty($nodo['persona_activo']));
        ?>
        <li>
            <div class="nodo-organigrama <?= empty($nodo['activo']) ? 'nodo-inactivo' : '' ?>">
                <?php if ($mostrarPersona): ?>
                <img src="<?= e(foto_o_avatar($nodo['persona_foto'] ?? null, (string) $nodo['persona_nombre'], 48)) ?>"
                     alt="" class="nodo-foto">
                <?php endif; ?>
                <div class="nodo-texto">
                    <span class="nodo-titulo"><?= e($nodo['titulo']) ?></span>
                    <?php if ($mostrarPersona): ?>
                    <span class="nodo-persona"><?= e($nodo['persona_nombre']) ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($admin): ?>
                <div class="nodo-acciones">
                    <a href="<?= e(url_admin('organigrama', 'nuevo', ['padre_id' => $nodo['id']])) ?>"
                       class="btn btn-sm btn-outline-secondary" title="Agregar subordinado">
                        <i class="bi bi-plus-lg"></i>
                    </a>
                    <a href="<?= e(url_admin('organigrama', 'editar', ['id' => $nodo['id']])) ?>"
                       class="btn btn-sm btn-outline-primary" title="Editar">
                        <i class="bi bi-pencil"></i>
                    </a>
                    <form method="POST" accept-charset="UTF-8"
                          action="<?= e(url_post('admin', 'organigrama', 'eliminar')) ?>" class="d-inline"
                          onsubmit="return confirm('¿Eliminar este nodo? Sus subordinados, si tiene, pasarán al primer nivel.');">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="id" value="<?= (int) $nodo['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($nodo['hijos'])): ?>
            <ul>
                <?php foreach ($nodo['hijos'] as $hijo) { organigrama_render_nodo($hijo, $admin, $csrf); } ?>
            </ul>
            <?php endif; ?>
        </li>
        <?php
    }
}

$oa_admin = $oa_admin ?? false;
$oa_csrf  = $oa_csrf ?? '';
?>
<?php if ($oa_nodos): ?>
<ul class="arbol-organigrama">
    <?php foreach ($oa_nodos as $nodo) { organigrama_render_nodo($nodo, $oa_admin, $oa_csrf); } ?>
</ul>
<?php else: ?>
<p class="text-muted fst-italic mb-0">Todavía no hay nodos en el organigrama.</p>
<?php endif; ?>
