<?php
/**
 * La lista de eventos en hoja aparte, para imprimirla o guardarla como PDF
 * con el diálogo de impresión del navegador. Página independiente (sin
 * layout_admin.php), como turnos_imprimir.php o mapa_imprimir.php: el
 * proyecto no lleva librería de PDF. Ver EventoController::imprimir().
 *
 * Va en orden cronológico y partida por meses, que es como se lee una agenda
 * en papel. Trae todas las páginas de la lista, con sus mismos filtros.
 *
 * Variables:
 *   $eventos   filas de EventoModel::listarTodos()
 *   $filtros   string[], los filtros puestos, en palabras
 *   $esPdf     bool, si se pidió como PDF (muestra el aviso de cómo guardarlo)
 *   $archivo   string, nombre sugerido —el navegador lo toma del <title>—
 *   $urlVolver string
 */
$MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio',
          'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$DIAS  = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
$MESES_CORTOS = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
$publicados = count(array_filter($eventos, static fn ($ev) => (int) $ev['publicado'] === 1));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($archivo) ?></title>
<style>
    * { box-sizing: border-box; }
    body { font-family: Arial, "Segoe UI", sans-serif; color: #000; margin: 0; padding: 1.25rem; background: #f4f6f9; font-size: 10pt; }
    .li-barra { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
    .li-volver { color: #1e4d8b; text-decoration: none; font-size: .9rem; }
    .li-volver:hover { text-decoration: underline; }
    .li-boton { background: #1e4d8b; color: #fff; border: 0; border-radius: .4rem; padding: .55rem 1.1rem; font-size: .9rem; cursor: pointer; }
    .li-aviso { background: #fff8e1; border: 1px solid #f0c36d; border-radius: .4rem; padding: .6rem .9rem; margin-bottom: 1rem; font-size: .9rem; }
    .li-hoja { background: #fff; max-width: 1100px; margin: 0 auto; padding: 1.5rem; box-shadow: 0 1px 4px rgba(0,0,0,.1); }
    .li-parroquia { margin: 0; color: #555; font-size: 9pt; text-transform: uppercase; letter-spacing: .05em; }
    h1 { margin: .15rem 0 .35rem; font-size: 16pt; color: #16233a; }
    .li-datos { margin: 0 0 1rem; color: #444; font-size: 9pt; }
    table { width: 100%; border-collapse: collapse; }
    thead th { text-align: left; background: #1e4d8b; color: #fff; padding: .35rem .45rem; font-size: 9pt; }
    td { padding: .3rem .45rem; border-bottom: 1px solid #ddd; vertical-align: top; }
    tr.li-mes th { text-align: left; background: #e8eef7; color: #16233a; padding: .4rem .45rem; font-size: 10pt; border-bottom: 1px solid #b9c7dd; }
    .li-fecha { white-space: nowrap; width: 7.5rem; }
    .li-hora { white-space: nowrap; width: 6.5rem; color: #333; }
    .li-titulo { font-weight: bold; }
    .li-punto { display: inline-block; width: .55rem; height: .55rem; border-radius: 50%; margin-right: .35rem; vertical-align: middle; }
    .li-sub { color: #555; font-size: 8.5pt; }
    .li-estado { white-space: nowrap; width: 5.5rem; font-size: 8.5pt; }
    .li-borrador { color: #8a5a00; font-style: italic; }
    .li-vacio { text-align: center; color: #666; padding: 2rem; }
    thead { display: table-header-group; }   /* el encabezado se repite en cada hoja */
    tr { page-break-inside: avoid; break-inside: avoid; }
    @media print {
        .no-imprimir { display: none !important; }
        body { padding: 0; background: #fff; }
        .li-hoja { box-shadow: none; padding: 0; max-width: none; }
        thead th, tr.li-mes th, .li-punto { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        @page { size: portrait; margin: 12mm 10mm; }
    }
</style>
</head>
<body>

<div class="li-barra no-imprimir">
    <a href="<?= e($urlVolver) ?>" class="li-volver">&larr; Volver a la lista</a>
    <button type="button" class="li-boton" onclick="window.print()">
        <?= $esPdf ? 'Guardar como PDF' : 'Imprimir' ?>
    </button>
</div>
<?php if ($esPdf): ?>
<div class="li-aviso no-imprimir">
    En la ventana que se abre, en <strong>Destino</strong> elige <strong>«Guardar como PDF»</strong>
    y pulsa <strong>Guardar</strong>. El archivo se llamará <strong><?= e($archivo) ?>.pdf</strong>.
</div>
<?php endif; ?>

<div class="li-hoja">
    <p class="li-parroquia"><?= e(APP_NAME) ?></p>
    <h1>Eventos</h1>
    <p class="li-datos">
        <?= count($eventos) === 1 ? '1 evento' : count($eventos) . ' eventos' ?>
        <?php if ($eventos): ?>(<?= $publicados ?> publicados, <?= count($eventos) - $publicados ?> en borrador)<?php endif; ?>
        <?php if ($filtros): ?> · <?= e(implode(' · ', $filtros)) ?><?php endif; ?>
        · Impreso el <?= e(fecha_larga(date('Y-m-d'))) ?>
    </p>

    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Hora</th>
                <th>Evento</th>
                <th>Lugar</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$eventos): ?>
            <tr><td colspan="5" class="li-vacio">Ningún evento coincide con el filtro.</td></tr>
            <?php endif; ?>
            <?php $mesActual = ''; ?>
            <?php foreach ($eventos as $ev): ?>
            <?php
            $ini = (string) $ev['fecha_inicio'];
            $fin = $ev['fecha_fin'] ? (string) $ev['fecha_fin'] : '';
            $ts  = strtotime($ini);
            $mes = substr($ini, 0, 7);

            $fecha = $DIAS[(int) date('w', $ts)] . ' ' . (int) date('j', $ts);
            if ($fin !== '' && substr($fin, 0, 10) !== substr($ini, 0, 10)) {
                $tsFin  = strtotime($fin);
                $fecha .= ' – ' . (substr($fin, 0, 7) === $mes
                    ? (int) date('j', $tsFin)
                    : (int) date('j', $tsFin) . ' ' . $MESES_CORTOS[(int) date('n', $tsFin) - 1]);
            }
            $hora = $ev['todo_el_dia'] ? 'Todo el día' : hora_corta(substr($ini, 11));
            // 00:00 y 23:59 no son una hora de término: es «hasta que acabe el
            // día», como quedaron los eventos de varios días de la agenda importada.
            if (!$ev['todo_el_dia'] && $fin !== '' && !in_array(substr($fin, 11, 5), ['00:00', '23:59'], true)
                && substr($fin, 11) !== substr($ini, 11)) {
                $hora .= ' – ' . hora_corta(substr($fin, 11));
            }
            // Muchos eventos tienen por lugar el nombre de la misma sede: no se repite.
            $lugar = array_unique(array_filter([trim((string) $ev['lugar']), trim((string) $ev['centro_nombre'])]));
            ?>
            <?php if ($mes !== $mesActual): $mesActual = $mes; ?>
            <tr class="li-mes"><th colspan="5"><?= e($MESES[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts)) ?></th></tr>
            <?php endif; ?>
            <tr>
                <td class="li-fecha"><?= e($fecha) ?></td>
                <td class="li-hora"><?= e($hora) ?></td>
                <td>
                    <span class="li-punto" style="background:<?= e($ev['color'] ?: '#1e4d8b') ?>"></span><span class="li-titulo"><?= e($ev['titulo']) ?></span>
                    <div class="li-sub"><?= e($ev['pastoral_nombre'] ?? 'General de la parroquia') ?></div>
                </td>
                <td class="li-sub"><?= e($lugar ? implode(' · ', $lugar) : '—') ?></td>
                <td class="li-estado <?= $ev['publicado'] ? '' : 'li-borrador' ?>"><?= $ev['publicado'] ? 'Publicado' : 'Borrador' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
// Se abrió desde el botón de la lista: el diálogo de impresión sale solo. Si
// se cierra, el botón de arriba lo vuelve a abrir.
window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });
</script>
</body>
</html>
