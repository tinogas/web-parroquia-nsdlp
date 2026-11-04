<?php
/**
 * Cabecera común del calendario interno —selector de vista, navegación de
 * periodo y contador— más la plantilla que toque.
 *
 * Es la contraparte de eventos/publico/calendario.php, con dos diferencias:
 * las URLs son del panel y aquí no hay AJAX, así que cada enlace recarga.
 *
 * Lo usan la Agenda y el calendario de Eventos (EventoController::calendario()).
 * $calRuta dice a dónde llevan el número del día y el nombre del mes:
 * [módulo, acción]. Sin ella, a la Agenda, que es de donde salió.
 */
$calRuta = $calRuta ?? ['agenda', ''];

// Para saltar a otro mes o año sin dar vuelta mes por mes con las flechas.
// Calendario::fechaSolicitada() ya entiende ?anio=&mes= sin ?fecha=.
$calAncla  = new DateTimeImmutable($fecha);
$calAnioHoy = (int) date('Y');
$calAnios  = range(min($calAnioHoy, (int) $calAncla->format('Y')) - 2,
                   max($calAnioHoy, (int) $calAncla->format('Y')) + 3);
$calMeses  = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio',
              'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
?>
<div class="card border-0 shadow-sm calendario-interno">
    <div class="card-body p-3 p-md-4">

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 calendario-controles">
            <div class="btn-group btn-group-sm" role="group" aria-label="Cómo ver la agenda">
                <?php foreach ($vistas as $clave => $etiqueta): ?>
                <a href="<?= e($urlsVista[$clave]) ?>"
                   class="btn <?= $vista === $clave ? 'btn-primary' : 'btn-outline-secondary' ?>"
                   <?= $vista === $clave ? 'aria-current="true"' : '' ?>>
                    <?= e($etiqueta) ?>
                </a>
                <?php endforeach; ?>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2">
                <form method="GET" action="<?= e(url_admin($calRuta[0], $calRuta[1])) ?>"
                      class="d-flex align-items-center gap-1" aria-label="Ir a un mes">
                    <?php if (!URLS_AMIGABLES): ?>
                    <input type="hidden" name="area" value="admin">
                    <input type="hidden" name="modulo" value="<?= e($calRuta[0]) ?>">
                    <?php if ($calRuta[1] !== ''): ?>
                    <input type="hidden" name="accion" value="<?= e($calRuta[1]) ?>">
                    <?php endif; ?>
                    <?php endif; ?>
                    <input type="hidden" name="vista" value="<?= e($vista) ?>">
                    <?php foreach ($comunes as $clave => $valor): ?>
                    <input type="hidden" name="<?= e($clave) ?>" value="<?= e((string) $valor) ?>">
                    <?php endforeach; ?>
                    <?php if ($vista !== 'anio'): /* La de año ya muestra los doce meses. */ ?>
                    <select name="mes" class="form-select form-select-sm w-auto" aria-label="Mes"
                            onchange="this.form.submit()">
                        <?php foreach ($calMeses as $i => $nombre): ?>
                        <option value="<?= $i + 1 ?>" <?= (int) $calAncla->format('n') === $i + 1 ? 'selected' : '' ?>><?= e($nombre) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                    <select name="anio" class="form-select form-select-sm w-auto" aria-label="Año"
                            onchange="this.form.submit()">
                        <?php foreach ($calAnios as $unAnio): ?>
                        <option value="<?= $unAnio ?>" <?= (int) $calAncla->format('Y') === $unAnio ? 'selected' : '' ?>><?= $unAnio ?></option>
                        <?php endforeach; ?>
                    </select>
                    <noscript><button type="submit" class="btn btn-sm btn-outline-secondary">Ir</button></noscript>
                </form>
                <a href="<?= e($urlAnterior) ?>" class="btn btn-sm btn-outline-secondary"
                   aria-label="Periodo anterior"><i class="bi bi-chevron-left"></i></a>
                <?php if (!$incluyeHoy): ?>
                <a href="<?= e($urlHoy) ?>" class="btn btn-sm btn-outline-secondary">Hoy</a>
                <?php endif; ?>
                <a href="<?= e($urlSiguiente) ?>" class="btn btn-sm btn-outline-secondary"
                   aria-label="Periodo siguiente"><i class="bi bi-chevron-right"></i></a>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()"
                        title="Imprimir o guardar como PDF lo que se ve">
                    <i class="bi bi-printer me-1"></i>Imprimir
                </button>
            </div>
        </div>

        <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-2 mb-3">
            <h2 class="h5 fw-bold mb-0"><?= e($periodo) ?></h2>
            <?php /* Solo en papel: los filtros de arriba no se imprimen, pero
                     sin ellos no se sabe qué recorte del calendario es. Lo
                     llena el script de abajo justo antes de imprimir. */ ?>
            <span class="calendario-filtros-impresos small text-muted d-none d-print-inline"></span>
            <span class="small text-muted">
                <?= $total === 1 ? '1 registro' : (int) $total . ' registros' ?>
            </span>
        </div>

        <?php require BASE_PATH . '/modules/agenda/views/vista_' . $vista . '.php'; ?>
    </div>
</div>
<script>
(function () {
    'use strict';
    var destino = document.querySelector('.calendario-filtros-impresos');
    if (!destino) { return; }
    window.addEventListener('beforeprint', function () {
        var partes = [];
        // Estado (Todos / Publicados / Borradores) del calendario de Eventos.
        document.querySelectorAll('[aria-label="Filtrar por estado"] .btn-primary').forEach(function (b) {
            if (b.textContent.trim() !== 'Todos') { partes.push(b.textContent.trim()); }
        });
        // Pastoral y sede: los selects de filtro fuera de la tarjeta del calendario.
        document.querySelectorAll('select[name="pastoral"], select[name="centro"]').forEach(function (s) {
            if (!s.closest('.calendario-interno') && s.value !== '') {
                partes.push(s.options[s.selectedIndex].text.trim());
            }
        });
        destino.textContent = partes.length ? partes.join(' · ') : 'Todas las pastorales y sedes';
    });
})();
</script>
