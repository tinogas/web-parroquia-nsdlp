<?php
if (!function_exists('lector_texto_legible')) {
    /** Blanco o negro según qué tan clara es la casilla, para que el texto del turno siempre se lea. */
    function lector_texto_legible(string $hex): string
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
        <h1 class="h4 fw-bold mb-1">Calendario de lectores</h1>
        <p class="text-muted mb-0 small">Quién proclama la Palabra en cada misa.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= e(url_admin('lector', 'lectores')) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-people me-1"></i>Lectores
        </a>
        <?php if (Auth::tienePermiso('lector.crear')): ?>
        <a href="<?= e(url_admin('lector', 'turno_nuevo')) ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Nuevo turno
        </a>
        <?php endif; ?>
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
                            <?php $fondo = $turno['color_hex'] ?: '#1e4d8b'; ?>
                            <a href="<?= e(url_admin('lector', 'turno_editar', ['id' => $turno['id']])) ?>"
                               class="evento-punto d-block" style="background:<?= e($fondo) ?>;color:<?= lector_texto_legible($fondo) ?>"
                               title="<?= e($turno['descripcion']
                                          . ($turno['color_nombre'] ? ' — color ' . $turno['color_nombre'] : '')
                                          . ($turno['lectores_nombres'] ? ' — ' . $turno['lectores_nombres'] : ' — sin lectores asignados')) ?>">
                                <?= e($turno['hora'] ? hora_corta($turno['hora']) . ' ' : '') ?><?= e($turno['descripcion']) ?>
                            </a>
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
