<?php $esNueva = $persona === null; ?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <nav aria-label="Ubicación">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item">
                    <a href="<?= e(url_admin('personas')) ?>" class="text-decoration-none">Equipo pastoral</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page"><?= $esNueva ? 'Nueva' : 'Editar' ?></li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0"><?= $esNueva ? 'Nueva persona' : e($persona['nombre']) ?></h1>
    </div>
    <a href="<?= e(url_admin('personas')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<form method="POST" accept-charset="UTF-8" action="<?= e(url_post('admin', 'personas', 'guardar')) ?>"
      enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="id" value="<?= $esNueva ? 0 : (int) $persona['id'] ?>">

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">

                    <div class="row g-3 mb-3">
                        <div class="col-md-7">
                            <label for="nombre" class="form-label fw-semibold">Nombre completo</label>
                            <input type="text" name="nombre" id="nombre" class="form-control"
                                   value="<?= e($esNueva ? '' : $persona['nombre']) ?>" maxlength="140" required>
                        </div>
                        <div class="col-md-5">
                            <label for="tipo" class="form-label fw-semibold">Tipo</label>
                            <select name="tipo" id="tipo" class="form-select">
                                <?php foreach (PersonaModel::TIPOS as $valor => $etiqueta): ?>
                                <option value="<?= e($valor) ?>"
                                    <?= (!$esNueva && $persona['tipo'] === $valor) ? 'selected' : '' ?>>
                                    <?= e($etiqueta) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="cargo" class="form-label fw-semibold">Cargo</label>
                        <input type="text" name="cargo" id="cargo" class="form-control"
                               value="<?= e($esNueva ? '' : (string) $persona['cargo']) ?>" maxlength="100"
                               placeholder="Ej. Párroco, Coordinador de catequesis…">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="email" class="form-label fw-semibold">Correo institucional</label>
                            <input type="email" name="email" id="email" class="form-control"
                                   value="<?= e($esNueva ? '' : (string) $persona['email']) ?>">
                            <div class="form-text">Solo el correo institucional se publica. Ver docs/PRIVACIDAD.md.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="telefono" class="form-label fw-semibold">Teléfono</label>
                            <input type="tel" name="telefono" id="telefono" class="form-control"
                                   value="<?= e($esNueva ? '' : (string) $persona['telefono']) ?>">
                            <div class="form-text">Opcional. No se muestra en el sitio público.</div>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label for="semblanza" class="form-label fw-semibold">Semblanza</label>
                        <textarea name="semblanza" id="semblanza" class="form-control" rows="4"
                                  maxlength="600"><?= e($esNueva ? '' : (string) $persona['semblanza']) ?></textarea>
                        <div class="form-text">Un párrafo breve para "Quiénes somos".</div>
                    </div>

                </div>
            </div>

            <div class="card border-0 shadow-sm mt-4">
                <div class="card-body p-4">
                    <label class="form-label fw-semibold">Pastorales</label>
                    <div class="form-text mb-2">Muchas personas del equipo llevan más de una pastoral a la vez.</div>
                    <?php if (!$pastorales): ?>
                    <p class="text-muted small mb-0">Todavía no hay pastorales dadas de alta.</p>
                    <?php else: ?>
                    <div class="row row-cols-1 row-cols-sm-2 g-1">
                        <?php foreach ($pastorales as $pastoral): ?>
                        <div class="col">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="pastorales[]"
                                       value="<?= (int) $pastoral['id'] ?>" id="pas<?= (int) $pastoral['id'] ?>"
                                       <?= in_array((int) $pastoral['id'], $asignadas, true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="pas<?= (int) $pastoral['id'] ?>">
                                    <?= e($pastoral['nombre']) ?>
                                </label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <?php
                    $ci_nombre   = 'foto';
                    $ci_etiqueta = 'Fotografía';
                    $ci_actual   = $esNueva ? '' : (string) $persona['foto'];
                    $ci_ayuda    = 'Formato vertical o cuadrado.';
                    require BASE_PATH . '/shared/views/parciales/campo_imagen.php';
                    ?>

                    <div class="mb-3">
                        <label for="orden" class="form-label fw-semibold">Orden</label>
                        <input type="number" name="orden" id="orden" class="form-control"
                               value="<?= (int) ($esNueva ? 0 : $persona['orden']) ?>" min="0" max="999">
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               name="activo" id="activo" value="1"
                               <?= ($esNueva || $persona['activo']) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="activo">Activo</label>
                        <div class="form-text">Si ya no forma parte del equipo, desactívala en vez de eliminarla.</div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-check-lg me-1"></i>Guardar
                </button>
                <a href="<?= e(url_admin('personas')) ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </div>
    </div>
</form>
