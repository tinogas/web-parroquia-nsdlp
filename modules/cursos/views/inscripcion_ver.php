<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <nav aria-label="Ubicación">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item">
                    <a href="<?= e(url_admin('inscripciones')) ?>" class="text-decoration-none">Inscripciones</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page"><?= e($inscripcion['folio']) ?></li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-1">
            <?= e($inscripcion['curso_titulo']) ?>
            <span class="font-monospace fs-6 text-muted"><?= e($inscripcion['folio']) ?></span>
        </h1>
        <p class="text-muted mb-0 small">Recibida el <?= e(fecha_larga($inscripcion['created_at'])) ?></p>
    </div>
    <a href="<?= e(url_admin('inscripciones')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h2 class="h6 fw-bold mb-3">Datos de quien se inscribe</h2>
                <dl class="row mb-0 small">
                    <dt class="col-sm-4">Nombre</dt>
                    <dd class="col-sm-8"><?= e($inscripcion['nombre']) ?></dd>

                    <?php if ($inscripcion['fecha_nacimiento']): ?>
                    <dt class="col-sm-4">Fecha de nacimiento</dt>
                    <dd class="col-sm-8">
                        <?= e(fecha_larga($inscripcion['fecha_nacimiento'])) ?>
                        <?php if ($inscripcion['es_menor']): ?>
                        <span class="badge bg-info-subtle text-info-emphasis">Menor de edad</span>
                        <?php endif; ?>
                    </dd>
                    <?php endif; ?>

                    <?php if ($inscripcion['telefono']): ?>
                    <dt class="col-sm-4">Teléfono</dt>
                    <dd class="col-sm-8"><?= e($inscripcion['telefono']) ?></dd>
                    <?php endif; ?>

                    <dt class="col-sm-4">Correo</dt>
                    <dd class="col-sm-8"><a href="mailto:<?= e($inscripcion['email']) ?>"><?= e($inscripcion['email']) ?></a></dd>

                    <?php if ($inscripcion['notas']): ?>
                    <dt class="col-sm-4">Notas</dt>
                    <dd class="col-sm-8"><?= e($inscripcion['notas']) ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <?php if ($inscripcion['es_menor']): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h2 class="h6 fw-bold mb-3">Padre, madre o tutor</h2>
                <dl class="row mb-0 small">
                    <dt class="col-sm-4">Nombre</dt>
                    <dd class="col-sm-8"><?= e((string) $inscripcion['tutor_nombre']) ?></dd>
                    <dt class="col-sm-4">Parentesco</dt>
                    <dd class="col-sm-8"><?= e((string) $inscripcion['tutor_parentesco']) ?></dd>
                    <dt class="col-sm-4">Teléfono</dt>
                    <dd class="col-sm-8"><?= e((string) $inscripcion['tutor_telefono']) ?></dd>
                </dl>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h2 class="h6 fw-bold mb-3">Estado</h2>

                <?php if ($inscripcion['estado'] === 'lista_espera'): ?>
                <div class="alert alert-warning small">
                    <i class="bi bi-hourglass-split me-1"></i>Está en lista de espera: el curso no tenía lugar disponible al momento de inscribirse.
                </div>
                <?php endif; ?>

                <?php if (Auth::tienePermiso('inscripciones.editar')): ?>
                <form method="POST" accept-charset="UTF-8" action="<?= e(url_post('admin', 'inscripciones', 'cambiarEstado')) ?>">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="id" value="<?= (int) $inscripcion['id'] ?>">
                    <div class="mb-3">
                        <select name="estado" class="form-select">
                            <?php foreach (InscripcionCursoModel::ESTADOS as $valor => $etiqueta): ?>
                            <option value="<?= e($valor) ?>" <?= $inscripcion['estado'] === $valor ? 'selected' : '' ?>>
                                <?= e($etiqueta) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-check-lg me-1"></i>Actualizar estado
                    </button>
                </form>
                <?php else: ?>
                <span class="badge bg-secondary-subtle text-secondary-emphasis">
                    <?= e(InscripcionCursoModel::ESTADOS[$inscripcion['estado']]) ?>
                </span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
