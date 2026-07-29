<?php $esNuevo = $cuenta === null; ?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <nav aria-label="Ubicación">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item">
                    <a href="<?= e(url_admin('usuarios')) ?>" class="text-decoration-none">Usuarios</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page"><?= $esNuevo ? 'Nuevo' : 'Editar' ?></li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0"><?= $esNuevo ? 'Nuevo usuario' : e($cuenta['nombre']) ?></h1>
    </div>
    <a href="<?= e(url_admin('usuarios')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<form method="POST" accept-charset="UTF-8" action="<?= e(url_post('admin', 'usuarios', 'guardar')) ?>"
      enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="id" value="<?= $esNuevo ? 0 : (int) $cuenta['id'] ?>">

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">

                    <div class="row g-3 mb-3">
                        <div class="col-md-7">
                            <label for="nombre" class="form-label fw-semibold">Nombre completo</label>
                            <input type="text" name="nombre" id="nombre" class="form-control"
                                   value="<?= e($esNuevo ? '' : $cuenta['nombre']) ?>" maxlength="120" required>
                        </div>
                        <div class="col-md-5">
                            <label for="rol" class="form-label fw-semibold">Rol</label>
                            <select name="rol" id="rol" class="form-select">
                                <?php foreach (ROLES_NOMBRES as $valor => $etiqueta): ?>
                                <option value="<?= e($valor) ?>"
                                    <?= (!$esNuevo && $cuenta['rol'] === $valor) ? 'selected' : '' ?>>
                                    <?= e($etiqueta) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-7">
                            <label for="email" class="form-label fw-semibold">Correo electrónico</label>
                            <input type="email" name="email" id="email" class="form-control"
                                   value="<?= e($esNuevo ? '' : $cuenta['email']) ?>" required>
                            <div class="form-text">Con este correo se inicia sesión.</div>
                        </div>
                        <div class="col-md-5">
                            <label for="telefono" class="form-label fw-semibold">Teléfono</label>
                            <input type="tel" name="telefono" id="telefono" class="form-control"
                                   value="<?= e($esNuevo ? '' : (string) $cuenta['telefono']) ?>">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="password" class="form-label fw-semibold">
                                Contraseña<?= $esNuevo ? '' : ' nueva' ?>
                            </label>
                            <input type="password" name="password" id="password" class="form-control"
                                   placeholder="<?= $esNuevo ? 'Mínimo 8 caracteres' : 'Déjalo en blanco para no cambiarla' ?>"
                                   autocomplete="new-password">
                        </div>
                    </div>

                    <?php if ($esNuevo || in_array($cuenta['rol'], ROLES_CON_ALCANCE_PASTORAL, true)): ?>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Pastorales que administra</label>
                        <div class="form-text mb-2">
                            Solo se guarda con un rol acotado por pastoral (Coordinador, o Administrador/Consulta de
                            MESC, Catequesis o Lector); con cualquier otro rol se ignora.
                        </div>
                        <?php if (!$pastorales): ?>
                        <p class="text-muted small">Todavía no hay pastorales dadas de alta.</p>
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

                    <div class="mb-0 mt-3">
                        <label class="form-label fw-semibold">Centros o sedes que administra completos</label>
                        <div class="form-text mb-2">
                            Quien administra un centro/sede administra todas sus pastorales, sin tener que
                            marcarlas una por una arriba. Aplica a los mismos roles que arriba.
                        </div>
                        <?php if (!$centros): ?>
                        <p class="text-muted small">Todavía no hay centros o sedes dados de alta.</p>
                        <?php else: ?>
                        <div class="row row-cols-1 row-cols-sm-2 g-1">
                            <?php foreach ($centros as $centro): ?>
                            <div class="col">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="centros[]"
                                           value="<?= (int) $centro['id'] ?>" id="cen<?= (int) $centro['id'] ?>"
                                           <?= in_array((int) $centro['id'], $centrosAsignados, true) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="cen<?= (int) $centro['id'] ?>">
                                        <?= e($centro['nombre']) ?>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
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
                    $ci_actual   = $esNuevo ? '' : (string) $cuenta['foto'];
                    $ci_ayuda    = 'Se muestra junto a su nombre en el panel.';
                    require BASE_PATH . '/shared/views/parciales/campo_imagen.php';
                    ?>

                    <?php $esPropio = !$esNuevo && (int) $cuenta['id'] === (int) Auth::usuario()['id']; ?>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               name="activo" id="activo" value="1"
                               <?= ($esNuevo || $cuenta['activo']) ? 'checked' : '' ?>
                               <?= $esPropio ? 'disabled' : '' ?>>
                        <label class="form-check-label fw-semibold" for="activo">Activo</label>
                        <?php if ($esPropio): ?>
                        <div class="form-text">No puedes desactivar tu propia cuenta.</div>
                        <?php else: ?>
                        <div class="form-text">Si ya no debe entrar al panel, desactívalo en vez de darlo de baja.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-check-lg me-1"></i>Guardar
                </button>
                <a href="<?= e(url_admin('usuarios')) ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </div>
    </div>
</form>
