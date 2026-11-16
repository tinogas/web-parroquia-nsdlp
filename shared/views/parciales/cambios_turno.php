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

if (!function_exists('cambios_turno_detalle')) {
    /** Qué cambia en cada turno: «dom 6 dic, Misa 09:00: ~~Eugenia, Sergio~~ → Hiram, Sandra». */
    function cambios_turno_detalle(array $cambio): void
    {
        foreach (CambiosTurno::porTurno($cambio['movimientos']) as $g) {
            echo '<div><span class="fw-semibold">' . e(CambiosTurno::turnoCorto($g['turno'])) . ':</span> ';
            if ($g['salen']) {
                echo '<span class="text-decoration-line-through text-muted">' . e(implode(', ', $g['salen'])) . '</span> ';
            }
            if ($g['salen'] && $g['entran']) {
                echo '<i class="bi bi-arrow-right mx-1"></i>';
            } elseif ($g['entran']) {
                echo '<i class="bi bi-plus-lg me-1"></i>';
            } else {
                echo '<span class="text-muted">(sale)</span>';
            }
            echo e(implode(', ', $g['entran'])) . '</div>';
        }
        if (!empty($cambio['notas'])) {
            echo '<div class="text-muted fst-italic mt-1">' . e($cambio['notas']) . '</div>';
        }
    }
}

if (!function_exists('cambios_turno_estado')) {
    /**
     * Etiqueta del estado de un cambio: [clase del badge, texto]. Una
     * solicitud se reconoce por resuelto_at (ver CambiosTurno::HISTORIAL); un
     * cambio deshecho dice si había sido registro directo o solicitud.
     */
    function cambios_turno_estado(array $c): array
    {
        $solicitud = $c['resuelto_at'] !== null;
        return match ($c['estado']) {
            'pendiente' => ['text-bg-warning', 'Pendiente'],
            'rechazado' => ['text-bg-danger', 'Solicitud rechazada'],
            'retirado'  => ['text-bg-light border', 'Solicitud retirada'],
            'deshecho'  => ['text-bg-dark', $solicitud ? 'Solicitud deshecha' : 'Registro deshecho'],
            default     => $solicitud ? ['text-bg-success', 'Solicitud aprobada'] : ['text-bg-secondary', 'Registrado directo'],
        };
    }

    /** La bitácora de un cambio, renglón por renglón: quién lo pidió o registró, quién lo resolvió, quién lo deshizo o retiró, cuándo y por qué. */
    function cambios_turno_bitacora(array $c): void
    {
        $cuando = static fn (?string $f): string => $f ? ' · ' . e(fecha_larga(substr($f, 0, 10))) : '';
        $quien  = static fn (?string $n): string => e($n ?? 'cuenta eliminada');
        $linea  = static function (string $icono, string $texto): void {
            echo '<div><i class="bi ' . $icono . ' me-1"></i>' . $texto . '</div>';
        };
        $motivo = static function (?string $m, string $clase = 'text-danger'): void {
            if ($m) {
                echo '<div class="' . $clase . '"><i class="bi bi-chat-left-text me-1"></i>' . e($m) . '</div>';
            }
        };

        $solicitud = $c['resuelto_at'] !== null || in_array($c['estado'], ['pendiente', 'retirado'], true);
        if ($solicitud) {
            $linea('bi-send', 'Pidió ' . $quien($c['autor'] ?? null) . $cuando($c['created_at']));
        } else {
            $linea('bi-pencil-square', 'Registró ' . $quien($c['autor'] ?? null) . $cuando($c['created_at']));
        }
        if ($c['resuelto_at'] !== null) {
            $rechazada = $c['estado'] === 'rechazado';
            $linea($rechazada ? 'bi-x-circle text-danger' : 'bi-check-circle text-success',
                ($rechazada ? 'Rechazó ' : 'Aprobó ') . $quien($c['resolvio'] ?? null) . $cuando($c['resuelto_at']));
            if ($rechazada) {
                $motivo($c['motivo_rechazo']);
            }
        }
        if ($c['estado'] === 'deshecho') {
            $linea('bi-arrow-counterclockwise text-dark', 'Deshizo ' . $quien($c['cancelo'] ?? null) . $cuando($c['cancelado_at']));
            $motivo($c['motivo_cancelacion'], 'text-dark');
        } elseif ($c['estado'] === 'retirado') {
            $linea('bi-box-arrow-left', 'Retiró ' . $quien($c['cancelo'] ?? null) . $cuando($c['cancelado_at']));
        }
    }
}

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

        <form method="POST" accept-charset="UTF-8" action="<?= e(url_post('admin', $modulo, 'cambio_guardar')) ?>"
              id="form-cambio" data-url-dia="<?= e($urlTurnosDia) ?>">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

            <div class="mb-3">
                <div class="btn-group flex-wrap" role="group" aria-label="Tipo de cambio">
                    <input type="radio" class="btn-check" name="tipo" id="tipo-permuta" value="permuta" checked>
                    <label class="btn btn-outline-primary" for="tipo-permuta"><i class="bi bi-arrow-left-right me-1"></i>Permuta</label>
                    <input type="radio" class="btn-check" name="tipo" id="tipo-sustitucion" value="sustitucion">
                    <label class="btn btn-outline-primary" for="tipo-sustitucion"><i class="bi bi-person-check me-1"></i>Cubre a otro</label>
                    <input type="radio" class="btn-check" name="tipo" id="tipo-agregado" value="agregado">
                    <label class="btn btn-outline-primary" for="tipo-agregado"><i class="bi bi-person-plus me-1"></i>Se agrega</label>
                </div>
                <div class="form-text" data-solo="permuta">
                    Los marcados de cada turno toman el turno de los del otro: uno por uno, dos por dos, o el turno
                    completo por el otro completo con «Todo el turno». Pueden ser de la misma fecha y hora o de fechas distintas.
                </div>
                <div class="form-text d-none" data-solo="sustitucion">
                    Alguien toma el lugar de otro en un turno, sin devolución.
                </div>
                <div class="form-text d-none" data-solo="agregado">
                    Alguien más se suma a un turno; nadie sale.
                </div>
            </div>

            <?php /* Cada turno se busca por su fecha: se elige el día y solo se
                     ofrecen los turnos de ese día, en vez de recorrer una lista
                     de meses enteros. */ ?>
            <div class="row g-3 mb-3">
                <div class="col-sm-5 col-md-3">
                    <label for="fecha_a" class="form-label fw-semibold">
                        <span data-solo="permuta">Fecha del primer turno</span>
                        <span class="d-none" data-solo="sustitucion agregado">Fecha</span>
                    </label>
                    <input type="date" id="fecha_a" class="form-control" min="<?= e($hoy) ?>"
                           value="<?= e($turnoPedido['fecha'] ?? '') ?>">
                </div>
                <div class="col-sm-7 col-md-4">
                    <label for="turno_a" class="form-label fw-semibold">Turno</label>
                    <select name="turno_a" id="turno_a" class="form-select" required
                            data-seleccionar="<?= (int) ($turnoPedido['id'] ?? 0) ?>">
                        <option value="">Elige primero la fecha</option>
                    </select>
                </div>
                <div class="col-md-5 d-none" data-solo="sustitucion">
                    <label for="sale_a" class="form-label fw-semibold">A quién cubre</label>
                    <select name="sale_a" id="sale_a" class="form-select" data-rol-de="turno_a" data-mostrar="dentro">
                        <option value="">Elige…</option>
                        <?php foreach ($personas as $p): ?>
                        <option value="<?= (int) $p['id'] ?>"><?= e($p['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5" data-solo="permuta">
                    <?php $lado = 'a'; require __DIR__ . '/cambios_turno_casillas.php'; ?>
                </div>
            </div>

            <div class="row g-3 mb-3" data-solo="permuta">
                <div class="col-sm-5 col-md-3">
                    <label for="fecha_b" class="form-label fw-semibold">Fecha del segundo turno</label>
                    <input type="date" id="fecha_b" class="form-control" min="<?= e($hoy) ?>">
                </div>
                <div class="col-sm-7 col-md-4">
                    <label for="turno_b" class="form-label fw-semibold">Turno</label>
                    <select name="turno_b" id="turno_b" class="form-select">
                        <option value="">Elige primero la fecha</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <?php $lado = 'b'; require __DIR__ . '/cambios_turno_casillas.php'; ?>
                </div>
            </div>

            <div class="row g-3 mb-3 d-none" data-solo="sustitucion agregado">
                <div class="col-md-7">
                    <label for="entra" class="form-label fw-semibold">Quién entra</label>
                    <select name="entra" id="entra" class="form-select" data-rol-de="turno_a" data-mostrar="fuera">
                        <option value="">Elige…</option>
                        <?php foreach ($personas as $p): ?>
                        <?php if (!$p['activo']) { continue; } ?>
                        <option value="<?= (int) $p['id'] ?>"><?= e($p['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label for="notas" class="form-label fw-semibold">Nota <span class="text-muted fw-normal">(opcional)</span></label>
                <input type="text" name="notas" id="notas" class="form-control" maxlength="255"
                       placeholder="<?= $puedeRegistrar ? 'Ej. Lo avisó Zulema por WhatsApp' : 'Ej. Ya lo platiqué con Sandra y está de acuerdo' ?>">
            </div>

            <button type="submit" class="btn btn-primary">
                <?php if ($puedeRegistrar): ?>
                <i class="bi bi-check-lg me-1"></i>Registrar cambio
                <?php else: ?>
                <i class="bi bi-send me-1"></i>Enviar solicitud
                <?php endif; ?>
            </button>
        </form>
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
                <div class="text-muted mt-1"><?php cambios_turno_bitacora($sol + ['autor' => 'tú']); ?></div>
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

    var form = document.getElementById('form-cambio');
    if (!form) { return; }
    var roles  = {};
    var urlDia = form.dataset.urlDia;

    // Muestra solo lo que aplica al tipo elegido. Se oculta por completo, no
    // solo se deshabilita: un campo que no aplica no debe estorbar.
    function aplicarTipo() {
        var tipo = form.querySelector('input[name="tipo"]:checked').value;
        form.querySelectorAll('[data-solo]').forEach(function (el) {
            el.classList.toggle('d-none', el.dataset.solo.split(' ').indexOf(tipo) === -1);
        });
        document.getElementById('turno_b').required = tipo === 'permuta';
        document.getElementById('sale_a').required  = tipo === 'sustitucion';
        document.getElementById('entra').required   = tipo !== 'permuta';
    }

    // «Quiénes dejan el turno» lista solo a quienes están en él; «quién
    // entra», solo a quienes no. El servidor vuelve a revisarlo todo.
    function filtrarPersonas() {
        form.querySelectorAll('[data-rol-de]').forEach(function (cont) {
            var turno  = document.getElementById(cont.dataset.rolDe).value;
            var rol    = (roles[turno] || []).map(String);
            var dentro = cont.dataset.mostrar === 'dentro';
            var visto  = function (id) {
                var esta = rol.indexOf(id) !== -1;
                return turno === '' ? !dentro : (dentro ? esta : !esta);
            };
            if (cont.tagName === 'SELECT') {
                cont.querySelectorAll('option[value]:not([value=""])').forEach(function (op) {
                    var ver = visto(op.value);
                    op.hidden = !ver;
                    op.disabled = !ver;
                    if (!ver && op.selected) { cont.value = ''; }
                });
                return;
            }
            // Casillas de la permuta: quien deja de verse se desmarca, para no
            // mandar a alguien que ya no está a la vista.
            var visibles = 0;
            cont.querySelectorAll('[data-persona]').forEach(function (fila) {
                var ver = visto(fila.dataset.persona);
                fila.classList.toggle('d-none', !ver);
                if (!ver) { fila.querySelector('input').checked = false; } else { visibles++; }
            });
            var vacio = cont.querySelector('[data-vacio]');
            vacio.classList.toggle('d-none', visibles > 0);
            vacio.textContent = turno === '' ? 'Elige primero el turno.' : 'Nadie sirve en ese turno.';
        });
    }

    // Turnos del día elegido, con su rol real, pedidos al servidor.
    function cargarTurnos(lado, seleccionar) {
        var fecha = document.getElementById('fecha_' + lado).value;
        var sel   = document.getElementById('turno_' + lado);
        var poner = function (texto) { sel.innerHTML = ''; sel.add(new Option(texto, '')); };
        if (!fecha) { poner('Elige primero la fecha'); filtrarPersonas(); return; }
        poner('Buscando turnos…');
        fetch(urlDia + (urlDia.indexOf('?') === -1 ? '?' : '&') + 'fecha=' + encodeURIComponent(fecha),
              { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (datos) {
                if (!datos.turnos.length) { poner(datos.aviso || 'No hay turnos ese día'); }
                else {
                    poner('Elige el turno…');
                    datos.turnos.forEach(function (t) {
                        roles[t.id] = t.rol;
                        sel.add(new Option(t.etiqueta, t.id));
                    });
                    if (seleccionar) { sel.value = String(seleccionar); }
                }
                filtrarPersonas();
            })
            .catch(function () { poner('No se pudieron cargar los turnos'); filtrarPersonas(); });
    }

    // «Todo el turno»: marca a todos los que están en él, para intercambiar
    // turnos completos.
    form.querySelectorAll('[data-marcar-todo]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            form.querySelectorAll('[data-rol-de="' + boton.dataset.marcarTodo + '"] [data-persona]:not(.d-none) input')
                .forEach(function (c) { c.checked = true; });
        });
    });

    // Una permuta necesita al menos a uno marcado de cada lado; mejor avisarlo
    // aquí que después de enviar.
    form.addEventListener('submit', function (ev) {
        if (form.querySelector('input[name="tipo"]:checked').value !== 'permuta') { return; }
        var faltan = ['a', 'b'].filter(function (lado) {
            return !form.querySelector('input[name="salen_' + lado + '[]"]:checked');
        });
        if (faltan.length) {
            ev.preventDefault();
            alert('Marca al menos a uno de cada turno: quiénes dejan el ' + (faltan[0] === 'a' ? 'primer' : 'segundo') + ' turno.');
        }
    });

    form.querySelectorAll('input[name="tipo"]').forEach(function (r) { r.addEventListener('change', aplicarTipo); });
    ['a', 'b'].forEach(function (lado) {
        document.getElementById('fecha_' + lado).addEventListener('change', function () { cargarTurnos(lado); });
        document.getElementById('turno_' + lado).addEventListener('change', filtrarPersonas);
    });
    aplicarTipo();
    if (document.getElementById('fecha_a').value) {
        cargarTurnos('a', document.getElementById('turno_a').dataset.seleccionar);
    } else {
        filtrarPersonas();
    }
})();
</script>
