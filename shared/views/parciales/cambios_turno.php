<?php
/**
 * Pantalla «Cambios de turno», común a MESC y Proclamadores. La incluyen
 * modules/mesc/views/cambios.php y modules/proclamadores/views/cambios.php;
 * las variables las arma CambiosTurnoAcciones::cambios().
 *
 *   $cfg             vocabulario y módulo (ver cambiosConfig())
 *   $filtro          anio, mes (0 = todo el año), dia (0 = todo el mes), desde, hasta, etiqueta
 *   $aniosFiltro     años que ofrece el filtro
 *   $cambios         cambios aprobados que tocan algún turno del filtro
 *   $pendientes      solicitudes por resolver (solo a quien coordina)
 *   $misSolicitudes  lo que pidió esta cuenta (solo al laico)
 *   $miPersona       su ficha en el módulo, o null (solo al laico)
 *   $puedeRegistrar  coordina: registra directo y resuelve solicitudes
 *   $puedeSolicitar  laico: lo que envía queda como solicitud
 *   $personas        todas las personas del módulo (id, nombre, activo)
 *   $turnoPedido     turno a preseleccionar, o null
 *   $urlTurnosDia    JSON con los turnos de una fecha (CambiosTurnoAcciones::cambiosTurnos())
 */
$modulo        = $cfg['modulo'];
$puedeDeshacer = Auth::tienePermiso($modulo . '.eliminar');
$verFormulario = $puedeRegistrar || ($puedeSolicitar && $miPersona);
$meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
          'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

require_once __DIR__ . '/cambios_turno_funciones.php';

// El filtro viaja de vuelta en cada formulario POST, para regresar a la misma vista.
$camposFiltro = '<input type="hidden" name="anio" value="' . (int) $filtro['anio'] . '">'
              . '<input type="hidden" name="mes" value="' . (int) $filtro['mes'] . '">'
              . '<input type="hidden" name="dia" value="' . (int) $filtro['dia'] . '">'
              . '<input type="hidden" name="mostrar" value="' . e($filtro['mostrar']) . '">';
$esHistorial = $filtro['mostrar'] !== 'vigentes';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <nav aria-label="Ubicación">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item"><a href="<?= e(url_admin($modulo)) ?>" class="text-decoration-none"><?= e($cfg['titulo']) ?></a></li>
                <li class="breadcrumb-item"><a href="<?= e($urlCalendario) ?>" class="text-decoration-none">Turnos</a></li>
                <li class="breadcrumb-item active" aria-current="page">Cambios</li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0">Cambios de turno</h1>
        <p class="text-muted small mb-0">
            Permutas y coberturas acordadas después de armar el rol. El rol original no se borra:
            el calendario muestra quién sirve y quién estaba originalmente.
        </p>
    </div>
    <a href="<?= e($urlCalendario) ?>" class="btn btn-outline-secondary">
        <i class="bi bi-calendar3 me-1"></i>Volver al calendario
    </a>
</div>

<?php /* ── Solicitudes por aprobar: solo a quien coordina ── */ ?>
<?php if ($puedeRegistrar && $pendientes): ?>
<div class="card border-0 shadow-sm mb-4 border-start border-4 border-warning">
    <div class="card-body p-4">
        <h2 class="h6 fw-bold mb-3">
            <i class="bi bi-hourglass-split me-1 text-warning"></i>Solicitudes por aprobar
            <span class="badge rounded-pill bg-danger"><?= count($pendientes) ?></span>
        </h2>
        <?php foreach ($pendientes as $i => $sol): ?>
        <?php $solVencida = CambiosTurno::yaPaso($sol); ?>
        <div class="<?= $i ? 'border-top pt-3 mt-3' : '' ?>">
            <div class="d-flex flex-wrap justify-content-between gap-2 mb-1">
                <div>
                    <span class="badge text-bg-primary me-1"><?= e(CambiosTurno::TIPOS[$sol['tipo']] ?? $sol['tipo']) ?></span>
                    <span class="fw-semibold"><?= e($sol['autor'] ?? 'Cuenta eliminada') ?></span>
                    <?php if (!empty($sol['por_enlace'])): ?>
                    <span class="badge text-bg-light border fw-normal" title="La pidió sin cuenta, desde el enlace que le mandó la coordinación"><i class="bi bi-link-45deg"></i> enlace personal</span>
                    <?php endif; ?>
                    <span class="text-muted small">lo pidió el <?= e(fecha_larga(substr((string) $sol['created_at'], 0, 10))) ?></span>
                    <?php if ($solVencida): ?>
                    <span class="badge text-bg-dark ms-1"><i class="bi bi-clock-history me-1"></i>El turno ya pasó</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="small mb-2"><?php cambios_turno_detalle($sol); ?></div>
            <div class="d-flex flex-wrap gap-2 align-items-start">
                <?php /* Lo que ya pasó no se mueve: una solicitud vencida solo se
                         puede rechazar, para cerrarla y que quien la pidió se entere. */ ?>
                <?php if (!$solVencida): ?>
                <form method="POST" action="<?= e(url_post('admin', $modulo, 'solicitud_aprobar')) ?>">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="id" value="<?= (int) $sol['id'] ?>">
                    <?= $camposFiltro ?>
                    <button type="submit" class="btn btn-sm btn-success">
                        <i class="bi bi-check-lg me-1"></i>Aprobar
                    </button>
                </form>
                <?php endif; ?>
                <form method="POST" action="<?= e(url_post('admin', $modulo, 'solicitud_rechazar')) ?>" class="d-flex gap-2 flex-grow-1" style="max-width:32rem">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="id" value="<?= (int) $sol['id'] ?>">
                    <?= $camposFiltro ?>
                    <input type="text" name="motivo" class="form-control form-control-sm" maxlength="255"
                           value="<?= $solVencida ? 'El turno ya pasó' : '' ?>"
                           placeholder="Motivo del rechazo (opcional)" aria-label="Motivo del rechazo">
                    <button type="submit" class="btn btn-sm btn-outline-danger text-nowrap">
                        <i class="bi bi-x-lg me-1"></i>Rechazar
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php /* ── Formulario: registrar (coordinación) o solicitar (laico) ── */ ?>
<?php if ($puedeSolicitar && !$miPersona): ?>
<div class="alert alert-info small">
    <i class="bi bi-info-circle me-1"></i>
    Para solicitar un cambio de turno, tu cuenta tiene que estar ligada a tu ficha de <?= e($cfg['singular']) ?>.
    Pídeselo a la coordinación de <?= e($cfg['titulo']) ?>.
</div>
<?php endif; ?>

<?php if ($verFormulario): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h2 class="h6 fw-bold mb-1"><?= $puedeRegistrar ? 'Registrar un cambio' : 'Solicitar un cambio' ?></h2>
        <?php if ($puedeSolicitar): ?>
        <p class="small text-muted mb-3">
            Tu solicitud le llega a la coordinación de <?= e($cfg['titulo']) ?> para que la apruebe; mientras tanto el
            calendario no cambia. Debe incluirte a ti, <strong><?= e($miPersona['nombre']) ?></strong>: que dejes un turno,
            cubras a alguien o te sumes a uno.
        </p>
        <?php else: ?>
        <p class="small text-muted mb-3">Se aplica de inmediato en el calendario.</p>
        <?php endif; ?>

        <?php
        $ctf_accion    = url_post('admin', $modulo, 'cambio_guardar');
        $ctf_ocultos   = '<input type="hidden" name="_csrf" value="' . e($csrf) . '">';
        $ctf_urlDia    = $urlTurnosDia;
        $ctf_solicitar = !$puedeRegistrar;
        $ctf_yo        = $puedeRegistrar ? null : $miPersona;
        require __DIR__ . '/cambios_turno_formulario.php';
        ?>
    </div>
</div>
<?php endif; ?>

<?php /* ── Mis solicitudes: solo al laico ── */ ?>
<?php if ($puedeSolicitar && $misSolicitudes): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h2 class="h6 fw-bold mb-3">Mis solicitudes</h2>
        <?php foreach ($misSolicitudes as $i => $sol): ?>
        <?php [$claseEstado, $textoEstado] = cambios_turno_estado($sol); ?>
        <div class="d-flex flex-wrap justify-content-between gap-2 <?= $i ? 'border-top pt-3 mt-3' : '' ?>">
            <div class="small">
                <span class="badge <?= $claseEstado ?> me-1"><?= e($textoEstado) ?></span>
                <span class="text-muted"><?= e(CambiosTurno::TIPOS[$sol['tipo']] ?? $sol['tipo']) ?></span>
                <div class="mt-1"><?php cambios_turno_detalle($sol); ?></div>
                <div class="text-muted mt-1"><?php cambios_turno_bitacora($sol, true); ?></div>
            </div>
            <?php if ($sol['estado'] === 'pendiente' && CambiosTurno::yaPaso($sol)): ?>
            <span class="small text-muted"><i class="bi bi-clock-history me-1"></i>El turno ya pasó</span>
            <?php elseif ($sol['estado'] === 'pendiente'): ?>
            <form method="POST" action="<?= e(url_post('admin', $modulo, 'solicitud_cancelar')) ?>"
                  onsubmit="return confirm('¿Retirar esta solicitud?')">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="id" value="<?= (int) $sol['id'] ?>">
                <?= $camposFiltro ?>
                <button type="submit" class="btn btn-sm btn-outline-secondary">Retirar</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php /* ── Historial de cambios (coordinación) o cambios vigentes (laico), con filtro por año, mes y día ── */ ?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-3 p-md-4">
        <form method="GET" action="<?= e(url_admin($modulo, 'cambios')) ?>" id="filtro-cambios"
              class="d-flex flex-wrap align-items-end gap-2 mb-3">
            <?php if (!URLS_AMIGABLES): ?>
            <input type="hidden" name="area" value="admin">
            <input type="hidden" name="modulo" value="<?= e($modulo) ?>">
            <input type="hidden" name="accion" value="cambios">
            <?php endif; ?>
            <?php if ($esHistorial && $filtro['mostrar'] !== 'todos'): ?>
            <input type="hidden" name="mostrar" value="<?= e($filtro['mostrar']) ?>">
            <?php endif; ?>
            <div>
                <label for="filtro-anio" class="form-label small fw-semibold mb-1">Año</label>
                <select name="anio" id="filtro-anio" class="form-select form-select-sm">
                    <?php foreach (array_unique(array_merge($aniosFiltro, [(int) $filtro['anio']])) as $a): ?>
                    <option value="<?= (int) $a ?>" <?= (int) $a === (int) $filtro['anio'] ? 'selected' : '' ?>><?= (int) $a ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filtro-mes" class="form-label small fw-semibold mb-1">Mes</label>
                <select name="mes" id="filtro-mes" class="form-select form-select-sm">
                    <option value="0" <?= $filtro['mes'] === 0 ? 'selected' : '' ?>>Todo el año</option>
                    <?php foreach ($meses as $n => $nombre): ?>
                    <option value="<?= $n + 1 ?>" <?= $filtro['mes'] === $n + 1 ? 'selected' : '' ?>><?= e(ucfirst($nombre)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filtro-dia" class="form-label small fw-semibold mb-1">Día</label>
                <select name="dia" id="filtro-dia" class="form-select form-select-sm" <?= $filtro['mes'] === 0 ? 'disabled' : '' ?>>
                    <option value="0">Todo el mes</option>
                    <?php for ($d = 1; $d <= 31; $d++): ?>
                    <option value="<?= $d ?>" <?= $filtro['dia'] === $d ? 'selected' : '' ?>><?= $d ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-funnel me-1"></i>Filtrar
            </button>
        </form>

        <h2 class="h6 fw-bold mb-3">
            <?= $esHistorial ? 'Historial de cambios de' : 'Cambios de' ?> <?= e($filtro['etiqueta']) ?>
            <span class="text-muted fw-normal">(<?= count($cambios) ?>)</span>
        </h2>

        <?php if ($esHistorial && $conteo): ?>
        <?php /* Pestañas del historial, con cuántos hay de cada clase en las
                 fechas filtradas: de un vistazo, cuántas solicitudes se
                 aprobaron y cuántas se rechazaron. */ ?>
        <ul class="nav nav-pills small mb-3 gap-1">
            <?php foreach (CambiosTurno::HISTORIAL as $clave => $etiqueta): ?>
            <li class="nav-item">
                <a class="nav-link py-1 px-2 <?= $filtro['mostrar'] === $clave ? 'active' : '' ?>"
                   href="<?= e(url_admin($modulo, 'cambios', array_filter([
                       'anio' => $filtro['anio'], 'mes' => $filtro['mes'], 'dia' => $filtro['dia'],
                       'mostrar' => $clave === 'todos' ? null : $clave,
                   ], static fn ($v): bool => $v !== null))) ?>">
                    <?= e($etiqueta) ?>
                    <span class="badge rounded-pill <?= $filtro['mostrar'] === $clave ? 'text-bg-light' : 'text-bg-secondary' ?>"><?= (int) $conteo[$clave] ?></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <?php if (!$cambios): ?>
        <p class="text-muted text-center small my-4">
            <?= $esHistorial && $filtro['mostrar'] !== 'todos'
                ? 'No hay ' . e(mb_strtolower(CambiosTurno::HISTORIAL[$filtro['mostrar']])) . ' en los turnos de ' . e($filtro['etiqueta']) . '.'
                : 'No hay cambios registrados en los turnos de ' . e($filtro['etiqueta']) . '.' ?>
        </p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr class="small text-muted">
                        <th>Tipo</th>
                        <th>Qué cambió</th>
                        <?php if ($esHistorial): ?><th>Estado</th><?php endif; ?>
                        <th><?= $esHistorial ? 'Historial' : 'Registrado' ?></th>
                        <?php if ($puedeDeshacer): ?><th></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cambios as $cambio): ?>
                    <?php
                    $aplicado = $cambio['estado'] === 'aprobado';
                    $vencido  = CambiosTurno::yaPaso($cambio);
                    [$claseEstado, $textoEstado] = cambios_turno_estado($cambio);
                    ?>
                    <tr class="<?= $aplicado ? '' : 'table-light' ?>">
                        <td class="text-nowrap">
                            <span class="badge text-bg-<?= $cambio['tipo'] === 'permuta' ? 'primary' : ($cambio['tipo'] === 'sustitucion' ? 'warning' : 'success') ?>">
                                <?= e(CambiosTurno::TIPOS[$cambio['tipo']] ?? $cambio['tipo']) ?>
                            </span>
                        </td>
                        <td class="small <?= $aplicado ? '' : 'opacity-75' ?>">
                            <?php cambios_turno_detalle($cambio); ?>
                            <?php if (!$aplicado): ?>
                            <div class="text-muted mt-1"><i class="bi bi-info-circle me-1"></i><?= $cambio['estado'] === 'deshecho'
                                ? 'Ya no cuenta: el turno volvió a como estaba.'
                                : 'No se aplicó al calendario.' ?></div>
                            <?php endif; ?>
                        </td>
                        <?php if ($esHistorial): ?>
                        <td class="text-nowrap"><span class="badge <?= $claseEstado ?>"><?= e($textoEstado) ?></span></td>
                        <?php endif; ?>
                        <td class="small text-muted"><?php cambios_turno_bitacora($cambio); ?></td>
                        <?php if ($puedeDeshacer): ?>
                        <td class="text-end">
                            <?php if ($aplicado && $vencido): ?>
                            <span class="text-muted small text-nowrap" title="Lo que ya pasó no se mueve">
                                <i class="bi bi-lock"></i> Ya pasó
                            </span>
                            <?php elseif ($aplicado): ?>
                            <?php /* Deshacer pide un motivo (opcional): queda en la bitácora
                                     y, si fue una solicitud, en la campanita de quien la pidió. */ ?>
                            <form method="POST" action="<?= e(url_post('admin', $modulo, 'cambio_eliminar')) ?>" data-deshacer>
                                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                <input type="hidden" name="id" value="<?= (int) $cambio['id'] ?>">
                                <input type="hidden" name="motivo" value="">
                                <?= $camposFiltro ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Deshacer">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    // Deshacer pide confirmación y un motivo opcional, que queda en la
    // bitácora y en la campanita de quien lo había pedido.
    document.querySelectorAll('form[data-deshacer]').forEach(function (f) {
        f.addEventListener('submit', function (ev) {
            var motivo = prompt('¿Deshacer este cambio? El turno vuelve a como estaba antes de él.\n\nMotivo (opcional):', '');
            if (motivo === null) { ev.preventDefault(); return; }
            f.querySelector('input[name="motivo"]').value = motivo;
        });
    });

    // Filtro de la lista: el día solo tiene sentido dentro de un mes, y
    // cambiar cualquiera de los tres ya filtra.
    var filtro = document.getElementById('filtro-cambios');
    if (filtro) {
        var mes = document.getElementById('filtro-mes');
        var dia = document.getElementById('filtro-dia');
        mes.addEventListener('change', function () {
            dia.disabled = mes.value === '0';
            if (dia.disabled) { dia.value = '0'; }
        });
        filtro.querySelectorAll('select').forEach(function (s) {
            s.addEventListener('change', function () { filtro.submit(); });
        });
    }
})();
</script>
