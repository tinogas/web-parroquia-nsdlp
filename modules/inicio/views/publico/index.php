<div class="row justify-content-center">
    <div class="col-lg-8 text-center">

        <p class="lead text-muted">
            Estamos preparando el sitio de la parroquia. Aquí encontrarás pronto los
            horarios de misas, los requisitos de los sacramentos, las pastorales y los
            avisos de la comunidad.
        </p>

        <?php if (Config::tiene('telefono') || Config::tiene('direccion')): ?>
        <div class="card border-0 shadow-sm mt-4 text-start">
            <div class="card-body p-4">
                <h2 class="h6 text-uppercase text-muted mb-3">Mientras tanto</h2>
                <ul class="list-unstyled mb-0 lista-contacto">
                    <?php if (Config::tiene('direccion')): ?>
                    <li><i class="bi bi-geo-alt text-primary"></i> <?= e(Config::get('direccion')) ?></li>
                    <?php endif; ?>
                    <?php if (Config::tiene('telefono')): ?>
                    <li>
                        <i class="bi bi-telephone text-primary"></i>
                        <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', Config::get('telefono'))) ?>">
                            <?= e(Config::get('telefono')) ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (Config::tiene('horario_oficina')): ?>
                    <li><i class="bi bi-clock text-primary"></i> <?= nl2br(e(Config::get('horario_oficina'))) ?></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>
