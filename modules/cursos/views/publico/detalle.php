<div class="row justify-content-center">
    <div class="col-lg-8">

        <nav aria-label="Ubicación" class="mb-3">
            <a href="<?= e(url_publica('cursos')) ?>" class="small text-decoration-none">
                <i class="bi bi-arrow-left me-1"></i>Todos los cursos
            </a>
        </nav>

        <span class="badge bg-secondary-subtle text-secondary-emphasis mb-2">
            <?= e(CursoModel::MODALIDADES[$curso['modalidad']] ?? $curso['modalidad']) ?>
        </span>
        <h1 class="titulo-pagina mb-3"><?= e($curso['titulo']) ?></h1>

        <?php if ($curso['imagen']): ?>
        <img src="<?= e(url_activo($curso['imagen'])) ?>" alt="" class="img-fluid rounded mb-4">
        <?php endif; ?>

        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <?php if (!empty($curso['descripcion'])): ?>
                <div class="contenido-editorial mb-4">
                    <?= $curso['descripcion'] ?>
                </div>
                <?php endif; ?>

                <?php if ($curso['objetivos']): ?>
                <h2 class="h6 fw-bold mb-2">Objetivos</h2>
                <p><?= nl2br(e($curso['objetivos'])) ?></p>
                <?php endif; ?>

                <?php if ($sesiones): ?>
                <h2 class="h6 fw-bold mb-3 mt-4">Temario</h2>
                <ol class="list-group list-group-numbered mb-4">
                    <?php foreach ($sesiones as $sesion): ?>
                    <li class="list-group-item">
                        <div class="fw-semibold"><?= e($sesion['titulo']) ?></div>
                        <?php if ($sesion['fecha']): ?>
                        <div class="text-muted small"><?= e(fecha_larga($sesion['fecha'])) ?></div>
                        <?php endif; ?>
                        <?php if ($sesion['descripcion']): ?>
                        <div class="small mt-1"><?= e($sesion['descripcion']) ?></div>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ol>
                <?php endif; ?>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h2 class="h6 fw-bold mb-3">Información</h2>
                        <ul class="list-unstyled lista-contacto mb-0">
                            <?php if ($curso['dirigido_a']): ?>
                            <li><i class="bi bi-people text-primary"></i> <?= e($curso['dirigido_a']) ?></li>
                            <?php endif; ?>
                            <?php if ($curso['instructor_nombre']): ?>
                            <li><i class="bi bi-person-badge text-primary"></i> <?= e($curso['instructor_nombre']) ?></li>
                            <?php endif; ?>
                            <?php if ($curso['fecha_inicio']): ?>
                            <li>
                                <i class="bi bi-calendar3 text-primary"></i>
                                Del <?= e(fecha_larga($curso['fecha_inicio'])) ?>
                                <?php if ($curso['fecha_fin']): ?> al <?= e(fecha_larga($curso['fecha_fin'])) ?><?php endif; ?>
                            </li>
                            <?php endif; ?>
                            <?php if ($curso['horario']): ?>
                            <li><i class="bi bi-clock text-primary"></i> <?= e($curso['horario']) ?></li>
                            <?php endif; ?>
                            <?php if ($curso['lugar']): ?>
                            <li><i class="bi bi-geo-alt text-primary"></i> <?= e($curso['lugar']) ?></li>
                            <?php endif; ?>
                            <?php if ($curso['aportacion']): ?>
                            <li><i class="bi bi-cash-coin text-primary"></i> <?= e($curso['aportacion']) ?></li>
                            <?php endif; ?>
                        </ul>

                        <?php if ($curso['inscripciones_abiertas'] && !$cupoLleno): ?>
                        <a href="<?= e(url_publica('cursos', ['slug' => $curso['slug'], 'accion' => 'inscribirse'])) ?>"
                           class="btn btn-primary w-100 mt-3">
                            <i class="bi bi-pencil-square me-1"></i>Inscribirme
                        </a>
                        <?php elseif ($cupoLleno): ?>
                        <a href="<?= e(url_publica('cursos', ['slug' => $curso['slug'], 'accion' => 'inscribirse'])) ?>"
                           class="btn btn-outline-warning w-100 mt-3">
                            <i class="bi bi-hourglass-split me-1"></i>Cupo lleno: anotarme en lista de espera
                        </a>
                        <?php else: ?>
                        <p class="text-muted small mt-3 mb-0">
                            <i class="bi bi-info-circle me-1"></i>Las inscripciones para este curso están cerradas.
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
