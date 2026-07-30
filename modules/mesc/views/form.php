<?php
$esNuevo = $visita === null;
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <nav aria-label="Ubicación">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item">
                    <a href="<?= e(url_admin('mesc')) ?>" class="text-decoration-none">MESC</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page"><?= $esNuevo ? 'Nueva' : 'Editar' ?></li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0"><?= $esNuevo ? 'Nueva visita' : e($visita['nombre_enfermo']) ?></h1>
    </div>
    <a href="<?= e(url_admin('mesc')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<div class="alert alert-light border small">
    <i class="bi bi-shield-lock text-primary me-1"></i>
    Este registro contiene un dato de salud (que la persona está enferma). Solo lo ven quienes administran
    esta pastoral. El consentimiento para tratarlo se obtiene en persona al solicitar la visita.
</div>

<form method="POST" accept-charset="UTF-8" action="<?= e(url_post('admin', 'mesc', 'guardar')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="id" value="<?= $esNuevo ? 0 : (int) $visita['id'] ?>">

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">

                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label for="nombre_enfermo" class="form-label fw-semibold">Nombre del enfermo</label>
                            <input type="text" name="nombre_enfermo" id="nombre_enfermo" class="form-control"
                                   value="<?= e($esNuevo ? '' : $visita['nombre_enfermo']) ?>" maxlength="150" required>
                        </div>
                        <div class="col-md-4">
                            <label for="telefono" class="form-label fw-semibold">Teléfono</label>
                            <input type="tel" name="telefono" id="telefono" class="form-control"
                                   value="<?= e($esNuevo ? '' : (string) $visita['telefono']) ?>" maxlength="20">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="direccion" class="form-label fw-semibold">Dirección</label>
                        <input type="text" name="direccion" id="direccion" class="form-control"
                               value="<?= e($esNuevo ? '' : $visita['direccion']) ?>" maxlength="255" required>
                        <div class="form-text">Marca el pin en el mapa si quieres que entre en el cálculo de rutas; si no, se guarda solo la dirección escrita.</div>
                    </div>

                    <div id="mapa-visita" style="height:320px;border-radius:.5rem;overflow:hidden"
                         data-lat="<?= e($esNuevo ? '' : (string) $visita['latitud']) ?>"
                         data-lng="<?= e($esNuevo ? '' : (string) $visita['longitud']) ?>"
                         data-lat-parroquia="<?= e(Config::get('latitud', '')) ?>"
                         data-lng-parroquia="<?= e(Config::get('longitud', '')) ?>"></div>
                    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
                        <span class="small text-muted">Haz clic en el mapa para marcar la ubicación. Puedes arrastrar el pin para ajustarlo.</span>
                        <button type="button" id="quitar-ubicacion" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-x-lg me-1"></i>Quitar ubicación
                        </button>
                    </div>
                    <input type="hidden" name="latitud" id="latitud" value="<?= e($esNuevo ? '' : (string) $visita['latitud']) ?>">
                    <input type="hidden" name="longitud" id="longitud" value="<?= e($esNuevo ? '' : (string) $visita['longitud']) ?>">

                    <div class="mb-3">
                        <label for="notas" class="form-label fw-semibold">Notas</label>
                        <textarea name="notas" id="notas" class="form-control" rows="3"
                                  ><?= e($esNuevo ? '' : (string) $visita['notas']) ?></textarea>
                        <div class="form-text">Cuidados especiales, mejor horario de visita, etc.</div>
                    </div>

                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h2 class="h6 fw-bold mb-3">Quién solicita la visita</h2>
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label for="solicitante_nombre" class="form-label fw-semibold">Nombre</label>
                            <input type="text" name="solicitante_nombre" id="solicitante_nombre" class="form-control"
                                   value="<?= e($esNuevo ? '' : (string) $visita['solicitante_nombre']) ?>" maxlength="150">
                        </div>
                        <div class="col-md-3">
                            <label for="solicitante_parentesco" class="form-label fw-semibold">Parentesco</label>
                            <input type="text" name="solicitante_parentesco" id="solicitante_parentesco" class="form-control"
                                   value="<?= e($esNuevo ? '' : (string) $visita['solicitante_parentesco']) ?>" maxlength="60"
                                   placeholder="Ej. Hija">
                        </div>
                        <div class="col-md-4">
                            <label for="solicitante_telefono" class="form-label fw-semibold">Teléfono</label>
                            <input type="tel" name="solicitante_telefono" id="solicitante_telefono" class="form-control"
                                   value="<?= e($esNuevo ? '' : (string) $visita['solicitante_telefono']) ?>" maxlength="20">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <input type="hidden" name="pastoral_id" value="<?= (int) $pastoralId ?>">

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               name="activo" id="activo" value="1"
                               <?= ($esNuevo || $visita['activo']) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="activo">Activa</label>
                    </div>
                    <div class="form-text">Al desactivarla, deja de aparecer al generar una ruta nueva, pero el registro se conserva.</div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-check-lg me-1"></i>Guardar
                </button>
                <a href="<?= e(url_admin('mesc')) ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </div>
    </div>
</form>
