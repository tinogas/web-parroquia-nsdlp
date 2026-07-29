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
    <?php if (Auth::tienePermiso('mesc.crear')): ?>
    <a href="<?= e(url_admin('mesc', 'turno_nuevo')) ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nuevo turno
    </a>
    <?php endif; ?>
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
                            <a href="<?= e(url_admin('mesc', 'turno_editar', ['id' => $turno['id']])) ?>"
                               class="evento-punto d-block" style="background:#1e4d8b"
                               title="<?= e($turno['descripcion'] . ($turno['ministros_nombres'] ? ' — ' . $turno['ministros_nombres'] : ' — sin ministros asignados')) ?>">
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
