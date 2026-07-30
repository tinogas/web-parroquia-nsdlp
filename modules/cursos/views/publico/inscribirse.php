<div class="row justify-content-center">
    <div class="col-lg-7">

        <nav aria-label="Ubicación" class="mb-3">
            <a href="<?= e(url_publica('cursos', ['slug' => $curso['slug']])) ?>" class="small text-decoration-none">
                <i class="bi bi-arrow-left me-1"></i>Volver a <?= e($curso['titulo']) ?>
            </a>
        </nav>

        <h1 class="titulo-pagina mb-2">Inscripción a <?= e($curso['titulo']) ?></h1>

        <?php if ($cupoLleno): ?>
        <div class="alert alert-warning">
            <i class="bi bi-hourglass-split me-1"></i>
            Este curso ya llegó a su cupo. Tu inscripción quedará en <strong>lista de espera</strong>
            y te avisaremos si se libera un lugar.
        </div>
        <?php endif; ?>

        <?php if (!empty($errores)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0 ps-3">
                <?php foreach ($errores as $error): ?>
                <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="POST" accept-charset="UTF-8"
              action="<?= e(url_post('publico', 'cursos', 'inscribirse', ['slug' => $curso['slug']])) ?>"
              class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <?= AntiSpam::campos() ?>

                <div class="row g-3 mb-3">
                    <div class="col-md-8">
                        <label for="nombre" class="form-label fw-semibold">Nombre completo</label>
                        <input type="text" name="nombre" id="nombre" class="form-control"
                               value="<?= e($valores['nombre'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="fecha_nacimiento" class="form-label fw-semibold">Fecha de nacimiento</label>
                        <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" class="form-control"
                               value="<?= e($valores['fecha_nacimiento'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label fw-semibold">Correo electrónico</label>
                        <input type="email" name="email" id="email" class="form-control"
                               value="<?= e($valores['email'] ?? '') ?>" required>
                        <div class="form-text">Con él evitamos que te inscribas dos veces por error.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="telefono" class="form-label fw-semibold">Teléfono</label>
                        <input type="tel" name="telefono" id="telefono" class="form-control"
                               value="<?= e($valores['telefono'] ?? '') ?>">
                    </div>
                    <div class="col-md-12">
                        <label for="centro" class="form-label fw-semibold">Centro al que perteneces</label>
                        <input type="text" name="centro" id="centro" class="form-control" maxlength="140"
                               value="<?= e($valores['centro'] ?? '') ?>" placeholder="Ej. Sede, San Pío, Centro Guadalupe…">
                    </div>
                </div>

                <?php $tieneTutor = !empty($valores['tiene_tutor']); ?>
                <h2 class="h6 fw-bold mb-2">Padre, madre o tutor</h2>
                <p class="text-muted small mb-2">Obligatorio si quien se inscribe es menor de edad.</p>
                <input class="form-check-input" type="checkbox" name="tiene_tutor" id="tiene_tutor"
                       value="1" <?= $tieneTutor ? 'checked' : '' ?>>
                <label class="form-check-label ms-2 mb-3 d-inline-block" for="tiene_tutor">
                    Sí, voy a completar los datos de padre, madre o tutor
                </label>
                <?php /* .campos-tutor se revela solo con CSS: #tiene_tutor:checked ~ .campos-tutor en publico.css */ ?>
                <div class="row g-3 mb-3 campos-tutor">
                    <div class="col-md-5">
                        <label for="tutor_nombre" class="form-label fw-semibold">Nombre</label>
                        <input type="text" name="tutor_nombre" id="tutor_nombre" class="form-control"
                               value="<?= e($valores['tutor_nombre'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="tutor_parentesco" class="form-label fw-semibold">Parentesco</label>
                        <input type="text" name="tutor_parentesco" id="tutor_parentesco" class="form-control"
                               value="<?= e($valores['tutor_parentesco'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="tutor_telefono" class="form-label fw-semibold">Teléfono</label>
                        <input type="tel" name="tutor_telefono" id="tutor_telefono" class="form-control"
                               value="<?= e($valores['tutor_telefono'] ?? '') ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="notas" class="form-label fw-semibold">Notas (opcional)</label>
                    <textarea name="notas" id="notas" class="form-control" rows="2"><?= e($valores['notas'] ?? '') ?></textarea>
                </div>

                <?php require BASE_PATH . '/shared/views/parciales/consentimiento_privacidad.php'; ?>

                <button type="submit" class="btn btn-primary mt-3">
                    <i class="bi bi-send me-1"></i>Enviar inscripción
                </button>
            </div>
        </form>

    </div>
</div>
