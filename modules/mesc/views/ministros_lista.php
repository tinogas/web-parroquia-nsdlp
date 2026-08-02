<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <nav aria-label="Ubicación">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item"><a href="<?= e(url_admin('mesc')) ?>" class="text-decoration-none">MESC</a></li>
                <li class="breadcrumb-item active" aria-current="page">Ministros</li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0">Ministros MESC</h1>
    </div>
    <a href="<?= e(url_admin('mesc')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <?php if (!$ministros): ?>
        <p class="text-muted small mb-3">Todavía no hay ministros registrados.</p>
        <?php else: ?>
        <div class="table-responsive mb-3">
            <table class="table table-sm align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Nombre completo</th>
                        <th>Nombre corto</th>
                        <th class="d-none d-md-table-cell">Teléfono</th>
                        <th>&nbsp;</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($ministros as $ministro): ?>
                    <tr class="<?= $ministro['activo'] ? '' : 'text-muted' ?>">
                        <td>
                            <?php if ($ministro['nombre_completo']): ?>
                            <?= e($ministro['nombre_completo']) ?>
                            <?php else: ?>
                            <?php /* Sin ficha vinculada no hay más nombre que el corto; se dice
                                     aquí para que se note a quién le falta. */ ?>
                            <span class="text-body-tertiary fst-italic small">Sin ficha en el equipo</span>
                            <?php endif; ?>
                            <?= $ministro['activo'] ? '' : ' (inactivo)' ?>
                        </td>
                        <td class="fw-semibold"><?= e($ministro['nombre']) ?></td>
                        <td class="d-none d-md-table-cell small"><?= e($ministro['telefono']) ?></td>
                        <td class="text-end">
                            <?php if (Auth::tienePermiso('mesc.editar')): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal" data-bs-target="#ministro<?= (int) $ministro['id'] ?>">
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

        <?php if (Auth::tienePermiso('mesc.crear')): ?>
        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#ministroNuevo">
            <i class="bi bi-plus-lg me-1"></i>Agregar ministro
        </button>
        <?php endif; ?>
    </div>
</div>

<?php
$dibujarModalMinistro = static function (string $idModal, ?array $ministro, int $pastoralId, string $csrf, array $personas) {
    $vacio     = $ministro === null;
    $personaId = $vacio ? 0 : (int) ($ministro['persona_id'] ?? 0);
    ?>
    <div class="modal fade" id="<?= e($idModal) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" accept-charset="UTF-8"
                  action="<?= e(url_post('admin', 'mesc', 'ministro_guardar')) ?>" class="modal-content">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="pastoral_id" value="<?= $pastoralId ?>">
                <input type="hidden" name="id" value="<?= $vacio ? 0 : (int) $ministro['id'] ?>">

                <div class="modal-header border-0 pb-0">
                    <h2 class="h6 modal-title fw-bold"><?= $vacio ? 'Nuevo ministro' : 'Editar ministro' ?></h2>
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
                        <label class="form-label small fw-semibold">Nombre corto</label>
                        <input type="text" name="nombre" class="form-control form-control-sm"
                               value="<?= e($vacio ? '' : $ministro['nombre']) ?>" maxlength="150">
                        <div class="form-text">
                            Como aparece en el calendario de turnos, donde no cabe el nombre completo:
                            «Zulema», «Tino». Se guarda aunque arriba se elija a alguien del equipo —es un
                            dato propio, no se pisa con el de su ficha— y es con lo que se reconoce a cada
                            ministro al capturar un calendario que venga de fuera. Si se deja en blanco, se
                            usa el primer nombre de su ficha.
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Teléfono</label>
                        <?php if ($personaId): ?>
                        <p class="form-control-plaintext form-control-sm py-1 mb-0 small">
                            <?= e((string) ($ministro['telefono'] ?: '—')) ?>
                        </p>
                        <div class="form-text">Viene de su ficha del equipo pastoral.</div>
                        <?php else: ?>
                        <input type="tel" name="telefono" class="form-control form-control-sm"
                               value="<?= e($vacio ? '' : (string) $ministro['telefono']) ?>" maxlength="20">
                        <?php endif; ?>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" name="activo" value="1"
                               id="act<?= e($idModal) ?>" <?= ($vacio || $ministro['activo']) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="act<?= e($idModal) ?>">Activo</label>
                    </div>
                </div>
                <div class="modal-footer border-0 justify-content-between">
                    <?php if (!$vacio && Auth::tienePermiso('mesc.eliminar')): ?>
                    <button type="submit" formaction="<?= e(url_post('admin', 'mesc', 'ministro_eliminar')) ?>"
                            class="btn btn-outline-danger btn-sm"
                            onclick="return confirm('¿Eliminar este ministro?');">
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

$dibujarModalMinistro('ministroNuevo', null, $pastoralId, $csrf, $personas);
foreach ($ministros as $ministro) {
    $dibujarModalMinistro('ministro' . (int) $ministro['id'], $ministro, $pastoralId, $csrf, $personas);
}
?>
