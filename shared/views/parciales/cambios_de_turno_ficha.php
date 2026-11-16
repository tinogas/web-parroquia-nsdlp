<?php
/**
 * En la ficha de un turno (turno_form.php de MESC y Proclamadores): quién
 * sirve realmente, quién estaba en el rol original y los cambios que lo
 * explican, con el acceso para registrar otro.
 *
 *   $modulo        'mesc' | 'proclamadores'
 *   $plural        'ministros' | 'proclamadores'
 *   $turnoRol      el turno pasado por CambiosTurno::aplicar()
 *   $cambiosTurno  CambiosTurno::cambiosDeTurno()
 */
?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h2 class="h6 fw-bold mb-0"><i class="bi bi-arrow-left-right me-1"></i>Cambios de este turno</h2>
            <?php if ((string) $turnoRol['fecha'] < date('Y-m-d')): ?>
            <?php /* Lo que ya pasó no se mueve: ver CambiosTurno::yaPaso(). */ ?>
            <span class="small text-muted"><i class="bi bi-lock me-1"></i>El turno ya pasó: no admite cambios.</span>
            <?php elseif (Auth::tienePermiso($modulo . '.editar')): ?>
            <a href="<?= e(url_admin($modulo, 'cambios', ['turno' => (int) $turnoRol['id']])) ?>" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-plus-lg me-1"></i>Registrar permuta o cobertura
            </a>
            <?php endif; ?>
        </div>

        <?php if (!$cambiosTurno): ?>
        <p class="text-muted small mb-0">Sin cambios: sirven los <?= e($plural) ?> del rol original.</p>
        <?php else: ?>
        <dl class="row small mb-3">
            <dt class="col-sm-4">Sirven</dt>
            <dd class="col-sm-8">
                <?php foreach ($turnoRol['actuales'] as $id => $nombre): ?>
                <span class="badge <?= isset($turnoRol['entraron'][$id]) ? 'text-bg-success' : 'text-bg-light border' ?> me-1"><?= e($nombre) ?></span>
                <?php endforeach; ?>
                <?php if (!$turnoRol['actuales']): ?><span class="text-muted">nadie</span><?php endif; ?>
            </dd>
            <dt class="col-sm-4">Rol original</dt>
            <dd class="col-sm-8 text-muted"><?= e($turnoRol['nombres_originales'] ?: 'sin asignar') ?></dd>
        </dl>
        <ul class="list-unstyled small mb-0">
            <?php foreach ($cambiosTurno as $cambio): ?>
            <li class="mb-1">
                <i class="bi bi-dot"></i><?= e(CambiosTurno::resumen($cambio)) ?>
                <?php if ($cambio['notas']): ?><span class="text-muted fst-italic">— <?= e($cambio['notas']) ?></span><?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>
