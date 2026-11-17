<?php
/**
 * Formulario de un cambio de turno (permuta, cobertura o refuerzo), con su
 * script. Lo comparten la pantalla «Cambios de turno» del panel
 * (cambios_turno.php) y la página del enlace personal (modules/mi/), para que
 * se pida igual desde los dos lados; el servidor lo valida con
 * core/CambiosTurnoValidacion.php.
 *
 *   $cfg            vocabulario del módulo (CambiosTurno::MODULOS)
 *   $personas       todas las personas del módulo (id, nombre, activo)
 *   $hoy            'Y-m-d': nada antes de hoy
 *   $turnoPedido    turno a preseleccionar, o null
 *   $ctf_accion     a dónde se envía
 *   $ctf_ocultos    HTML de los campos ocultos (el token de seguridad)
 *   $ctf_urlDia     JSON con los turnos de una fecha
 *   $ctf_solicitar  true: lo envía un laico y queda como solicitud
 *   $ctf_yo         su ficha en el módulo (id, nombre) si es laico, o null:
 *                   el formulario lo propone a él donde haga falta
 */
$ctf_yo = $ctf_yo ?? null;
?>
<form method="POST" accept-charset="UTF-8" action="<?= e($ctf_accion) ?>"
      id="form-cambio" data-url-dia="<?= e($ctf_urlDia) ?>"
      <?= $ctf_yo ? 'data-yo="' . (int) $ctf_yo['id'] . '"' : '' ?>>
    <?= $ctf_ocultos ?>

    <div class="mb-3">
        <div class="btn-group flex-wrap" role="group" aria-label="Tipo de cambio">
            <input type="radio" class="btn-check" name="tipo" id="tipo-permuta" value="permuta" checked>
            <label class="btn btn-outline-primary" for="tipo-permuta"><i class="bi bi-arrow-left-right me-1"></i>Permuta</label>
            <input type="radio" class="btn-check" name="tipo" id="tipo-sustitucion" value="sustitucion">
            <label class="btn btn-outline-primary" for="tipo-sustitucion"><i class="bi bi-person-check me-1"></i>Cubre a otro</label>
            <input type="radio" class="btn-check" name="tipo" id="tipo-agregado" value="agregado">
            <label class="btn btn-outline-primary" for="tipo-agregado"><i class="bi bi-person-plus me-1"></i>Se agrega</label>
        </div>
        <div class="form-text" data-solo="permuta">
            Los marcados de cada turno toman el turno de los del otro: uno por uno, dos por dos, o el turno
            completo por el otro completo con «Todo el turno». Pueden ser de la misma fecha y hora o de fechas distintas.
        </div>
        <div class="form-text d-none" data-solo="sustitucion">
            Alguien toma el lugar de otro en un turno, sin devolución.
        </div>
        <div class="form-text d-none" data-solo="agregado">
            Alguien más se suma a un turno; nadie sale.
        </div>
    </div>

    <?php /* Cada turno se busca por su fecha: se elige el día y solo se
             ofrecen los turnos de ese día, en vez de recorrer una lista
             de meses enteros. */ ?>
    <div class="row g-3 mb-3">
        <div class="col-sm-5 col-md-3">
            <label for="fecha_a" class="form-label fw-semibold">
                <span data-solo="permuta">Fecha del primer turno</span>
                <span class="d-none" data-solo="sustitucion agregado">Fecha</span>
            </label>
            <input type="date" id="fecha_a" class="form-control" min="<?= e($hoy) ?>"
                   value="<?= e($turnoPedido['fecha'] ?? '') ?>">
        </div>
        <div class="col-sm-7 col-md-4">
            <label for="turno_a" class="form-label fw-semibold">Turno</label>
            <select name="turno_a" id="turno_a" class="form-select" required
                    data-seleccionar="<?= (int) ($turnoPedido['id'] ?? 0) ?>">
                <option value="">Elige primero la fecha</option>
            </select>
        </div>
        <div class="col-md-5 d-none" data-solo="sustitucion">
            <label for="sale_a" class="form-label fw-semibold">A quién cubre</label>
            <select name="sale_a" id="sale_a" class="form-select" data-rol-de="turno_a" data-mostrar="dentro">
                <option value="">Elige…</option>
                <?php foreach ($personas as $p): ?>
                <option value="<?= (int) $p['id'] ?>"><?= e($p['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-5" data-solo="permuta">
            <?php $lado = 'a'; require __DIR__ . '/cambios_turno_casillas.php'; ?>
        </div>
    </div>

    <div class="row g-3 mb-3" data-solo="permuta">
        <div class="col-sm-5 col-md-3">
            <label for="fecha_b" class="form-label fw-semibold">Fecha del segundo turno</label>
            <input type="date" id="fecha_b" class="form-control" min="<?= e($hoy) ?>">
        </div>
        <div class="col-sm-7 col-md-4">
            <label for="turno_b" class="form-label fw-semibold">Turno</label>
            <select name="turno_b" id="turno_b" class="form-select">
                <option value="">Elige primero la fecha</option>
            </select>
        </div>
        <div class="col-md-5">
            <?php $lado = 'b'; require __DIR__ . '/cambios_turno_casillas.php'; ?>
        </div>
    </div>

    <div class="row g-3 mb-3 d-none" data-solo="sustitucion agregado">
        <div class="col-md-7">
            <label for="entra" class="form-label fw-semibold">Quién entra</label>
            <select name="entra" id="entra" class="form-select" data-rol-de="turno_a" data-mostrar="fuera">
                <option value="">Elige…</option>
                <?php foreach ($personas as $p): ?>
                <?php if (!$p['activo']) { continue; } ?>
                <option value="<?= (int) $p['id'] ?>"><?= e($p['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="mb-3">
        <label for="notas" class="form-label fw-semibold">Nota <span class="text-muted fw-normal">(opcional)</span></label>
        <input type="text" name="notas" id="notas" class="form-control" maxlength="255"
               placeholder="<?= $ctf_solicitar ? 'Ej. Ya lo platiqué con Sandra y está de acuerdo' : 'Ej. Lo avisó Zulema por WhatsApp' ?>">
    </div>

    <button type="submit" class="btn btn-primary">
        <?php if ($ctf_solicitar): ?>
        <i class="bi bi-send me-1"></i>Enviar solicitud
        <?php else: ?>
        <i class="bi bi-check-lg me-1"></i>Registrar cambio
        <?php endif; ?>
    </button>
</form>

<script>
(function () {
    var form = document.getElementById('form-cambio');
    if (!form) { return; }
    var roles  = {};
    var urlDia = form.dataset.urlDia;
    var yo     = form.dataset.yo || '';

    // Muestra solo lo que aplica al tipo elegido. Se oculta por completo, no
    // solo se deshabilita: un campo que no aplica no debe estorbar.
    function aplicarTipo() {
        var tipo = form.querySelector('input[name="tipo"]:checked').value;
        form.querySelectorAll('[data-solo]').forEach(function (el) {
            el.classList.toggle('d-none', el.dataset.solo.split(' ').indexOf(tipo) === -1);
        });
        document.getElementById('turno_b').required = tipo === 'permuta';
        document.getElementById('sale_a').required  = tipo === 'sustitucion';
        document.getElementById('entra').required   = tipo !== 'permuta';
        sugerirYo();
    }

    // Quien pide para sí (el laico) casi siempre es uno de los que se mueven:
    // se le propone en el lugar que le toca, sin pisar lo que ya eligió. Si
    // está en el turno, es quien lo deja (o a quien cubren); si no, quien entra.
    function sugerirYo() {
        if (!yo) { return; }
        var tipo  = form.querySelector('input[name="tipo"]:checked').value;
        var turno = document.getElementById('turno_a').value;
        if (turno === '') { return; }
        var esta = (roles[turno] || []).map(String).indexOf(yo) !== -1;
        if (tipo === 'permuta' && esta && !form.querySelector('input[name="salen_a[]"]:checked')) {
            var mia = document.getElementById('salen-a-' + yo);
            if (mia) { mia.checked = true; }
        } else if (tipo === 'sustitucion') {
            var sale = document.getElementById('sale_a'), entra = document.getElementById('entra');
            if (esta && sale.value === '') { sale.value = yo; }
            if (!esta && entra.value === '') { entra.value = yo; }
        } else if (tipo === 'agregado' && !esta && document.getElementById('entra').value === '') {
            document.getElementById('entra').value = yo;
        }
    }

    // «Quiénes dejan el turno» lista solo a quienes están en él; «quién
    // entra», solo a quienes no. El servidor vuelve a revisarlo todo.
    function filtrarPersonas() {
        form.querySelectorAll('[data-rol-de]').forEach(function (cont) {
            var turno  = document.getElementById(cont.dataset.rolDe).value;
            var rol    = (roles[turno] || []).map(String);
            var dentro = cont.dataset.mostrar === 'dentro';
            var visto  = function (id) {
                var esta = rol.indexOf(id) !== -1;
                return turno === '' ? !dentro : (dentro ? esta : !esta);
            };
            if (cont.tagName === 'SELECT') {
                cont.querySelectorAll('option[value]:not([value=""])').forEach(function (op) {
                    var ver = visto(op.value);
                    op.hidden = !ver;
                    op.disabled = !ver;
                    if (!ver && op.selected) { cont.value = ''; }
                });
                return;
            }
            // Casillas de la permuta: quien deja de verse se desmarca, para no
            // mandar a alguien que ya no está a la vista.
            var visibles = 0;
            cont.querySelectorAll('[data-persona]').forEach(function (fila) {
                var ver = visto(fila.dataset.persona);
                fila.classList.toggle('d-none', !ver);
                if (!ver) { fila.querySelector('input').checked = false; } else { visibles++; }
            });
            var vacio = cont.querySelector('[data-vacio]');
            vacio.classList.toggle('d-none', visibles > 0);
            vacio.textContent = turno === '' ? 'Elige primero el turno.' : 'Nadie sirve en ese turno.';
        });
        sugerirYo();
    }

    // Turnos del día elegido, con su rol real, pedidos al servidor.
    function cargarTurnos(lado, seleccionar) {
        var fecha = document.getElementById('fecha_' + lado).value;
        var sel   = document.getElementById('turno_' + lado);
        var poner = function (texto) { sel.innerHTML = ''; sel.add(new Option(texto, '')); };
        if (!fecha) { poner('Elige primero la fecha'); filtrarPersonas(); return; }
        poner('Buscando turnos…');
        fetch(urlDia + (urlDia.indexOf('?') === -1 ? '?' : '&') + 'fecha=' + encodeURIComponent(fecha),
              { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (datos) {
                if (!datos.turnos.length) { poner(datos.aviso || 'No hay turnos ese día'); }
                else {
                    poner('Elige el turno…');
                    datos.turnos.forEach(function (t) {
                        roles[t.id] = t.rol;
                        sel.add(new Option(t.etiqueta, t.id));
                    });
                    if (seleccionar) { sel.value = String(seleccionar); }
                }
                filtrarPersonas();
            })
            .catch(function () { poner('No se pudieron cargar los turnos'); filtrarPersonas(); });
    }

    // «Todo el turno»: marca a todos los que están en él, para intercambiar
    // turnos completos.
    form.querySelectorAll('[data-marcar-todo]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            form.querySelectorAll('[data-rol-de="' + boton.dataset.marcarTodo + '"] [data-persona]:not(.d-none) input')
                .forEach(function (c) { c.checked = true; });
        });
    });

    // Una permuta necesita al menos a uno marcado de cada lado; mejor avisarlo
    // aquí que después de enviar.
    form.addEventListener('submit', function (ev) {
        if (form.querySelector('input[name="tipo"]:checked').value !== 'permuta') { return; }
        var faltan = ['a', 'b'].filter(function (lado) {
            return !form.querySelector('input[name="salen_' + lado + '[]"]:checked');
        });
        if (faltan.length) {
            ev.preventDefault();
            alert('Marca al menos a uno de cada turno: quiénes dejan el ' + (faltan[0] === 'a' ? 'primer' : 'segundo') + ' turno.');
        }
    });

    form.querySelectorAll('input[name="tipo"]').forEach(function (r) { r.addEventListener('change', aplicarTipo); });
    ['a', 'b'].forEach(function (lado) {
        document.getElementById('fecha_' + lado).addEventListener('change', function () { cargarTurnos(lado); });
        document.getElementById('turno_' + lado).addEventListener('change', filtrarPersonas);
    });
    aplicarTipo();
    if (document.getElementById('fecha_a').value) {
        cargarTurnos('a', document.getElementById('turno_a').dataset.seleccionar);
    } else {
        filtrarPersonas();
    }
})();
</script>
