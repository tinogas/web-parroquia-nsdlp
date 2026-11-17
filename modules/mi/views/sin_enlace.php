<?php
/**
 * Cuando no hay página que mostrar: el teléfono no recuerda ningún enlace, el
 * enlace ya no vale (se generó uno nuevo o la persona ya no está activa), o
 * quien lo abre es la app que arma la vista previa del mensaje.
 *
 *   $motivo  'sin_enlace' | 'no_vale' | 'olvidado' | 'vista_previa'
 */
[$icono, $titulo, $texto] = match ($motivo) {
    'no_vale' => [
        'bi-link-45deg',
        'Este enlace ya no funciona',
        'Puede que la coordinación te haya mandado uno más nuevo: búscalo en tu WhatsApp. Si no lo encuentras, pídele a tu coordinación que te lo vuelva a mandar.',
    ],
    'olvidado' => [
        'bi-phone',
        'Listo: este teléfono ya no recuerda tu enlace',
        'Para volver a entrar desde aquí, abre otra vez el enlace que te mandó la coordinación.',
    ],
    'vista_previa' => [
        'bi-person-badge',
        'Tu página personal en la parroquia',
        'Abre el enlace para ver tus turnos y pedir cambios.',
    ],
    default => [
        'bi-person-badge',
        'Tu página personal en la parroquia',
        'Para entrar, abre el enlace personal que te mandó la coordinación de tu pastoral por WhatsApp. Después de abrirlo una vez, este teléfono lo recuerda.',
    ],
};
?>
<div class="mx-auto text-center py-4" style="max-width: 32rem">
    <i class="bi <?= e($icono) ?> display-5 text-secondary"></i>
    <h1 class="titulo-pagina h3 mt-3 mb-3"><?= e($titulo) ?></h1>
    <p class="text-muted mb-4"><?= e($texto) ?></p>
    <a href="<?= e(url_publica('inicio')) ?>" class="btn btn-outline-secondary">Ir al sitio de la parroquia</a>
</div>
