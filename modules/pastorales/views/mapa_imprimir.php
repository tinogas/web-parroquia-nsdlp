<?php
/**
 * Mapa de pastorales en hoja aparte: página independiente (sin
 * layout_admin.php), pensada para abrirse en pestaña nueva e imprimirse o
 * guardarse como PDF con el diálogo del propio navegador — igual que
 * PersonaController::directorioImprimir() y MescController::turnosImprimir().
 * Ver PastoralController::mapaImprimir().
 *
 * Árbol indentado, no cajitas conectadas por líneas como el organigrama de
 * personas (modules/nosotros/views/publico/organigrama_imprimir.php): con
 * Comisiones de hasta seis hijas, un árbol horizontal se haría demasiado
 * ancho para cualquier hoja. Aquí cada Comisión encabeza su bloque y, debajo,
 * indentadas con una guía vertical, sus dimensiones — máximo dos niveles,
 * igual que en toda la jerarquía de pastorales (docs/ARQUITECTURA.md).
 *
 * Variables esperadas:
 *   $comisiones  array, PastoralModel::agrupadoVisible()['comisiones'] —
 *                cada una con 'padre' y 'hijas'.
 *   $sueltas     array, pastorales sin Comisión ni hijas propias.
 */
if (!function_exists('pastorales_mapa_nodo')) {
    function pastorales_mapa_nodo(array $pastoral): void
    {
        ?>
        <span class="mp-nombre"><?= e($pastoral['nombre']) ?></span>
        <?php if ($pastoral['responsable_nombre']): ?>
        <span class="mp-responsable">
            <?= $pastoral['responsable_pareja_id'] ? '♥' : '' ?>
            <?= e($pastoral['responsable_nombre']) ?>
        </span>
        <?php endif; ?>
        <?php
    }
}
$huboAlgo = $comisiones || $sueltas;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Mapa de pastorales — <?= e(APP_CORTO) ?></title>
<link rel="stylesheet" href="<?= e(url_activo('assets/css/pastorales_mapa_imprimir.css')) ?>?v=<?= e(APP_VERSION) ?>">
</head>
<body>

<div class="mp-barra no-imprimir">
    <a href="<?= e(url_admin('pastorales')) ?>" class="mp-volver">&larr; Volver a Pastorales</a>
    <button type="button" class="mp-boton-imprimir" onclick="window.print()">Imprimir / Guardar como PDF</button>
</div>

<div class="mp-encabezado">
    <p class="mp-parroquia"><?= e(APP_NAME) ?></p>
    <p class="mp-subtitulo">Mapa de pastorales</p>
</div>

<?php if (!$huboAlgo): ?>
<p class="mp-vacio">Todavía no hay pastorales que mostrar.</p>
<?php else: ?>

<ul class="mp-arbol">
    <?php foreach ($comisiones as $grupo): ?>
    <li>
        <?php pastorales_mapa_nodo($grupo['padre']); ?>
        <?php if ($grupo['hijas']): ?>
        <ul>
            <?php foreach ($grupo['hijas'] as $hija): ?>
            <li><?php pastorales_mapa_nodo($hija); ?></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </li>
    <?php endforeach; ?>

    <?php foreach ($sueltas as $suelta): ?>
    <li><?php pastorales_mapa_nodo($suelta); ?></li>
    <?php endforeach; ?>
</ul>

<?php endif; ?>

</body>
</html>
