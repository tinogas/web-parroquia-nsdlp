<?php
/**
 * Pie del sitio. Todo lo que muestra sale de la tabla de configuración, así
 * que se administra desde el panel sin tocar esta plantilla.
 *
 * El enlace al aviso de privacidad es obligatorio y debe aparecer en todas las
 * páginas. Ver docs/PRIVACIDAD.md
 */
$redes = array_filter([
    'facebook'  => ['bi-facebook',  Config::get('facebook')],
    'instagram' => ['bi-instagram', Config::get('instagram')],
    'youtube'   => ['bi-youtube',   Config::get('youtube')],
], static fn (array $red): bool => $red[1] !== '');
?>
<footer class="pie-sitio mt-auto">
    <div class="container py-5">
        <div class="row g-4">

            <div class="col-lg-4">
                <h2 class="h6 text-uppercase text-dorado mb-3">
                    <?= e(Config::get('parroquia_nombre', APP_NAME)) ?>
                </h2>
                <?php if (Config::tiene('parroquia_diocesis')): ?>
                <p class="small text-white-50 mb-3"><?= e(Config::get('parroquia_diocesis')) ?></p>
                <?php endif; ?>

                <?php if ($redes): ?>
                <div class="d-flex gap-2">
                    <?php foreach ($redes as $nombre => [$icono, $enlace]): ?>
                    <a href="<?= e($enlace) ?>" class="enlace-red" target="_blank" rel="noopener"
                       aria-label="<?= e(ucfirst($nombre)) ?>">
                        <i class="bi <?= e($icono) ?>"></i>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="col-sm-6 col-lg-4">
                <h2 class="h6 text-uppercase text-white-50 mb-3">Dónde estamos</h2>
                <ul class="list-unstyled small mb-0 lista-pie">
                    <?php if (Config::tiene('direccion')): ?>
                    <li>
                        <i class="bi bi-geo-alt"></i>
                        <span>
                            <?= e(Config::get('direccion')) ?>
                            <?php if (Config::tiene('ciudad')): ?><br><?= e(Config::get('ciudad')) ?><?php endif; ?>
                        </span>
                    </li>
                    <?php endif; ?>
                    <?php if (Config::tiene('telefono')): ?>
                    <li>
                        <i class="bi bi-telephone"></i>
                        <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', Config::get('telefono'))) ?>">
                            <?= e(Config::get('telefono')) ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (Config::tiene('email')): ?>
                    <li>
                        <i class="bi bi-envelope"></i>
                        <a href="mailto:<?= e(Config::get('email')) ?>"><?= e(Config::get('email')) ?></a>
                    </li>
                    <?php endif; ?>
                    <?php if (!Config::tiene('direccion') && !Config::tiene('telefono') && !Config::tiene('email')): ?>
                    <li class="text-white-50 fst-italic">Datos de contacto pendientes de capturar.</li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="col-sm-6 col-lg-4">
                <h2 class="h6 text-uppercase text-white-50 mb-3">Oficina parroquial</h2>
                <?php if (Config::tiene('horario_oficina')): ?>
                <p class="small mb-0"><?= nl2br(e(Config::get('horario_oficina'))) ?></p>
                <?php else: ?>
                <p class="small text-white-50 fst-italic mb-0">Horario pendiente de capturar.</p>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <div class="pie-legal">
        <div class="container py-3 d-flex flex-wrap justify-content-between align-items-center gap-2 small">
            <span>&copy; <?= date('Y') ?> <?= e(Config::get('parroquia_nombre', APP_NAME)) ?></span>
            <span class="d-flex gap-3">
                <?php if (Router::existeRutaPublica('pagina')): ?>
                <a href="<?= e(url_publica('pagina', ['slug' => 'aviso-de-privacidad'])) ?>">Aviso de privacidad</a>
                <?php endif; ?>
                <a href="<?= e(url_admin('auth', 'login')) ?>">Acceso</a>
            </span>
        </div>
    </div>
</footer>
