<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <nav aria-label="Ubicación">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item">
                    <a href="<?= e(url_admin('sacramentos')) ?>" class="text-decoration-none">Sacramentos</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Editar</li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0"><?= e($sacramento['nombre']) ?></h1>
    </div>
    <a href="<?= e(url_admin('sacramentos')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<form method="POST" accept-charset="UTF-8" action="<?= e(url_post('admin', 'sacramentos', 'guardar')) ?>"
      enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="id" value="<?= (int) $sacramento['id'] ?>">

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">

                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label for="nombre" class="form-label fw-semibold">Nombre</label>
                            <input type="text" name="nombre" id="nombre" class="form-control"
                                   value="<?= e($sacramento['nombre']) ?>" maxlength="80" required>
                        </div>
                        <div class="col-md-4">
                            <label for="aportacion" class="form-label fw-semibold">Aportación</label>
                            <input type="text" name="aportacion" id="aportacion" class="form-control"
                                   value="<?= e((string) $sacramento['aportacion']) ?>" maxlength="80"
                                   placeholder="Ej. $500 o Libre">
                        </div>
                    </div>

                    <?php
                    $eh_nombre   = 'descripcion';
                    $eh_etiqueta = 'Descripción';
                    $eh_valor    = (string) $sacramento['descripcion'];
                    $eh_ayuda    = '';
                    require BASE_PATH . '/shared/views/parciales/editor_html.php';
                    ?>
                    <?php
                    $eh_nombre   = 'requisitos';
                    $eh_etiqueta = 'Requisitos';
                    $eh_valor    = (string) $sacramento['requisitos'];
                    $eh_ayuda    = 'Lo que debe cumplir quien solicita el sacramento.';
                    require BASE_PATH . '/shared/views/parciales/editor_html.php';
                    ?>
                    <?php
                    $eh_nombre   = 'documentos';
                    $eh_etiqueta = 'Documentos a presentar';
                    $eh_valor    = (string) $sacramento['documentos'];
                    $eh_ayuda    = '';
                    require BASE_PATH . '/shared/views/parciales/editor_html.php';
                    ?>

                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h6 fw-bold mb-0">Campos adicionales del formulario</h2>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                data-bs-target="#campoNuevo">
                            <i class="bi bi-plus-lg me-1"></i>Agregar campo
                        </button>
                    </div>
                    <p class="text-muted small">
                        Además de los datos que siempre se piden (nombre, fecha de nacimiento,
                        contacto...), aquí se agrega lo específico de este sacramento, como el
                        nombre del padrino.
                    </p>

                    <?php if (!$campos): ?>
                    <p class="text-muted small mb-0">Este sacramento no tiene campos adicionales.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead class="table-light">
                                <tr><th>Etiqueta</th><th class="d-none d-md-table-cell">Tipo</th><th>&nbsp;</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($campos as $campo): ?>
                                <tr class="<?= $campo['activo'] ? '' : 'text-muted' ?>">
                                    <td>
                                        <?= e($campo['etiqueta']) ?>
                                        <?php if ($campo['requerido']): ?><span class="text-danger">*</span><?php endif; ?>
                                        <?php if ($campo['dato_sensible']): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis">Sensible</span>
                                        <?php endif; ?>
                                        <?php if (!$campo['activo']): ?> (oculto)<?php endif; ?>
                                    </td>
                                    <td class="d-none d-md-table-cell small">
                                        <?= e(SacramentoModel::TIPOS_CAMPO[$campo['tipo']] ?? $campo['tipo']) ?>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                                data-bs-toggle="modal" data-bs-target="#campo<?= (int) $campo['id'] ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <?php
                    $ci_nombre   = 'imagen';
                    $ci_etiqueta = 'Imagen';
                    $ci_actual   = (string) $sacramento['imagen'];
                    $ci_ayuda    = '';
                    require BASE_PATH . '/shared/views/parciales/campo_imagen.php';
                    ?>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch"
                               name="acepta_solicitudes" id="acepta_solicitudes" value="1"
                               <?= $sacramento['acepta_solicitudes'] ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="acepta_solicitudes">
                            Recibir solicitudes en línea
                        </label>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch"
                               name="requiere_tutor" id="requiere_tutor" value="1"
                               <?= $sacramento['requiere_tutor'] ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="requiere_tutor">
                            Normalmente lo solicita un menor (muestra la sección de tutor)
                        </label>
                    </div>
                    <div class="mb-3">
                        <label for="orden" class="form-label fw-semibold">Orden</label>
                        <input type="number" name="orden" id="orden" class="form-control"
                               value="<?= (int) $sacramento['orden'] ?>" min="0" max="999">
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               name="activo" id="activo" value="1" <?= $sacramento['activo'] ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="activo">Visible en el sitio</label>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-check-lg me-1"></i>Guardar
                </button>
                <a href="<?= e(url_admin('sacramentos')) ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </div>
    </div>
</form>

<?php
/** Modal para agregar o editar un campo adicional. */
$dibujarModalCampo = static function (string $idModal, ?array $campo, int $sacramentoId, string $csrf) {
    $vacio = $campo === null;
    ?>
    <div class="modal fade" id="<?= e($idModal) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" accept-charset="UTF-8"
                  action="<?= e(url_post('admin', 'sacramentos', 'campoGuardar')) ?>" class="modal-content">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="sacramento_id" value="<?= $sacramentoId ?>">
                <input type="hidden" name="id" value="<?= $vacio ? 0 : (int) $campo['id'] ?>">

                <div class="modal-header border-0 pb-0">
                    <h2 class="h6 modal-title fw-bold"><?= $vacio ? 'Nuevo campo' : 'Editar campo' ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Etiqueta</label>
                        <input type="text" name="etiqueta" class="form-control form-control-sm"
                               value="<?= e($vacio ? '' : $campo['etiqueta']) ?>" maxlength="120" required
                               placeholder="Ej. Nombre del padrino">
                        <?php if (!$vacio): ?>
                        <div class="form-text">Clave interna: <code><?= e($campo['nombre_campo']) ?></code> (no cambia).</div>
                        <?php endif; ?>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Tipo</label>
                        <select name="tipo" class="form-select form-select-sm">
                            <?php foreach (SacramentoModel::TIPOS_CAMPO as $valor => $etiqueta): ?>
                            <option value="<?= e($valor) ?>" <?= (!$vacio && $campo['tipo'] === $valor) ? 'selected' : '' ?>>
                                <?= e($etiqueta) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Opciones (solo para "Lista de opciones")</label>
                        <input type="text" name="opciones" class="form-control form-control-sm"
                               value="<?= e($vacio ? '' : (string) $campo['opciones']) ?>"
                               placeholder="Separadas por coma: Opción 1, Opción 2">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Orden</label>
                        <input type="number" name="orden" class="form-control form-control-sm"
                               value="<?= $vacio ? 0 : (int) $campo['orden'] ?>" min="0" max="999">
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" name="requerido" value="1"
                               id="req<?= e($idModal) ?>" <?= (!$vacio && $campo['requerido']) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="req<?= e($idModal) ?>">Obligatorio</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" name="dato_sensible" value="1"
                               id="sen<?= e($idModal) ?>" <?= (!$vacio && $campo['dato_sensible']) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="sen<?= e($idModal) ?>">
                            Dato sensible (solo lo ve administración y secretaría)
                        </label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" name="activo" value="1"
                               id="act<?= e($idModal) ?>" <?= ($vacio || $campo['activo']) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="act<?= e($idModal) ?>">Visible en el formulario</label>
                    </div>
                </div>
                <div class="modal-footer border-0 justify-content-between">
                    <?php if (!$vacio): ?>
                    <button type="submit" formaction="<?= e(url_post('admin', 'sacramentos', 'campoEliminar')) ?>"
                            class="btn btn-outline-danger btn-sm"
                            onclick="return confirm('¿Eliminar este campo? Las solicitudes ya enviadas conservan su respuesta.');">
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

$dibujarModalCampo('campoNuevo', null, (int) $sacramento['id'], $csrf);
foreach ($campos as $campo) {
    $dibujarModalCampo('campo' . (int) $campo['id'], $campo, (int) $sacramento['id'], $csrf);
}
?>
