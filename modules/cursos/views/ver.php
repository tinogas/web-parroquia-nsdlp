<?php
/**
 * Lectura de un curso dentro del panel, hermana de avisos/views/ver.php: lo
 * que abre un miembro de la pastoral cuando le llega un curso interno, antes
 * de que exista página pública que enseñarle.
 */
$estado = estado_publicacion($curso);
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <nav aria-label="Ubicación">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item">
                    <a href="<?= e(url_admin('cursos')) ?>" class="text-decoration-none">Cursos</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Leer</li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0"><?= e($curso['titulo']) ?></h1>
    </div>
    <div class="d-flex gap-2">
        <?php if ($puedeEditar): ?>
        <a href="<?= e(url_admin('cursos', 'editar', ['id' => $curso['id']])) ?>" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-pencil me-1"></i>Editar
        </a>
        <?php endif; ?>
        <a href="<?= e(url_admin('cursos')) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <?php if ($curso['imagen']): ?>
            <img src="<?= e(url_activo($curso['imagen'])) ?>" class="card-img-top" alt=""
                 style="max-height:320px;object-fit:cover">
            <?php endif; ?>
            <div class="card-body p-4">
                <?php if ($curso['dirigido_a']): ?>
                <p class="lead fs-6">Dirigido a <?= e($curso['dirigido_a']) ?>.</p>
                <?php endif; ?>

                <?php if ($curso['descripcion']): ?>
                <?= $curso['descripcion'] /* ya saneado con SanitizadorHtml al guardarse */ ?>
                <?php else: ?>
                <p class="text-muted fst-italic mb-0">Este curso todavía no tiene descripción.</p>
                <?php endif; ?>

                <?php if ($curso['objetivos']): ?>
                <h2 class="h6 fw-bold mt-4 mb-2">Objetivos</h2>
                <p class="mb-0"><?= nl2br(e($curso['objetivos'])) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($sesiones): ?>
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body p-4">
                <h2 class="h6 fw-bold mb-3">Temario</h2>
                <ol class="mb-0 ps-3">
                    <?php foreach ($sesiones as $sesion): ?>
                    <li class="mb-1">
                        <span class="fw-semibold"><?= e($sesion['titulo']) ?></span>
                        <?php if (!empty($sesion['descripcion'])): ?>
                        <span class="d-block text-muted small"><?= e($sesion['descripcion']) ?></span>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ol>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <p class="text-uppercase text-muted small fw-semibold mb-2">Ficha</p>

                <div class="mb-2"><?= badge_escalon($curso) ?></div>

                <dl class="row small mb-0">
                    <dt class="col-5 fw-semibold">Pastoral</dt>
                    <dd class="col-7"><?= e($pastoral['nombre'] ?? 'Toda la parroquia') ?></dd>

                    <dt class="col-5 fw-semibold">Modalidad</dt>
                    <dd class="col-7"><?= e(CursoModel::MODALIDADES[$curso['modalidad']] ?? $curso['modalidad']) ?></dd>

                    <?php if ($curso['fecha_inicio']): ?>
                    <dt class="col-5 fw-semibold">Empieza</dt>
                    <dd class="col-7"><?= e(fecha_larga($curso['fecha_inicio'])) ?></dd>
                    <?php endif; ?>

                    <?php if ($curso['horario']): ?>
                    <dt class="col-5 fw-semibold">Horario</dt>
                    <dd class="col-7"><?= e($curso['horario']) ?></dd>
                    <?php endif; ?>

                    <?php if ($curso['lugar']): ?>
                    <dt class="col-5 fw-semibold">Lugar</dt>
                    <dd class="col-7"><?= e($curso['lugar']) ?></dd>
                    <?php endif; ?>

                    <?php if ($curso['aportacion']): ?>
                    <dt class="col-5 fw-semibold">Aportación</dt>
                    <dd class="col-7"><?= e($curso['aportacion']) ?></dd>
                    <?php endif; ?>
                </dl>

                <?php if ($estado === 'publico'): ?>
                <a href="<?= e(url_publica('cursos', ['slug' => $curso['slug']])) ?>" target="_blank"
                   class="btn btn-sm btn-outline-secondary mt-3">
                    <i class="bi bi-box-arrow-up-right me-1"></i>Verlo en el sitio
                </a>
                <?php else: ?>
                <p class="small text-muted mb-0 mt-3">
                    <i class="bi bi-info-circle me-1"></i>Todavía no está en el sitio web, así que no acepta inscripciones.
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
