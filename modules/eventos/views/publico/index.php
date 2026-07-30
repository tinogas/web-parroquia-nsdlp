<h1 class="titulo-pagina mb-4"><?= $pastoral ? 'Eventos de ' . e($pastoral['nombre']) : 'Eventos' ?></h1>

<?php if ($pastoral): ?>
<p class="mb-4">
    <a href="<?= e(url_publica('eventos')) ?>" class="small text-decoration-none">
        <i class="bi bi-arrow-left me-1"></i>Ver el calendario de todas las pastorales
    </a>
</p>
<?php endif; ?>

<div data-calendario-contenedor>
    <?php require BASE_PATH . '/modules/eventos/views/publico/calendario.php'; ?>
</div>

<h2 class="h5 fw-bold mb-3">Próximos eventos</h2>

<?php if (!$proximos): ?>
<p class="text-muted fst-italic">No hay eventos próximos registrados.</p>
<?php endif; ?>

<div class="row g-3">
    <?php foreach ($proximos as $evento): ?>
    <div class="col-md-6">
        <a href="<?= e(url_publica('eventos', ['slug' => $evento['slug']])) ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3 d-flex gap-3 align-items-center">
                    <div class="fecha-destacada" style="border-color:<?= e($evento['color'] ?: '#1e4d8b') ?>">
                        <span class="dia"><?= e(date('j', strtotime($evento['fecha_inicio']))) ?></span>
                        <span class="mes text-uppercase"><?= e(mes_abreviado($evento['fecha_inicio'])) ?></span>
                    </div>
                    <div>
                        <h3 class="h6 fw-bold mb-1 text-body"><?= e($evento['titulo']) ?></h3>
                        <p class="small text-muted mb-0">
                            <?php if (!$evento['todo_el_dia']): ?><?= e(hora_corta(substr($evento['fecha_inicio'], 11))) ?> · <?php endif; ?>
                            <?= e($evento['lugar'] ?: 'Parroquia Nuestra Señora de la Paz') ?>
                        </p>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>
