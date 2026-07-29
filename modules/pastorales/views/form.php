<?php
$esNueva      = $pastoral === null;
$puedeActivar = Auth::tieneAlcanceGlobal();
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <nav aria-label="Ubicación">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item">
                    <a href="<?= e(url_admin('pastorales')) ?>" class="text-decoration-none">Pastorales</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page"><?= $esNueva ? 'Nueva' : 'Editar' ?></li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0"><?= $esNueva ? 'Nueva pastoral' : e($pastoral['nombre']) ?></h1>
    </div>
    <a href="<?= e(url_admin('pastorales')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<form method="POST" accept-charset="UTF-8" action="<?= e(url_post('admin', 'pastorales', 'guardar')) ?>"
      enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="id" value="<?= $esNueva ? 0 : (int) $pastoral['id'] ?>">

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">

                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label for="nombre" class="form-label fw-semibold">Nombre</label>
                            <input type="text" name="nombre" id="nombre" class="form-control"
                                   value="<?= e($esNueva ? '' : $pastoral['nombre']) ?>" maxlength="120" required>
                        </div>
                        <div class="col-md-4">
                            <label for="icono" class="form-label fw-semibold">Icono</label>
                            <input type="text" name="icono" id="icono" class="form-control"
                                   value="<?= e($esNueva ? 'bi-people' : (string) $pastoral['icono']) ?>">
                            <div class="form-text">Clase de <a href="https://icons.getbootstrap.com/" target="_blank" rel="noopener">Bootstrap Icons</a>.</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="descripcion_corta" class="form-label fw-semibold">Descripción breve</label>
                        <input type="text" name="descripcion_corta" id="descripcion_corta" class="form-control"
                               value="<?= e($esNueva ? '' : (string) $pastoral['descripcion_corta']) ?>" maxlength="255">
                        <div class="form-text">Aparece en la tarjeta del listado público.</div>
                    </div>

                    <?php
                    $eh_nombre   = 'descripcion';
                    $eh_etiqueta = 'Descripción completa';
                    $eh_valor    = $esNueva ? '' : (string) $pastoral['descripcion'];
                    $eh_ayuda    = '';
                    require BASE_PATH . '/shared/views/parciales/editor_html.php';
                    ?>

                </div>
            </div>

            <?php if (!$esNueva): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h2 class="h6 fw-bold mb-3">Actividades comunitarias y de apoyo social</h2>

                    <?php if (!$actividades): ?>
                    <p class="text-muted small mb-3">Todavía no hay actividades registradas.</p>
                    <?php else: ?>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm align-middle">
                            <thead class="table-light">
                                <tr><th>Actividad</th><th class="d-none d-md-table-cell">Tipo</th><th>&nbsp;</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($actividades as $actividad): ?>
                                <tr class="<?= $actividad['activa'] ? '' : 'text-muted' ?>">
                                    <td><?= e($actividad['titulo']) ?><?= $actividad['activa'] ? '' : ' (oculta)' ?></td>
                                    <td class="d-none d-md-table-cell small">
                                        <?= e(PastoralModel::TIPOS_ACTIVIDAD[$actividad['tipo']] ?? $actividad['tipo']) ?>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                                data-bs-toggle="modal" data-bs-target="#actividad<?= (int) $actividad['id'] ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>

                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                            data-bs-target="#actividadNueva">
                        <i class="bi bi-plus-lg me-1"></i>Agregar actividad
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <?php
                    $ci_nombre   = 'imagen';
                    $ci_etiqueta = 'Imagen';
                    $ci_actual   = $esNueva ? '' : (string) $pastoral['imagen'];
                    $ci_ayuda    = '';
                    require BASE_PATH . '/shared/views/parciales/campo_imagen.php';
                    ?>

                    <div class="mb-3">
                        <label for="responsable_nombre" class="form-label fw-semibold">Responsable</label>
                        <input type="text" name="responsable_nombre" id="responsable_nombre" class="form-control"
                               value="<?= e($esNueva ? '' : (string) $pastoral['responsable_nombre']) ?>" maxlength="140">
                    </div>
                    <div class="mb-3">
                        <label for="contacto_email" class="form-label fw-semibold">Correo de contacto</label>
                        <input type="email" name="contacto_email" id="contacto_email" class="form-control"
                               value="<?= e($esNueva ? '' : (string) $pastoral['contacto_email']) ?>">
                    </div>
                    <div class="mb-3">
                        <label for="contacto_telefono" class="form-label fw-semibold">Teléfono de contacto</label>
                        <input type="tel" name="contacto_telefono" id="contacto_telefono" class="form-control"
                               value="<?= e($esNueva ? '' : (string) $pastoral['contacto_telefono']) ?>">
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h2 class="h6 fw-bold mb-3">Reunión</h2>
                    <div class="mb-3">
                        <label for="dia_reunion" class="form-label fw-semibold">Día</label>
                        <input type="text" name="dia_reunion" id="dia_reunion" class="form-control"
                               value="<?= e($esNueva ? '' : (string) $pastoral['dia_reunion']) ?>" maxlength="60"
                               placeholder="Ej. Sábados">
                    </div>
                    <div class="mb-3">
                        <label for="hora_reunion" class="form-label fw-semibold">Hora</label>
                        <input type="time" name="hora_reunion" id="hora_reunion" class="form-control"
                               value="<?= e($esNueva || !$pastoral['hora_reunion'] ? '' : substr((string) $pastoral['hora_reunion'], 0, 5)) ?>">
                    </div>
                    <div class="mb-2">
                        <label for="lugar_reunion" class="form-label fw-semibold">Lugar</label>
                        <input type="text" name="lugar_reunion" id="lugar_reunion" class="form-control"
                               value="<?= e($esNueva ? '' : (string) $pastoral['lugar_reunion']) ?>" maxlength="140">
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               name="acepta_voluntarios" id="acepta_voluntarios" value="1"
                               <?= ($esNueva || $pastoral['acepta_voluntarios']) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="acepta_voluntarios">Acepta voluntarios</label>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label for="orden" class="form-label fw-semibold">Orden</label>
                        <input type="number" name="orden" id="orden" class="form-control"
                               value="<?= (int) ($esNueva ? 0 : $pastoral['orden']) ?>" min="0" max="999">
                    </div>
                    <?php if ($puedeActivar): ?>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               name="activa" id="activa" value="1"
                               <?= ($esNueva || $pastoral['activa']) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="activa">Visible en el sitio</label>
                    </div>
                    <?php else: ?>
                    <p class="small text-muted mb-0">
                        <i class="bi bi-info-circle me-1"></i>Solo un administrador o editor puede ocultar o mostrar la pastoral.
                    </p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-check-lg me-1"></i>Guardar
                </button>
                <a href="<?= e(url_admin('pastorales')) ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </div>
    </div>
</form>

<?php if (!$esNueva): ?>
<?php
/** Modal para agregar o editar una actividad, reutilizado con datos vacíos o los de la actividad. */
$dibujarModalActividad = static function (string $idModal, ?array $actividad, int $pastoralId, string $csrf) {
    $vacia = $actividad === null;
    ?>
    <div class="modal fade" id="<?= e($idModal) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" accept-charset="UTF-8"
                  action="<?= e(url_post('admin', 'pastorales', 'actividadGuardar')) ?>" class="modal-content">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="pastoral_id" value="<?= $pastoralId ?>">
                <input type="hidden" name="id" value="<?= $vacia ? 0 : (int) $actividad['id'] ?>">

                <div class="modal-header border-0 pb-0">
                    <h2 class="h6 modal-title fw-bold"><?= $vacia ? 'Nueva actividad' : 'Editar actividad' ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Título</label>
                        <input type="text" name="titulo" class="form-control form-control-sm"
                               value="<?= e($vacia ? '' : $actividad['titulo']) ?>" maxlength="160" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Descripción</label>
                        <textarea name="descripcion" class="form-control form-control-sm" rows="2"><?= e($vacia ? '' : (string) $actividad['descripcion']) ?></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Tipo</label>
                        <select name="tipo" class="form-select form-select-sm">
                            <?php foreach (PastoralModel::TIPOS_ACTIVIDAD as $valor => $etiqueta): ?>
                            <option value="<?= e($valor) ?>" <?= (!$vacia && $actividad['tipo'] === $valor) ? 'selected' : '' ?>>
                                <?= e($etiqueta) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Orden</label>
                        <input type="number" name="orden" class="form-control form-control-sm"
                               value="<?= $vacia ? 0 : (int) $actividad['orden'] ?>" min="0" max="999">
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" name="activa" value="1"
                               id="act<?= e($idModal) ?>" <?= ($vacia || $actividad['activa']) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="act<?= e($idModal) ?>">Visible</label>
                    </div>
                </div>
                <div class="modal-footer border-0 justify-content-between">
                    <?php if (!$vacia): ?>
                    <button type="submit" formaction="<?= e(url_post('admin', 'pastorales', 'actividadEliminar')) ?>"
                            class="btn btn-outline-danger btn-sm"
                            onclick="return confirm('¿Eliminar esta actividad?');">
                        <i class="bi bi-trash me-1"></i>Eliminar
                    </button>
                    <?php else: ?>
                    <span></span>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-check-lg me-1"></i>Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php
};

$dibujarModalActividad('actividadNueva', null, (int) $pastoral['id'], $csrf);
foreach ($actividades as $actividad) {
    $dibujarModalActividad('actividad' . (int) $actividad['id'], $actividad, (int) $pastoral['id'], $csrf);
}
?>
<?php endif; ?>
