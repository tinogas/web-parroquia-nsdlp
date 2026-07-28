<?php
$esNuevo       = $curso === null;
$puedePublicar = Auth::tienePermiso('cursos.publicar');
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <nav aria-label="Ubicación">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item">
                    <a href="<?= e(url_admin('cursos')) ?>" class="text-decoration-none">Cursos</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page"><?= $esNuevo ? 'Nuevo' : 'Editar' ?></li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0"><?= $esNuevo ? 'Nuevo curso' : e($curso['titulo']) ?></h1>
    </div>
    <a href="<?= e(url_admin('cursos')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<form method="POST" accept-charset="UTF-8" action="<?= e(url_post('admin', 'cursos', 'guardar')) ?>"
      enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="id" value="<?= $esNuevo ? 0 : (int) $curso['id'] ?>">

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">

                    <div class="mb-3">
                        <label for="titulo" class="form-label fw-semibold">Título</label>
                        <input type="text" name="titulo" id="titulo" class="form-control"
                               value="<?= e($esNuevo ? '' : $curso['titulo']) ?>" maxlength="160" required>
                    </div>

                    <div class="mb-3">
                        <label for="dirigido_a" class="form-label fw-semibold">Dirigido a</label>
                        <input type="text" name="dirigido_a" id="dirigido_a" class="form-control"
                               value="<?= e($esNuevo ? '' : (string) $curso['dirigido_a']) ?>" maxlength="160"
                               placeholder="Ej. Jóvenes de 15 a 18 años">
                    </div>

                    <?php
                    $eh_nombre   = 'descripcion';
                    $eh_etiqueta = 'Descripción';
                    $eh_valor    = $esNuevo ? '' : (string) $curso['descripcion'];
                    $eh_ayuda    = '';
                    require BASE_PATH . '/shared/views/parciales/editor_html.php';
                    ?>

                    <div class="mb-0">
                        <label for="objetivos" class="form-label fw-semibold">Objetivos</label>
                        <textarea name="objetivos" id="objetivos" class="form-control" rows="3"><?= e($esNuevo ? '' : (string) $curso['objetivos']) ?></textarea>
                    </div>

                </div>
            </div>

            <?php if (!$esNuevo): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h6 fw-bold mb-0">Temario</h2>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                data-bs-target="#sesionNueva">
                            <i class="bi bi-plus-lg me-1"></i>Agregar sesión
                        </button>
                    </div>

                    <?php if (!$sesiones): ?>
                    <p class="text-muted small mb-0">Todavía no hay sesiones registradas.</p>
                    <?php else: ?>
                    <ol class="list-group list-group-numbered">
                        <?php foreach ($sesiones as $sesion): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fw-semibold"><?= e($sesion['titulo']) ?></div>
                                <?php if ($sesion['fecha']): ?>
                                <div class="text-muted small"><?= e(fecha_larga($sesion['fecha'])) ?></div>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal" data-bs-target="#sesion<?= (int) $sesion['id'] ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                        </li>
                        <?php endforeach; ?>
                    </ol>
                    <?php endif; ?>
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
                    $ci_actual   = $esNuevo ? '' : (string) $curso['imagen'];
                    $ci_ayuda    = '';
                    require BASE_PATH . '/shared/views/parciales/campo_imagen.php';
                    ?>

                    <div class="mb-3">
                        <label for="modalidad" class="form-label fw-semibold">Modalidad</label>
                        <select name="modalidad" id="modalidad" class="form-select">
                            <?php foreach (CursoModel::MODALIDADES as $valor => $etiqueta): ?>
                            <option value="<?= e($valor) ?>" <?= (!$esNuevo && $curso['modalidad'] === $valor) ? 'selected' : '' ?>>
                                <?= e($etiqueta) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="instructor_id" class="form-label fw-semibold">Instructor</label>
                        <select name="instructor_id" id="instructor_id" class="form-select">
                            <option value="">— Sin asignar —</option>
                            <?php foreach ($instructores as $persona): ?>
                            <option value="<?= (int) $persona['id'] ?>"
                                <?= (!$esNuevo && (int) $curso['instructor_id'] === (int) $persona['id']) ? 'selected' : '' ?>>
                                <?= e($persona['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="pastoral_id" class="form-label fw-semibold">Pastoral (opcional)</label>
                        <select name="pastoral_id" id="pastoral_id" class="form-select">
                            <option value="">— Ninguna —</option>
                            <?php foreach ($pastorales as $p): ?>
                            <option value="<?= (int) $p['id'] ?>"
                                <?= (!$esNuevo && (int) $curso['pastoral_id'] === (int) $p['id']) ? 'selected' : '' ?>>
                                <?= e($p['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h2 class="h6 fw-bold mb-3">Fechas y lugar</h2>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label for="fecha_inicio" class="form-label fw-semibold">Inicio</label>
                            <input type="date" name="fecha_inicio" id="fecha_inicio" class="form-control"
                                   value="<?= e($esNuevo ? '' : (string) $curso['fecha_inicio']) ?>">
                        </div>
                        <div class="col-6">
                            <label for="fecha_fin" class="form-label fw-semibold">Fin</label>
                            <input type="date" name="fecha_fin" id="fecha_fin" class="form-control"
                                   value="<?= e($esNuevo ? '' : (string) $curso['fecha_fin']) ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="horario" class="form-label fw-semibold">Horario</label>
                        <input type="text" name="horario" id="horario" class="form-control"
                               value="<?= e($esNuevo ? '' : (string) $curso['horario']) ?>" maxlength="120"
                               placeholder="Ej. Sábados de 10:00 a 12:00">
                    </div>
                    <div class="mb-0">
                        <label for="lugar" class="form-label fw-semibold">Lugar</label>
                        <input type="text" name="lugar" id="lugar" class="form-control"
                               value="<?= e($esNuevo ? '' : (string) $curso['lugar']) ?>" maxlength="160">
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h2 class="h6 fw-bold mb-3">Inscripciones</h2>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label for="cupo" class="form-label fw-semibold">Cupo</label>
                            <input type="number" name="cupo" id="cupo" class="form-control"
                                   value="<?= e($esNuevo ? '' : (string) $curso['cupo']) ?>" min="0"
                                   placeholder="Sin límite">
                        </div>
                        <div class="col-6">
                            <label for="aportacion" class="form-label fw-semibold">Aportación</label>
                            <input type="text" name="aportacion" id="aportacion" class="form-control"
                                   value="<?= e($esNuevo ? '' : (string) $curso['aportacion']) ?>" maxlength="60">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="fecha_cierre_inscripcion" class="form-label fw-semibold">Cierre de inscripciones</label>
                        <input type="date" name="fecha_cierre_inscripcion" id="fecha_cierre_inscripcion" class="form-control"
                               value="<?= e($esNuevo ? '' : (string) $curso['fecha_cierre_inscripcion']) ?>">
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch"
                               name="inscripciones_abiertas" id="inscripciones_abiertas" value="1"
                               <?= ($esNuevo || $curso['inscripciones_abiertas']) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="inscripciones_abiertas">Recibir inscripciones</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               name="requiere_tutor" id="requiere_tutor" value="1"
                               <?= (!$esNuevo && $curso['requiere_tutor']) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="requiere_tutor">Normalmente lo toman menores</label>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label for="orden" class="form-label fw-semibold">Orden</label>
                        <input type="number" name="orden" id="orden" class="form-control"
                               value="<?= (int) ($esNuevo ? 0 : $curso['orden']) ?>" min="0" max="999">
                    </div>
                    <?php if ($puedePublicar): ?>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               name="publicado" id="publicado" value="1"
                               <?= ($esNuevo || $curso['publicado']) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="publicado">Publicado</label>
                    </div>
                    <?php else: ?>
                    <p class="small text-muted mb-0">
                        <i class="bi bi-info-circle me-1"></i>Este curso se enviará como borrador para revisión.
                    </p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-check-lg me-1"></i>Guardar
                </button>
                <a href="<?= e(url_admin('cursos')) ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </div>
    </div>
</form>

<?php if (!$esNuevo): ?>
<?php
$dibujarModalSesion = static function (string $idModal, ?array $sesion, int $cursoId, string $csrf) {
    $vacia = $sesion === null;
    ?>
    <div class="modal fade" id="<?= e($idModal) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" accept-charset="UTF-8"
                  action="<?= e(url_post('admin', 'cursos', 'sesionGuardar')) ?>" class="modal-content">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="curso_id" value="<?= $cursoId ?>">
                <input type="hidden" name="id" value="<?= $vacia ? 0 : (int) $sesion['id'] ?>">

                <div class="modal-header border-0 pb-0">
                    <h2 class="h6 modal-title fw-bold"><?= $vacia ? 'Nueva sesión' : 'Editar sesión' ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-2">
                        <div class="col-4">
                            <label class="form-label small fw-semibold">Número</label>
                            <input type="number" name="numero" class="form-control form-control-sm"
                                   value="<?= $vacia ? 1 : (int) $sesion['numero'] ?>" min="1">
                        </div>
                        <div class="col-8">
                            <label class="form-label small fw-semibold">Fecha</label>
                            <input type="date" name="fecha" class="form-control form-control-sm"
                                   value="<?= $vacia ? '' : (string) $sesion['fecha'] ?>">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Título</label>
                        <input type="text" name="titulo" class="form-control form-control-sm"
                               value="<?= e($vacia ? '' : $sesion['titulo']) ?>" maxlength="160" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Descripción</label>
                        <textarea name="descripcion" class="form-control form-control-sm" rows="2"><?= e($vacia ? '' : (string) $sesion['descripcion']) ?></textarea>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small fw-semibold">Orden</label>
                        <input type="number" name="orden" class="form-control form-control-sm"
                               value="<?= $vacia ? 0 : (int) $sesion['orden'] ?>" min="0" max="999">
                    </div>
                </div>
                <div class="modal-footer border-0 justify-content-between">
                    <?php if (!$vacia): ?>
                    <button type="submit" formaction="<?= e(url_post('admin', 'cursos', 'sesionEliminar')) ?>"
                            class="btn btn-outline-danger btn-sm"
                            onclick="return confirm('¿Eliminar esta sesión?');">
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

$dibujarModalSesion('sesionNueva', null, (int) $curso['id'], $csrf);
foreach ($sesiones as $sesion) {
    $dibujarModalSesion('sesion' . (int) $sesion['id'], $sesion, (int) $curso['id'], $csrf);
}
?>
<?php endif; ?>
