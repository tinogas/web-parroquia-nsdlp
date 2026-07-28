<?php $esNuevo = $nodo === null; ?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <nav aria-label="Ubicación">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item">
                    <a href="<?= e(url_admin('organigrama')) ?>" class="text-decoration-none">Organigrama</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page"><?= $esNuevo ? 'Nuevo nodo' : 'Editar' ?></li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0"><?= $esNuevo ? 'Nuevo nodo' : e($nodo['titulo']) ?></h1>
    </div>
    <a href="<?= e(url_admin('organigrama')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <form method="POST" accept-charset="UTF-8" action="<?= e(url_post('admin', 'organigrama', 'guardar')) ?>"
              class="card border-0 shadow-sm">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="id" value="<?= $esNuevo ? 0 : (int) $nodo['id'] ?>">

            <div class="card-body p-4">

                <div class="mb-3">
                    <label for="titulo" class="form-label fw-semibold">Título</label>
                    <input type="text" name="titulo" id="titulo" class="form-control"
                           value="<?= e($esNuevo ? '' : $nodo['titulo']) ?>" maxlength="140" required
                           placeholder="Ej. Párroco, Consejo Pastoral, Coordinación de catequesis…">
                </div>

                <div class="mb-3">
                    <label for="padre_id" class="form-label fw-semibold">Depende de</label>
                    <select name="padre_id" id="padre_id" class="form-select">
                        <option value="">— Primer nivel —</option>
                        <?php
                        $padreActual = $esNuevo
                            ? ($padrePreseleccionado ?? null)
                            : $nodo['padre_id'];
                        ?>
                        <?php foreach ($padres as $opcion): ?>
                        <option value="<?= (int) $opcion['id'] ?>"
                            <?= ((int) $padreActual === (int) $opcion['id']) ? 'selected' : '' ?>>
                            <?= str_repeat('— ', max(0, (int) $opcion['nivel'] - 1)) ?><?= e($opcion['titulo']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">
                        El nivel se calcula solo, según de quién dependa. El organigrama admite
                        hasta <?= OrganigramaModel::NIVEL_MAXIMO ?> niveles.
                    </div>
                </div>

                <div class="mb-3">
                    <label for="pastoral_id" class="form-label fw-semibold">Pastoral (opcional)</label>
                    <select name="pastoral_id" id="pastoral_id" class="form-select">
                        <option value="">— Ninguna —</option>
                        <?php foreach ($pastorales as $p): ?>
                        <option value="<?= (int) $p['id'] ?>"
                            <?= (!$esNuevo && (int) $nodo['pastoral_id'] === (int) $p['id']) ? 'selected' : '' ?>>
                            <?= e($p['nombre']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">
                        Vincula este nodo con la página pública de esa pastoral, si aplica.
                    </div>
                </div>

                <div class="mb-3">
                    <label for="persona_id" class="form-label fw-semibold">Persona (opcional)</label>
                    <select name="persona_id" id="persona_id" class="form-select">
                        <option value="">— Solo el título, sin persona asignada —</option>
                        <?php foreach ($personas as $persona): ?>
                        <option value="<?= (int) $persona['id'] ?>"
                            <?= (!$esNuevo && (int) $nodo['persona_id'] === (int) $persona['id']) ? 'selected' : '' ?>>
                            <?= e($persona['nombre']) ?><?= $persona['cargo'] ? ' — ' . e($persona['cargo']) : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">
                        Si el puesto está vacante, deja esto en blanco: el título se sigue
                        mostrando en el sitio, solo que sin nombre.
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="orden" class="form-label fw-semibold">Orden entre sus iguales</label>
                        <input type="number" name="orden" id="orden" class="form-control"
                               value="<?= (int) ($esNuevo ? 0 : $nodo['orden']) ?>" min="0" max="999">
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   name="activo" id="activo" value="1"
                                   <?= ($esNuevo || $nodo['activo']) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="activo">Visible en el sitio</label>
                        </div>
                    </div>
                </div>

            </div>

            <div class="card-footer bg-white border-top-0 pb-4 px-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Guardar
                </button>
                <a href="<?= e(url_admin('organigrama')) ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 bg-light-subtle">
            <div class="card-body p-4">
                <h2 class="h6 fw-bold mb-2"><i class="bi bi-info-circle text-primary me-1"></i>Cómo se arma</h2>
                <p class="small text-muted mb-0">
                    Cada nodo depende de otro (o de ninguno, si es de primer nivel). Para agregar
                    un subordinado directamente, usa el botón <i class="bi bi-plus-lg"></i> junto
                    al nodo correspondiente en el listado del organigrama.
                </p>
            </div>
        </div>
    </div>
</div>
