<h1 class="titulo-pagina mb-4">Eventos</h1>

<div class="card border-0 shadow-sm mb-5" id="calendario" data-anio="<?= (int) $anio ?>" data-mes="<?= (int) $mes ?>">
    <div class="card-body p-3 p-md-4">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="<?= e($urlMesAnterior) ?>" class="btn btn-sm btn-outline-secondary" data-calendario-nav="anterior"
               aria-label="Mes anterior">
                <i class="bi bi-chevron-left"></i>
            </a>
            <h2 class="h5 fw-bold mb-0" data-calendario-titulo><?= e($nombreMes) ?></h2>
            <a href="<?= e($urlMesSiguiente) ?>" class="btn btn-sm btn-outline-secondary" data-calendario-nav="siguiente"
               aria-label="Mes siguiente">
                <i class="bi bi-chevron-right"></i>
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered calendario-tabla mb-0">
                <thead>
                    <tr class="text-center small text-uppercase">
                        <?php foreach (['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'] as $dia): ?>
                        <th><?= $dia ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody data-calendario-cuerpo>
                    <?php foreach ($semanas as $semana): ?>
                    <tr>
                        <?php foreach ($semana as $celda): ?>
                        <td class="<?= $celda && $celda['hoy'] ? 'dia-hoy' : '' ?>">
                            <?php if ($celda): ?>
                            <div class="numero-dia"><?= $celda['dia'] ?></div>
                            <?php foreach ($celda['eventos'] as $evento): ?>
                            <a href="<?= e(url_publica('eventos', ['slug' => $evento['slug']])) ?>"
                               class="evento-punto" style="background:<?= e($evento['color'] ?: '#1e4d8b') ?>"
                               title="<?= e($evento['titulo']) ?>">
                                <?= e($evento['titulo']) ?>
                            </a>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
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
