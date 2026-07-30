<?php
if (!function_exists('mesc_texto_legible')) {
    /** Blanco o negro según qué tan clara es la casilla, para que el texto del turno siempre se lea. */
    function mesc_texto_legible(string $hex): string
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
        <nav aria-label="Ubicación">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item"><a href="<?= e(url_admin('mesc')) ?>" class="text-decoration-none">MESC</a></li>
                <li class="breadcrumb-item active" aria-current="page">Turnos</li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0">Calendario de turnos</h1>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= e(url_admin('mesc', 'colores')) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-palette me-1"></i>Colores litúrgicos
        </a>
        <?php if (Auth::tienePermiso('mesc.crear')): ?>
        <a href="<?= e(url_admin('mesc', 'turno_nuevo')) ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Nuevo turno
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="alert alert-warning small d-flex gap-2 align-items-start">
    <i class="bi bi-exclamation-triangle-fill mt-1"></i>
    <div>
        En caso de necesidad, consiga entre los compañeros un <strong>cambio de turno</strong>, y dé aviso a la
        Coordinación para evitar malentendidos. Gracias por su colaboración.
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-3 p-md-4">
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
                                     . ($turno['ministros_nombres'] ? ' — ' . $turno['ministros_nombres'] : ' — sin ministros asignados');
                            $etiqueta = e($turno['hora'] ? hora_corta($turno['hora']) . ' ' : '') . e($turno['descripcion']);
                            ?>
                            <?php if (Auth::tienePermiso('mesc.editar')): ?>
                            <a href="<?= e(url_admin('mesc', 'turno_editar', ['id' => $turno['id']])) ?>"
                               class="evento-punto d-block" style="background:<?= e($fondo) ?>;color:<?= mesc_texto_legible($fondo) ?>"
                               title="<?= e($titulo) ?>"><?= $etiqueta ?></a>
                            <?php else: ?>
                            <span class="evento-punto d-block" style="background:<?= e($fondo) ?>;color:<?= mesc_texto_legible($fondo) ?>"
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
