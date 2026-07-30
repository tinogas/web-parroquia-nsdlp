<?php
/**
 * Configuración de la parroquia.
 *
 * Un formulario independiente por sección: si algo falla al guardar el mapa, no
 * se pierde lo que se escribió en las demás pestañas. Los campos se generan a
 * partir de ConfiguracionModel::CAMPOS, así que agregar un dato nuevo no
 * requiere tocar esta vista.
 */
$grupos = ConfiguracionModel::GRUPOS;
$activo = 'general';
?>

<div class="mb-4">
    <h1 class="h4 fw-bold mb-1">Configuración</h1>
    <p class="text-muted mb-0 small">
        Estos datos alimentan el encabezado, el pie y la página de contacto del sitio.
    </p>
</div>

<div class="row g-4">

    <div class="col-lg-3">
        <div class="list-group list-group-flush nav nav-pills flex-column" id="pestanasConfig" role="tablist">
            <?php foreach ($grupos as $clave => [$nombre, $icono, $descripcion]): ?>
            <button class="list-group-item list-group-item-action d-flex align-items-center gap-2
                           <?= $clave === $activo ? 'active' : '' ?>"
                    id="tab-<?= e($clave) ?>" data-bs-toggle="pill"
                    data-bs-target="#panel-<?= e($clave) ?>" type="button" role="tab">
                <i class="bi <?= e($icono) ?>"></i>
                <span><?= e($nombre) ?></span>
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="col-lg-9">
        <div class="tab-content">
        <?php foreach ($grupos as $grupo => [$nombre, $icono, $descripcion]): ?>

            <div class="tab-pane fade <?= $grupo === $activo ? 'show active' : '' ?>"
                 id="panel-<?= e($grupo) ?>" role="tabpanel" tabindex="0">

                <form method="POST" accept-charset="UTF-8" action="<?= e(url_post('admin', 'configuracion', 'guardar')) ?>"
                      enctype="multipart/form-data" class="card border-0 shadow-sm">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="grupo" value="<?= e($grupo) ?>">

                    <div class="card-body p-4">
                        <h2 class="h6 fw-bold mb-1"><?= e($nombre) ?></h2>
                        <p class="text-muted small mb-4"><?= e($descripcion) ?></p>

                        <?php foreach (ConfiguracionModel::camposDe($grupo) as $clave => [$etiqueta, $tipo, , $ayuda]): ?>
                            <?php
                            $valor = $valores[$clave] ?? '';
                            $id    = 'cfg_' . $clave;
                            ?>

                            <?php if ($tipo === 'imagen'): ?>
                                <?php
                                $ci_nombre   = $clave;
                                $ci_etiqueta = $etiqueta;
                                $ci_actual   = $valor;
                                $ci_ayuda    = $ayuda;
                                require BASE_PATH . '/shared/views/parciales/campo_imagen.php';
                                ?>

                            <?php elseif ($tipo === 'parrafo'): ?>
                                <div class="mb-3">
                                    <label for="<?= e($id) ?>" class="form-label fw-semibold"><?= e($etiqueta) ?></label>
                                    <textarea name="<?= e($clave) ?>" id="<?= e($id) ?>" class="form-control"
                                              rows="3"><?= e($valor) ?></textarea>
                                    <?php if ($ayuda !== ''): ?>
                                    <div class="form-text"><?= e($ayuda) ?></div>
                                    <?php endif; ?>
                                </div>

                            <?php elseif ($tipo === 'mapa'): ?>
                                <div class="mb-3">
                                    <label for="<?= e($id) ?>" class="form-label fw-semibold"><?= e($etiqueta) ?></label>
                                    <textarea name="<?= e($clave) ?>" id="<?= e($id) ?>" class="form-control font-monospace"
                                              rows="3" placeholder="&lt;iframe src=&quot;https://www.google.com/maps/embed?...&quot;&gt;"><?= e($valor) ?></textarea>
                                    <div class="form-text">
                                        <?= e($ayuda) ?>
                                        Solo se guarda la dirección del mapa, no el código completo.
                                    </div>
                                    <?php if ($valor !== ''): ?>
                                    <div class="mt-2 ratio ratio-16x9 marco-mapa">
                                        <iframe src="<?= e($valor) ?>" loading="lazy" title="Ubicación de la parroquia"
                                                referrerpolicy="no-referrer-when-downgrade"></iframe>
                                    </div>
                                    <?php endif; ?>
                                </div>

                            <?php elseif ($tipo === 'booleano'): ?>
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch"
                                               name="<?= e($clave) ?>" id="<?= e($id) ?>" value="1"
                                               <?= $valor === '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-semibold" for="<?= e($id) ?>"><?= e($etiqueta) ?></label>
                                    </div>
                                    <?php if ($ayuda !== ''): ?>
                                    <div class="form-text"><?= e($ayuda) ?></div>
                                    <?php endif; ?>
                                </div>

                            <?php elseif ($tipo === 'solo_lectura'): ?>
                                <div class="mb-3">
                                    <label for="<?= e($id) ?>" class="form-label fw-semibold"><?= e($etiqueta) ?></label>
                                    <input type="text" id="<?= e($id) ?>" class="form-control bg-light"
                                           value="<?= e($valor) ?>" readonly>
                                    <?php if ($ayuda !== ''): ?>
                                    <div class="form-text"><?= e($ayuda) ?></div>
                                    <?php endif; ?>
                                </div>

                            <?php else: ?>
                                <?php
                                $tipoHtml = match ($tipo) {
                                    'email'    => 'email',
                                    'telefono' => 'tel',
                                    'url'      => 'url',
                                    'numero'   => 'number',
                                    default    => 'text',
                                };
                                ?>
                                <div class="mb-3">
                                    <label for="<?= e($id) ?>" class="form-label fw-semibold"><?= e($etiqueta) ?></label>
                                    <input type="<?= e($tipoHtml) ?>" name="<?= e($clave) ?>" id="<?= e($id) ?>"
                                           class="form-control" value="<?= e($valor) ?>"
                                           <?= $tipo === 'numero' ? 'min="0" max="600"' : '' ?>>
                                    <?php if ($ayuda !== ''): ?>
                                    <div class="form-text"><?= e($ayuda) ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                        <?php endforeach; ?>
                    </div>

                    <div class="card-footer bg-white border-top-0 pb-4 px-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Guardar <?= e(mb_strtolower($nombre)) ?>
                        </button>
                    </div>
                </form>
            </div>

        <?php endforeach; ?>
        </div>
    </div>

</div>
