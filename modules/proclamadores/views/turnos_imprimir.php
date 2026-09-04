<?php
/**
 * Calendario de turnos de Proclamadores en hoja aparte: página independiente
 * (sin layout_admin.php), pensada para abrirse en pestaña nueva e imprimirse,
 * guardarse como PDF o capturarse como imagen con el propio navegador. Ver
 * ProclamadoresController::turnosImprimir() y docs/ARQUITECTURA.md.
 *
 * Comparte hoja de estilo con la de MESC (assets/css/turnos_imprimir.css): es
 * el mismo formato de calendario, y lo único que cambia es a quién nombra cada
 * casilla. Por eso las clases de los nombres son `.ti-nombres`/`.ti-sin-nombres`
 * y no `.ti-ministros`.
 *
 * Los nombres van escritos en la casilla, no en el `title`: en papel no hay
 * dónde pasar el ratón.
 *
 * Variables esperadas:
 *   $nombreMes  string, "Septiembre 2026"
 *   $semanas    array, cuadrícula de ProclamadoresController::construirCalendarioTurnos()
 *   $urlVolver  string
 */
if (!function_exists('proclamadores_imprimir_texto_legible')) {
    /** Blanco o negro según qué tan oscuro sea el color litúrgico del turno. */
    function proclamadores_imprimir_texto_legible(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) { return '#000'; }
        [$r, $g, $b] = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
        return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 140 ? '#000' : '#fff';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Calendario de proclamadores, <?= e($nombreMes) ?> — <?= e(APP_CORTO) ?></title>
<link rel="stylesheet" href="<?= e(url_activo('assets/css/turnos_imprimir.css')) ?>?v=<?= e(APP_VERSION) ?>">
</head>
<body>

<div class="ti-barra no-imprimir">
    <a href="<?= e($urlVolver) ?>" class="ti-volver">&larr; Volver al calendario</a>
    <button type="button" class="ti-boton-imprimir" onclick="window.print()">Imprimir / Guardar como PDF</button>
</div>

<div class="ti-hoja">

    <p class="ti-parroquia"><?= e(APP_NAME) ?></p>
    <div class="ti-titulo">
        <span class="ti-titulo-izq">Calendario de proclamadores</span>
        <span class="ti-titulo-der"><?= e($nombreMes) ?></span>
    </div>

    <table class="ti-calendario">
        <colgroup>
            <?php /* El domingo concentra las misas, igual que en MESC: sin esta
                     diferencia de ancho se desborda y el resto de la hoja queda
                     medio vacía. */ ?>
            <col class="ti-col-domingo">
            <col span="6">
        </colgroup>
        <thead>
            <tr>
                <?php foreach (['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'] as $dia): ?>
                <th><?= $dia ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($semanas as $semana): ?>
            <tr>
                <?php foreach ($semana as $celda): ?>
                <td class="<?= $celda ? '' : 'ti-vacia' ?>">
                    <?php if ($celda): ?>
                    <div class="ti-dia"><?= (int) $celda['dia'] ?></div>
                    <div class="ti-turnos">
                        <?php foreach ($celda['turnos'] as $turno): ?>
                        <?php
                        $fondo  = $turno['color_hex'] ?: '';
                        $estilo = $fondo !== ''
                            ? 'background:' . e($fondo) . ';color:' . proclamadores_imprimir_texto_legible($fondo)
                            : '';
                        ?>
                        <div class="ti-turno" style="<?= $estilo ?>">
                            <span class="ti-cabecera-turno">
                                <?php /* Hora en 24h y no con hora_corta(): "19:00" cabe en una línea
                                         donde "7:00 p. m." se parte en dos. */ ?>
                                <?= e($turno['descripcion']) ?><?php if ($turno['hora']): ?>
                                <?= e(substr((string) $turno['hora'], 0, 5)) ?><?php endif; ?>
                            </span>
                            <?php if ($turno['proclamadores_nombres']): ?>
                            <span class="ti-nombres"><?= e($turno['proclamadores_nombres']) ?></span>
                            <?php else: ?>
                            <span class="ti-sin-nombres">Sin asignar</span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="ti-aviso">
        <p class="ti-aviso-titulo">==== Aviso ====</p>
        <p class="ti-aviso-texto">
            En caso de necesidad, consiga entre los compañeros un Cambio de Turno, y dé aviso a la
            Coordinación para evitar malentendidos. Gracias por su colaboración.
        </p>
    </div>

</div>

</body>
</html>
