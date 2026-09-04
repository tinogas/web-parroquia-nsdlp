<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <nav aria-label="Ubicación">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item"><a href="<?= e(url_admin('proclamadores')) ?>" class="text-decoration-none">Proclamadores</a></li>
                <li class="breadcrumb-item active" aria-current="page">Catálogo</li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0">Catálogo de proclamadores</h1>
        <p class="text-muted mb-0 small">Quiénes proclaman, y qué prefiere hacer cada quien.</p>
    </div>
    <a href="<?= e(url_admin('proclamadores')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver al calendario
    </a>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <?php if (!$proclamadores): ?>
        <p class="text-muted small mb-3">Todavía no hay proclamadores registrados.</p>
        <?php else: ?>
        <div class="table-responsive mb-3">
            <table class="table table-sm align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th>
                        <th>Prefiere</th>
                        <th class="d-none d-md-table-cell">Contacto</th>
                        <th>&nbsp;</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($proclamadores as $proclamador): ?>
                    <tr class="<?= $proclamador['activo'] ? '' : 'text-muted' ?>">
                        <td><?= e($proclamador['nombre']) ?><?= $proclamador['activo'] ? '' : ' (inactivo)' ?></td>
                        <td class="small">
                            <?php
                            // La columna SET llega como 'monitor,lectura'; sin nada
                            // marcado se muestra un guion, que no es lo mismo que
                            // "no prefiere nada": es que no se le ha preguntado.
                            $marcadas = array_filter(explode(',', (string) ($proclamador['preferencias'] ?? '')));
                            ?>
                            <?php if (!$marcadas): ?>
                            <span class="text-muted">—</span>
                            <?php else: ?>
                            <?php foreach ($marcadas as $clave): ?>
                            <span class="badge bg-light text-dark border fw-normal"><?= e($preferencias[$clave] ?? $clave) ?></span>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td class="d-none d-md-table-cell small">
                            <?= e(trim(($proclamador['telefono'] ?? '')
                                . ($proclamador['telefono'] && $proclamador['email'] ? ' · ' : '')
                                . ($proclamador['email'] ?? ''))) ?>
                        </td>
                        <td class="text-end">
                            <?php if (Auth::tienePermiso('proclamadores.editar')): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal" data-bs-target="#proclamador<?= (int) $proclamador['id'] ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if (Auth::tienePermiso('proclamadores.crear')): ?>
        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#proclamadorNuevo">
            <i class="bi bi-plus-lg me-1"></i>Agregar proclamador
        </button>
        <?php endif; ?>
    </div>
</div>

<?php
$dibujarModalProclamador = static function (
    string $idModal,
    ?array $proclamador,
    int $pastoralId,
    string $csrf,
    array $personas,
    array $preferencias
) {
    $vacio     = $proclamador === null;
    $personaId = $vacio ? 0 : (int) ($proclamador['persona_id'] ?? 0);
    $marcadas  = $vacio ? [] : array_filter(explode(',', (string) ($proclamador['preferencias'] ?? '')));
    $contacto  = $vacio ? '' : trim(
        (string) ($proclamador['telefono'] ?? '')
        . (($proclamador['telefono'] ?? '') && ($proclamador['email'] ?? '') ? ' · ' : '')
        . (string) ($proclamador['email'] ?? '')
    );
    ?>
    <div class="modal fade" id="<?= e($idModal) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" accept-charset="UTF-8"
                  action="<?= e(url_post('admin', 'proclamadores', 'proclamador_guardar')) ?>" class="modal-content">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="pastoral_id" value="<?= $pastoralId ?>">
                <input type="hidden" name="id" value="<?= $vacio ? 0 : (int) $proclamador['id'] ?>">

                <div class="modal-header border-0 pb-0">
                    <h2 class="h6 modal-title fw-bold"><?= $vacio ? 'Nuevo proclamador' : 'Editar proclamador' ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">¿Quién es?</label>
                        <select name="persona_id" class="form-select form-select-sm">
                            <option value="">— Todavía no está en el equipo pastoral —</option>
                            <?php foreach ($personas as $persona): ?>
                            <option value="<?= (int) $persona['id'] ?>"
                                <?= $personaId === (int) $persona['id'] ? 'selected' : '' ?>>
                                <?= e($persona['nombre']) ?><?= $persona['cargo'] ? ' — ' . e($persona['cargo']) : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Nombre, si no está en el equipo pastoral</label>
                        <input type="text" name="nombre" class="form-control form-control-sm"
                               value="<?= e($vacio || $personaId ? '' : $proclamador['nombre']) ?>" maxlength="140">
                        <div class="form-text">Se ignora si arriba eliges a alguien del equipo.</div>
                    </div>
                    <?php if ($personaId): ?>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Contacto</label>
                        <p class="form-control-plaintext form-control-sm py-1 mb-0 small">
                            <?= $contacto !== '' ? e($contacto) : '—' ?>
                        </p>
                        <div class="form-text">Viene de su ficha del equipo pastoral.</div>
                    </div>
                    <?php else: ?>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Teléfono</label>
                        <input type="tel" name="telefono" class="form-control form-control-sm"
                               value="<?= e($vacio ? '' : (string) $proclamador['telefono']) ?>" maxlength="20">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Correo</label>
                        <input type="email" name="email" class="form-control form-control-sm"
                               value="<?= e($vacio ? '' : (string) $proclamador['email']) ?>" maxlength="150">
                    </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Prefiere</label>
                        <?php foreach ($preferencias as $clave => $nombre): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="preferencias[]"
                                   value="<?= e($clave) ?>" id="pref<?= e($clave . $idModal) ?>"
                                   <?= in_array($clave, $marcadas, true) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="pref<?= e($clave . $idModal) ?>">
                                <?= e($nombre) ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                        <div class="form-text">Orienta a quien arma el turno; no impide asignarle otra cosa.</div>
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" name="activo" value="1"
                               id="act<?= e($idModal) ?>" <?= ($vacio || $proclamador['activo']) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="act<?= e($idModal) ?>">Activo</label>
                    </div>
                </div>
                <div class="modal-footer border-0 justify-content-between">
                    <?php if (!$vacio && Auth::tienePermiso('proclamadores.eliminar')): ?>
                    <button type="submit" formaction="<?= e(url_post('admin', 'proclamadores', 'proclamador_eliminar')) ?>"
                            class="btn btn-outline-danger btn-sm"
                            onclick="return confirm('¿Eliminar este proclamador?');">
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

$dibujarModalProclamador('proclamadorNuevo', null, $pastoralId, $csrf, $personas, $preferencias);
foreach ($proclamadores as $proclamador) {
    $dibujarModalProclamador('proclamador' . (int) $proclamador['id'], $proclamador, $pastoralId, $csrf, $personas, $preferencias);
}
?>
