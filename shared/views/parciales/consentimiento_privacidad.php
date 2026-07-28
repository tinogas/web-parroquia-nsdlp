<?php
/**
 * Casilla de consentimiento. Obligatoria en TODO formulario público que recabe
 * datos personales, y validada también en el servidor: sin ella no se guarda
 * nada. Ver docs/PRIVACIDAD.md
 *
 * Nunca viene marcada de antemano: un consentimiento premarcado no es
 * consentimiento.
 *
 * Variables opcionales:
 *   $cp_texto  redacción alternativa, por ejemplo la de padre, madre o tutor
 */
$cp_texto = $cp_texto ?? 'He leído y acepto el Aviso de Privacidad.';
$cp_hayAviso = pagina_publicada('aviso-de-privacidad');
?>
<div class="bloque-consentimiento">
    <div class="form-check">
        <input class="form-check-input" type="checkbox" name="consentimiento" id="consentimiento"
               value="1" required>
        <label class="form-check-label" for="consentimiento">
            <?= e($cp_texto) ?>
            <?php if ($cp_hayAviso): ?>
                <a href="<?= e(url_publica('pagina', ['slug' => 'aviso-de-privacidad'])) ?>"
                   target="_blank" rel="noopener">Leer el aviso</a>
                <span class="text-body-tertiary small">(se abre en otra pestaña)</span>
            <?php endif; ?>
            <span class="text-danger" aria-hidden="true">*</span>
        </label>
    </div>
    <p class="small text-muted mb-0 mt-2">
        Tus datos se usan únicamente para atender esta solicitud. No los compartimos con
        terceros ni los publicamos en el sitio.
    </p>
</div>
