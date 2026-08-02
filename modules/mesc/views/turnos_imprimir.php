<?php
/**
 * Calendario de turnos MESC en hoja aparte: página independiente (sin
 * layout_admin.php), pensada para abrirse en pestaña nueva e imprimirse,
 * guardarse como PDF o capturarse como imagen con el propio navegador. Ver
 * MescController::turnosImprimir() y docs/ARQUITECTURA.md.
 *
 * El diseño replica el calendario que la coordinación de MESC venía armando a
 * mano fuera del sistema (rol_ministros.jpeg): cabeceras DOM–SAB en negro,
 * banda gris con el número de día, un bloque por turno con el color litúrgico
 * de fondo, y los ministros en mayúsculas debajo de la hora. La columna del
 * domingo va más ancha porque es el día con más misas y más ministros.
 *
 * Los ministros van escritos en la casilla, no en el `title`: en papel no hay
 * dónde pasar el ratón. Por eso `mesc_ministros.nombre` es un nombre corto
 * —«Aimeé», «Tino»— y no el nombre completo de su ficha, que no cabría.
 *
 * Variables esperadas:
 *   $nombreMes  string, "Agosto 2026"
 *   $semanas    array, cuadrícula de MescController::construirCalendarioTurnos()
 *   $urlVolver  string
 */
if (!function_exists('mesc_imprimir_texto_legible')) {
    /** Blanco o negro según qué tan oscuro sea el color litúrgico del turno. */
    function mesc_imprimir_texto_legible(string $hex): string
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
<title>Calendario MESC, <?= e($nombreMes) ?> — <?= e(APP_CORTO) ?></title>
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
        <span class="ti-titulo-izq">Calendario MESC</span>
        <span class="ti-titulo-der"><?= e($nombreMes) ?></span>
    </div>

    <table class="ti-calendario">
        <colgroup>
            <?php /* El domingo lleva cuatro misas con dos ministros cada una; los
                     demás días, una. Sin esta diferencia de ancho el domingo se
                     desborda y el resto de la hoja queda medio vacía. */ ?>
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
                        $fondo = $turno['color_hex'] ?: '';
                        $estilo = $fondo !== ''
                            ? 'background:' . e($fondo) . ';color:' . mesc_imprimir_texto_legible($fondo)
                            : '';
                        ?>
                        <div class="ti-turno" style="<?= $estilo ?>">
                            <span class="ti-cabecera-turno">
                                <?php /* Hora en 24h y no con hora_corta(): "19:00" cabe en una línea
                                         donde "7:00 p. m." se parte en dos, y es como está escrito el
                                         calendario que esta hoja reemplaza. */ ?>
                                <?= e($turno['descripcion']) ?><?php if ($turno['hora']): ?>
                                <?= e(substr((string) $turno['hora'], 0, 5)) ?><?php endif; ?>
                            </span>
                            <?php if ($turno['ministros_nombres']): ?>
                            <span class="ti-ministros"><?= e($turno['ministros_nombres']) ?></span>
                            <?php else: ?>
                            <span class="ti-sin-ministros">Sin asignar</span>
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
