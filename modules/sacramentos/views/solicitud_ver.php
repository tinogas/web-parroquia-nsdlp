<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <nav aria-label="Ubicación">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item">
                    <a href="<?= e(url_admin('solicitudes')) ?>" class="text-decoration-none">Solicitudes</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page"><?= e($solicitud['folio']) ?></li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-1">
            <?= e($solicitud['sacramento_nombre']) ?>
            <span class="font-monospace fs-6 text-muted"><?= e($solicitud['folio']) ?></span>
        </h1>
        <p class="text-muted mb-0 small">Recibida el <?= e(fecha_larga($solicitud['created_at'])) ?></p>
    </div>
    <a href="<?= e(url_admin('solicitudes')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h2 class="h6 fw-bold mb-3">Datos del solicitante</h2>
                <dl class="row mb-0 small">
                    <dt class="col-sm-4">Nombre completo</dt>
                    <dd class="col-sm-8"><?= e($solicitud['nombre_solicitante']) ?></dd>

                    <?php if ($solicitud['fecha_nacimiento']): ?>
                    <dt class="col-sm-4">Fecha de nacimiento</dt>
                    <dd class="col-sm-8">
                        <?= e(fecha_larga($solicitud['fecha_nacimiento'])) ?>
                        <?php if ($solicitud['es_menor']): ?>
                        <span class="badge bg-info-subtle text-info-emphasis">Menor de edad</span>
                        <?php endif; ?>
                    </dd>
                    <?php endif; ?>

                    <?php if ($solicitud['telefono']): ?>
                    <dt class="col-sm-4">Teléfono</dt>
                    <dd class="col-sm-8">
                        <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $solicitud['telefono'])) ?>"><?= e($solicitud['telefono']) ?></a>
                    </dd>
                    <?php endif; ?>

                    <?php if ($solicitud['email']): ?>
                    <dt class="col-sm-4">Correo</dt>
                    <dd class="col-sm-8"><a href="mailto:<?= e($solicitud['email']) ?>"><?= e($solicitud['email']) ?></a></dd>
                    <?php endif; ?>

                    <?php if ($solicitud['direccion']): ?>
                    <dt class="col-sm-4">Dirección</dt>
                    <dd class="col-sm-8"><?= e($solicitud['direccion']) ?></dd>
                    <?php endif; ?>

                    <?php if ($solicitud['fecha_preferida']): ?>
                    <dt class="col-sm-4">Fecha preferida</dt>
                    <dd class="col-sm-8"><?= e(fecha_larga($solicitud['fecha_preferida'])) ?></dd>
                    <?php endif; ?>

                    <?php if ($solicitud['notas']): ?>
                    <dt class="col-sm-4">Notas</dt>
                    <dd class="col-sm-8"><?= e($solicitud['notas']) ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <?php if ($solicitud['es_menor']): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h2 class="h6 fw-bold mb-3">Padre, madre o tutor</h2>
                <dl class="row mb-0 small">
                    <dt class="col-sm-4">Nombre</dt>
                    <dd class="col-sm-8"><?= e((string) $solicitud['tutor_nombre']) ?></dd>
                    <dt class="col-sm-4">Parentesco</dt>
                    <dd class="col-sm-8"><?= e((string) $solicitud['tutor_parentesco']) ?></dd>
                    <dt class="col-sm-4">Teléfono</dt>
                    <dd class="col-sm-8"><?= e((string) $solicitud['tutor_telefono']) ?></dd>
                </dl>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($campos && $datosExtra): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h2 class="h6 fw-bold mb-3">Información adicional</h2>
                <dl class="row mb-0 small">
                    <?php foreach ($campos as $campo): ?>
                        <?php if (!array_key_exists($campo['nombre_campo'], $datosExtra)) { continue; } ?>
                        <dt class="col-sm-4">
                            <?= e($campo['etiqueta']) ?>
                            <?php if ($campo['dato_sensible']): ?>
                            <i class="bi bi-shield-lock text-warning" title="Dato sensible"></i>
                            <?php endif; ?>
                        </dt>
                        <dd class="col-sm-8">
                            <?php if ($campo['tipo'] === 'checkbox'): ?>
                                <?= $datosExtra[$campo['nombre_campo']] === '1' ? 'Sí' : 'No' ?>
                            <?php else: ?>
                                <?= e((string) $datosExtra[$campo['nombre_campo']]) ?>
                            <?php endif; ?>
                        </dd>
                    <?php endforeach; ?>
                </dl>
            </div>
        </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h2 class="h6 fw-bold mb-3">Historial</h2>
                <?php if (!$bitacora): ?>
                <p class="text-muted small mb-0">Sin cambios de estado todavía.</p>
                <?php else: ?>
                <ul class="list-unstyled small mb-0">
                    <?php foreach ($bitacora as $entrada): ?>
                    <li class="mb-2 pb-2 border-bottom">
                        <?php if ($entrada['estado_anterior']): ?>
                        <?= e(SolicitudModel::ESTADOS[$entrada['estado_anterior']] ?? $entrada['estado_anterior']) ?> →
                        <?php endif; ?>
                        <strong><?= e(SolicitudModel::ESTADOS[$entrada['estado_nuevo']] ?? $entrada['estado_nuevo']) ?></strong>
                        <span class="text-muted">
                            — <?= e($entrada['usuario_nombre'] ?? 'Sistema') ?>, <?= e(fecha_larga($entrada['created_at'])) ?>
                        </span>
                        <?php if ($entrada['comentario']): ?>
                        <div class="fst-italic">«<?= e($entrada['comentario']) ?>»</div>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h2 class="h6 fw-bold mb-3">Estado</h2>

                <?php if (Auth::tienePermiso('solicitudes.cambiar_estado')): ?>
                <form method="POST" accept-charset="UTF-8" action="<?= e(url_post('admin', 'solicitudes', 'cambiarEstado')) ?>">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="id" value="<?= (int) $solicitud['id'] ?>">

                    <div class="mb-3">
                        <select name="estado" class="form-select">
                            <?php foreach (SolicitudModel::ESTADOS as $valor => $etiqueta): ?>
                            <option value="<?= e($valor) ?>" <?= $solicitud['estado'] === $valor ? 'selected' : '' ?>>
                                <?= e($etiqueta) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <textarea name="comentario" class="form-control" rows="2"
                                  placeholder="Comentario para la bitácora (opcional)"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-check-lg me-1"></i>Actualizar estado
                    </button>
                </form>
                <?php else: ?>
                <span class="badge bg-secondary-subtle text-secondary-emphasis">
                    <?= e(SolicitudModel::ESTADOS[$solicitud['estado']]) ?>
                </span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
