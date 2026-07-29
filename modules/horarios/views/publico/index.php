<h1 class="titulo-pagina mb-2">Horarios</h1>

<?php if (!empty($bloques['horarios_intro']['contenido'])): ?>
<div class="contenido-editorial mb-4">
    <?= $bloques['horarios_intro']['contenido'] ?>
</div>
<?php endif; ?>

<?php if (!$porCentro): ?>
<p class="text-muted fst-italic">Los horarios se están actualizando. Vuelve pronto.</p>
<?php endif; ?>

<div class="row g-4">
    <?php foreach ($porCentro as $grupo): ?>
        <?php $icono = $grupo['tipo'] === 'sede' ? 'bi-building' : ($grupo['tipo'] === 'centro' ? 'bi-geo-alt' : 'bi-calendar3'); ?>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h2 class="h6 fw-bold mb-3">
                        <i class="bi <?= e($icono) ?> text-dorado me-1"></i><?= e($grupo['nombre']) ?>
                    </h2>
                    <?php foreach ($grupo['dias'] as $dia => $horariosDelDia): ?>
                    <div class="mb-3">
                        <p class="horario-dia-titulo mb-1"><?= e(ucfirst(nombre_dia((int) $dia))) ?></p>
                        <ul class="list-unstyled mb-0 lista-horarios">
                            <?php foreach ($horariosDelDia as $horario): ?>
                            <li>
                                <span class="badge bg-secondary-subtle text-secondary-emphasis">
                                    <?= e(HorarioModel::TIPOS[$horario['tipo']][0] ?? $horario['tipo']) ?>
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
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
