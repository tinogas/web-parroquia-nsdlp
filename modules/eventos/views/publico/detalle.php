<div class="row justify-content-center">
    <div class="col-lg-8">

        <nav aria-label="Ubicación" class="mb-3">
            <a href="<?= e(url_publica('eventos')) ?>" class="small text-decoration-none">
                <i class="bi bi-arrow-left me-1"></i>Todos los eventos
            </a>
        </nav>

        <h1 class="titulo-pagina mb-3"><?= e($evento['titulo']) ?></h1>

        <?php $mismoDia = !$evento['fecha_fin'] || substr($evento['fecha_fin'], 0, 10) === substr($evento['fecha_inicio'], 0, 10); ?>
        <ul class="list-unstyled lista-contacto mb-4">
            <li>
                <i class="bi bi-calendar-event text-primary"></i>
                <span>
                    <?php if ($mismoDia): ?>
                        <?= e(fecha_con_dia($evento['fecha_inicio'])) ?>
                        <?php if (!$evento['todo_el_dia']): ?>
                            , <?= e(hora_corta(substr($evento['fecha_inicio'], 11))) ?>
                            <?php if ($evento['fecha_fin']): ?> – <?= e(hora_corta(substr($evento['fecha_fin'], 11))) ?><?php endif; ?>
                        <?php endif; ?>
                    <?php else: ?>
                        Del <?= e(fecha_con_dia($evento['fecha_inicio'])) ?>
                        <?php if (!$evento['todo_el_dia']): ?>, <?= e(hora_corta(substr($evento['fecha_inicio'], 11))) ?><?php endif; ?>
                        al <?= e(fecha_con_dia($evento['fecha_fin'])) ?>
                        <?php if (!$evento['todo_el_dia']): ?>, <?= e(hora_corta(substr($evento['fecha_fin'], 11))) ?><?php endif; ?>
                    <?php endif; ?>
                </span>
            </li>
            <?php if ($evento['lugar']): ?>
            <li><i class="bi bi-geo-alt text-primary"></i> <?= e($evento['lugar']) ?></li>
            <?php endif; ?>
        </ul>

        <?php if ($evento['imagen']): ?>
        <img src="<?= e(url_activo($evento['imagen'])) ?>" alt="" class="img-fluid rounded mb-4">
        <?php endif; ?>

        <?php if (!empty($evento['descripcion'])): ?>
        <div class="contenido-editorial">
            <?= $evento['descripcion'] ?>
        </div>
        <?php endif; ?>

    </div>
</div>
