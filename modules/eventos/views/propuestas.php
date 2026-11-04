<?php
/**
 * Propuestas del año: una fila por pastoral —agrupadas bajo su Comisión—, una
 * columna por mes, y en cada casilla las fechas que esa pastoral tiene
 * capturadas. Ver EventoController::propuestas().
 *
 * Por omisión muestra el año que viene y solo los borradores, que es como las
 * pastorales proponen: capturan el evento sin publicarlo.
 */
$pestana   = 'propuestas';
$subtitulo = $global
    ? 'Las fechas que cada pastoral tiene capturadas para el año, para revisarlas juntas antes de publicarlas.'
    : 'Las fechas que tus pastorales tienen capturadas para el año.';
require __DIR__ . '/_pestanas.php';

$MESES_CORTOS = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
$ESTADOS = ['borradores' => 'Solo borradores', 'todos' => 'Borradores y publicados', 'publicados' => 'Solo publicados'];
$comunes = ($estado !== 'borradores' ? ['estado' => $estado] : [])
         + ($filtroCentro !== '' ? ['centro' => $filtroCentro] : []);

/**
 * Dibuja la fila de una pastoral; $nivel 0 = Comisión o suelta, 1 = dimensión.
 * $grupoConFechas: en una Comisión, si alguna de sus dimensiones tiene fechas.
 * Entonces su fila no se esconde con «Ocultar las pastorales sin fechas»,
 * porque es el encabezado que dice de quién son las de abajo.
 */
$fila = static function (?array $pastoral, string $nombre, int $nivel, bool $esComision, bool $grupoConFechas = false) use ($celdas): string {
    $id     = $pastoral ? (int) $pastoral['id'] : 0;
    $porMes = $celdas[$id] ?? [];
    $cuenta = array_sum(array_map('count', $porMes));

    // Una pastoral inactiva sin nada en el año no aporta nada a esta tabla.
    if ($pastoral && empty($pastoral['activa']) && $cuenta === 0) {
        return '';
    }

    ob_start();
    ?>
    <tr class="<?= $esComision ? 'propuestas-comision' : '' ?> <?= $cuenta === 0 && !$grupoConFechas ? 'propuestas-vacia' : '' ?>">
        <th scope="row" class="propuestas-pastoral <?= $nivel ? 'ps-4' : '' ?>">
            <?php if ($nivel): ?><i class="bi bi-arrow-return-right text-muted me-1"></i><?php endif; ?>
            <?= e($nombre) ?>
            <?php if ($pastoral && empty($pastoral['activa'])): ?>
            <span class="badge bg-secondary-subtle text-secondary-emphasis fw-normal">inactiva</span>
            <?php endif; ?>
        </th>
        <?php for ($mes = 1; $mes <= 12; $mes++): ?>
        <td>
            <?php foreach ($porMes[$mes] ?? [] as $ev): ?>
            <?php
            $ini  = substr((string) $ev['fecha_inicio'], 0, 10);
            $fin  = $ev['fecha_fin'] ? substr((string) $ev['fecha_fin'], 0, 10) : $ini;
            $dias = (int) substr($ini, 8, 2) . ($fin !== $ini && substr($fin, 0, 7) === substr($ini, 0, 7)
                    ? '–' . (int) substr($fin, 8, 2) : ($fin !== $ini ? '+' : ''));
            $detalle = $ev['titulo'] . ' · ' . fecha_larga($ini)
                     . ($fin !== $ini ? ' al ' . fecha_larga($fin) : '')
                     . ($ev['todo_el_dia'] ? '' : ' · ' . hora_corta(substr((string) $ev['fecha_inicio'], 11)))
                     . ($ev['lugar'] ? ' · ' . $ev['lugar'] : '')
                     . ($ev['centro_nombre'] ? ' · ' . $ev['centro_nombre'] : '')
                     . ($ev['publicado'] ? ' · publicado' : ' · borrador');
            $marca = $ev['url'] ? 'a' : 'span';
            ?>
            <<?= $marca ?> <?= $ev['url'] ? 'href="' . e($ev['url']) . '"' : '' ?>
                class="propuesta <?= $ev['publicado'] ? '' : 'es-borrador' ?>"
                style="--color:<?= e($ev['color'] ?: '#1e4d8b') ?>" title="<?= e($detalle) ?>">
                <span class="propuesta-dia"><?= e($dias) ?></span>
                <span class="propuesta-titulo"><?= e($ev['titulo']) ?></span>
            </<?= $marca ?>>
            <?php endforeach; ?>
        </td>
        <?php endfor; ?>
        <td class="text-center fw-semibold <?= $cuenta ? '' : 'text-muted' ?>"><?= $cuenta ?: '—' ?></td>
    </tr>
    <?php
    return (string) ob_get_clean();
};
?>
<div class="propuestas-filtros d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
    <div>
        <div class="small fw-semibold mb-1">Año</div>
        <div class="btn-group btn-group-sm" role="group" aria-label="Año">
            <?php foreach ($anios as $unAnio): ?>
            <a href="<?= e(url_admin('eventos', 'propuestas', ['anio' => $unAnio] + $comunes)) ?>"
               class="btn <?= $anio === $unAnio ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= (int) $unAnio ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <form method="GET" action="<?= e(url_admin('eventos', 'propuestas')) ?>" class="row g-2 align-items-end">
        <?php if (!URLS_AMIGABLES): ?>
        <input type="hidden" name="area" value="admin">
        <input type="hidden" name="modulo" value="eventos">
        <input type="hidden" name="accion" value="propuestas">
        <?php endif; ?>
        <input type="hidden" name="anio" value="<?= (int) $anio ?>">
        <div class="col-auto">
            <label for="estado" class="form-label small fw-semibold mb-1">Mostrar</label>
            <select name="estado" id="estado" class="form-select form-select-sm" onchange="this.form.submit()">
                <?php foreach ($ESTADOS as $clave => $etiqueta): ?>
                <option value="<?= e($clave) ?>" <?= $estado === $clave ? 'selected' : '' ?>><?= e($etiqueta) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if (count($centros) > 1): ?>
        <div class="col-auto">
            <label for="centro" class="form-label small fw-semibold mb-1">Sede</label>
            <select name="centro" id="centro" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Todas las sedes</option>
                <?php foreach ($centros as $unCentro): ?>
                <option value="<?= (int) $unCentro['id'] ?>" <?= $filtroCentro === (string) $unCentro['id'] ? 'selected' : '' ?>>
                    <?= e($unCentro['nombre']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="col-auto">
            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Filtrar</button>
        </div>
        <div class="col-auto">
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                <i class="bi bi-printer me-1"></i>Imprimir
            </button>
        </div>
    </form>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-4 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
            <div class="small text-muted"><?= ['borradores' => 'Fechas propuestas (borrador)', 'todos' => 'Fechas capturadas', 'publicados' => 'Eventos publicados'][$estado] ?> en <?= (int) $anio ?></div>
            <div class="h3 fw-bold mb-0"><?= (int) $total ?></div>
        </div></div>
    </div>
    <div class="col-sm-4 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
            <div class="small text-muted">Pastorales con fechas</div>
            <div class="h3 fw-bold mb-0"><?= (int) $conPropuestas ?></div>
        </div></div>
    </div>
    <?php if ($global): ?>
    <div class="col-sm-4 col-lg-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
            <div class="small text-muted">Pastorales sin ninguna fecha</div>
            <div class="h3 fw-bold mb-1 <?= $sinPropuestas ? 'text-warning-emphasis' : 'text-success' ?>"><?= count($sinPropuestas) ?></div>
            <?php if ($sinPropuestas): ?>
            <details class="small">
                <summary class="text-primary" style="cursor:pointer">Ver cuáles</summary>
                <?= e(implode(', ', array_column($sinPropuestas, 'nombre'))) ?>.
            </details>
            <?php endif; ?>
        </div></div>
    </div>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-2 p-md-3">
        <p class="small text-muted mb-2">
            <span class="propuesta d-inline-flex" style="--color:#1e4d8b"><span class="propuesta-dia">12</span> Publicado</span>
            <span class="propuesta es-borrador d-inline-flex" style="--color:#1e4d8b"><span class="propuesta-dia">12</span> Borrador</span>
            <span class="ms-1">El número es el día; «12–14» dura varios días, y «28+» sigue en el mes siguiente.
                Al pasar el ratón se ve el detalle<?= Auth::tienePermiso('eventos.editar') ? ', y con un clic se abre para editarlo' : '' ?>.</span>
        </p>
        <div class="form-check form-switch small mb-2">
            <input class="form-check-input" type="checkbox" role="switch" id="soloConFechas">
            <label class="form-check-label" for="soloConFechas">Ocultar las pastorales sin fechas</label>
        </div>
        <div class="table-responsive" style="max-height: 75vh">
            <table class="table table-bordered propuestas-tabla mb-0">
                <thead>
                    <tr>
                        <th>Pastoral</th>
                        <?php foreach ($MESES_CORTOS as $corto): ?>
                        <th><?= e($corto) ?></th>
                        <?php endforeach; ?>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($global): ?>
                    <?= $fila(null, 'General de la parroquia', 0, false) ?>
                    <?php endif; ?>

                    <?php foreach ($agrupado['comisiones'] as $grupo): ?>
                    <?php
                    $grupoConFechas = false;
                    foreach ($grupo['hijas'] as $hija) {
                        $grupoConFechas = $grupoConFechas || !empty($celdas[(int) $hija['id']]);
                    }
                    ?>
                    <?= $fila($grupo['padre'], $grupo['padre']['nombre'], 0, true, $grupoConFechas) ?>
                    <?php foreach ($grupo['hijas'] as $hija): ?>
                    <?= $fila($hija, $hija['nombre'], 1, false) ?>
                    <?php endforeach; ?>
                    <?php endforeach; ?>

                    <?php if ($agrupado['sueltas']): ?>
                    <tr class="propuestas-grupo"><th colspan="14">Otras pastorales</th></tr>
                    <?php foreach ($agrupado['sueltas'] as $suelta): ?>
                    <?= $fila($suelta, $suelta['nombre'], 0, false) ?>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';
    // Filtro solo de pantalla: esconder las filas vacías no cambia lo que se
    // consulta. Se recuerda en el navegador, por comodidad.
    var casilla = document.getElementById('soloConFechas');
    var tabla   = document.querySelector('.propuestas-tabla');
    if (!casilla || !tabla) { return; }
    try { casilla.checked = localStorage.getItem('propuestas:soloConFechas') === '1'; } catch (e) { /* sin almacenamiento */ }
    var aplicar = function () {
        tabla.classList.toggle('solo-con-fechas', casilla.checked);
        try { localStorage.setItem('propuestas:soloConFechas', casilla.checked ? '1' : '0'); } catch (e) { /* idem */ }
    };
    casilla.addEventListener('change', aplicar);
    aplicar();
})();
</script>
