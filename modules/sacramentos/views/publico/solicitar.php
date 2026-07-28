<div class="row justify-content-center">
    <div class="col-lg-8">

        <nav aria-label="Ubicación" class="mb-3">
            <a href="<?= e(url_publica('sacramentos', ['slug' => $sacramento['slug']])) ?>" class="small text-decoration-none">
                <i class="bi bi-arrow-left me-1"></i>Volver a <?= e($sacramento['nombre']) ?>
            </a>
        </nav>

        <h1 class="titulo-pagina mb-2">Solicitud de <?= e($sacramento['nombre']) ?></h1>
        <p class="text-muted mb-4">
            Completa este formulario y nos pondremos en contacto contigo para confirmar los
            detalles y agendar una cita.
        </p>

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
              action="<?= e(url_post('publico', 'sacramentos', 'solicitar', ['slug' => $sacramento['slug']])) ?>"
              class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <?= AntiSpam::campos() ?>

                <h2 class="h6 fw-bold mb-3">Datos del solicitante</h2>
                <div class="row g-3 mb-4">
                    <div class="col-md-8">
                        <label for="nombre_solicitante" class="form-label fw-semibold">Nombre completo</label>
                        <input type="text" name="nombre_solicitante" id="nombre_solicitante" class="form-control"
                               value="<?= e($valores['nombre_solicitante'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="fecha_nacimiento" class="form-label fw-semibold">Fecha de nacimiento</label>
                        <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" class="form-control"
                               value="<?= e($valores['fecha_nacimiento'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="telefono" class="form-label fw-semibold">Teléfono</label>
                        <input type="tel" name="telefono" id="telefono" class="form-control"
                               value="<?= e($valores['telefono'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label fw-semibold">Correo electrónico</label>
                        <input type="email" name="email" id="email" class="form-control"
                               value="<?= e($valores['email'] ?? '') ?>">
                    </div>
                    <p class="form-text mt-n2 mb-0 col-12">Déjanos al menos un teléfono o un correo.</p>
                    <div class="col-12">
                        <label for="direccion" class="form-label fw-semibold">Dirección</label>
                        <input type="text" name="direccion" id="direccion" class="form-control"
                               value="<?= e($valores['direccion'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="fecha_preferida" class="form-label fw-semibold">Fecha preferida (opcional)</label>
                        <input type="date" name="fecha_preferida" id="fecha_preferida" class="form-control"
                               value="<?= e($valores['fecha_preferida'] ?? '') ?>">
                    </div>
                </div>

                <h2 class="h6 fw-bold mb-2">Padre, madre o tutor</h2>
                <p class="text-muted small mb-3">
                    Completa esta sección solo si el solicitante es menor de edad.
                </p>
                <div class="row g-3 mb-4">
                    <div class="col-md-5">
                        <label for="tutor_nombre" class="form-label fw-semibold">Nombre</label>
                        <input type="text" name="tutor_nombre" id="tutor_nombre" class="form-control"
                               value="<?= e($valores['tutor_nombre'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="tutor_parentesco" class="form-label fw-semibold">Parentesco</label>
                        <input type="text" name="tutor_parentesco" id="tutor_parentesco" class="form-control"
                               value="<?= e($valores['tutor_parentesco'] ?? '') ?>" placeholder="Madre, padre...">
                    </div>
                    <div class="col-md-4">
                        <label for="tutor_telefono" class="form-label fw-semibold">Teléfono</label>
                        <input type="tel" name="tutor_telefono" id="tutor_telefono" class="form-control"
                               value="<?= e($valores['tutor_telefono'] ?? '') ?>">
                    </div>
                </div>

                <?php if ($campos): ?>
                <h2 class="h6 fw-bold mb-3">Información adicional</h2>
                <div class="row g-3 mb-4">
                    <?php foreach ($campos as $campo):
                        $nombreInput = 'extra_' . $campo['nombre_campo'];
                        $valorActual = $valores[$nombreInput] ?? '';
                    ?>
                    <div class="<?= $campo['tipo'] === 'textarea' ? 'col-12' : 'col-md-6' ?>">
                        <?php if ($campo['tipo'] === 'checkbox'): ?>
                        <div class="form-check mt-4">
                            <input type="checkbox" name="<?= e($nombreInput) ?>" value="1" class="form-check-input"
                                   id="<?= e($nombreInput) ?>" <?= $valorActual ? 'checked' : '' ?>>
                            <label class="form-check-label" for="<?= e($nombreInput) ?>">
                                <?= e($campo['etiqueta']) ?><?= $campo['requerido'] ? ' *' : '' ?>
                            </label>
                        </div>
                        <?php else: ?>
                        <label for="<?= e($nombreInput) ?>" class="form-label fw-semibold">
                            <?= e($campo['etiqueta']) ?><?= $campo['requerido'] ? ' *' : '' ?>
                        </label>
                        <?php if ($campo['tipo'] === 'textarea'): ?>
                        <textarea name="<?= e($nombreInput) ?>" id="<?= e($nombreInput) ?>" class="form-control"
                                  rows="3" <?= $campo['requerido'] ? 'required' : '' ?>><?= e($valorActual) ?></textarea>
                        <?php elseif ($campo['tipo'] === 'seleccion'): ?>
                        <select name="<?= e($nombreInput) ?>" id="<?= e($nombreInput) ?>" class="form-select"
                                <?= $campo['requerido'] ? 'required' : '' ?>>
                            <option value="">— Selecciona —</option>
                            <?php foreach (array_map('trim', explode(',', (string) $campo['opciones'])) as $opcion): ?>
                                <?php if ($opcion === '') { continue; } ?>
                            <option value="<?= e($opcion) ?>" <?= $valorActual === $opcion ? 'selected' : '' ?>>
                                <?= e($opcion) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php else: ?>
                        <?php
                        $tipoInput = match ($campo['tipo']) {
                            'fecha'    => 'date',
                            'telefono' => 'tel',
                            'email'    => 'email',
                            default    => 'text',
                        };
                        ?>
                        <input type="<?= e($tipoInput) ?>" name="<?= e($nombreInput) ?>" id="<?= e($nombreInput) ?>"
                               class="form-control" value="<?= e($valorActual) ?>"
                               <?= $campo['requerido'] ? 'required' : '' ?>>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label for="notas" class="form-label fw-semibold">Notas (opcional)</label>
                    <textarea name="notas" id="notas" class="form-control" rows="2"><?= e($valores['notas'] ?? '') ?></textarea>
                </div>

                <?php
                $cp_texto = "Como padre, madre o tutor, autorizo el tratamiento de los datos del menor, "
                          . "en caso de que el solicitante lo sea. He leído y acepto el Aviso de Privacidad.";
                require BASE_PATH . '/shared/views/parciales/consentimiento_privacidad.php';
                ?>

                <button type="submit" class="btn btn-primary mt-3">
                    <i class="bi bi-send me-1"></i>Enviar solicitud
                </button>
            </div>
        </form>

    </div>
</div>
