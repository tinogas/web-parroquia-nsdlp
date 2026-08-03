<?php $miId = (int) Auth::usuario()['id']; ?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1">Usuarios</h1>
        <p class="text-muted mb-0 small">
            <?php if ($alcanceLimitado): ?>
            Coordinadores y consultas de tu propia pastoral. Lo que administra cada quien más
            allá de eso se cambia desde Administrador.
            <?php else: ?>
            Cuentas del panel, su rol y las pastorales que administran.
            <?php endif; ?>
        </p>
    </div>
    <a href="<?= e(url_admin('usuarios', 'nuevo')) ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nuevo usuario
    </a>
</div>

<?php if ($usuarios): ?>
<div class="mb-3">
    <div class="input-group input-group-sm" style="max-width: 340px;">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="search" id="buscarUsuario" class="form-control"
               placeholder="Buscar por nombre, correo, rol o pastoral...">
    </div>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <?php if (!$usuarios): ?>
        <div class="card-body text-center py-5">
            <div class="display-6 text-body-tertiary mb-2"><i class="bi bi-people"></i></div>
            <p class="text-muted mb-0">Todavía no hay usuarios registrados.</p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Usuario</th>
                    <th class="d-none d-md-table-cell">Rol</th>
                    <th class="d-none d-lg-table-cell">Pastorales</th>
                    <th class="d-none d-md-table-cell">Estado</th>
                    <th class="d-none d-lg-table-cell">Último acceso</th>
                    <th class="text-end">&nbsp;</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($usuarios as $usuario): ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <img src="<?= e(foto_o_avatar($usuario['foto'], $usuario['nombre'], 40)) ?>"
                                 class="rounded-circle" style="width:32px;height:32px;object-fit:cover" alt="">
                            <div>
                                <div class="fw-semibold">
                                    <?= e($usuario['nombre']) ?>
                                    <?php if ((int) $usuario['id'] === $miId): ?>
                                    <span class="badge bg-primary-subtle text-primary-emphasis">Tú</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-muted small"><?= e($usuario['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="d-none d-md-table-cell">
                        <?= e(ROLES_NOMBRES[$usuario['rol']] ?? $usuario['rol']) ?>
                        <?php if ($usuario['cargo']): ?>
                        <?php /* El cargo real sale de su ficha del equipo pastoral; el rol es lo
                                 que puede hacer en el panel, que no siempre se llama igual. */ ?>
                        <div class="text-body-tertiary small"><?= e($usuario['cargo']) ?></div>
                        <?php elseif (!$usuario['persona_id']): ?>
                        <div class="text-body-tertiary small">Sin ficha en el equipo</div>
                        <?php endif; ?>
                    </td>
                    <td class="d-none d-lg-table-cell small text-muted">
                        <?php $hayPastorales = $usuario['pastorales_agrupadas']['comisiones'] || $usuario['pastorales_agrupadas']['sueltas']; ?>
                        <?php if (!$hayPastorales): ?>
                        —
                        <?php else: ?>
                        <?php /* Padre (Comisión) primero, sus hijas detrás en la misma línea: mismo
                                 agrupado que el checklist del formulario, en un formato compacto para
                                 la tabla. */ ?>
                        <?php foreach ($usuario['pastorales_agrupadas']['comisiones'] as $grupo): ?>
                        <div>
                            <span class="fw-semibold text-body-secondary"><?= e($grupo['padre']['nombre']) ?>:</span>
                            <?= e(implode(' · ', array_column($grupo['hijas'], 'nombre'))) ?>
                        </div>
                        <?php endforeach; ?>
                        <?php if ($usuario['pastorales_agrupadas']['sueltas']): ?>
                        <div><?= e(implode(' · ', array_column($usuario['pastorales_agrupadas']['sueltas'], 'nombre'))) ?></div>
                        <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($hayPastorales): ?>
                        <?php /* Sin sedes marcadas administra su pastoral en toda la parroquia. */ ?>
                        <div class="text-body-tertiary">
                            <i class="bi bi-geo-alt me-1"></i>
                            <?= $usuario['centros_nombres'] ? e($usuario['centros_nombres']) : 'Todas las sedes' ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="d-none d-md-table-cell">
                        <?php if ($usuario['activo']): ?>
                            <span class="badge bg-success-subtle text-success-emphasis">Activo</span>
                        <?php else: ?>
                            <span class="badge bg-secondary-subtle text-secondary-emphasis">Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td class="d-none d-lg-table-cell small text-muted">
                        <?= $usuario['ultimo_acceso'] ? e(fecha_con_dia($usuario['ultimo_acceso'])) : 'Nunca' ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="<?= e(url_admin('usuarios', 'editar', ['id' => $usuario['id']])) ?>"
                           class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-pencil me-1"></i>Editar
                        </a>
                        <?php if ((int) $usuario['id'] !== $miId): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger"
                                data-bs-toggle="modal" data-bs-target="#baja<?= (int) $usuario['id'] ?>" title="Dar de baja">
                            <i class="bi bi-person-dash"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php foreach ($usuarios as $usuario): ?>
    <?php if ((int) $usuario['id'] === $miId) continue; ?>
    <div class="modal fade" id="baja<?= (int) $usuario['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h2 class="h6 modal-title fw-bold">Dar de baja a <?= e($usuario['nombre']) ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">
                        Su cuenta quedará inactiva y no podrá iniciar sesión, pero su historial en la
                        auditoría se conserva. Puedes reactivarla después desde su ficha.
                    </p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" accept-charset="UTF-8"
                          action="<?= e(url_post('admin', 'usuarios', 'eliminar')) ?>" class="m-0">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="id" value="<?= (int) $usuario['id'] ?>">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-person-dash me-1"></i>Dar de baja
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<script>
(function () {
    var buscador = document.getElementById('buscarUsuario');
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
