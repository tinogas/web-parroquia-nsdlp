<?php
if (!function_exists('proclamadores_texto_legible')) {
    /** Blanco o negro según qué tan clara es la casilla, para que el texto del turno siempre se lea. */
    function proclamadores_texto_legible(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) { return '#fff'; }
        [$r, $g, $b] = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
        $luminancia = 0.299 * $r + 0.587 * $g + 0.114 * $b;
        return $luminancia > 150 ? '#212529' : '#fff';
    }
}
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1">Calendario de turnos</h1>
        <p class="text-muted mb-0 small">Quién proclama la Palabra en cada misa.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php $navActiva = 'turnos'; require __DIR__ . '/_nav.php'; ?>
        <a href="<?= e($urlImprimir) ?>" target="_blank" class="btn btn-outline-secondary">
            <i class="bi bi-printer me-1"></i>Imprimir
        </a>
        <?php if (Auth::tienePermiso('proclamadores.crear')): ?>
        <a href="<?= e(url_admin('proclamadores', 'turno_nuevo')) ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Nuevo turno
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-3 p-md-4">
        <?php /* Filtro por persona: deja en el calendario solo sus turnos, y la
                 hoja de imprimir hereda el filtro, para darle a cada quien la
                 suya. Viaja en la URL igual que el mes. */ ?>
        <form method="GET" action="<?= e(url_admin('proclamadores', 'turnos')) ?>"
              class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <?php if (!URLS_AMIGABLES): ?>
            <input type="hidden" name="area" value="admin">
            <input type="hidden" name="modulo" value="proclamadores">
            <input type="hidden" name="accion" value="turnos">
            <?php endif; ?>
            <input type="hidden" name="anio" value="<?= (int) $anio ?>">
            <input type="hidden" name="mes" value="<?= (int) $mes ?>">
            <label for="filtro-proclamador" class="small fw-semibold mb-0">Ver los turnos de</label>
            <select name="proclamador" id="filtro-proclamador" class="form-select form-select-sm w-auto"
                    onchange="this.form.submit()">
                <option value="">Todos los proclamadores</option>
                <?php foreach ($proclamadores as $persona): ?>
                <option value="<?= (int) $persona['id'] ?>"
                        <?= $proclamador && (int) $proclamador['id'] === (int) $persona['id'] ? 'selected' : '' ?>>
                    <?= e($persona['nombre']) ?><?= $persona['activo'] ? '' : ' (inactivo)' ?>
                </option>
                <?php endforeach; ?>
            </select>
            <noscript><button type="submit" class="btn btn-sm btn-outline-secondary">Ver</button></noscript>
            <?php if ($proclamador): ?>
            <span class="small text-muted">
                <?= $totalTurnos === 1 ? '1 turno' : (int) $totalTurnos . ' turnos' ?> este mes
            </span>
            <a href="<?= e($urlSinFiltro) ?>" class="small">Ver a todos</a>
            <?php endif; ?>
        </form>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="<?= e($urlMesAnterior) ?>" class="btn btn-sm btn-outline-secondary" aria-label="Mes anterior">
                <i class="bi bi-chevron-left"></i>
            </a>
            <h2 class="h5 fw-bold mb-0"><?= e($nombreMes) ?></h2>
            <a href="<?= e($urlMesSiguiente) ?>" class="btn btn-sm btn-outline-secondary" aria-label="Mes siguiente">
                <i class="bi bi-chevron-right"></i>
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered calendario-tabla mb-0">
                <thead>
                    <tr class="text-center small text-uppercase">
                        <?php foreach (['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'] as $dia): ?>
                        <th><?= $dia ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($semanas as $semana): ?>
                    <tr>
                        <?php foreach ($semana as $celda): ?>
                        <td class="<?= $celda && $celda['hoy'] ? 'dia-hoy' : '' ?>">
                            <?php if ($celda): ?>
                            <div class="numero-dia"><?= $celda['dia'] ?></div>
                            <?php foreach ($celda['turnos'] as $turno): ?>
                            <?php
                            $fondo   = $turno['color_hex'] ?: '#1e4d8b';
                            $titulo  = $turno['descripcion']
                                     . ($turno['color_nombre'] ? ' — color ' . $turno['color_nombre'] : '')
                                     . ($turno['proclamadores_nombres'] ? ' — ' . $turno['proclamadores_nombres'] : ' — sin proclamadores asignados');
                            $etiqueta = e($turno['hora'] ? hora_corta($turno['hora']) . ' ' : '') . e($turno['descripcion']);
                            ?>
                            <?php if (Auth::tienePermiso('proclamadores.editar')): ?>
                            <a href="<?= e(url_admin('proclamadores', 'turno_editar', ['id' => $turno['id']])) ?>"
                               class="evento-punto d-block" style="background:<?= e($fondo) ?>;color:<?= proclamadores_texto_legible($fondo) ?>"
                               title="<?= e($titulo) ?>"><?= $etiqueta ?></a>
                            <?php else: ?>
                            <span class="evento-punto d-block" style="background:<?= e($fondo) ?>;color:<?= proclamadores_texto_legible($fondo) ?>"
                                  title="<?= e($titulo) ?>"><?= $etiqueta ?></span>
                            <?php endif; ?>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
