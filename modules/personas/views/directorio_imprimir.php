<?php
/**
 * Directorio telefónico en hoja aparte: página independiente (sin
 * layout_admin.php), pensada para abrirse en pestaña nueva e imprimirse o
 * guardarse como PDF con el diálogo del propio navegador — igual que
 * MescController::turnosImprimir() y NosotrosController::organigramaImprimir().
 * Ver PersonaController::directorioImprimir() y
 * docs/migraciones/2026-09-13-directorio-telefonico.sql
 *
 * A diferencia del organigrama, esta vista vive en el panel (requiere sesión
 * y el permiso personas.ver) y no en el sitio público: lleva teléfonos, que
 * nunca se publican (docs/PRIVACIDAD.md). Cada quien lleva además su sede o
 * centro (`persona_centros`, puede ser más de uno), igual que el resto de
 * listados del equipo pastoral.
 *
 * Agrupado por pastoral y su jerarquía —una Comisión y, indentadas debajo,
 * sus dimensiones— y no por tipo: dentro de cada pastoral, primero quien la
 * coordina (Coordinador general si es la Comisión entera, Coordinador si es
 * la dimensión), luego quien tiene "Encargad-" en su cargo, y el resto por
 * nombre. Ver PersonaModel::paraDirectorioPorPastoral().
 *
 * Variables esperadas:
 *   $agrupado     array, PastoralModel::activasAgrupadas() — comisiones (con
 *                 sus hijas) y sueltas.
 *   $porPastoral  array, pastoral_id => fichas de personas ya ordenadas.
 *   $sinPastoral  array, fichas de quien está en el directorio sin pertenecer
 *                 ni coordinar ninguna pastoral —normalmente párroco, vicario
 *                 y personal de oficina—, ya en ese orden fijo. Se imprime
 *                 siempre arriba de todo, antes que cualquier Comisión.
 */
if (!function_exists('directorio_imprimir_persona')) {
    /**
     * Las cinco columnas se escriben siempre, aunque vengan vacías: con
     * columnas de ancho fijo (ver .di-persona en el CSS), omitir un `<span>`
     * cuando falta el dato correrría las columnas de esa fila respecto a las
     * demás. Cada una trunca con "…" si no cabe, para que todo el renglón
     * quepa en una sola línea —incluido el correo, que antes se iba abajo—.
     */
    function directorio_imprimir_persona(array $persona): void
    {
        ?>
        <li class="di-persona">
            <span class="di-nombre" title="<?= e($persona['nombre']) ?>"><?= e($persona['nombre']) ?></span>
            <span class="di-cargo" title="<?= e((string) $persona['cargo']) ?>"><?= e((string) $persona['cargo']) ?></span>
            <span class="di-sede" title="<?= e((string) ($persona['centros_nombres'] ?? '')) ?>"><?= e((string) ($persona['centros_nombres'] ?? '')) ?></span>
            <span class="di-telefono"><?= e((string) $persona['telefono']) ?></span>
            <span class="di-correo" title="<?= e((string) $persona['email']) ?>"><?= e((string) $persona['email']) ?></span>
        </li>
        <?php
    }
}
if (!function_exists('directorio_imprimir_lista')) {
    function directorio_imprimir_lista(array $personas, int $nivel): void
    {
        if (!$personas) {
            return;
        }
        ?>
        <ul class="di-personas di-nivel-<?= (int) $nivel ?>">
            <?php foreach ($personas as $persona) { directorio_imprimir_persona($persona); } ?>
        </ul>
        <?php
    }
}

$huboAlgo = (bool) array_filter($porPastoral) || $sinPastoral;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Directorio telefónico — <?= e(APP_CORTO) ?></title>
<link rel="stylesheet" href="<?= e(url_activo('assets/css/directorio_imprimir.css')) ?>?v=<?= e(APP_VERSION) ?>">
</head>
<body>

<div class="di-barra no-imprimir">
    <a href="<?= e(url_admin('personas')) ?>" class="di-volver">&larr; Volver a Equipo pastoral</a>
    <button type="button" class="di-boton-imprimir" onclick="window.print()">Imprimir / Guardar como PDF</button>
</div>

<div class="di-encabezado">
    <p class="di-parroquia"><?= e(APP_NAME) ?></p>
    <p class="di-subtitulo">Directorio telefónico</p>
</div>

<?php if (!$huboAlgo): ?>
<p class="di-vacio">
    Todavía no hay nadie marcado para el directorio. Márcalo desde su ficha, en
    Equipo pastoral, con la casilla «Incluir en el directorio telefónico».
</p>
<?php else: ?>

<?php
/* Párroco, vicario y personal de oficina no se organizan por pastoral, así
   que van siempre arriba de todo, antes que cualquier Comisión — ya en el
   orden fijo que arma PersonaModel::paraDirectorioPorPastoral() (párroco,
   vicario, personal de oficina y hasta el final los laicos sueltos). */
if ($sinPastoral):
?>
<div class="di-comision">
    <h2 class="di-comision-titulo">Sin pastoral asignada</h2>
    <?php directorio_imprimir_lista($sinPastoral, 1); ?>
</div>
<?php endif; ?>

<?php foreach ($agrupado['comisiones'] as $grupo): ?>
<?php
    $padreId          = (int) $grupo['padre']['id'];
    $personasDirectas = $porPastoral[$padreId] ?? [];
    $hijasConGente     = array_filter($grupo['hijas'], static fn (array $h): bool => !empty($porPastoral[(int) $h['id']]));
    if (!$personasDirectas && !$hijasConGente) { continue; }
?>
<div class="di-comision">
    <h2 class="di-comision-titulo"><?= e($grupo['padre']['nombre']) ?></h2>
    <?php directorio_imprimir_lista($personasDirectas, 1); ?>
    <?php foreach ($hijasConGente as $hija): ?>
    <div class="di-dimension">
        <h3 class="di-dimension-titulo"><?= e($hija['nombre']) ?></h3>
        <?php directorio_imprimir_lista($porPastoral[(int) $hija['id']], 2); ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>

<?php foreach ($agrupado['sueltas'] as $suelta): ?>
<?php $personasSuelta = $porPastoral[(int) $suelta['id']] ?? []; ?>
<?php if (!$personasSuelta) { continue; } ?>
<div class="di-comision">
    <h2 class="di-comision-titulo"><?= e($suelta['nombre']) ?></h2>
    <?php directorio_imprimir_lista($personasSuelta, 1); ?>
</div>
<?php endforeach; ?>

<?php endif; ?>

</body>
</html>
