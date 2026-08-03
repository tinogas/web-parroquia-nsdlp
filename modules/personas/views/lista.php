<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1">Equipo pastoral</h1>
        <p class="text-muted mb-0 small">
            Sacerdotes, diáconos y personal que aparecen en "Quiénes somos" y pueden
            asignarse al organigrama.
        </p>
    </div>
    <a href="<?= e(url_admin('personas', 'nueva')) ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nueva persona
    </a>
</div>

<?php
// Filtro de conveniencia (por pertenencia propia de la ficha), no de acceso:
// quien llega aquí ya ve a todo el equipo.
$porPastoral = $filtroPastoral !== '' ? ['pastoral' => $filtroPastoral] : [];
$porCentro   = $filtroCentro !== '' ? ['centro' => $filtroCentro] : [];
$porAmbito   = $porPastoral + $porCentro;
?>

<form method="GET" action="<?= e(url_admin('personas')) ?>" class="row g-2 align-items-end mb-3">
    <?php if (!URLS_AMIGABLES): ?>
    <?php /* Sin URLs amigables la ruta va en la cadena de consulta, y un GET
             descarta la del action: hay que repetirla como campos. */ ?>
    <input type="hidden" name="area" value="admin">
    <input type="hidden" name="modulo" value="personas">
    <?php endif; ?>
    <div class="col-auto">
        <label for="pastoral" class="form-label small fw-semibold mb-1">Pastoral</label>
        <select name="pastoral" id="pastoral" class="form-select form-select-sm">
            <option value="">Todas</option>
            <?php foreach ($pastorales as $unaPastoral): ?>
            <option value="<?= (int) $unaPastoral['id'] ?>"
                    <?= $filtroPastoral === (string) $unaPastoral['id'] ? 'selected' : '' ?>>
                <?= e($unaPastoral['nombre']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php if (count($centros) > 1): /* Con una sola sede el selector no elige nada. */ ?>
    <div class="col-auto">
        <label for="centro" class="form-label small fw-semibold mb-1">Sede</label>
        <select name="centro" id="centro" class="form-select form-select-sm">
            <option value="">Todas</option>
            <?php foreach ($centros as $unCentro): ?>
            <option value="<?= (int) $unCentro['id'] ?>"
                    <?= $filtroCentro === (string) $unCentro['id'] ? 'selected' : '' ?>>
                <?= e($unCentro['nombre']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-primary">
            <i class="bi bi-funnel me-1"></i>Filtrar
        </button>
    </div>
    <?php if ($porAmbito): ?>
    <div class="col-auto">
        <a href="<?= e(url_admin('personas')) ?>" class="btn btn-sm btn-outline-secondary">Quitar</a>
    </div>
    <?php endif; ?>
</form>

<?php if ($personas): ?>
<div class="mb-3">
    <div class="input-group input-group-sm" style="max-width: 340px;">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="search" id="buscarPersona" class="form-control"
               placeholder="Buscar por nombre o cargo...">
    </div>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <?php if (!$personas): ?>
        <div class="card-body text-center py-5">
            <div class="display-6 text-body-tertiary mb-2"><i class="bi bi-person-badge"></i></div>
            <?php if ($porAmbito): ?>
            <p class="text-muted mb-2">Nadie coincide con el filtro.</p>
            <a href="<?= e(url_admin('personas')) ?>" class="btn btn-sm btn-outline-secondary">
                Ver todo el equipo
            </a>
            <?php else: ?>
            <p class="text-muted mb-0">Todavía no hay nadie registrado.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Nombre</th>
                    <th class="d-none d-md-table-cell">Tipo</th>
                    <th class="d-none d-md-table-cell">Estado</th>
                    <th class="text-end">&nbsp;</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($personas as $persona): ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <img src="<?= e(foto_o_avatar($persona['foto'], $persona['nombre'], 40)) ?>"
                                 class="rounded-circle" style="width:32px;height:32px;object-fit:cover" alt="">
                            <div>
                                <div class="fw-semibold"><?= e($persona['nombre']) ?></div>
                                <?php if ($persona['cargo']): ?>
                                <div class="text-muted small"><?= e($persona['cargo']) ?></div>
                                <?php endif; ?>
                                <?php
                                $ambitos = array_filter([$persona['pastorales_nombres'] ?? null, $persona['centros_nombres'] ?? null]);
                                if ($ambitos):
                                ?>
                                <div class="text-muted small"><i class="bi bi-people me-1"></i><?= e(implode(', ', $ambitos)) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($persona['pastorales_coordina'])): ?>
                                <div class="small mt-1">
                                    <span class="badge bg-primary-subtle text-primary-emphasis">
                                        <i class="bi bi-star-fill me-1"></i>Coordina: <?= e($persona['pastorales_coordina']) ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="d-none d-md-table-cell"><?= e(PersonaModel::TIPOS[$persona['tipo']] ?? $persona['tipo']) ?></td>
                    <td class="d-none d-md-table-cell">
                        <?php if ($persona['activo']): ?>
                            <span class="badge bg-success-subtle text-success-emphasis">Activo</span>
                        <?php else: ?>
                            <span class="badge bg-secondary-subtle text-secondary-emphasis">Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="<?= e(url_admin('personas', 'editar', ['id' => $persona['id']])) ?>"
                           class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-pencil me-1"></i>Editar
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-danger"
                                data-bs-toggle="modal" data-bs-target="#borrar<?= (int) $persona['id'] ?>" title="Eliminar">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php foreach ($personas as $persona): ?>
    <div class="modal fade" id="borrar<?= (int) $persona['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h2 class="h6 modal-title fw-bold">Eliminar a <?= e($persona['nombre']) ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">
                        Si ya no forma parte del equipo, es mejor <strong>desactivarla</strong> desde su ficha:
                        conserva el historial y su lugar en el organigrama. Elimínala solo si el registro se
                        creó por error. Si aparecía en el organigrama, ese lugar quedará sin asignar.
                    </p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" accept-charset="UTF-8"
                          action="<?= e(url_post('admin', 'personas', 'eliminar')) ?>" class="m-0">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="id" value="<?= (int) $persona['id'] ?>">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash me-1"></i>Eliminar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<script>
(function () {
    var buscador = document.getElementById('buscarPersona');
    var filas    = document.querySelectorAll('table tbody tr');
    if (!buscador || !filas.length) { return; }

    buscador.addEventListener('input', function () {
        var q = this.value.trim().toLowerCase();
        filas.forEach(function (tr) {
            tr.classList.toggle('d-none', q !== '' && !tr.textContent.toLowerCase().includes(q));
        });
    });
})();
</script>
