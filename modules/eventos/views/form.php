<?php
$esNuevo       = $evento === null;
$puedePublicar = Auth::tienePermiso('eventos.publicar');

/** El input datetime-local espera "YYYY-MM-DDTHH:MM"; la BD guarda "YYYY-MM-DD HH:MM:SS". */
$paraInput = static fn (?string $valor): string => $valor ? str_replace(' ', 'T', substr($valor, 0, 16)) : '';
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <nav aria-label="Ubicación">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item">
                    <a href="<?= e(url_admin('eventos')) ?>" class="text-decoration-none">Eventos</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page"><?= $esNuevo ? 'Nuevo' : 'Editar' ?></li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0"><?= $esNuevo ? 'Nuevo evento' : e($evento['titulo']) ?></h1>
    </div>
    <a href="<?= e(url_admin('eventos')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<form method="POST" accept-charset="UTF-8" action="<?= e(url_post('admin', 'eventos', 'guardar')) ?>"
      enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="id" value="<?= $esNuevo ? 0 : (int) $evento['id'] ?>">

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">

                    <div class="mb-3">
                        <label for="titulo" class="form-label fw-semibold">Título</label>
                        <input type="text" name="titulo" id="titulo" class="form-control"
                               value="<?= e($esNuevo ? '' : $evento['titulo']) ?>" maxlength="200" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="fecha_inicio" class="form-label fw-semibold">Inicio</label>
                            <input type="datetime-local" name="fecha_inicio" id="fecha_inicio" class="form-control"
                                   value="<?= e($esNuevo ? '' : $paraInput($evento['fecha_inicio'])) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="fecha_fin" class="form-label fw-semibold">Fin</label>
                            <input type="datetime-local" name="fecha_fin" id="fecha_fin" class="form-control"
                                   value="<?= e($esNuevo ? '' : $paraInput($evento['fecha_fin'])) ?>">
                            <div class="form-text">Opcional. Déjalo en blanco si es de un solo momento.</div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label for="lugar" class="form-label fw-semibold">Lugar</label>
                            <input type="text" name="lugar" id="lugar" class="form-control"
                                   value="<?= e($esNuevo ? '' : (string) $evento['lugar']) ?>" maxlength="160">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" role="switch"
                                       name="todo_el_dia" id="todo_el_dia" value="1"
                                       <?= (!$esNuevo && $evento['todo_el_dia']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="todo_el_dia">Todo el día</label>
                            </div>
                        </div>
                    </div>

                    <?php
                    $eh_nombre   = 'descripcion';
                    $eh_etiqueta = 'Descripción';
                    $eh_valor    = $esNuevo ? '' : (string) $evento['descripcion'];
                    $eh_ayuda    = '';
                    require BASE_PATH . '/shared/views/parciales/editor_html.php';
                    ?>

                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">

                    <?php
                    $ci_nombre   = 'imagen';
                    $ci_etiqueta = 'Imagen';
                    $ci_actual   = $esNuevo ? '' : (string) $evento['imagen'];
                    $ci_ayuda    = '';
                    require BASE_PATH . '/shared/views/parciales/campo_imagen.php';
                    ?>

                    <?php
                    $sp_valorActual = $esNuevo ? ($pastoralIdPreseleccionado ?? null) : $evento['pastoral_id'];
                    require BASE_PATH . '/shared/views/parciales/selector_pastoral.php';

                    $sc_valorActual = $esNuevo ? null : $evento['centro_id'];
                    require BASE_PATH . '/shared/views/parciales/selector_centro.php';
                    ?>

                    <div class="mb-3">
                        <label for="color" class="form-label fw-semibold">Color en el calendario</label>
                        <input type="color" name="color" id="color" class="form-control form-control-color"
                               value="<?= e($esNuevo ? '#1e4d8b' : ($evento['color'] ?: '#1e4d8b')) ?>">
                    </div>

                    <?php if ($puedePublicar): ?>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               name="publicado" id="publicado" value="1"
                               <?= ($esNuevo || $evento['publicado']) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="publicado">Publicado</label>
                    </div>
                    <?php else: ?>
                    <p class="small text-muted mb-0">
                        <i class="bi bi-info-circle me-1"></i>Este evento se enviará como borrador para que un editor lo publique.
                    </p>
                    <?php endif; ?>

                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-check-lg me-1"></i>Guardar
                </button>
                <a href="<?= e(url_admin('eventos')) ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </div>
    </div>
</form>
