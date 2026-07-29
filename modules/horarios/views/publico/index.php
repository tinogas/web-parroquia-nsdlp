<h1 class="titulo-pagina mb-2">Horarios</h1>

<?php if (!empty($bloques['horarios_intro']['contenido'])): ?>
<div class="contenido-editorial mb-4">
    <?= $bloques['horarios_intro']['contenido'] ?>
</div>
<?php endif; ?>

<?php if (count($centros) > 1): ?>
<form method="GET" action="<?= e(url_publica('horarios')) ?>" class="row g-2 align-items-end mb-4">
    <div class="col-auto">
        <label for="centro" class="form-label small fw-semibold mb-1">Ver horarios de</label>
        <select name="centro" id="centro" class="form-select">
            <option value="" <?= $centroId === null ? 'selected' : '' ?>>Todos</option>
            <?php foreach ($centros as $centro): ?>
            <option value="<?= (int) $centro['id'] ?>" <?= $centroId === (int) $centro['id'] ? 'selected' : '' ?>>
                <?= e($centro['nombre']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-dorado">
            <i class="bi bi-funnel me-1"></i>Filtrar
        </button>
    </div>
</form>
<?php endif; ?>

<?php if (!$porTipo): ?>
<p class="text-muted fst-italic">Los horarios se están actualizando. Vuelve pronto.</p>
<?php endif; ?>

<div class="row g-4">
    <?php foreach ($porTipo as $tipo => $horariosDelTipo): ?>
        <?php [$nombreTipo, $icono] = HorarioModel::TIPOS[$tipo] ?? [$tipo, 'bi-calendar3']; ?>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h2 class="h6 fw-bold mb-3">
                        <i class="bi <?= e($icono) ?> text-dorado me-1"></i><?= e($nombreTipo) ?>
                    </h2>
                    <ul class="list-unstyled mb-0 lista-horarios">
                        <?php foreach ($horariosDelTipo as $horario): ?>
                        <li>
                            <span class="d-inline-flex align-items-center gap-2">
                                <span class="dia"><?= e(ucfirst(nombre_dia((int) $horario['dia_semana']))) ?></span>
                                <?php if ($horario['centro_nombre'] && $centroId === null): ?>
                                <span class="badge bg-secondary-subtle text-secondary-emphasis">
                                    <?= e($horario['centro_nombre']) ?>
                                </span>
                                <?php endif; ?>
                            </span>
                            <span class="hora">
                                <?= e(hora_corta($horario['hora'])) ?>
                                <?php if ($horario['hora_fin']): ?> – <?= e(hora_corta($horario['hora_fin'])) ?><?php endif; ?>
                            </span>
                            <?php if ($horario['lugar'] || $horario['nota']): ?>
                            <div class="detalle">
                                <?= e(trim(($horario['lugar'] ?? '') . ($horario['lugar'] && $horario['nota'] ? ' · ' : '') . ($horario['nota'] ?? ''))) ?>
                            </div>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
