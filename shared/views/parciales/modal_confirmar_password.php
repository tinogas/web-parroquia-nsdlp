<?php
/**
 * Modal de confirmación para una acción de Administrador que además exige
 * reescribir la contraseña en el momento (Controller::requireAdminConPassword()).
 * Solo debe incluirse cuando Auth::esAdmin() — no oculta la acción a otros
 * roles, la esconde el llamador.
 *
 * Variables esperadas:
 *   $mcp_idModal        string  id HTML del modal, único en la página
 *   $mcp_titulo         string  título del modal
 *   $mcp_mensaje        string  HTML ya escapado del cuerpo (la advertencia)
 *   $mcp_accionUrl      string  action del form (url_post(...))
 *   $mcp_camposOcultos  array   [nombre => valor] adicionales al _csrf
 *   $mcp_csrf           string  token CSRF vigente
 *   $mcp_textoBoton     string  texto del botón de confirmar
 *   $mcp_claseBoton     string  clase Bootstrap del botón (btn-danger, btn-primary...)
 */
?>
<div class="modal fade" id="<?= e($mcp_idModal) ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" accept-charset="UTF-8" action="<?= e($mcp_accionUrl) ?>" class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h2 class="h6 modal-title fw-bold"><?= e($mcp_titulo) ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= e($mcp_csrf) ?>">
                <?php foreach ($mcp_camposOcultos as $campo => $valor): ?>
                <input type="hidden" name="<?= e($campo) ?>" value="<?= e((string) $valor) ?>">
                <?php endforeach; ?>
                <p><?= $mcp_mensaje ?></p>
                <label for="<?= e($mcp_idModal) ?>_pass" class="form-label fw-semibold">
                    Confirma tu contraseña para continuar
                </label>
                <input type="password" name="confirmar_password" id="<?= e($mcp_idModal) ?>_pass"
                       class="form-control" required autocomplete="current-password">
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn <?= e($mcp_claseBoton) ?>"><?= e($mcp_textoBoton) ?></button>
            </div>
        </form>
    </div>
</div>
