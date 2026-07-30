<?php
/**
 * Vista de impresión del organigrama: página independiente (sin
 * layout_publico.php), pensada para abrirse en pestaña nueva e imprimirse o
 * guardarse como PDF con el diálogo del propio navegador. Ver
 * NosotrosController::organigramaImprimir() y docs/ARQUITECTURA.md.
 *
 * Variables esperadas:
 *   $organigramaImagen  string, '' si no hay imagen cargada
 *   $organigramaArbol   array, árbol de OrganigramaModel::arbolPublico()
 */
if (!function_exists('organigrama_imprimir_nodo')) {
    function organigrama_imprimir_nodo(array $nodo): void
    {
        $mostrarPersona = !empty($nodo['persona_id']) && !empty($nodo['persona_activo']);
        ?>
        <li>
            <div class="oi-nodo <?= empty($nodo['activo']) ? 'oi-inactivo' : '' ?>">
                <?php if ($mostrarPersona): ?>
                <img src="<?= e(foto_o_avatar($nodo['persona_foto'] ?? null, (string) $nodo['persona_nombre'], 64)) ?>"
                     alt="" class="oi-foto">
                <?php endif; ?>
                <span class="oi-titulo"><?= e($nodo['titulo']) ?></span>
                <?php if ($mostrarPersona): ?>
                <span class="oi-persona"><?= e($nodo['persona_nombre']) ?></span>
                <?php endif; ?>
            </div>

            <?php if (!empty($nodo['hijos'])): ?>
            <ul>
                <?php foreach ($nodo['hijos'] as $hijo) { organigrama_imprimir_nodo($hijo); } ?>
            </ul>
            <?php endif; ?>
        </li>
        <?php
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Organigrama — <?= e(APP_CORTO) ?></title>
<link rel="stylesheet" href="<?= e(url_activo('assets/css/organigrama_imprimir.css')) ?>?v=<?= e(APP_VERSION) ?>">
</head>
<body>

<div class="oi-barra no-imprimir">
    <a href="<?= e(url_publica('nosotros')) ?>" class="oi-volver">&larr; Volver a Quiénes somos</a>
    <button type="button" class="oi-boton-imprimir" onclick="window.print()">Imprimir / Guardar como PDF</button>
</div>

<div class="oi-encabezado">
    <p class="oi-parroquia"><?= e(APP_NAME) ?></p>
    <p class="oi-subtitulo">Organigrama</p>
</div>

<?php if ($organigramaImagen !== ''): ?>
<img src="<?= e(url_activo($organigramaImagen)) ?>" alt="Organigrama de la parroquia" class="oi-imagen">
<?php elseif ($organigramaArbol): ?>
<ul class="oi-arbol">
    <?php foreach ($organigramaArbol as $nodo) { organigrama_imprimir_nodo($nodo); } ?>
</ul>
<?php else: ?>
<p class="oi-vacio">Todavía no hay nodos en el organigrama.</p>
<?php endif; ?>

</body>
</html>
