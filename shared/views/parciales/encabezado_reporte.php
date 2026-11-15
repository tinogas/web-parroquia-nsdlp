<?php
/**
 * Encabezado de los reportes impresos del panel: el logo negro de la
 * parroquia y su nombre. Va en negro —y no el logo de configuración, que es
 * blanco para la barra oscura del sitio— porque en papel blanco el blanco no
 * se ve, y en negro sale igual en cualquier impresora.
 *
 * Lo usan la hoja de la lista de eventos (eventos/lista_imprimir.php), los
 * calendarios internos (agenda/calendario.php) y Propuestas. En las dos
 * últimas es una página del panel que se imprime tal cual, así que el
 * encabezado solo aparece en papel ($rep_soloImpresion = true).
 *
 * Variables:
 *   $rep_titulo         string, el nombre del reporte («Eventos», «Agenda»…)
 *   $rep_soloImpresion  bool, oculto en pantalla (por omisión, no)
 */
$rep_soloImpresion = $rep_soloImpresion ?? false;
?>
<div class="encabezado-reporte <?= $rep_soloImpresion ? 'd-none d-print-flex' : '' ?>">
    <img src="<?= e(url_activo_versionado('assets/img/logo-nsdlp-negro.png')) ?>"
         alt="Parroquia Nuestra Señora de la Paz" class="encabezado-reporte-logo">
    <div>
        <p class="encabezado-reporte-parroquia"><?= e(APP_NAME) ?></p>
        <?php if (!empty($rep_titulo)): ?>
        <p class="encabezado-reporte-titulo"><?= e($rep_titulo) ?></p>
        <?php endif; ?>
    </div>
</div>
