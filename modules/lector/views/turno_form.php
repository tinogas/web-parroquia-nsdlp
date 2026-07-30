<?php $esNuevo = $turno === null; ?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <nav aria-label="Ubicación">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item"><a href="<?= e(url_admin('lector')) ?>" class="text-decoration-none">Lectores</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= $esNuevo ? 'Nuevo' : 'Editar' ?></li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0"><?= $esNuevo ? 'Nuevo turno' : e($titulo) ?></h1>
    </div>
    <a href="<?= e(url_admin('lector')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<form method="POST" accept-charset="UTF-8" action="<?= e(url_post('admin', 'lector', 'turno_guardar')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="id" value="<?= $esNuevo ? 0 : (int) $turno['id'] ?>">

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label for="fecha" class="form-label fw-semibold">Fecha</label>
                    <input type="date" name="fecha" id="fecha" class="form-control"
                           value="<?= e($esNuevo ? $fechaSugerida : (string) $turno['fecha']) ?>" required>
                </div>
                <div class="col-md-3">
                    <label for="hora" class="form-label fw-semibold">Hora <span class="text-muted fw-normal">(opcional)</span></label>
                    <input type="time" name="hora" id="hora" class="form-control"
                           value="<?= e($esNuevo || !$turno['hora'] ? '' : substr((string) $turno['hora'], 0, 5)) ?>">
                </div>
                <div class="col-md-5">
                    <label for="descripcion" class="form-label fw-semibold">Qué se cubre</label>
                    <input type="text" name="descripcion" id="descripcion" class="form-control" maxlength="160"
                           value="<?= e($esNuevo ? '' : $turno['descripcion']) ?>"
                           placeholder="Ej. Misa 12:00, primera lectura" required>
                </div>
            </div>

            <div class="mb-3">
                <label for="color_liturgico_id" class="form-label fw-semibold">
                    Color litúrgico <span class="text-muted fw-normal">(opcional)</span>
                </label>
                <select name="color_liturgico_id" id="color_liturgico_id" class="form-select">
                    <option value="">Sin asignar</option>
                    <?php foreach ($colores as $color): ?>
                    <option value="<?= (int) $color['id'] ?>"
                        <?= (!$esNuevo && (int) ($turno['color_liturgico_id'] ?? 0) === (int) $color['id']) ? 'selected' : '' ?>>
                        <?= e($color['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">
                    <a href="<?= e(url_admin('mesc', 'colores')) ?>" target="_blank">Ver el significado de cada color</a>.
                </div>
            </div>

            <label class="form-label fw-semibold">Lectores asignados</label>
            <?php if (!$lectores): ?>
            <p class="text-muted small">Todavía no hay lectores activos registrados.
                <a href="<?= e(url_admin('lector', 'lectores')) ?>">Agregar uno</a>.
            </p>
            <?php else: ?>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-1 mb-2">
                <?php foreach ($lectores as $lector): ?>
                <div class="col">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="lectores[]"
                               value="<?= (int) $lector['id'] ?>" id="lec<?= (int) $lector['id'] ?>"
                               <?= in_array((int) $lector['id'], $asignados, true) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="lec<?= (int) $lector['id'] ?>">
                            <?= e($lector['nombre']) ?>
                        </label>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary flex-grow-1">
            <i class="bi bi-check-lg me-1"></i>Guardar
        </button>
        <a href="<?= e(url_admin('lector')) ?>" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>
