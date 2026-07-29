<?php
$esNuevo = $turno === null;
$pastoralFija = !$esNuevo ? (int) $turno['pastoral_id'] : (count($pastorales) === 1 ? (int) $pastorales[0]['id'] : null);

$nombrePastoralFija = '';
if ($pastoralFija !== null) {
    foreach ($pastorales as $pastoral) {
        if ((int) $pastoral['id'] === $pastoralFija) {
            $nombrePastoralFija = $pastoral['nombre'];
            break;
        }
    }
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <nav aria-label="Ubicación">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item"><a href="<?= e(url_admin('mesc')) ?>" class="text-decoration-none">MESC</a></li>
                <li class="breadcrumb-item"><a href="<?= e(url_admin('mesc', 'turnos')) ?>" class="text-decoration-none">Turnos</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= $esNuevo ? 'Nuevo' : 'Editar' ?></li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0"><?= $esNuevo ? 'Nuevo turno' : e($turno['descripcion']) ?></h1>
    </div>
    <a href="<?= e(url_admin('mesc', 'turnos')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<form method="POST" accept-charset="UTF-8" action="<?= e(url_post('admin', 'mesc', 'turno_guardar')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="id" value="<?= $esNuevo ? 0 : (int) $turno['id'] ?>">

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">

            <?php if (!$esNuevo || count($pastorales) === 1): ?>
            <input type="hidden" name="pastoral_id" value="<?= (int) $pastoralFija ?>">
            <p class="small text-muted mb-3">
                Pastoral: <strong><?= e($nombrePastoralFija) ?></strong>
            </p>
            <?php else: ?>
            <div class="mb-3">
                <label for="pastoral_id" class="form-label fw-semibold">Pastoral</label>
                <select name="pastoral_id" id="pastoral_id" class="form-select" required
                        onchange="mescMostrarMinistros(this.value)">
                    <option value="">Elige una pastoral</option>
                    <?php foreach ($pastorales as $pastoral): ?>
                    <option value="<?= (int) $pastoral['id'] ?>"><?= e($pastoral['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label for="fecha" class="form-label fw-semibold">Fecha</label>
                    <input type="date" name="fecha" id="fecha" class="form-control"
                           value="<?= e($esNuevo ? $fechaSugerida : (string) $turno['fecha']) ?>" required>
                </div>
                <div class="col-md-3">
                    <label for="hora" class="form-label fw-semibold">Hora <span class="text-muted fw-normal">(opcional)</span></label>
                    <input type="time" name="hora" id="hora" class="form-control"
                           value="<?= e($esNuevo || !$turno['hora'] ? '' : substr((string) $turno['hora'], 0, 5)) ?>">
                </div>
                <div class="col-md-5">
                    <label for="descripcion" class="form-label fw-semibold">Qué se cubre</label>
                    <input type="text" name="descripcion" id="descripcion" class="form-control" maxlength="160"
                           value="<?= e($esNuevo ? '' : $turno['descripcion']) ?>"
                           placeholder="Ej. Misa de 12:00" required>
                </div>
            </div>

            <div class="mb-3">
                <label for="color_liturgico_id" class="form-label fw-semibold">
                    Color litúrgico <span class="text-muted fw-normal">(opcional)</span>
                </label>
                <select name="color_liturgico_id" id="color_liturgico_id" class="form-select">
                    <option value="">Sin asignar</option>
                    <?php foreach ($colores as $color): ?>
                    <option value="<?= (int) $color['id'] ?>"
                        <?= (!$esNuevo && (int) ($turno['color_liturgico_id'] ?? 0) === (int) $color['id']) ? 'selected' : '' ?>>
                        <?= e($color['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">
                    <a href="<?= e(url_admin('mesc', 'colores')) ?>" target="_blank">Ver el significado de cada color</a>.
                </div>
            </div>

            <label class="form-label fw-semibold">Ministros asignados</label>
            <?php foreach ($pastorales as $pastoral): ?>
            <?php
            $pid = (int) $pastoral['id'];
            $lista = $ministrosPorPastoral[$pid] ?? [];
            $mostrar = $pastoralFija === $pid;
            ?>
            <div class="mesc-grupo-ministros" data-pastoral="<?= $pid ?>" style="<?= $mostrar ? '' : 'display:none' ?>">
                <?php if (!$lista): ?>
                <p class="text-muted small">Esta pastoral no tiene ministros activos registrados todavía.
                    <a href="<?= e(url_admin('mesc', 'ministros')) ?>">Agregar uno</a>.
                </p>
                <?php else: ?>
                <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-1 mb-2">
                    <?php foreach ($lista as $ministro): ?>
                    <div class="col">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="ministros[]"
                                   value="<?= (int) $ministro['id'] ?>" id="min<?= (int) $ministro['id'] ?>"
                                   <?= in_array((int) $ministro['id'], $asignados, true) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="min<?= (int) $ministro['id'] ?>">
                                <?= e($ministro['nombre']) ?>
                            </label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary flex-grow-1">
            <i class="bi bi-check-lg me-1"></i>Guardar
        </button>
        <a href="<?= e(url_admin('mesc', 'turnos')) ?>" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<?php if ($esNuevo && count($pastorales) > 1): ?>
<script>
function mescMostrarMinistros(pastoralId) {
    document.querySelectorAll('.mesc-grupo-ministros').forEach(function (grupo) {
        grupo.style.display = (grupo.dataset.pastoral === pastoralId) ? '' : 'none';
    });
}
</script>
<?php endif; ?>
