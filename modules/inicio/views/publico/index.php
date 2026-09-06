<?php if (!empty($bloques['bienvenida_parroco']['contenido'])): ?>
<section class="mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-8 text-center">
            <?php if (!empty($bloques['bienvenida_parroco']['titulo'])): ?>
            <h2 class="h4 fw-bold mb-3"><?= e($bloques['bienvenida_parroco']['titulo']) ?></h2>
            <?php endif; ?>
            <div class="contenido-editorial">
                <?= $bloques['bienvenida_parroco']['contenido'] ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($bloques['inicio_intro']['contenido'])): ?>
<section class="mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-8 text-center text-muted contenido-editorial">
            <?= $bloques['inicio_intro']['contenido'] ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (empty($bloques['bienvenida_parroco']['contenido']) && empty($bloques['inicio_intro']['contenido'])): ?>
<div class="row justify-content-center mb-5">
    <div class="col-lg-8 text-center">
        <p class="lead text-muted">
            Estamos preparando el sitio de la parroquia. Aquí encontrarás pronto los
            horarios de misas, los requisitos de los sacramentos, las pastorales y los
            avisos de la comunidad.
        </p>
    </div>
</div>
<?php endif; ?>

<?php /* La portada se abre en dos columnas: a la izquierda el evangelio del día
         —el contenido que cambia más rápido de todo el sitio, a diario contra
         semanal de las misas— y a la derecha lo que se viene a consultar de un
         vistazo, las próximas misas y los próximos eventos. Cada bloque sigue
         desapareciendo por su cuenta si no tiene qué mostrar: ninguno deja un
         encabezado con un hueco debajo. Y si falta una columna entera, la que
         queda recupera el ancho de lectura del resto de la página en vez de
         quedarse angosta a un lado. */ ?>
<?php
$hayEvangelio   = !empty($evangelioHoy);
$hayLateral     = !empty($proximasMisas) || !empty($proximosEventos);
$anchoEvangelio = $hayLateral ? 'col-lg-7' : 'col-lg-8';
$anchoLateral   = $hayEvangelio ? 'col-lg-5' : 'col-lg-8';
?>
<?php if ($hayEvangelio || $hayLateral): ?>
<section class="mb-5">
    <div class="row justify-content-center g-4 g-lg-5">

        <?php if ($hayEvangelio): ?>
        <div class="<?= $anchoEvangelio ?>">
            <?php /* Las dos partes llevan su propia etiqueta, y son la misma
                     etiqueta con el mismo aspecto: así el párroco escribe solo
                     el texto y no tiene que abrir cada entrada poniendo
                     "REFLEXIÓN" a mano dentro del contenido —que es como
                     estaba, y dependía de que se acordara y lo formateara
                     igual todos los días—. La fecha va debajo de la primera,
                     que es la que encabeza la sección entera. */ ?>
            <h2 class="h6 text-uppercase fw-bold text-muted mb-2 text-center">Evangelio</h2>
            <p class="text-center text-muted small text-capitalize"><?= e(fecha_con_dia($evangelioHoy['fecha'])) ?></p>
            <?php /* Contenido ya saneado con lista blanca al guardarse: se imprime sin escapar a propósito. */ ?>
            <div class="contenido-editorial"><?= $evangelioHoy['evangelio'] ?></div>
            <?php if (!empty($evangelioHoy['reflexion'])): ?>
            <hr>
            <h3 class="h6 text-uppercase fw-bold text-muted mb-3 text-center">Reflexión</h3>
            <div class="contenido-editorial"><?= $evangelioHoy['reflexion'] ?></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($hayLateral): ?>
        <div class="<?= $anchoLateral ?>">

            <?php if (!empty($proximasMisas)): ?>
            <?php /* En una columna angosta las tres misas ya no caben lado a
                     lado sin partir la hora en dos renglones, así que van una
                     debajo de otra: el día y el lugar a la izquierda, la hora
                     a la derecha, que es el dato que se viene a buscar. */ ?>
            <h2 class="h6 text-uppercase text-muted mb-3">Próximas misas</h2>
            <div class="d-flex flex-column gap-2">
                <?php foreach ($proximasMisas as $misa): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-3 d-flex justify-content-between align-items-center gap-3">
                        <div>
                            <div class="fw-bold text-capitalize"><?= e(nombre_dia((int) $misa['dia_semana'])) ?></div>
                            <?php if ($misa['lugar']): ?>
                            <div class="text-muted small"><?= e($misa['lugar']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="text-dorado fs-5 fw-bold text-nowrap"><?= e(hora_corta($misa['hora'])) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <p class="mt-2 mb-0">
                <a href="<?= e(url_publica('horarios')) ?>" class="small">Ver todos los horarios <i class="bi bi-arrow-right"></i></a>
            </p>
            <?php endif; ?>

            <?php if (!empty($proximosEventos)): ?>
            <h2 class="h6 text-uppercase text-muted mb-3 <?= !empty($proximasMisas) ? 'mt-4' : '' ?>">Próximos eventos</h2>
            <div class="d-flex flex-column gap-2">
                <?php foreach ($proximosEventos as $evento): ?>
                <a href="<?= e(url_publica('eventos', ['slug' => $evento['slug']])) ?>"
                   class="card border-0 shadow-sm text-decoration-none">
                    <div class="card-body p-3 d-flex gap-3 align-items-center">
                        <div class="fecha-destacada" style="border-color:<?= e($evento['color'] ?: '#1e4d8b') ?>">
                            <span class="dia"><?= e(date('j', strtotime($evento['fecha_inicio']))) ?></span>
                            <span class="mes text-uppercase"><?= e(mes_abreviado($evento['fecha_inicio'])) ?></span>
                        </div>
                        <span class="fw-semibold text-body"><?= e($evento['titulo']) ?></span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <p class="mt-2 mb-0">
                <a href="<?= e(url_publica('eventos')) ?>" class="small">Ver el calendario completo <i class="bi bi-arrow-right"></i></a>
            </p>
            <?php endif; ?>

        </div>
        <?php endif; ?>

    </div>
</section>
<?php endif; ?>

<?php /* Los cursos se quedaron solos al subir los eventos a la columna de la
         derecha, así que ocupan su propia franja, al mismo ancho de lectura
         que el resto de la portada y con las tarjetas en rejilla en lugar de
         una debajo de otra. Los datos de contacto no se repiten aquí porque
         el pie de página ya los lleva en todas las pantallas del sitio. */ ?>
<?php if (!empty($proximosCursos)): ?>
<section class="mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h2 class="h6 text-uppercase text-muted mb-3">Próximos cursos</h2>
            <div class="row g-3">
                <?php foreach ($proximosCursos as $curso): ?>
                <?php
                // Se avisa de la inscripción solo si de verdad se puede uno inscribir:
                // la casilla abierta y, si hay fecha de cierre, que no haya pasado.
                $abierta = $curso['inscripciones_abiertas']
                    && (!$curso['fecha_cierre_inscripcion'] || $curso['fecha_cierre_inscripcion'] >= date('Y-m-d'));
                ?>
                <div class="col-md-4">
                    <a href="<?= e(url_publica('cursos', ['slug' => $curso['slug']])) ?>"
                       class="card border-0 shadow-sm text-decoration-none h-100">
                        <div class="card-body p-3">
                            <div class="d-flex flex-wrap gap-1 mb-2">
                                <span class="badge bg-secondary-subtle text-secondary-emphasis">
                                    <?= e(CursoModel::MODALIDADES[$curso['modalidad']] ?? $curso['modalidad']) ?>
                                </span>
                                <?php if ($abierta): ?>
                                <span class="badge bg-success-subtle text-success-emphasis">Inscripciones abiertas</span>
                                <?php endif; ?>
                            </div>
                            <span class="fw-semibold text-body d-block mb-1"><?= e($curso['titulo']) ?></span>
                            <?php if ($curso['fecha_inicio']): ?>
                            <span class="small text-muted d-block">
                                <i class="bi bi-calendar3 me-1"></i>Inicia el <?= e(fecha_larga($curso['fecha_inicio'])) ?>
                            </span>
                            <?php endif; ?>
                            <?php if ($curso['horario'] || $curso['lugar']): ?>
                            <span class="small text-muted d-block">
                                <?php if ($curso['horario']): ?>
                                <i class="bi bi-clock me-1"></i><?= e($curso['horario']) ?><?php endif; ?>
                                <?php if ($curso['horario'] && $curso['lugar']): ?> · <?php endif; ?>
                                <?php if ($curso['lugar']): ?>
                                <i class="bi bi-geo-alt me-1"></i><?= e($curso['lugar']) ?><?php endif; ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
            <p class="mt-3 mb-0">
                <a href="<?= e(url_publica('cursos')) ?>" class="small">Ver todos los cursos <i class="bi bi-arrow-right"></i></a>
            </p>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($avisosRecientes)): ?>
<div class="row justify-content-center mt-4">
<div class="col-lg-8">
    <h2 class="h6 text-uppercase text-muted mb-3">Últimos avisos</h2>
    <div class="row g-3">
        <?php foreach ($avisosRecientes as $aviso): ?>
        <div class="col-md-4">
            <a href="<?= e(url_publica('avisos', ['slug' => $aviso['slug']])) ?>"
               class="card border-0 shadow-sm h-100 text-decoration-none">
                <div class="card-body p-3">
                    <span class="fw-semibold text-body d-block"><?= e($aviso['titulo']) ?></span>
                    <span class="small text-muted"><?= e(fecha_larga($aviso['fecha_publicacion'])) ?></span>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <p class="mt-2 mb-0">
        <a href="<?= e(url_publica('avisos')) ?>" class="small">Ver todos los avisos <i class="bi bi-arrow-right"></i></a>
    </p>
</div>
</div>
<?php endif; ?>

<?php /* El widget oficial de Facebook (Page Plugin, sin SDK): un iframe que
         Facebook mismo mantiene con las últimas publicaciones de la página,
         tal como se ven ahí. Se activa capturando la URL en Configuración →
         Redes sociales, el mismo campo que ya alimentaba el icono del pie. */ ?>
<?php if (Config::tiene('facebook')): ?>
<div class="row justify-content-center mt-4">
<div class="col-lg-8">
    <h2 class="h6 text-uppercase text-muted mb-3">Lo último en Facebook</h2>
    <?php
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
    <div class="card border-0 shadow-sm overflow-hidden">
        <iframe src="<?= e($facebookSrc) ?>" width="100%" height="460"
                style="border:none;overflow:hidden" scrolling="no" loading="lazy"
                allowfullscreen="true" title="Publicaciones recientes en Facebook"></iframe>
    </div>
</div>
</div>
<?php endif; ?>

<?php if (!empty($bloques['ligas_interes']['contenido'])): ?>
<div class="row justify-content-center mt-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h2 class="h6 text-uppercase text-muted mb-3">
                    <?= e($bloques['ligas_interes']['titulo'] ?: 'Ligas de interés') ?>
                </h2>
                <div class="contenido-editorial">
                    <?= $bloques['ligas_interes']['contenido'] ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
