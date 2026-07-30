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

<?php if (!empty($proximasMisas)): ?>
<section class="mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h2 class="h6 text-uppercase text-muted mb-3 text-center">Próximas misas</h2>
            <div class="row g-3">
                <?php foreach ($proximasMisas as $misa): ?>
                <div class="col-sm-4">
                    <div class="card border-0 shadow-sm text-center h-100">
                        <div class="card-body p-3">
                            <div class="fw-bold text-capitalize"><?= e(nombre_dia((int) $misa['dia_semana'])) ?></div>
                            <div class="text-dorado fs-5 fw-bold"><?= e(hora_corta($misa['hora'])) ?></div>
                            <?php if ($misa['lugar']): ?>
                            <div class="text-muted small"><?= e($misa['lugar']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <p class="text-center mt-3 mb-0">
                <a href="<?= e(url_publica('horarios')) ?>" class="small">Ver todos los horarios <i class="bi bi-arrow-right"></i></a>
            </p>
        </div>
    </div>
</section>
<?php endif; ?>

<?php /* Lo que viene: eventos y cursos, uno en cada columna. Los datos de
         contacto no se repiten aquí porque el pie de página ya los lleva en
         todas las pantallas del sitio. */ ?>
<?php if (!empty($proximosEventos) || !empty($proximosCursos)): ?>
<div class="row justify-content-center mt-1">
<div class="col-lg-8">
<div class="row g-4 justify-content-center">

    <?php if (!empty($proximosEventos)): ?>
    <div class="col-md-6">
        <h2 class="h6 text-uppercase text-muted mb-3">Próximos eventos</h2>
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
    </div>
    <?php endif; ?>

    <?php if (!empty($proximosCursos)): ?>
    <div class="col-md-6">
        <h2 class="h6 text-uppercase text-muted mb-3">Próximos cursos</h2>
        <div class="d-flex flex-column gap-2">
            <?php foreach ($proximosCursos as $curso): ?>
            <?php
            // Se avisa de la inscripción solo si de verdad se puede uno inscribir:
            // la casilla abierta y, si hay fecha de cierre, que no haya pasado.
            $abierta = $curso['inscripciones_abiertas']
                && (!$curso['fecha_cierre_inscripcion'] || $curso['fecha_cierre_inscripcion'] >= date('Y-m-d'));
            ?>
            <a href="<?= e(url_publica('cursos', ['slug' => $curso['slug']])) ?>"
               class="card border-0 shadow-sm text-decoration-none">
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
            <?php endforeach; ?>
        </div>
        <p class="mt-2 mb-0">
            <a href="<?= e(url_publica('cursos')) ?>" class="small">Ver todos los cursos <i class="bi bi-arrow-right"></i></a>
        </p>
    </div>
    <?php endif; ?>

</div>
</div>
</div>
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
