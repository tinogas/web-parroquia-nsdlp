<?php
$esNuevo   = $cuenta === null;
// Una cuenta es, ante todo, alguien del equipo pastoral. El vínculo es opcional
// porque crear una ficha publica a esa persona en el sitio, y la cuenta técnica
// del administrador no tiene por qué salir en «Quiénes somos».
$vinculada = !$esNuevo && $cuenta['persona_id'] !== null;
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <nav aria-label="Ubicación">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item">
                    <a href="<?= e(url_admin('usuarios')) ?>" class="text-decoration-none">Usuarios</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page"><?= $esNuevo ? 'Nuevo' : 'Editar' ?></li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0"><?= $esNuevo ? 'Nuevo usuario' : e($cuenta['nombre']) ?></h1>
    </div>
    <a href="<?= e(url_admin('usuarios')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<form method="POST" accept-charset="UTF-8" action="<?= e(url_post('admin', 'usuarios', 'guardar')) ?>"
      enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="id" value="<?= $esNuevo ? 0 : (int) $cuenta['id'] ?>">

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">

                    <div class="mb-3">
                        <label for="persona_id" class="form-label fw-semibold">¿Quién es?</label>
                        <select name="persona_id" id="persona_id" class="form-select"
                                data-id-vinculada="<?= $vinculada ? (int) $fichaCuenta['id'] : '' ?>">
                            <option value="">— Cuenta sin ficha en el equipo pastoral —</option>
                            <?php foreach ($personas as $persona): ?>
                            <option value="<?= (int) $persona['id'] ?>"
                                <?= (!$esNuevo && (int) $cuenta['persona_id'] === (int) $persona['id']) ? 'selected' : '' ?>>
                                <?= e($persona['nombre']) ?><?= $persona['cargo'] ? ' — ' . e($persona['cargo']) : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">
                            El equipo pastoral es el registro principal: de ahí salen el organigrama y las
                            cuentas. Al elegir a alguien, <strong>su nombre, su teléfono, su foto y —si el rol
                            lo usa— sus pastorales y sus sedes se toman siempre de su ficha</strong>: para
                            cambiarlos se edita la ficha, no la cuenta. Solo aparecen las personas que
                            todavía no tienen cuenta.
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <?php if (!$vinculada): ?>
                        <div class="col-md-7">
                            <label for="nombre" class="form-label fw-semibold">Nombre completo</label>
                            <input type="text" name="nombre" id="nombre" class="form-control"
                                   value="<?= e($esNuevo ? '' : $cuenta['nombre']) ?>" maxlength="120">
                            <div class="form-text">Se ignora si arriba eliges a alguien del equipo.</div>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-5">
                            <label for="rol" class="form-label fw-semibold">Rol</label>
                            <select name="rol" id="rol" class="form-select">
                                <?php foreach ($rolesDisponibles as $valor => $etiqueta): ?>
                                <option value="<?= e($valor) ?>"
                                    <?= (!$esNuevo && $cuenta['rol'] === $valor) ? 'selected' : '' ?>>
                                    <?= e($etiqueta) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">
                                Coordinador administra su pastoral en <strong>una</strong> sede; Coordinador
                                general, en varias o en todas; Consulta solo mira.
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-7">
                            <label for="email" class="form-label fw-semibold">Correo electrónico</label>
                            <input type="email" name="email" id="email" class="form-control"
                                   value="<?= e($esNuevo ? '' : $cuenta['email']) ?>" required>
                            <div class="form-text">Con este correo se inicia sesión.</div>
                        </div>
                        <?php if (!$vinculada): ?>
                        <div class="col-md-5">
                            <label for="telefono" class="form-label fw-semibold">Teléfono</label>
                            <input type="tel" name="telefono" id="telefono" class="form-control"
                                   value="<?= e($esNuevo ? '' : (string) $cuenta['telefono']) ?>">
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="password" class="form-label fw-semibold">
                                Contraseña<?= $esNuevo ? '' : ' nueva' ?>
                            </label>
                            <input type="password" name="password" id="password" class="form-control"
                                   placeholder="<?= $esNuevo ? 'Mínimo 8 caracteres' : 'Déjalo en blanco para no cambiarla' ?>"
                                   autocomplete="new-password">
                        </div>
                    </div>

                    <?php if ($esNuevo || in_array($cuenta['rol'], ROLES_CON_ALCANCE_PASTORAL, true)): ?>
                    <?php /* Las dos mitades del alcance. Marcar sedes acota DÓNDE; marcar
                             pastorales dice QUÉ administra ahí. Ni una ni otra reparte nada
                             por su cuenta: una sede marcada no da las demás pastorales de esa
                             sede. Sede primero porque acota lo que sigue, no al revés.

                             Con persona vinculada, ninguno de los dos se marca aquí: se heredan
                             siempre de su ficha (ver UsuarioController::guardar()), y esta
                             sección solo los resume de solo lectura. Cuál de los tres bloques
                             de abajo se ve lo decide el JS al final del archivo, según la
                             persona elegida arriba en cada momento —la ya vinculada al cargar,
                             otra distinta, o ninguna—, para que cambiar la selección sin
                             guardar no deje en pantalla el resumen de otra persona. */ ?>

                    <div id="bloqueAlcanceManual" class="<?= $vinculada ? 'd-none' : '' ?>">
                        <div class="mb-0">
                            <label class="form-label fw-semibold">Sedes en las que trabaja</label>
                            <div class="form-text mb-2">
                                En qué comunidades trabaja. <strong>Sin marcar ninguna trabaja en toda la
                                parroquia</strong>, que es como se representa una coordinación general.
                            </div>
                            <?php if (!$centros): ?>
                            <p class="text-muted small">Todavía no hay centros o sedes dados de alta.</p>
                            <?php else: ?>
                            <div class="row row-cols-1 row-cols-sm-2 g-1">
                                <?php foreach ($centros as $centro): ?>
                                <div class="col">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="centros[]"
                                               value="<?= (int) $centro['id'] ?>" id="cen<?= (int) $centro['id'] ?>"
                                               <?= in_array((int) $centro['id'], $centrosAsignados, true) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="cen<?= (int) $centro['id'] ?>">
                                            <?= e($centro['nombre']) ?>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-0 mt-3">
                            <label class="form-label fw-semibold">Pastorales que administra</label>
                            <div class="form-text mb-2">
                                Esta cuenta trabajará <strong>únicamente</strong> con lo que se marque aquí: sus
                                eventos, sus cursos y su contenido, acotado a la sede de arriba si marcaste
                                alguna —quien coordina la catequesis de Jesús el Señor marca «Jesús el Señor»
                                arriba y «Catecismo» aquí—. Solo se guarda con un rol acotado por pastoral
                                (Coordinador, o Administrador/Consulta de MESC, Catequesis o Lector); con
                                cualquier otro rol se ignora. Cada Comisión (Litúrgica, Profética...) aparece
                                solo como encabezado para agrupar a sus pastorales: no tiene contenido propio
                                que administrar, no se marca aquí, y el alcance no se hereda de una Comisión a
                                las que agrupa.
                            </div>
                            <?php if (!$pastoralesAgrupadas['comisiones'] && !$pastoralesAgrupadas['sueltas']): ?>
                            <p class="text-muted small">Todavía no hay pastorales dadas de alta.</p>
                            <?php else: ?>
                            <?php /* Padre arriba, hijas debajo, cada Comisión en su propia columna: el
                                     mismo agrupado que ya usa el panel (PastoralModel::agrupadoVisible()),
                                     recortado al alcance de quien edita. El nombre de la Comisión es solo
                                     un encabezado visual —no hay checkbox para ella—, las hijas sí. */ ?>
                            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
                                <?php foreach ($pastoralesAgrupadas['comisiones'] as $grupo): ?>
                                <div class="col">
                                    <div class="border rounded-3 p-2 h-100">
                                        <p class="text-uppercase text-muted small fw-semibold mb-2 pb-1 border-bottom">
                                            <?= e($grupo['padre']['nombre']) ?>
                                        </p>
                                        <?php foreach ($grupo['hijas'] as $hija): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="pastorales[]"
                                                   value="<?= (int) $hija['id'] ?>" id="pas<?= (int) $hija['id'] ?>"
                                                   <?= in_array((int) $hija['id'], $asignadas, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="pas<?= (int) $hija['id'] ?>">
                                                <?= e($hija['nombre']) ?>
                                            </label>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>

                                <?php if ($pastoralesAgrupadas['sueltas']): ?>
                                <div class="col">
                                    <div class="border rounded-3 p-2 h-100">
                                        <p class="text-uppercase text-muted small fw-semibold mb-2 pb-1 border-bottom">
                                            Otras pastorales
                                        </p>
                                        <?php foreach ($pastoralesAgrupadas['sueltas'] as $suelta): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="pastorales[]"
                                                   value="<?= (int) $suelta['id'] ?>" id="pas<?= (int) $suelta['id'] ?>"
                                                   <?= in_array((int) $suelta['id'], $asignadas, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="pas<?= (int) $suelta['id'] ?>">
                                                <?= e($suelta['nombre']) ?>
                                            </label>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php
                        // Nombres resueltos de solo lectura para quien YA estaba vinculada al
                        // cargar la página. Se cruza contra el catálogo COMPLETO (centrosTodos,
                        // sin acotar por alcance): la ficha pudo editarla alguien con más alcance
                        // que quien ve esta pantalla. Las pastorales usan pastoralesFichaAgrupadas
                        // (ya calculado en el controlador con PastoralModel::agruparIds()), que
                        // agrupa padre/hijas incluso cuando la propia Comisión está marcada en la
                        // ficha (como Litúrgica y Profética en la de Zulema).
                        $nombresCentrosFicha = $vinculada ? array_values(array_filter(array_map(
                            static fn (array $c): ?string => in_array((int) $c['id'], $centrosAsignados, true) ? $c['nombre'] : null,
                            $centrosTodos
                        ))) : [];
                    ?>
                    <div id="resumenAlcanceFicha" class="<?= $vinculada ? '' : 'd-none' ?>">
                        <label class="form-label fw-semibold">Sedes y pastorales</label>
                        <div class="form-text mb-2">
                            Se toman siempre de
                            <a href="<?= e(url_admin('personas', 'editar', ['id' => $vinculada ? (int) $fichaCuenta['id'] : 0])) ?>">su
                            ficha en el equipo pastoral</a>; no se marcan aquí.
                        </div>
                        <p class="mb-3">
                            <span class="fw-semibold small d-block mb-1">Sedes</span>
                            <?= $nombresCentrosFicha
                                ? e(implode(' · ', $nombresCentrosFicha))
                                : '<span class="text-muted">Toda la parroquia (ninguna marcada)</span>' ?>
                        </p>
                        <?php
                            // Color del badge según el nivel real de acceso —ver
                            // UsuarioController::pastoralesFichaConNivel()—: verde para
                            // control total (rol principal), azul para un perfil
                            // adicional (con su propio rol), gris para ninguno.
                            $claseNivel = [
                                'principal'  => 'bg-success-subtle text-success-emphasis',
                                'perfil'     => 'bg-info-subtle text-info-emphasis',
                                'sin_acceso' => 'bg-secondary-subtle text-secondary-emphasis',
                            ];
                        ?>
                        <div>
                            <span class="fw-semibold small d-block mb-2">Pastorales</span>
                            <?php if (!$pastoralesFichaAgrupadas['comisiones'] && !$pastoralesFichaAgrupadas['sueltas']): ?>
                            <span class="text-muted">Ninguna en su ficha todavía</span>
                            <?php else: ?>
                            <div class="row row-cols-1 row-cols-md-2 g-2">
                                <?php foreach ($pastoralesFichaAgrupadas['comisiones'] as $grupo): ?>
                                <div class="col">
                                    <div class="border rounded-3 p-2 h-100">
                                        <p class="text-uppercase text-muted small fw-semibold mb-2 pb-1 border-bottom">
                                            <?= e($grupo['padre']['nombre']) ?>
                                        </p>
                                        <?php foreach ($grupo['hijas'] as $hija): ?>
                                        <div class="d-flex justify-content-between align-items-center gap-2 small mb-1">
                                            <span><?= e($hija['nombre']) ?></span>
                                            <span class="badge <?= e($claseNivel[$hija['nivel']] ?? '') ?>">
                                                <?= e($hija['nivel_etiqueta']) ?>
                                            </span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php if ($pastoralesFichaAgrupadas['sueltas']): ?>
                                <div class="col">
                                    <div class="border rounded-3 p-2 h-100">
                                        <p class="text-uppercase text-muted small fw-semibold mb-2 pb-1 border-bottom">
                                            Otras pastorales
                                        </p>
                                        <?php foreach ($pastoralesFichaAgrupadas['sueltas'] as $suelta): ?>
                                        <div class="d-flex justify-content-between align-items-center gap-2 small mb-1">
                                            <span><?= e($suelta['nombre']) ?></span>
                                            <span class="badge <?= e($claseNivel[$suelta['nivel']] ?? '') ?>">
                                                <?= e($suelta['nivel_etiqueta']) ?>
                                            </span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="form-text mt-2 mb-0">
                                <span class="badge bg-success-subtle text-success-emphasis">Control total</span> crea,
                                edita y publica ahí. <span class="badge bg-info-subtle text-info-emphasis">Un perfil</span>
                                solo tiene el nivel de ese perfil (ver "Perfiles adicionales de acceso" abajo).
                                <span class="badge bg-secondary-subtle text-secondary-emphasis">Sin acceso</span>
                                pertenece en la ficha, pero esta cuenta no la administra en absoluto —normal en una
                                Comisión, que no tiene contenido propio.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div id="resumenAlcanceGenerico" class="d-none">
                        <label class="form-label fw-semibold">Sedes y pastorales</label>
                        <div class="form-text mb-0">
                            Se tomarán de
                            <a href="#" id="lnkFichaGenerica"
                               data-url-base="<?= e(url_admin('personas', 'editar', ['id' => '__ID__'])) ?>">su
                            ficha en el equipo pastoral</a>; no se marcan aquí. Podrás verlas en esta
                            pantalla después de guardar.
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <?php if ($vinculada): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <p class="text-uppercase text-muted small fw-semibold mb-2">Su ficha en el equipo</p>
                    <?php if ($fichaCuenta['foto']): ?>
                    <img src="<?= e(url_activo('uploads/' . $fichaCuenta['foto'])) ?>" alt=""
                         class="rounded-circle mb-2" style="width:64px;height:64px;object-fit:cover">
                    <?php endif; ?>
                    <p class="fw-semibold mb-0"><?= e($fichaCuenta['nombre']) ?></p>
                    <p class="text-muted small mb-1"><?= e($fichaCuenta['cargo'] ?: 'Sin cargo anotado') ?></p>
                    <?php if ($fichaCuenta['telefono']): ?>
                    <p class="text-muted small mb-2"><i class="bi bi-telephone me-1"></i><?= e($fichaCuenta['telefono']) ?></p>
                    <?php endif; ?>
                    <a href="<?= e(url_admin('personas', 'editar', ['id' => (int) $fichaCuenta['id']])) ?>"
                       class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-pencil me-1"></i>Editar su ficha
                    </a>
                    <div class="form-text mt-2">
                        El nombre, el teléfono y la foto de esta cuenta salen de aquí.
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <?php if (!$vinculada): ?>
                    <?php
                    $ci_nombre   = 'foto';
                    $ci_etiqueta = 'Fotografía';
                    $ci_actual   = $esNuevo ? '' : (string) $cuenta['foto'];
                    $ci_ayuda    = 'Se muestra junto a su nombre en el panel.';
                    require BASE_PATH . '/shared/views/parciales/campo_imagen.php';
                    ?>
                    <?php endif; ?>

                    <?php $esPropio = !$esNuevo && (int) $cuenta['id'] === (int) Auth::usuario()['id']; ?>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               name="activo" id="activo" value="1"
                               <?= ($esNuevo || $cuenta['activo']) ? 'checked' : '' ?>
                               <?= $esPropio ? 'disabled' : '' ?>>
                        <label class="form-check-label fw-semibold" for="activo">Activo</label>
                        <?php if ($esPropio): ?>
                        <div class="form-text">No puedes desactivar tu propia cuenta.</div>
                        <?php else: ?>
                        <div class="form-text">Si ya no debe entrar al panel, desactívalo en vez de darlo de baja.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-check-lg me-1"></i>Guardar
                </button>
                <a href="<?= e(url_admin('usuarios')) ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </div>
    </div>
</form>

<?php if (!$esNuevo && Auth::tieneAlcanceGlobal()): ?>
<?php /* Un rol distinto sobre otra pastoral, para la MISMA cuenta —ver
         docs/ARQUITECTURA.md, "Perfiles adicionales"—. Fuera del <form>
         principal a propósito: cada perfil se guarda con su propio POST,
         independiente de los datos de la cuenta. */ ?>
<div class="card border-0 shadow-sm mt-4">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
            <div>
                <p class="fw-bold mb-1">Perfiles adicionales de acceso</p>
                <p class="text-muted small mb-0">
                    Para cuando, además de su rol de siempre, necesita otro nivel de acceso
                    sobre una pastoral distinta —coordina Catequesis y, aparte, solo consulta
                    MESC—. Una pastoral cubierta aquí ya no se hereda con el rol principal
                    aunque la ficha la tenga marcada.
                </p>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary flex-shrink-0"
                    data-bs-toggle="modal" data-bs-target="#modalPerfil" data-modo="nuevo">
                <i class="bi bi-plus-lg me-1"></i>Agregar
            </button>
        </div>

        <?php if (!$perfiles): ?>
        <p class="text-muted small mb-0">Todavía no tiene ningún perfil adicional.</p>
        <?php else: ?>
        <div class="list-group">
            <?php foreach ($perfiles as $perfil): ?>
            <div class="list-group-item d-flex justify-content-between align-items-center gap-2">
                <div>
                    <span class="fw-semibold"><?= e($perfil['nombre']) ?></span>
                    <?php if (!$perfil['activo']): ?>
                    <span class="badge bg-secondary-subtle text-secondary-emphasis">Inactivo</span>
                    <?php endif; ?>
                    <div class="text-muted small">
                        <?= e(ROLES_NOMBRES[$perfil['rol']] ?? $perfil['rol']) ?> de
                        <?= e($perfil['pastoral_nombre']) ?><?= $perfil['centro_nombre'] ? ' · ' . e($perfil['centro_nombre']) : '' ?>
                    </div>
                </div>
                <div class="d-flex gap-2 flex-shrink-0">
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                            data-bs-toggle="modal" data-bs-target="#modalPerfil" data-modo="editar"
                            data-id="<?= (int) $perfil['id'] ?>"
                            data-nombre="<?= e($perfil['nombre']) ?>"
                            data-rol="<?= e($perfil['rol']) ?>"
                            data-pastoral="<?= (int) $perfil['pastoral_id'] ?>"
                            data-centro="<?= (int) ($perfil['centro_id'] ?? 0) ?>"
                            data-activo="<?= (int) $perfil['activo'] ?>">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <form method="POST" accept-charset="UTF-8"
                          action="<?= e(url_post('admin', 'usuarios', 'perfil_eliminar')) ?>" class="m-0">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="id" value="<?= (int) $perfil['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                onclick="return confirm('¿Eliminar este perfil adicional?');">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="modalPerfil" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" accept-charset="UTF-8" action="<?= e(url_post('admin', 'usuarios', 'perfil_guardar')) ?>" class="modal-content">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="usuario_id" value="<?= (int) $cuenta['id'] ?>">
            <input type="hidden" name="id" id="perfilId" value="">

            <div class="modal-header border-0 pb-0">
                <h2 class="h6 modal-title fw-bold" id="tituloModalPerfil">Agregar perfil</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Nombre</label>
                    <input type="text" name="nombre" id="perfilNombre" class="form-control form-control-sm"
                           placeholder="Consulta de Ministros MESC" maxlength="80" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Rol</label>
                    <select name="rol" id="perfilRol" class="form-select form-select-sm">
                        <?php foreach (ROLES_CON_ALCANCE_PASTORAL as $valorRol): ?>
                        <option value="<?= e($valorRol) ?>"><?= e(ROLES_NOMBRES[$valorRol] ?? $valorRol) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Pastoral</label>
                    <select name="pastoral_id" id="perfilPastoral" class="form-select form-select-sm">
                        <?php foreach ($pastorales as $unaPastoral): ?>
                        <option value="<?= (int) $unaPastoral['id'] ?>"><?= e($unaPastoral['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Sede</label>
                    <select name="centro_id" id="perfilCentro" class="form-select form-select-sm">
                        <option value="">Todas las sedes</option>
                        <?php foreach ($centros as $unCentro): ?>
                        <option value="<?= (int) $unCentro['id'] ?>"><?= e($unCentro['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" name="activo" id="perfilActivo" value="1" checked>
                    <label class="form-check-label small fw-semibold" for="perfilActivo">Activo</label>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-check-lg me-1"></i>Guardar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var modalPerfil = document.getElementById('modalPerfil');
    if (!modalPerfil) { return; }

    modalPerfil.addEventListener('show.bs.modal', function (evento) {
        var boton = evento.relatedTarget;
        var modo  = boton ? boton.dataset.modo : 'nuevo';

        document.getElementById('tituloModalPerfil').textContent = modo === 'editar' ? 'Editar perfil' : 'Agregar perfil';
        document.getElementById('perfilId').value = modo === 'editar' ? boton.dataset.id : '';
        document.getElementById('perfilNombre').value = modo === 'editar' ? boton.dataset.nombre : '';
        document.getElementById('perfilRol').value = modo === 'editar' ? boton.dataset.rol : document.getElementById('perfilRol').options[0].value;
        document.getElementById('perfilPastoral').value = modo === 'editar' ? boton.dataset.pastoral : document.getElementById('perfilPastoral').options[0].value;
        document.getElementById('perfilCentro').value = modo === 'editar' && boton.dataset.centro !== '0' ? boton.dataset.centro : '';
        document.getElementById('perfilActivo').checked = modo !== 'editar' || boton.dataset.activo === '1';
    });
})();
</script>
<?php endif; ?>

<script>
(function () {
    var selectPersona = document.getElementById('persona_id');
    var bloqueManual    = document.getElementById('bloqueAlcanceManual');
    var resumenFicha     = document.getElementById('resumenAlcanceFicha');
    var resumenGenerico  = document.getElementById('resumenAlcanceGenerico');
    if (!selectPersona || (!bloqueManual && !resumenFicha && !resumenGenerico)) { return; }

    var idVinculada = selectPersona.dataset.idVinculada || '';

    function actualizarAlcancePorPersona() {
        var valor = selectPersona.value;
        var esLaVinculada = valor !== '' && valor === idVinculada;
        var esOtraPersona = valor !== '' && valor !== idVinculada;

        if (bloqueManual)   { bloqueManual.classList.toggle('d-none', valor !== ''); }
        if (resumenFicha)   { resumenFicha.classList.toggle('d-none', !esLaVinculada); }
        if (resumenGenerico) { resumenGenerico.classList.toggle('d-none', !esOtraPersona); }

        if (esOtraPersona) {
            var lnk = document.getElementById('lnkFichaGenerica');
            if (lnk && lnk.dataset.urlBase) { lnk.href = lnk.dataset.urlBase.replace('__ID__', valor); }
        }
    }

    selectPersona.addEventListener('change', actualizarAlcancePorPersona);
    actualizarAlcancePorPersona();
})();
</script>
