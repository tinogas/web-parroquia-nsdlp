<?php
/**
 * Portada, con la estructura de la propuesta de rediseño: cada sección es una
 * franja a todo el ancho con su propio fondo. La cabecera (portada editorial
 * o carrusel) la compone InicioController.
 *
 * Orden: evangelio del día · horarios · fiesta patronal · sacramentos ·
 * pastorales · agenda · lo que publica el panel (cursos, avisos, Facebook) ·
 * quiénes somos · contacto.
 *
 * Cada franja desaparece sola si no tiene qué mostrar: ninguna deja un
 * encabezado con un hueco debajo.
 */
$fechaHoy     = date('Y-m-d');
$nombreSitio  = Config::get('parroquia_nombre', APP_NAME);

/** '6622207214' → '662 220 7214'; cualquier otra forma se deja como está. */
$telefonoLegible = static function (string $tel): string {
    $digitos = preg_replace('/\D/', '', $tel);
    return strlen($digitos) === 10
        ? substr($digitos, 0, 3) . ' ' . substr($digitos, 3, 3) . ' ' . substr($digitos, 6)
        : $tel;
};

/** El horario de oficina se captura en renglones "días / horas"; en pares se lee como tabla. */
$renglonesOficina = array_values(array_filter(array_map('trim', preg_split('/\R/', Config::get('horario_oficina')))));
$oficinaEnPares   = $renglonesOficina && count($renglonesOficina) % 2 === 0;
?>

<?php if (!$portadaEditorial && !empty($proximasMisas)): ?>
<?php /* Con carrusel, las próximas misas no caben en la cabecera: van aquí. */ ?>
<div class="container pt-5">
    <?php require BASE_PATH . '/modules/inicio/views/publico/proximas_misas.php'; ?>
</div>
<?php endif; ?>


<?php /* ================================================================
         EVANGELIO DEL DÍA — el evangelio a la izquierda y la reflexión del
         párroco a la derecha. Cada columna enseña su título y el principio
         del texto, y se despliega con "Leer completo"; publico.js solo la
         pliega si de verdad es larga. Sin JavaScript se ve todo.
         ================================================================ */ ?>
<?php if (!empty($evangelioHoy)): ?>
<?php
$hayReflexion   = !empty($evangelioHoy['reflexion']);
$fechaEvangelio = fecha_con_dia($evangelioHoy['fecha']);   // "miércoles 14 de octubre"
$lecturas = array_filter([
    ['evangelio', 'Evangelio', 'bi-book',        $evangelioHoy['evangelio']],
    $hayReflexion ? ['reflexion', 'Reflexión', 'bi-chat-quote', $evangelioHoy['reflexion']] : null,
]);
?>
<section id="evangelio" class="franja franja-evangelio" aria-labelledby="evangelio-titulo">
    <div class="container">
        <div class="encabezado-seccion">
            <span class="antetitulo">Evangelio del día</span>
            <h2 id="evangelio-titulo" class="titulo-seccion"><?= e(mb_strtoupper(mb_substr($fechaEvangelio, 0, 1)) . mb_substr($fechaEvangelio, 1)) ?></h2>
        </div>
        <div class="row g-4 align-items-start <?= $hayReflexion ? '' : 'justify-content-center' ?>">
            <?php foreach ($lecturas as [$clave, $titulo, $icono, $html]): ?>
            <div class="<?= $hayReflexion ? 'col-lg-6' : 'col-lg-8' ?>">
                <article class="lectura plegada" data-plegable>
                    <h3 class="lectura-titulo">
                        <i class="bi <?= e($icono) ?>" aria-hidden="true"></i><?= e($titulo) ?>
                    </h3>
                    <?php /* Contenido ya saneado con lista blanca al guardarse: se imprime sin escapar a propósito. */ ?>
                    <div class="lectura-texto contenido-editorial" id="lectura-<?= e($clave) ?>"><?= $html ?></div>
                    <button type="button" class="lectura-boton" aria-expanded="false" aria-controls="lectura-<?= e($clave) ?>" hidden>
                        <span>Leer completo</span><i class="bi bi-chevron-down" aria-hidden="true"></i>
                    </button>
                </article>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>


<?php /* ================================================================
         HORARIOS — la misma semana que la página de Horarios
         (horarios/views/publico/semana.php).
         ================================================================ */ ?>
<section id="horarios" class="franja franja-blanca" data-horarios>
    <div class="container">
        <?php
        $semana = [
            'nivel' => 'h2',
            'intro' => '<p>Los horarios de las misas y confesiones en la sede parroquial y en los centros. Se abre en el día de hoy.</p>',
        ];
        require BASE_PATH . '/modules/horarios/views/publico/semana.php';
        ?>
    </div>
</section>


<?php /* ================================================================
         FIESTA PATRONAL — aparece cuando la fiesta está publicada en la
         agenda y falta menos de dos meses; se va sola al día siguiente.
         ================================================================ */ ?>
<?php if ($fiesta): ?>
<?php
$diaFiesta  = substr($fiesta['fiesta']['fecha_inicio'], 0, 10);
$faltan     = (int) (new DateTimeImmutable($fechaHoy))->diff(new DateTimeImmutable($diaFiesta))->days;
$horaFiesta = date('H:i', strtotime($fiesta['fiesta']['fecha_inicio']));
$conMisa    = stripos($fiesta['fiesta']['titulo'], 'misa') !== false;
$meses      = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$mesFiesta  = $meses[(int) date('n', strtotime($diaFiesta)) - 1];
$novena     = $fiesta['novena'];
$advocacion = preg_replace('/^Parroquia\s+/iu', '', $nombreSitio);
?>
<section class="franja-fiesta" aria-labelledby="fiesta-titulo">
    <div class="container">
        <div class="row g-4 g-lg-5 align-items-center">
            <div class="col-lg-7">
                <p class="antetitulo d-flex align-items-center gap-2 mb-0">
                    <i class="bi bi-calendar-heart" aria-hidden="true"></i>Fiesta patronal <?= e(date('Y', strtotime($diaFiesta))) ?>
                </p>
                <h2 id="fiesta-titulo" class="titulo-seccion mt-3"><?= e($advocacion) ?></h2>
                <p class="fiesta-texto">
                    <?php if ($novena): ?>
                    <?php
                    $iniNovena = substr($novena['fecha_inicio'], 0, 10);
                    $finNovena = substr($novena['fecha_fin'] ?: $novena['fecha_inicio'], 0, 10);
                    $enNovena  = $fechaHoy >= $iniNovena && $fechaHoy <= $finNovena;
                    ?>
                    <?= $enNovena ? 'Estamos en la novena: ' : 'Prepárate con la novena, ' ?>del <?= (int) date('j', strtotime($iniNovena)) ?>
                    al <?= (int) date('j', strtotime($finNovena)) ?> de <?= e($meses[(int) date('n', strtotime($finNovena)) - 1]) ?>.
                    <?php endif; ?>
                    La fiesta se celebra el <?= e(nombre_dia((int) date('w', strtotime($diaFiesta)))) ?>
                    <?= (int) date('j', strtotime($diaFiesta)) ?> de <?= e($mesFiesta) ?>
                    <?php if ($horaFiesta !== '00:00'): ?><?= $conMisa ? 'con misa a las' : 'a las' ?> <?= e(hora_corta($horaFiesta)) ?><?php endif; ?><?= $fiesta['kermes'] ? ' y la kermés parroquial' : '' ?>.
                </p>
                <a href="#agenda" class="btn btn-dorado btn-lg px-4 fs-6 mt-3">Ver la agenda</a>
            </div>
            <div class="col-lg-5">
                <div class="d-flex gap-3">
                    <div class="fiesta-cifra">
                        <span class="numero text-dorado"><?= $faltan ?></span>
                        <span class="etiqueta"><?= $faltan === 0 ? 'Es hoy' : ($faltan === 1 ? 'día para la fiesta' : 'días para la fiesta') ?></span>
                    </div>
                    <div class="fiesta-cifra">
                        <span class="numero"><?= (int) date('j', strtotime($diaFiesta)) ?></span>
                        <span class="etiqueta text-capitalize"><?= e($mesFiesta) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>


<?php /* ================================================================
         SACRAMENTOS — acordeón con requisitos y documentos, y la oficina
         parroquial al lado. Cada uno enlaza además a su ficha completa.
         ================================================================ */ ?>
<?php if ($sacramentos): ?>
<section id="sacramentos" class="franja">
    <div class="container">
        <div class="row g-4 g-lg-5">
            <div class="col-lg-7">
                <div class="encabezado-seccion">
                    <span class="antetitulo">Sacramentos</span>
                    <h2 class="titulo-seccion">Lo que necesitas para cada sacramento</h2>
                    <p>Requisitos y documentos para tramitarlos en nuestra parroquia. Todo trámite se asienta en la oficina parroquial.</p>
                </div>

                <div class="acordeon-sacramentos" id="acordeonSacramentos">
                    <?php foreach ($sacramentos as $i => $s): ?>
                    <?php
                    $aportacion = trim((string) $s['aportacion']);
                    if ($aportacion !== '' && is_numeric($aportacion)) {
                        $aportacion = '$' . number_format((float) $aportacion, 0);
                    }
                    $abierto = $i === 0;
                    // Un espacio antes de cada etiqueta, para que "<b>Qué es</b><br>Un" no quede "Qué esUn" al quitarlas.
    $resumen = resumen(str_replace('<', ' <', (string) $s['descripcion']), 260);
                    ?>
                    <div class="sacramento">
                        <h3 class="m-0">
                            <button class="sacramento-boton <?= $abierto ? '' : 'collapsed' ?>" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#sacramento-<?= (int) $s['id'] ?>"
                                    aria-expanded="<?= $abierto ? 'true' : 'false' ?>" aria-controls="sacramento-<?= (int) $s['id'] ?>">
                                <span class="nombre"><?= e($s['nombre']) ?></span>
                                <?php if ($aportacion !== ''): ?>
                                <span class="aportacion">Aportación <?= e($aportacion) ?></span>
                                <?php endif; ?>
                                <i class="bi bi-chevron-down ms-auto" aria-hidden="true"></i>
                            </button>
                        </h3>
                        <div id="sacramento-<?= (int) $s['id'] ?>" class="collapse <?= $abierto ? 'show' : '' ?>" data-bs-parent="#acordeonSacramentos">
                            <div class="sacramento-cuerpo">
                                <?php if ($resumen !== ''): ?>
                                <p class="sacramento-resumen"><?= e($resumen) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($s['requisitos']) || !empty($s['documentos'])): ?>
                                <div class="row g-4">
                                    <?php if (!empty($s['requisitos'])): ?>
                                    <div class="col-sm-6">
                                        <p class="sacramento-etiqueta">Requisitos</p>
                                        <?php /* HTML saneado con lista blanca al guardarse. */ ?>
                                        <div class="lista-dorada"><?= $s['requisitos'] ?></div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($s['documentos'])): ?>
                                    <div class="col-sm-6">
                                        <p class="sacramento-etiqueta">Documentos</p>
                                        <div class="lista-dorada"><?= $s['documentos'] ?></div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php elseif ($resumen === ''): ?>
                                <p class="sacramento-resumen">Pide informes en la oficina parroquial.</p>
                                <?php endif; ?>
                                <a href="<?= e(url_publica('sacramentos', ['slug' => $s['slug']])) ?>" class="enlace-flecha mt-3">
                                    Ver la ficha completa <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <aside class="col-lg-4 offset-lg-1">
                <div class="tarjeta-oficina">
                    <i class="bi bi-file-earmark-text icono" aria-hidden="true"></i>
                    <h3 class="h2">Oficina parroquial</h3>
                    <?php if ($oficinaEnPares): ?>
                    <dl class="oficina-horas">
                        <?php foreach (array_chunk($renglonesOficina, 2) as [$dias, $horas]): ?>
                        <div><dt><?= e($dias) ?></dt><dd><?= e($horas) ?></dd></div>
                        <?php endforeach; ?>
                    </dl>
                    <?php elseif ($renglonesOficina): ?>
                    <p class="horario-oficina"><?= nl2br(e(implode("\n", $renglonesOficina))) ?></p>
                    <?php endif; ?>
                    <div class="d-grid gap-2 mt-4">
                        <?php if ($telEnlace = tel_enlace(Config::get('telefono'))): ?>
                        <a href="<?= e($telEnlace) ?>" class="btn btn-primary">
                            <i class="bi bi-telephone me-1"></i>Llamar al <?= e($telefonoLegible(Config::get('telefono'))) ?>
                        </a>
                        <?php endif; ?>
                        <?php if ($waEnlace = whatsapp_enlace(Config::get('whatsapp'))): ?>
                        <a href="<?= e($waEnlace) ?>" class="btn btn-outline-primary" target="_blank" rel="noopener">
                            <i class="bi bi-whatsapp me-1"></i>Escribir por WhatsApp
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</section>
<?php endif; ?>


<?php /* ================================================================
         PASTORALES — bento: una tarjeta por Comisión con sus pastorales.
         La descripción de cada Comisión viene de su ficha (descripción
         corta); para las que no la tienen capturada, y para el lema, se
         usa el texto de la propuesta.
         ================================================================ */ ?>
<?php if ($pastorales['comisiones'] || $pastorales['sueltas']): ?>
<?php
$textosComision = [
    'liturgica'          => ['Celebrar',    'Quienes sirven en la celebración: el altar, la Palabra, el canto y la Comunión.'],
    'profetica'          => ['Anunciar',    'El anuncio de la fe: catequesis de niños y jóvenes y animación misionera.'],
    'pastoral-social'    => ['Servir',      'La caridad hecha obra: enfermos, despensas, desayunador y cuidado de la creación.'],
    'de-la-familia'      => ['Acompañar',   'Grupos para cada etapa de la vida: matrimonios, adolescentes, jóvenes y adultos mayores.'],
    'piedad-popular-2'   => ['Orar',        'La oración del pueblo: el rosario, la reparación y los encuentros comunitarios.'],
    'de-la-comunicacion' => ['Comunicar',   'Redes, página web y cultura digital al servicio de la parroquia.'],
    'asuntos-economicos' => ['Administrar', ''],
];
$reunion = static function (array $p): string {
    return implode(', ', array_filter([
        $p['dia_reunion'] ? ucfirst(mb_strtolower($p['dia_reunion'])) : '',
        $p['hora_reunion'] ? hora_corta($p['hora_reunion']) : '',
    ]));
};
$masGrande = null;
foreach ($pastorales['comisiones'] as $i => $grupo) {
    if ($masGrande === null || count($grupo['hijas']) > count($pastorales['comisiones'][$masGrande]['hijas'])) {
        $masGrande = $i;
    }
}
?>
<section id="pastorales" class="franja franja-marfil">
    <div class="container">
        <div class="encabezado-seccion mb-5">
            <span class="antetitulo">Pastorales</span>
            <h2 class="titulo-seccion">Hay un lugar para ti</h2>
            <p>Las pastorales se agrupan en comisiones. Escríbenos y te ponemos en contacto con quien coordina el grupo que te interesa.</p>
        </div>

        <div class="bento">
            <?php foreach ($pastorales['comisiones'] as $i => $grupo): ?>
            <?php
            $padre = $grupo['padre'];
            $hijas = $grupo['hijas'];
            [$lema, $descripcionPropuesta] = $textosComision[$padre['slug']] ?? ['', ''];
            $descripcion = $padre['descripcion_corta'] ?: $descripcionPropuesta;
            ?>
            <div class="bento-tarjeta <?= count($hijas) >= 4 ? 'ancha' : '' ?> <?= $i === $masGrande ? 'destacada' : '' ?>">
                <div class="bento-cabecera">
                    <span class="bento-icono"><i class="bi <?= e($padre['icono'] ?: 'bi-people') ?>" aria-hidden="true"></i></span>
                    <?php if ($lema !== ''): ?><span class="bento-cuenta"><?= e($lema) ?></span><?php endif; ?>
                </div>
                <div>
                    <h3 class="bento-titulo">
                        <a href="<?= e(url_publica('pastorales', ['slug' => $padre['slug']])) ?>"><?= e($padre['nombre']) ?></a>
                        <span class="bento-meta"><?= count($hijas) ?> <?= count($hijas) === 1 ? 'grupo' : 'grupos' ?></span>
                    </h3>
                    <?php if ($descripcion): ?>
                    <p class="bento-descripcion mt-1"><?= e($descripcion) ?></p>
                    <?php endif; ?>
                </div>
                <?php if ($hijas): ?>
                <ul class="bento-lista">
                    <?php foreach ($hijas as $hija): ?>
                    <?php $detalle = $reunion($hija) ?: $hija['descripcion_corta']; ?>
                    <li>
                        <a href="<?= e(url_publica('pastorales', ['slug' => $hija['slug']])) ?>">
                            <span class="nombre"><?= e($hija['nombre']) ?></span>
                            <?php if ($detalle): ?><span class="detalle"><?= e($detalle) ?></span><?php endif; ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <div class="bento-pie text-end">
                    <a href="#contacto" class="bento-participar">Quiero participar →</a>
                </div>
            </div>
            <?php endforeach; ?>

            <?php foreach ($pastorales['sueltas'] as $suelta): ?>
            <?php $cuando = $reunion($suelta); ?>
            <div class="bento-tarjeta">
                <div class="bento-cabecera">
                    <span class="bento-icono"><i class="bi <?= e($suelta['icono'] ?: 'bi-people') ?>" aria-hidden="true"></i></span>
                    <?php if ($cuando): ?><span class="bento-cuenta"><?= e($cuando) ?></span><?php endif; ?>
                </div>
                <div>
                    <h3 class="bento-titulo">
                        <a href="<?= e(url_publica('pastorales', ['slug' => $suelta['slug']])) ?>"><?= e($suelta['nombre']) ?></a>
                    </h3>
                    <?php if ($suelta['descripcion_corta']): ?>
                    <p class="bento-descripcion mt-1"><?= e($suelta['descripcion_corta']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="bento-pie text-end">
                    <a href="#contacto" class="bento-participar">Quiero participar →</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>


<?php /* ================================================================
         AGENDA — próximos eventos publicados; los de la fiesta patronal
         (fiesta, novena y kermés) van en dorado.
         ================================================================ */ ?>
<?php if ($agenda): ?>
<?php
$idsFiesta   = $fiesta ? array_filter([(int) $fiesta['fiesta']['id'], (int) ($fiesta['novena']['id'] ?? 0)]) : [];
$mesesCortos = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
?>
<section id="agenda" class="franja">
    <div class="container">
        <div class="encabezado-seccion mb-5">
            <span class="antetitulo">Agenda parroquial</span>
            <h2 class="titulo-seccion">Lo que viene</h2>
            <p>
                Celebraciones y actividades abiertas a toda la comunidad. La agenda completa, con las reuniones
                de cada pastoral, sigue en <a href="<?= e(url_publica('eventos')) ?>">el calendario del sitio</a>.
            </p>
        </div>

        <ol class="agenda-rejilla">
            <?php foreach ($agenda as $ev): ?>
            <?php
            $inicio     = strtotime($ev['fecha_inicio']);
            $fin        = $ev['fecha_fin'] ? strtotime($ev['fecha_fin']) : null;
            $variosDias = $fin && date('Y-m-d', $fin) !== date('Y-m-d', $inicio);
            $conHora    = empty($ev['todo_el_dia']) && date('H:i', $inicio) !== '00:00';
            $destacado  = in_array((int) $ev['id'], $idsFiesta, true)
                || ($fiesta && stripos($ev['titulo'], 'kerm') !== false);
            ?>
            <li class="<?= $destacado ? 'destacado' : '' ?>">
                <a href="<?= e(url_publica('eventos', ['slug' => $ev['slug']])) ?>">
                    <span class="fecha-destacada <?= $destacado ? 'dorada' : '' ?>">
                        <span class="dia"><?= (int) date('j', $inicio) ?></span>
                        <span class="mes text-uppercase"><?= e($mesesCortos[(int) date('n', $inicio) - 1]) ?></span>
                    </span>
                    <span class="min-w-0">
                        <span class="cuando">
                            <?php if ($variosDias): ?>
                            Del <?= (int) date('j', $inicio) ?> <?= e($mesesCortos[(int) date('n', $inicio) - 1]) ?>
                            al <?= (int) date('j', $fin) ?> <?= e($mesesCortos[(int) date('n', $fin) - 1]) ?>
                            <?php else: ?>
                            <span class="text-capitalize"><?= e(nombre_dia((int) date('w', $inicio))) ?></span>
                            <?php endif; ?>
                            <?php if ($conHora): ?> · <?= e(hora_corta(date('H:i', $inicio))) ?><?php endif; ?>
                        </span>
                        <span class="titulo">
                            <?php if ($destacado): ?><i class="bi bi-star-fill text-dorado me-1" aria-label="Destacado"></i><?php endif; ?><?= e($ev['titulo']) ?>
                        </span>
                        <?php if ($ev['centro_id'] && isset($nombresCortos[(int) $ev['centro_id']])): ?>
                        <span class="sede"><i class="bi bi-geo-alt" aria-hidden="true"></i> <?= e($nombresCortos[(int) $ev['centro_id']]) ?></span>
                        <?php endif; ?>
                    </span>
                </a>
            </li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>
<?php endif; ?>


<?php /* ================================================================
         LO QUE PUBLICA EL PANEL — cursos, avisos y Facebook. No estaban
         en la propuesta; van aquí, entre la agenda y quiénes somos, con el
         mismo aire que el resto.
         ================================================================ */ ?>
<?php
$hayFacebook = Config::tiene('facebook');
$hayLigas    = !empty($bloques['ligas_interes']['contenido']);
?>
<?php if (!empty($proximosCursos) || !empty($avisosRecientes) || $hayFacebook || $hayLigas): ?>
<section class="franja franja-blanca">
    <div class="container">

        <?php if (!empty($proximosCursos)): ?>
        <div class="bloque-panel">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <span class="antetitulo">Formación</span>
                    <h2 class="titulo-seccion mb-0">Próximos cursos</h2>
                </div>
                <a href="<?= e(url_publica('cursos')) ?>" class="enlace-flecha">Ver todos los cursos <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="row g-3">
                <?php foreach ($proximosCursos as $curso): ?>
                <?php
                // Se avisa de la inscripción solo si de verdad se puede uno inscribir:
                // la casilla abierta y, si hay fecha de cierre, que no haya pasado.
                $abierta = $curso['inscripciones_abiertas']
                    && (!$curso['fecha_cierre_inscripcion'] || $curso['fecha_cierre_inscripcion'] >= $fechaHoy);
                ?>
                <div class="col-md-4">
                    <a href="<?= e(url_publica('cursos', ['slug' => $curso['slug']])) ?>" class="card text-decoration-none h-100">
                        <div class="card-body p-4">
                            <div class="d-flex flex-wrap gap-1 mb-3">
                                <span class="badge bg-secondary-subtle text-secondary-emphasis">
                                    <?= e(CursoModel::MODALIDADES[$curso['modalidad']] ?? $curso['modalidad']) ?>
                                </span>
                                <?php if ($abierta): ?>
                                <span class="badge bg-success-subtle text-success-emphasis">Inscripciones abiertas</span>
                                <?php endif; ?>
                            </div>
                            <span class="titulo-tarjeta d-block mb-2"><?= e($curso['titulo']) ?></span>
                            <?php if ($curso['fecha_inicio']): ?>
                            <span class="small text-muted d-block"><i class="bi bi-calendar3 me-1"></i>Inicia el <?= e(fecha_larga($curso['fecha_inicio'])) ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($avisosRecientes)): ?>
        <div class="bloque-panel">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <span class="antetitulo">Avisos</span>
                    <h2 class="titulo-seccion mb-0">Lo más reciente</h2>
                </div>
                <a href="<?= e(url_publica('avisos')) ?>" class="enlace-flecha">Ver todos los avisos <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="row g-3">
                <?php foreach ($avisosRecientes as $aviso): ?>
                <div class="col-md-4">
                    <a href="<?= e(url_publica('avisos', ['slug' => $aviso['slug']])) ?>" class="card h-100 text-decoration-none">
                        <div class="card-body p-4">
                            <span class="d-block small text-muted mb-2"><?= e(fecha_larga($aviso['fecha_publicacion'])) ?></span>
                            <span class="titulo-tarjeta d-block"><?= e($aviso['titulo']) ?></span>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($hayFacebook || $hayLigas): ?>
        <div class="row g-4 g-lg-5 bloque-panel">
            <?php if ($hayFacebook): ?>
            <div class="<?= $hayLigas ? 'col-lg-7' : 'col-lg-8 mx-auto' ?>">
                <span class="antetitulo">Redes</span>
                <h2 class="titulo-seccion mb-4">Lo último en Facebook</h2>
                <?php
                // Page Plugin oficial (un iframe, sin SDK), con la URL de Configuración → Redes sociales.
                $facebookSrc = 'https://www.facebook.com/plugins/page.php?' . http_build_query([
                    'href'                  => Config::get('facebook'),
                    'tabs'                  => 'timeline',
                    'width'                 => 500,
                    'height'                => 460,
                    'small_header'          => 'false',
                    'adapt_container_width' => 'true',
                    'hide_cover'            => 'false',
                    'show_facepile'         => 'true',
                ]);
                ?>
                <div class="card overflow-hidden" style="max-width:502px">
                    <iframe src="<?= e($facebookSrc) ?>" width="100%" height="460"
                            style="border:none;overflow:hidden;display:block" scrolling="no" loading="lazy"
                            allowfullscreen="true" title="Publicaciones recientes en Facebook"></iframe>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($hayLigas): ?>
            <div class="<?= $hayFacebook ? 'col-lg-5' : 'col-lg-8 mx-auto' ?>">
                <span class="antetitulo">Enlaces</span>
                <h2 class="titulo-seccion mb-4"><?= e($bloques['ligas_interes']['titulo'] ?: 'Ligas de interés') ?></h2>
                <div class="card">
                    <div class="card-body p-4 contenido-editorial"><?= $bloques['ligas_interes']['contenido'] ?></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
</section>
<?php endif; ?>


<?php /* ================================================================
         QUIÉNES SOMOS — misión, visión y las sedes, en azul noche.
         ================================================================ */ ?>
<?php
$mision = $bloquesNosotros['mision']['contenido'] ?? '';
$vision = $bloquesNosotros['vision']['contenido'] ?? '';
?>
<section id="nosotros" class="franja franja-noche">
    <div class="container">
        <div class="encabezado-seccion">
            <span class="antetitulo">Quiénes somos</span>
            <h2 class="titulo-seccion">Una Iglesia de comunión y participación</h2>
            <p><?= e($nombreSitio) ?>, <?= e(rtrim(Config::get('parroquia_diocesis'), '. ')) ?> de la Arquidiócesis de Hermosillo.</p>
        </div>

        <?php if ($mision !== '' || $vision !== ''): ?>
        <div class="row g-4 g-lg-5 mt-2">
            <?php if ($mision !== ''): ?>
            <div class="col-lg-6">
                <span class="antetitulo">Misión</span>
                <div class="cita-noche"><?= $mision ?></div>
            </div>
            <?php endif; ?>
            <?php if ($vision !== ''): ?>
            <div class="col-lg-6">
                <span class="antetitulo">Visión</span>
                <div class="cita-noche"><?= $vision ?></div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($centros): ?>
        <h3 class="etiqueta-noche">Nuestras sedes</h3>
        <ul class="sedes-noche">
            <?php foreach ($centros as $centro): ?>
            <li>
                <i class="bi bi-house-heart text-dorado fs-5" aria-hidden="true"></i>
                <span class="tipo"><?= $centro['tipo'] === 'sede' ? 'Sede parroquial' : 'Centro' ?></span>
                <span class="nombre"><?= e($centro['nombre']) ?></span>
                <?php if ($centro['direccion']): ?>
                <span class="zona"><i class="bi bi-geo-alt" aria-hidden="true"></i><?= e($centro['direccion']) ?></span>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <?php if (Router::existeRutaPublica('nosotros')): ?>
        <a href="<?= e(url_publica('nosotros')) ?>" class="enlace-flecha enlace-claro mt-5">Conoce a nuestro equipo pastoral <i class="bi bi-arrow-right"></i></a>
        <?php endif; ?>
    </div>
</section>


<?php /* ================================================================
         CONTACTO — datos a la izquierda, mapa a la derecha.
         ================================================================ */ ?>
<?php
$direccionCompleta = trim(implode(', ', array_filter([
    Config::get('direccion'),
    trim(Config::get('cp') . ' ' . Config::get('ciudad')),
])), ', ');
$tel = tel_enlace(Config::get('telefono'));
$wa  = whatsapp_enlace(Config::get('whatsapp'));
$datosContacto = array_filter([
    Config::tiene('direccion') ? ['bi-geo-alt', 'Dirección', $direccionCompleta,
        'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($direccionCompleta), true] : null,
    $tel ? ['bi-telephone', 'Teléfono', $telefonoLegible(Config::get('telefono')), $tel, false] : null,
    $wa ? ['bi-whatsapp', 'WhatsApp', $telefonoLegible(Config::get('whatsapp')), $wa, true] : null,
    Config::tiene('email') ? ['bi-envelope', 'Correo', Config::get('email'), 'mailto:' . Config::get('email'), false] : null,
]);
?>
<section id="contacto" class="franja">
    <div class="container">
        <div class="row g-4 g-lg-5">
            <div class="col-lg-5">
                <div class="encabezado-seccion">
                    <span class="antetitulo">Contacto</span>
                    <h2 class="titulo-seccion">Ven a visitarnos</h2>
                    <p>Escríbenos o llámanos en horario de oficina; para cualquier trámite de sacramentos te atendemos en persona.</p>
                </div>

                <ul class="datos-contacto">
                    <?php foreach ($datosContacto as [$icono, $etiqueta, $texto, $enlace, $externo]): ?>
                    <li>
                        <span class="icono"><i class="bi <?= e($icono) ?>" aria-hidden="true"></i></span>
                        <span class="min-w-0">
                            <span class="etiqueta"><?= e($etiqueta) ?></span>
                            <a href="<?= e($enlace) ?>" <?= $externo ? 'target="_blank" rel="noopener"' : '' ?>><?= e($texto) ?></a>
                        </span>
                    </li>
                    <?php endforeach; ?>
                    <?php if ($renglonesOficina): ?>
                    <li>
                        <span class="icono"><i class="bi bi-clock" aria-hidden="true"></i></span>
                        <span>
                            <span class="etiqueta">Oficina</span>
                            <?php if ($oficinaEnPares): ?>
                            <?php foreach (array_chunk($renglonesOficina, 2) as [$dias, $horas]): ?>
                            <span class="d-block"><?= e($dias) ?>: <?= e($horas) ?></span>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <span class="d-block"><?= nl2br(e(implode("\n", $renglonesOficina))) ?></span>
                            <?php endif; ?>
                        </span>
                    </li>
                    <?php endif; ?>
                </ul>

                <div class="d-flex flex-wrap gap-2 mt-4">
                    <?php if (Config::tiene('facebook')): ?>
                    <a href="<?= e(Config::get('facebook')) ?>" class="btn btn-outline-primary" target="_blank" rel="noopener">
                        <i class="bi bi-facebook me-1"></i>Síguenos en Facebook
                    </a>
                    <?php endif; ?>
                    <?php if (Router::existeRutaPublica('contacto')): ?>
                    <a href="<?= e(url_publica('contacto')) ?>" class="btn btn-primary">
                        <i class="bi bi-send me-1"></i>Escríbenos
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (Config::tiene('mapa_embed')): ?>
            <div class="col-lg-7">
                <div class="mapa-contacto">
                    <iframe title="Ubicación de la parroquia en Google Maps" src="<?= e(Config::get('mapa_embed')) ?>"
                            loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>
