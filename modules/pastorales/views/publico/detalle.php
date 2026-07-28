<div class="row justify-content-center">
    <div class="col-lg-9">

        <nav aria-label="Ubicación" class="mb-3">
            <a href="<?= e(url_publica('pastorales')) ?>" class="small text-decoration-none">
                <i class="bi bi-arrow-left me-1"></i>Todas las pastorales
            </a>
        </nav>

        <div class="d-flex align-items-center gap-2 mb-3">
            <i class="bi <?= e($pastoral['icono'] ?: 'bi-people') ?> fs-2 text-dorado"></i>
            <h1 class="titulo-pagina mb-0"><?= e($pastoral['nombre']) ?></h1>
        </div>

        <?php if ($pastoral['imagen']): ?>
        <img src="<?= e(url_activo($pastoral['imagen'])) ?>" alt="" class="img-fluid rounded mb-4">
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-8">
                <?php if (!empty($pastoral['descripcion'])): ?>
                <div class="contenido-editorial mb-4">
                    <?= $pastoral['descripcion'] ?>
                </div>
                <?php elseif ($pastoral['descripcion_corta']): ?>
                <p class="mb-4"><?= e($pastoral['descripcion_corta']) ?></p>
                <?php endif; ?>

                <?php if ($actividades): ?>
                <h2 class="h5 fw-bold mb-3">Actividades</h2>
                <div class="row g-3 mb-4">
                    <?php foreach ($actividades as $actividad): ?>
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body p-3">
                                <span class="badge bg-secondary-subtle text-secondary-emphasis mb-2">
                                    <?= e(PastoralModel::TIPOS_ACTIVIDAD[$actividad['tipo']] ?? $actividad['tipo']) ?>
                                </span>
                                <h3 class="h6 fw-bold mb-1"><?= e($actividad['titulo']) ?></h3>
                                <?php if ($actividad['descripcion']): ?>
                                <p class="small text-muted mb-0"><?= e($actividad['descripcion']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h2 class="h6 fw-bold mb-3">Información</h2>
                        <ul class="list-unstyled lista-contacto mb-0">
                            <?php if ($pastoral['responsable_nombre']): ?>
                            <li><i class="bi bi-person text-primary"></i> <?= e($pastoral['responsable_nombre']) ?></li>
                            <?php endif; ?>
                            <?php if ($pastoral['dia_reunion'] || $pastoral['hora_reunion']): ?>
                            <li>
                                <i class="bi bi-clock text-primary"></i>
                                <?= e($pastoral['dia_reunion']) ?>
                                <?php if ($pastoral['hora_reunion']): ?>, <?= e(hora_corta($pastoral['hora_reunion'])) ?><?php endif; ?>
                            </li>
                            <?php endif; ?>
                            <?php if ($pastoral['lugar_reunion']): ?>
                            <li><i class="bi bi-geo-alt text-primary"></i> <?= e($pastoral['lugar_reunion']) ?></li>
                            <?php endif; ?>
                            <?php if ($pastoral['contacto_email']): ?>
                            <li>
                                <i class="bi bi-envelope text-primary"></i>
                                <a href="mailto:<?= e($pastoral['contacto_email']) ?>"><?= e($pastoral['contacto_email']) ?></a>
                            </li>
                            <?php endif; ?>
                            <?php if ($pastoral['contacto_telefono']): ?>
                            <li>
                                <i class="bi bi-telephone text-primary"></i>
                                <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $pastoral['contacto_telefono'])) ?>">
                                    <?= e($pastoral['contacto_telefono']) ?>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                        <?php if ($pastoral['acepta_voluntarios']): ?>
                        <div class="alert alert-light border small mt-3 mb-0">
                            <i class="bi bi-hand-thumbs-up text-primary me-1"></i>
                            Esta pastoral recibe nuevos voluntarios. Acércate a la oficina parroquial si quieres sumarte.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
