<?php $esNuevo = $horario === null; ?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <nav aria-label="Ubicación">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item">
                    <a href="<?= e(url_admin('horarios')) ?>" class="text-decoration-none">Horarios</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page"><?= $esNuevo ? 'Nuevo' : 'Editar' ?></li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0"><?= $esNuevo ? 'Nuevo horario' : 'Editar horario' ?></h1>
    </div>
    <a href="<?= e(url_admin('horarios')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<form method="POST" accept-charset="UTF-8" action="<?= e(url_post('admin', 'horarios', 'guardar')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="id" value="<?= $esNuevo ? 0 : (int) $horario['id'] ?>">

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="tipo" class="form-label fw-semibold">Tipo</label>
                            <select name="tipo" id="tipo" class="form-select">
                                <?php foreach (HorarioModel::TIPOS as $valor => [$etiqueta, ]): ?>
                                <option value="<?= e($valor) ?>"
                                    <?= (!$esNuevo && $horario['tipo'] === $valor) ? 'selected' : '' ?>>
                                    <?= e($etiqueta) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="dia_semana" class="form-label fw-semibold">Día de la semana</label>
                            <select name="dia_semana" id="dia_semana" class="form-select">
                                <?php for ($d = 0; $d <= 6; $d++): ?>
                                <option value="<?= $d ?>"
                                    <?= (!$esNuevo && (int) $horario['dia_semana'] === $d) ? 'selected' : '' ?>>
                                    <?= e(ucfirst(nombre_dia($d))) ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="centro_id" class="form-label fw-semibold">Sede o centro</label>
                        <select name="centro_id" id="centro_id" class="form-select">
                            <option value="">Sin asignar</option>
                            <?php foreach ($centros as $centro): ?>
                            <option value="<?= (int) $centro['id'] ?>"
                                <?= (!$esNuevo && (int) ($horario['centro_id'] ?? 0) === (int) $centro['id']) ? 'selected' : '' ?>>
                                <?= e($centro['nombre']) ?> (<?= e(CentroModel::TIPOS[$centro['tipo']] ?? $centro['tipo']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">En la página pública, los horarios se agrupan por sede o centro.</div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="hora" class="form-label fw-semibold">Hora</label>
                            <input type="time" name="hora" id="hora" class="form-control"
                                   value="<?= e($esNuevo ? '' : substr((string) $horario['hora'], 0, 5)) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="hora_fin" class="form-label fw-semibold">Hora de término</label>
                            <input type="time" name="hora_fin" id="hora_fin" class="form-control"
                                   value="<?= e($esNuevo || !$horario['hora_fin'] ? '' : substr((string) $horario['hora_fin'], 0, 5)) ?>">
                            <div class="form-text">Opcional. Útil para adoración u horario de oficina.</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="lugar" class="form-label fw-semibold">Lugar</label>
                        <input type="text" name="lugar" id="lugar" class="form-control"
                               value="<?= e($esNuevo ? '' : (string) $horario['lugar']) ?>" maxlength="120"
                               placeholder="Ej. Templo principal, capilla…">
                    </div>

                    <div class="mb-0">
                        <label for="nota" class="form-label fw-semibold">Nota</label>
                        <input type="text" name="nota" id="nota" class="form-control"
                               value="<?= e($esNuevo ? '' : (string) $horario['nota']) ?>" maxlength="160"
                               placeholder="Ej. Con coro, misa en inglés…">
                    </div>

                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h2 class="h6 fw-bold mb-3">Vigencia</h2>
                    <p class="text-muted small">
                        Déjalo en blanco para un horario de todo el año. Úsalo para horarios de
                        temporada, como Cuaresma o el horario de verano.
                    </p>

                    <div class="mb-3">
                        <label for="vigente_desde" class="form-label fw-semibold">Desde</label>
                        <input type="date" name="vigente_desde" id="vigente_desde" class="form-control"
                               value="<?= e($esNuevo ? '' : (string) $horario['vigente_desde']) ?>">
                    </div>
                    <div class="mb-3">
                        <label for="vigente_hasta" class="form-label fw-semibold">Hasta</label>
                        <input type="date" name="vigente_hasta" id="vigente_hasta" class="form-control"
                               value="<?= e($esNuevo ? '' : (string) $horario['vigente_hasta']) ?>">
                    </div>

                    <div class="mb-3">
                        <label for="orden" class="form-label fw-semibold">Orden</label>
                        <input type="number" name="orden" id="orden" class="form-control"
                               value="<?= (int) ($esNuevo ? 0 : $horario['orden']) ?>" min="0" max="999">
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               name="activo" id="activo" value="1"
                               <?= ($esNuevo || $horario['activo']) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="activo">Visible en el sitio</label>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-check-lg me-1"></i>Guardar
                </button>
                <a href="<?= e(url_admin('horarios')) ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </div>
    </div>
</form>
