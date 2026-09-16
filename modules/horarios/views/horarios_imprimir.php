<?php
/**
 * Horario completo en hoja aparte, agrupado por sede: página independiente
 * (sin layout_admin.php), pensada para abrirse en pestaña nueva e imprimirse
 * o guardarse como PDF con el diálogo del propio navegador — igual que
 * PersonaController::directorioImprimir() y PastoralController::mapaImprimir().
 * Ver HorarioController::horariosImprimir().
 *
 * Un solo encabezado (logo y nombre de la parroquia) arriba de todo el
 * documento, no uno por sede: las sedes van una tras otra debajo, no cada
 * una en su propia hoja. Sin coro ni encargado —eso es del módulo Coros—.
 *
 * Dentro de cada sede, agrupado además por tipo (Misa, Confesión, Adoración
 * eucarística, Oficina parroquial, Otro) para no mezclarlos en una sola
 * tabla larga.
 *
 * El lugar (la capilla o el sitio dentro de la sede) se muestra una sola vez,
 * bajo el nombre de la sede, en vez de repetirse en cada renglón — salvo el
 * renglón cuyo lugar no coincida con ese principal (o una sede sin centro
 * asignado, donde no hay uno solo que asumir), que sí lo muestra: ver
 * HorarioModel::todosPorCentro().
 *
 * Variables esperadas:
 *   $porCentro  array, HorarioModel::todosPorCentro().
 */
if (!function_exists('horarios_imprimir_tabla')) {
    function horarios_imprimir_tabla(array $horarios, ?string $lugarPrincipal, bool $conSede): void
    {
        ?>
        <table class="hi-tabla">
            <tbody>
                <?php foreach ($horarios as $horario): ?>
                <?php
                $mostrarLugar = !$conSede || (!empty($horario['lugar']) && $horario['lugar'] !== $lugarPrincipal);
                ?>
                <tr>
                    <td class="hi-dia text-capitalize"><?= e(nombre_dia((int) $horario['dia_semana'])) ?></td>
                    <td class="hi-hora">
                        <?= e(hora_corta($horario['hora'])) ?>
                        <?php if ($horario['hora_fin']): ?> – <?= e(hora_corta($horario['hora_fin'])) ?><?php endif; ?>
                    </td>
                    <td class="hi-nota">
                        <?php if ($mostrarLugar && $horario['lugar']): ?>
                        <span class="hi-lugar-fila"><?= e($horario['lugar']) ?></span><?= $horario['nota'] ? ' · ' : '' ?>
                        <?php endif; ?>
                        <?= e((string) $horario['nota']) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
}

$huboAlgo = (bool) $porCentro;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Horarios — <?= e(APP_CORTO) ?></title>
<link rel="stylesheet" href="<?= e(url_activo('assets/css/horarios_imprimir.css')) ?>?v=<?= e(APP_VERSION) ?>">
</head>
<body>

<div class="hi-barra no-imprimir">
    <a href="<?= e(url_admin('horarios')) ?>" class="hi-volver">&larr; Volver a Horarios</a>
    <button type="button" class="hi-boton-imprimir" onclick="window.print()">Imprimir / Guardar como PDF</button>
</div>

<div class="hi-encabezado">
    <?php if (Config::tiene('logo')): ?>
    <img src="<?= e(url_activo(Config::get('logo'))) ?>" alt="" class="hi-logo">
    <?php endif; ?>
    <p class="hi-parroquia"><?= e(APP_NAME) ?></p>
    <p class="hi-subtitulo">Horarios</p>
</div>

<?php if (!$huboAlgo): ?>
<p class="hi-vacio">Todavía no hay horarios vigentes que mostrar.</p>
<?php else: ?>

<?php foreach ($porCentro as $grupo): ?>
<?php
    $porTipo = [];
    foreach ($grupo['horarios'] as $horario) {
        $porTipo[$horario['tipo']][] = $horario;
    }
?>
<div class="hi-sede">
    <h2 class="hi-nombre-sede"><?= e($grupo['nombre']) ?></h2>
    <?php if ($grupo['lugar_principal']): ?>
    <p class="hi-lugar-sede"><?= e($grupo['lugar_principal']) ?></p>
    <?php endif; ?>

    <?php foreach (HorarioModel::TIPOS as $tipo => [$nombreTipo, ]): ?>
    <?php if (empty($porTipo[$tipo])) { continue; } ?>
    <h3 class="hi-tipo"><?= e($nombreTipo) ?></h3>
    <?php horarios_imprimir_tabla($porTipo[$tipo], $grupo['lugar_principal'], $grupo['con_sede']); ?>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>

<?php endif; ?>

</body>
</html>
