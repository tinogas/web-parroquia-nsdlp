<?php
/**
 * La página del enlace personal (MiController::pagina()). Pensada para leerse
 * en el teléfono: una columna, de lo más urgente a lo de consulta. Arriba lo
 * que respondió la coordinación; luego una pestaña por pastoral, con quién
 * coordina y cuándo se reúnen, sus turnos (en MESC y Proclamadores), los
 * avisos, lo que viene, el formulario de cambio, sus solicitudes, el rol del
 * mes y los documentos.
 *
 *   $persona      su ficha del equipo pastoral (id, nombre, foto, llamarle)
 *   $secciones    sus pastorales, por slug (MiController::secciones())
 *   $seccion      el slug de la que se está viendo, o null si no está en ninguna
 *   $novedades    respuestas de la coordinación que todavía no ha visto
 *   $vistaPrevia  la está viendo la coordinación desde el panel
 *   $urlPagina    fn (array $params): string, la dirección de esta misma página
 *   $firma        el campo `_mi` de los formularios
 *   con pastoral: $pastoral, $avisos, $eventos, $tablero, $documentos
 *   $turnos       null, o en MESC/Proclamadores: $cfg, $yo, $misTurnos,
 *                 $diasProximos, $solicitudes, $rol, $inicioMes, $personas,
 *                 $turnoPedido, $urlTurnosDia
 */
require_once BASE_PATH . '/shared/views/parciales/cambios_turno_funciones.php';

$pila  = $persona['llamarle'];   // su nombre corto de MESC, o su nombre de pila
$meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
          'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$ocultos = '<input type="hidden" name="_mi" value="' . e($firma) . '">'
         . ($seccion !== null ? '<input type="hidden" name="pastoral" value="' . e($seccion) . '">' : '');
?>
<div class="mx-auto" style="max-width: 46rem">

<?php if ($vistaPrevia): ?>
<div class="alert alert-warning small">
    <i class="bi bi-eye me-1"></i><strong>Vista previa.</strong>
    Así ve <?= e($persona['nombre']) ?> su página. Desde aquí no se envían solicitudes, y abrirla no cuenta
    como que la vio.
</div>
<?php endif; ?>

<div class="d-flex align-items-center gap-3 mb-4">
    <img src="<?= e(foto_o_avatar($persona['foto'] ?? null, $persona['nombre'], 112)) ?>"
         class="rounded-circle flex-shrink-0" style="width:56px;height:56px;object-fit:cover" alt="">
    <div>
        <h1 class="titulo-pagina h2 mb-0">Hola, <?= e($pila) ?></h1>
        <p class="text-muted small mb-0">Tu página personal en la parroquia</p>
    </div>
</div>

<?php /* ── Lo que respondió la coordinación ── */ ?>
<?php if ($novedades): ?>
<section class="card border-0 shadow-sm mb-4 border-start border-4 border-primary" aria-labelledby="titulo-novedades">
    <div class="card-body p-4">
        <h2 class="h6 fw-bold mb-3" id="titulo-novedades"><i class="bi bi-bell me-1"></i>Novedades</h2>
        <?php foreach ($novedades as $i => $c): ?>
        <?php
        [$texto, $icono, $motivo, $quien, $cuando] = match ($c['estado']) {
            'aprobado'  => ['Aprobaron tu solicitud', 'bi-check-circle-fill text-success', null, $c['resolvio'], $c['resuelto_at']],
            'deshecho'  => ['Se deshizo tu cambio de turno', 'bi-arrow-counterclockwise text-dark', $c['motivo_cancelacion'], $c['cancelo'], $c['cancelado_at']],
            default     => ['Rechazaron tu solicitud', 'bi-x-circle-fill text-danger', $c['motivo_rechazo'], $c['resolvio'], $c['resuelto_at']],
        };
        ?>
        <div class="d-flex gap-2 <?= $i ? 'border-top pt-3 mt-3' : '' ?>">
            <i class="bi <?= $icono ?> fs-5 flex-shrink-0"></i>
            <div class="small">
                <div class="fw-semibold">
                    <?= e($texto) ?>
                    <?php if (count($secciones) > 1): ?>
                    <span class="text-muted fw-normal">· <?= e($secciones[$c['seccion']]['titulo']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="mt-1"><?php cambios_turno_detalle($c); ?></div>
                <?php if ($motivo): ?>
                <div class="mt-1"><i class="bi bi-chat-left-text me-1"></i>«<?= e($motivo) ?>»</div>
                <?php endif; ?>
                <?php if ($quien): ?>
                <div class="text-muted mt-1"><?= e($quien) ?> · <?= e(fecha_larga(substr((string) $cuando, 0, 10))) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($seccion === null): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <p class="mb-2">Todavía no apareces en ninguna pastoral activa.</p>
        <p class="text-muted small mb-0">
            Cuando tu coordinación te marque en tu pastoral, aquí verás sus avisos, documentos y eventos.
        </p>
    </div>
</div>
<?php else: ?>

<?php if (count($secciones) > 1): ?>
<ul class="nav nav-pills mb-4" aria-label="Tus pastorales">
    <?php foreach ($secciones as $clave => $s): ?>
    <li class="nav-item">
        <a class="nav-link <?= $clave === $seccion ? 'active' : '' ?>"
           <?= $clave === $seccion ? 'aria-current="page"' : '' ?>
           href="<?= e($urlPagina(['pastoral' => $clave])) ?>"><?= e($s['titulo']) ?></a>
    </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>

<?php /* ── Su pastoral: quién coordina y cuándo se reúnen ── */ ?>
<?php
$reunion = implode(' · ', array_filter([
    $pastoral['dia_reunion'] ?? null,
    !empty($pastoral['hora_reunion']) ? hora_corta($pastoral['hora_reunion']) : null,
    $pastoral['lugar_reunion'] ?? null,
]));
// Solo una dirección https: el grupo lo captura la coordinación como texto libre.
$grupo = preg_match('#^https://#i', (string) ($pastoral['whatsapp_grupo'] ?? '')) ? $pastoral['whatsapp_grupo'] : null;
$nadaPublicado = !$turnos && !$avisos && !$eventos && !$tablero && !$documentos;
?>
<section class="card border-0 shadow-sm mb-4" aria-labelledby="titulo-pastoral">
    <div class="card-body p-4">
        <h2 class="h5 fw-bold mb-1" id="titulo-pastoral">
            <i class="bi <?= e($pastoral['icono'] ?: 'bi-people') ?> text-dorado me-1"></i><?= e($pastoral['nombre']) ?>
        </h2>
        <?php if (!empty($pastoral['descripcion_corta'])): ?>
        <p class="small text-muted mb-2"><?= e($pastoral['descripcion_corta']) ?></p>
        <?php endif; ?>
        <ul class="list-unstyled small mb-0">
            <?php if (!empty($pastoral['responsable_nombre'])): ?>
            <li class="mb-1">
                <i class="bi <?= $pastoral['responsable_pareja_id'] ? 'bi-heart-fill text-danger' : 'bi-person' ?> me-1"></i>Coordina
                <?= e($pastoral['responsable_nombre']) ?>
            </li>
            <?php endif; ?>
            <?php if ($reunion !== ''): ?>
            <li><i class="bi bi-calendar-week me-1"></i>Se reúnen: <?= e($reunion) ?></li>
            <?php endif; ?>
        </ul>
        <?php if ($grupo): ?>
        <a href="<?= e($grupo) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-success mt-3">
            <i class="bi bi-whatsapp me-1"></i>Grupo de WhatsApp de la pastoral
        </a>
        <?php endif; ?>
    </div>
</section>

<?php if ($turnos): ?>
<?php /* ── Mis próximos turnos ── */ ?>
<section class="card border-0 shadow-sm mb-4" id="turnos" aria-labelledby="titulo-turnos">
    <div class="card-body p-4">
        <h2 class="h5 fw-bold mb-1" id="titulo-turnos">Tus próximos turnos</h2>
        <p class="small text-muted mb-3">
            Los próximos <?= (int) $diasProximos ?> días en <?= e($cfg['titulo']) ?>, ya con los cambios aprobados.
        </p>
        <?php if (!$misTurnos): ?>
        <p class="text-muted mb-0">No tienes turnos en los próximos <?= (int) $diasProximos ?> días.</p>
        <?php else: ?>
        <ul class="list-group list-group-flush">
            <?php foreach ($misTurnos as $t): ?>
            <?php
            $yoId       = (int) $yo['id'];
            $companeros = array_diff_key($t['actuales'], [$yoId => true]);
            $entre      = isset($t['entraron'][$yoId]);
            ?>
            <li class="list-group-item px-0 d-flex justify-content-between align-items-start gap-2 <?= $t['cedido'] ? 'text-muted' : '' ?>">
                <div>
                    <div class="fw-semibold <?= $t['cedido'] ? 'text-decoration-line-through' : '' ?>">
                        <?= e(ucfirst(fecha_con_dia($t['fecha']))) ?>
                    </div>
                    <div class="small <?= $t['cedido'] ? 'text-decoration-line-through' : '' ?>">
                        <?= e(trim(hora_corta($t['hora']) . ' · ' . $t['descripcion'], ' ·')) ?>
                    </div>
                    <?php if ($t['cedido']): ?>
                    <div class="small mt-1"><i class="bi bi-arrow-left-right me-1"></i>Ya no te toca: lo cediste con un cambio aprobado.</div>
                    <?php else: ?>
                    <?php if ($companeros): ?>
                    <div class="small text-muted mt-1">Con <?= e(CambiosTurno::unirNombres($companeros)) ?></div>
                    <?php endif; ?>
                    <?php if ($entre): ?>
                    <div class="small text-success mt-1">
                        <i class="bi bi-arrow-left-right me-1"></i>Te toca por un cambio<?= $t['salieron'] ? ', en lugar de ' . e(CambiosTurno::unirNombres($t['salieron'])) : '' ?>.
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php if (!$t['cedido'] && !$vistaPrevia): ?>
                <a href="<?= e($urlPagina(['pastoral' => $seccion, 'turno' => (int) $t['id']])) ?>#pedir"
                   class="btn btn-sm btn-outline-primary text-nowrap">Pedir un cambio</a>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</section>

<?php endif; ?>

<?php /* ── Avisos: de su pastoral, de la parroquia y los comunicados ── */ ?>
<?php if ($avisos): ?>
<section class="card border-0 shadow-sm mb-4" id="avisos" aria-labelledby="titulo-avisos">
    <div class="card-body p-4">
        <h2 class="h5 fw-bold mb-3" id="titulo-avisos">Avisos</h2>
        <?php foreach ($avisos as $i => $aviso): ?>
        <article class="<?= $i ? 'border-top pt-3 mt-3' : '' ?>">
            <div class="small text-muted mb-1">
                <i class="bi <?= e(AvisoModel::icono($aviso['tipo'])) ?> me-1"></i><?= e($aviso['pastoral_nombre'] ?? 'Parroquia') ?>
                <?php if ($aviso['publicado_interno_at']): ?>
                · <?= e(fecha_larga(substr((string) $aviso['publicado_interno_at'], 0, 10))) ?>
                <?php endif; ?>
            </div>
            <h3 class="h6 fw-bold mb-1"><?= e($aviso['titulo']) ?></h3>
            <?php if ($aviso['resumen']): ?>
            <p class="small mb-1"><?= e($aviso['resumen']) ?></p>
            <?php endif; ?>
            <?php if ($aviso['contenido'] || $aviso['imagen'] || $aviso['archivo_pdf']): ?>
            <details class="small">
                <summary class="text-primary">Leer completo</summary>
                <div class="mt-2">
                    <?php if ($aviso['imagen']): ?>
                    <img src="<?= e(url_activo($aviso['imagen'])) ?>" alt="" class="img-fluid rounded mb-2" loading="lazy">
                    <?php endif; ?>
                    <?php if ($aviso['contenido']): ?>
                    <div class="contenido-editorial"><?= $aviso['contenido'] /* ya saneado con SanitizadorHtml al guardarse */ ?></div>
                    <?php endif; ?>
                    <?php if ($aviso['archivo_pdf']): ?>
                    <a href="<?= e(url_activo($aviso['archivo_pdf'])) ?>" target="_blank" rel="noopener">
                        <i class="bi bi-file-earmark-pdf me-1"></i>Abrir el PDF
                    </a>
                    <?php endif; ?>
                </div>
            </details>
            <?php endif; ?>
        </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php /* ── Lo que viene: eventos y tablero de su pastoral ── */ ?>
<?php if ($eventos || $tablero): ?>
<section class="card border-0 shadow-sm mb-4" id="proximos" aria-labelledby="titulo-proximos">
    <div class="card-body p-4">
        <h2 class="h5 fw-bold mb-3" id="titulo-proximos">Lo que viene</h2>
        <?php if ($eventos): ?>
        <ul class="list-unstyled mb-0">
            <?php foreach ($eventos as $evento): ?>
            <?php $inicio = (string) $evento['fecha_inicio']; ?>
            <li class="d-flex gap-3 py-2 border-bottom">
                <div class="text-center flex-shrink-0" style="width: 3rem">
                    <div class="small text-uppercase text-muted"><?= e(mes_abreviado($inicio)) ?></div>
                    <div class="fs-4 fw-bold lh-1"><?= (int) date('j', strtotime($inicio)) ?></div>
                </div>
                <div class="small">
                    <div class="fw-semibold"><?= e($evento['titulo']) ?></div>
                    <div class="text-muted">
                        <?= e(ucfirst(nombre_dia((int) date('w', strtotime($inicio))))) ?>
                        · <?= $evento['todo_el_dia'] ? 'Todo el día' : e(hora_corta(substr($inicio, 11))) ?>
                        <?php if ($evento['lugar']): ?>· <?= e($evento['lugar']) ?><?php endif; ?>
                    </div>
                    <?php if ($evento['publicado']): ?>
                    <a href="<?= e(url_publica('eventos', ['slug' => $evento['slug']])) ?>">Ver en el sitio</a>
                    <?php endif; ?>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <?php if ($tablero): ?>
        <h3 class="h6 fw-bold mt-3 mb-2">Actividades</h3>
        <?php foreach ($tablero as $entrada): ?>
        <div class="small py-2 border-bottom">
            <div class="fw-semibold"><?= e($entrada['titulo']) ?></div>
            <div class="text-muted">
                <?= e(fecha_larga($entrada['fecha_inicio'])) ?>
                <?php if ($entrada['fecha_fin'] && $entrada['fecha_fin'] !== $entrada['fecha_inicio']): ?>
                al <?= e(fecha_larga($entrada['fecha_fin'])) ?>
                <?php endif; ?>
            </div>
            <?php if ($entrada['descripcion']): ?>
            <div class="mt-1"><?= nl2br(e($entrada['descripcion'])) ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($turnos): ?>
<?php /* ── Pedir un cambio ── */ ?>
<section class="card border-0 shadow-sm mb-4" id="pedir" aria-labelledby="titulo-pedir">
    <div class="card-body p-4">
        <h2 class="h5 fw-bold mb-1" id="titulo-pedir">Pedir un cambio de turno</h2>
        <p class="small text-muted mb-3">
            Antes de pedirlo, ponte de acuerdo con la otra persona. Tu solicitud le llega a la coordinación de
            <?= e($cfg['titulo']) ?> para que la apruebe; mientras tanto el rol no cambia. Tiene que incluirte a ti:
            que dejes un turno, cubras a alguien o te sumes a uno.
        </p>
        <?php if ($vistaPrevia): ?>
        <p class="small text-muted fst-italic mb-0">
            En la vista previa no aparece el formulario: cada quien pide desde su propio teléfono.
        </p>
        <?php else: ?>
        <?php
        $ctf_accion    = url_post('publico', 'mi', 'cambio_guardar');
        $ctf_ocultos   = $ocultos;
        $ctf_urlDia    = $urlTurnosDia;
        $ctf_solicitar = true;
        $ctf_yo        = $yo;
        require BASE_PATH . '/shared/views/parciales/cambios_turno_formulario.php';
        ?>
        <?php endif; ?>
    </div>
</section>

<?php /* ── Mis solicitudes ── */ ?>
<section class="card border-0 shadow-sm mb-4" id="solicitudes" aria-labelledby="titulo-solicitudes">
    <div class="card-body p-4">
        <h2 class="h5 fw-bold mb-3" id="titulo-solicitudes">Tus solicitudes</h2>
        <?php if (!$solicitudes): ?>
        <p class="text-muted mb-0">Todavía no has pedido cambios en <?= e($cfg['titulo']) ?>.</p>
        <?php endif; ?>
        <?php foreach ($solicitudes as $i => $sol): ?>
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
            <?php elseif ($sol['estado'] === 'pendiente' && !$vistaPrevia): ?>
            <form method="POST" action="<?= e(url_post('publico', 'mi', 'solicitud_retirar')) ?>"
                  onsubmit="return confirm('¿Retirar esta solicitud?')">
                <?= $ocultos ?>
                <input type="hidden" name="id" value="<?= (int) $sol['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-secondary">Retirar</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<?php /* ── El rol del mes ── */ ?>
<?php
$anterior  = $inicioMes->modify('-1 month');
$siguiente = $inicioMes->modify('+1 month');
$porDia    = [];
foreach ($rol as $t) {
    $porDia[$t['fecha']][] = $t;
}
?>
<section class="card border-0 shadow-sm mb-4" id="rol" aria-labelledby="titulo-rol">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
            <a href="<?= e($urlPagina(['pastoral' => $seccion, 'anio' => (int) $anterior->format('Y'), 'mes' => (int) $anterior->format('n')])) ?>#rol"
               class="btn btn-sm btn-outline-secondary" aria-label="Mes anterior"><i class="bi bi-chevron-left"></i></a>
            <h2 class="h5 fw-bold mb-0 text-center" id="titulo-rol">
                Rol de <?= e($meses[(int) $inicioMes->format('n') - 1]) ?> <?= e($inicioMes->format('Y')) ?>
            </h2>
            <a href="<?= e($urlPagina(['pastoral' => $seccion, 'anio' => (int) $siguiente->format('Y'), 'mes' => (int) $siguiente->format('n')])) ?>#rol"
               class="btn btn-sm btn-outline-secondary" aria-label="Mes siguiente"><i class="bi bi-chevron-right"></i></a>
        </div>
        <p class="small text-muted text-center mb-3">Quién sirve en cada turno, ya con los cambios aprobados. Tus turnos van resaltados.</p>

        <?php if (!$porDia): ?>
        <p class="text-muted text-center mb-0">Todavía no hay turnos en este mes.</p>
        <?php endif; ?>
        <?php
        // Los días que ya pasaron se pliegan: en el teléfono, lo primero que
        // importa es de hoy en adelante, y el mes completo es muy largo.
        $pasados = array_filter($porDia, static fn (string $f): bool => $f < $hoy, ARRAY_FILTER_USE_KEY);
        $abierto = false;
        ?>
        <?php foreach ($porDia as $fecha => $turnosDia): ?>
        <?php if ($pasados && $fecha === array_key_first($pasados)): $abierto = true; ?>
        <details class="mt-2">
            <summary class="small text-muted">
                Ver los días que ya pasaron (<?= count($pasados) ?>)
            </summary>
        <?php endif; ?>
        <?php if ($abierto && $fecha >= $hoy): $abierto = false; ?>
        </details>
        <?php endif; ?>
        <div class="<?= $fecha < $hoy ? 'opacity-50' : '' ?>">
            <div class="fw-semibold small text-uppercase text-muted mt-3 mb-1"><?= e(fecha_con_dia($fecha)) ?></div>
            <?php foreach ($turnosDia as $t): ?>
            <?php $esMio = isset($t['actuales'][(int) $yo['id']]); ?>
            <div class="d-flex gap-3 py-2 px-2 border-bottom <?= $esMio ? 'bg-primary-subtle rounded' : '' ?>">
                <div class="small fw-semibold text-nowrap" style="min-width: 5.5rem"><?= e(hora_corta($t['hora'])) ?></div>
                <div class="small flex-grow-1">
                    <?php if ($t['descripcion']): ?>
                    <div class="text-muted"><?= e($t['descripcion']) ?></div>
                    <?php endif; ?>
                    <div>
                        <?php if (!$t['actuales']): ?>
                        <span class="text-muted">Sin asignar</span>
                        <?php endif; ?>
                        <?= implode(', ', array_map(
                            static fn ($id, $nombre): string => (int) $id === (int) $yo['id'] ? '<strong>Tú</strong>' : e($nombre),
                            array_keys($t['actuales']), $t['actuales']
                        )) ?>
                    </div>
                    <?php if ($t['salieron']): ?>
                    <div class="text-muted fst-italic">
                        <i class="bi bi-arrow-left-right me-1"></i>en lugar de <?= e(CambiosTurno::unirNombres($t['salieron'])) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
        <?php if ($abierto): /* el mes entero ya pasó */ ?>
        </details>
        <?php endif; ?>
    </div>
</section>
<?php endif; /* turnos */ ?>

<?php /* ── Documentos de su pastoral ── */ ?>
<?php if ($documentos): ?>
<section class="card border-0 shadow-sm mb-4" id="documentos" aria-labelledby="titulo-documentos">
    <div class="card-body p-4">
        <h2 class="h5 fw-bold mb-3" id="titulo-documentos">Documentos</h2>
        <ul class="list-unstyled mb-0">
            <?php foreach ($documentos as $documento): ?>
            <li class="py-1">
                <a href="<?= e(url_activo($documento['archivo'])) ?>" target="_blank" rel="noopener" class="text-decoration-none">
                    <i class="bi bi-file-earmark-text me-1"></i><?= e($documento['titulo']) ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>

<?php if ($nadaPublicado): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4 text-center">
        <i class="bi bi-hourglass-split fs-3 text-secondary"></i>
        <p class="mb-1 mt-2">Tu pastoral todavía no ha publicado nada para su equipo.</p>
        <p class="small text-muted mb-0">Cuando su coordinación publique avisos, documentos o eventos, aparecerán aquí.</p>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php if (!$vistaPrevia): ?>
<p class="text-center small text-muted mb-1">
    Este enlace es solo tuyo: no lo compartas. Si lo pierdes, pídele uno nuevo a tu coordinación.
</p>
<form method="POST" action="<?= e(url_post('publico', 'mi', 'olvidar')) ?>" class="text-center small text-muted"
      onsubmit="return confirm('¿Olvidar tu enlace en este teléfono? Para volver a entrar desde aquí tendrás que abrir otra vez el enlace que te mandaron.')">
    <input type="hidden" name="_mi" value="<?= e($firma) ?>">
    ¿Es un teléfono prestado?
    <button type="submit" class="btn btn-link btn-sm p-0 align-baseline">Olvidar mi enlace en este teléfono</button>
</form>
<?php endif; ?>

</div>
