<?php
/**
 * Contenido → Menú del sitio. Las flechas mueven el renglón dentro de la tabla
 * (assets/js/menu_sitio.js); el orden que se guarda es el de los renglones al
 * enviar, leído de los campos ocultos orden[].
 */
$deshabilitado = $puedeEditar ? '' : 'disabled';
?>
<div class="mb-4">
    <h1 class="h4 fw-bold mb-1">Menú del sitio</h1>
    <p class="text-muted mb-0 small">
        Lo que aparece en el menú de arriba del sitio, en qué orden y con qué nombre.
        Inicio va siempre primero y Contacto siempre al final, en el botón dorado.
    </p>
</div>

<form method="POST" accept-charset="UTF-8" action="<?= e(url_post('admin', 'menu', 'guardar')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

    <div class="card border-0 shadow-sm mb-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0" id="tablaMenu">
                <thead class="table-light">
                    <tr>
                        <th style="width:6rem">Orden</th>
                        <th style="width:6rem" class="text-center">Mostrar</th>
                        <th>Entrada</th>
                        <th style="min-width:14rem">Nombre en el menú</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="table-light text-muted">
                        <td><i class="bi bi-lock" title="Fijo"></i></td>
                        <td class="text-center"><i class="bi bi-check-lg"></i></td>
                        <td>Inicio <span class="small">· siempre primero</span></td>
                        <td class="small">Inicio</td>
                    </tr>

                    <?php foreach ($entradas as $entrada): ?>
                    <?php
                    $llave = $entrada['llave'];
                    $id    = 'm_' . str_replace(':', '_', $llave);
                    ?>
                    <tr data-movible>
                        <td class="text-nowrap">
                            <input type="hidden" name="orden[]" value="<?= e($llave) ?>">
                            <button type="button" class="btn btn-sm btn-outline-secondary px-2" data-subir
                                    aria-label="Subir <?= e($entrada['nombre']) ?>" <?= $deshabilitado ?>>
                                <i class="bi bi-arrow-up"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary px-2" data-bajar
                                    aria-label="Bajar <?= e($entrada['nombre']) ?>" <?= $deshabilitado ?>>
                                <i class="bi bi-arrow-down"></i>
                            </button>
                        </td>
                        <td class="text-center">
                            <div class="form-check form-switch d-inline-block m-0">
                                <input class="form-check-input" type="checkbox" role="switch"
                                       name="visible[<?= e($llave) ?>]" id="<?= e($id) ?>" value="1"
                                       <?= $entrada['visible'] ? 'checked' : '' ?> <?= $deshabilitado ?>
                                       aria-label="Mostrar <?= e($entrada['nombre']) ?> en el menú">
                            </div>
                        </td>
                        <td>
                            <label for="<?= e($id) ?>" class="fw-semibold mb-0"><?= e($entrada['nombre']) ?></label>
                            <?php if ($entrada['tipo'] === 'pagina'): ?>
                                <span class="badge bg-info-subtle text-info-emphasis ms-1">Página</span>
                                <?php if (!$entrada['publicada']): ?>
                                <div class="small text-warning-emphasis">
                                    <i class="bi bi-exclamation-triangle me-1"></i>Sin publicar: no aparece aunque esté marcada.
                                </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary-emphasis ms-1">Sección</span>
                                <?php if ($entrada['modulo'] === 'cursos' && $cursosApagado): ?>
                                <div class="small text-warning-emphasis">
                                    <i class="bi bi-exclamation-triangle me-1"></i>Apagada en Configuración → Secciones del sitio: no aparece aunque esté marcada.
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <input type="text" name="etiqueta[<?= e($llave) ?>]" class="form-control form-control-sm"
                                   value="<?= e($entrada['etiqueta']) ?>" maxlength="60"
                                   placeholder="<?= e($entrada['nombre']) ?>" <?= $deshabilitado ?>
                                   aria-label="Nombre en el menú de <?= e($entrada['nombre']) ?>">
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <tr class="table-light text-muted">
                        <td><i class="bi bi-lock" title="Fijo"></i></td>
                        <td class="text-center"><i class="bi bi-check-lg"></i></td>
                        <td>Contacto <span class="small">· siempre al final, en el botón dorado</span></td>
                        <td class="small">Contacto</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-3">
        <?php if ($puedeEditar): ?>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i>Guardar menú
        </button>
        <?php endif; ?>
        <a href="<?= e(url_publica('inicio')) ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary">
            <i class="bi bi-box-arrow-up-right me-1"></i>Ver el sitio
        </a>
        <span class="small text-muted">
            El nombre vacío deja el de siempre. Las páginas nuevas se agregan desde Contenido → Páginas.
        </span>
    </div>
</form>
