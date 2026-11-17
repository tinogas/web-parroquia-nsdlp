<?php
/**
 * Cuadro «Enlace personal» de las listas de integrantes (Ministros,
 * Proclamadores, Coros, Catequistas y «Quiénes están en esta pastoral» del
 * panel): copiar el enlace de alguien, mandárselo por WhatsApp, ver su página
 * o generar uno nuevo. Se incluye una vez por página; cada renglón pone su
 * botón con enlace_personal_boton() (abajo). Pide el enlace al servidor al
 * abrirse (EnlacePersonalAcciones::enlacePersonal() o
 * PastoralController::enlacePersonal()), que lo crea si no existía.
 *
 *   $ep_url      la acción enlace_personal del módulo
 *   $ep_explica  lo que verá con él, dicho a la coordinación: EnlacePersonal::explicacion()
 *   $csrf        el token del panel
 */
if (!function_exists('enlace_personal_boton')) {
    /**
     * El botón de un renglón. Su color dice en qué va: gris, todavía no tiene
     * enlace; azul, ya tiene pero no lo ha abierto; verde, ya lo abrió.
     *
     * @param array      $fila    el renglón de la lista (id, nombre, activo, persona_id)
     * @param array|null $estado  EnlacePersonal::estados() de su persona_id
     */
    function enlace_personal_boton(array $fila, ?array $estado): string
    {
        if (empty($fila['persona_id']) || empty($fila['activo'])) {
            return '';
        }
        $clase = !$estado ? 'btn-outline-secondary' : ($estado['ultimo_uso_at'] ? 'btn-outline-success' : 'btn-outline-primary');
        return '<button type="button" class="btn btn-sm ' . $clase . '"'
             . ' data-bs-toggle="modal" data-bs-target="#modalEnlacePersonal"'
             . ' data-enlace-personal="' . (int) $fila['id'] . '" data-nombre="' . e($fila['nombre']) . '"'
             . ' title="' . e('Enlace personal de ' . $fila['nombre'] . '. ' . EnlacePersonal::textoEstado($estado)) . '">'
             . '<i class="bi bi-link-45deg"></i></button>';
    }
}
?>
<div class="modal fade" id="modalEnlacePersonal" tabindex="-1" aria-labelledby="tituloEnlacePersonal" aria-hidden="true"
     data-url="<?= e($ep_url) ?>" data-csrf="<?= e($csrf) ?>">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h2 class="h6 modal-title fw-bold" id="tituloEnlacePersonal">
                    Enlace personal de <span data-ep="nombre"></span>
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-0" data-ep="cargando">Preparando el enlace…</p>
                <div class="alert alert-danger small mb-0 d-none" data-ep="error"></div>

                <div class="d-none" data-ep="contenido">
                    <div class="alert alert-success small py-2 d-none" data-ep="regenerado">
                        <i class="bi bi-check-circle me-1"></i>Listo: este es el enlace nuevo. El anterior ya no funciona; mándale este.
                    </div>
                    <p class="small mb-2"><i class="bi bi-info-circle me-1"></i><span data-ep="estado"></span></p>
                    <div class="alert alert-warning small py-2 d-none" data-ep="aviso"></div>

                    <div class="input-group input-group-sm mb-2">
                        <input type="text" class="form-control" readonly data-ep="url" aria-label="Enlace personal">
                        <button class="btn btn-outline-secondary" type="button" data-ep-accion="copiar">
                            <i class="bi bi-clipboard me-1"></i>Copiar
                        </button>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <a class="btn btn-success btn-sm" target="_blank" rel="noopener" data-ep="whatsapp">
                            <i class="bi bi-whatsapp me-1"></i>Enviar por WhatsApp
                        </a>
                        <a class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener" data-ep="ver">
                            <i class="bi bi-eye me-1"></i>Ver su página
                        </a>
                    </div>
                    <p class="small text-muted mb-1"><?= e($ep_explica) ?></p>
                    <p class="small text-muted mb-0 d-none" data-ep="sin-telefono">
                        No tiene teléfono registrado: WhatsApp te dejará elegir a quién mandárselo.
                    </p>

                    <hr>
                    <p class="small text-muted mb-2">
                        ¿Lo perdió, o se lo reenvió a alguien más? Genera uno nuevo: el anterior deja de funcionar
                        en ese momento, en cualquier teléfono.
                    </p>
                    <button class="btn btn-outline-danger btn-sm" type="button" data-ep-accion="regenerar">
                        <i class="bi bi-arrow-repeat me-1"></i>Generar un enlace nuevo
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var modal = document.getElementById('modalEnlacePersonal');
    if (!modal) { return; }
    var parte = function (k) { return modal.querySelector('[data-ep="' + k + '"]'); };
    var boton = null;   // el botón del renglón que abrió el cuadro

    function pedir(regenerar) {
        parte('cargando').classList.remove('d-none');
        parte('contenido').classList.add('d-none');
        parte('error').classList.add('d-none');
        parte('regenerado').classList.add('d-none');

        var datos = new FormData();
        datos.append('_csrf', modal.dataset.csrf);
        datos.append('id', boton.dataset.enlacePersonal);
        if (regenerar) { datos.append('regenerar', '1'); }

        fetch(modal.dataset.url, { method: 'POST', body: datos, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) {
                return r.json().catch(function () { return {}; }).then(function (j) {
                    if (!r.ok || !j.url) { throw new Error(j.error || 'No se pudo preparar el enlace. Recarga la página e inténtalo de nuevo.'); }
                    return j;
                });
            })
            .then(function (j) {
                parte('nombre').textContent = j.nombre;
                parte('estado').textContent = j.estado;
                parte('url').value = j.url;
                parte('whatsapp').href = j.whatsapp;
                parte('ver').href = j.url;
                parte('sin-telefono').classList.toggle('d-none', j.con_telefono);
                parte('aviso').textContent = j.aviso || '';
                parte('aviso').classList.toggle('d-none', !j.aviso);
                parte('regenerado').classList.toggle('d-none', !regenerar);
                parte('cargando').classList.add('d-none');
                parte('contenido').classList.remove('d-none');
                // El color del botón del renglón: ya tiene enlace; verde si ya lo abrió.
                boton.classList.remove('btn-outline-secondary', 'btn-outline-primary', 'btn-outline-success');
                boton.classList.add(j.abierto ? 'btn-outline-success' : 'btn-outline-primary');
            })
            .catch(function (e) {
                parte('cargando').classList.add('d-none');
                parte('error').textContent = e.message;
                parte('error').classList.remove('d-none');
            });
    }

    modal.addEventListener('show.bs.modal', function (ev) {
        boton = ev.relatedTarget;
        parte('nombre').textContent = boton.dataset.nombre || '';
        pedir(false);
    });

    modal.querySelector('[data-ep-accion="copiar"]').addEventListener('click', function () {
        var campo = parte('url'), este = this;
        var listo = function () {
            este.innerHTML = '<i class="bi bi-check-lg me-1"></i>Copiado';
            setTimeout(function () { este.innerHTML = '<i class="bi bi-clipboard me-1"></i>Copiar'; }, 2000);
        };
        // El portapapeles moderno solo existe en https (o localhost); fuera de
        // eso, el método de siempre.
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(campo.value).then(listo, function () { campo.select(); });
        } else {
            campo.select();
            try { if (document.execCommand('copy')) { listo(); } } catch (e) { /* queda seleccionado para copiarlo a mano */ }
        }
    });

    modal.querySelector('[data-ep-accion="regenerar"]').addEventListener('click', function () {
        if (confirm('¿Generar un enlace nuevo para ' + parte('nombre').textContent + '? El que tiene ahora dejará de funcionar en ese momento.')) {
            pedir(true);
        }
    });
})();
</script>
