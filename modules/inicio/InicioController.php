<?php
require_once BASE_PATH . '/core/ControllerPublico.php';

class InicioController extends ControllerPublico
{
    public function index(): void
    {
        // La portada definitiva se arma en la etapa 3, con el saludo del
        // párroco, los horarios destacados, los próximos eventos y los últimos
        // avisos. Por ahora solo confirma que el área pública responde sin
        // pedir sesión.
        $this->render('inicio/publico/index', [
            'metaTitulo'      => '',
            'metaDescripcion' => Config::get(
                'meta_descripcion',
                'Horarios de misas, sacramentos, pastorales y avisos de la Parroquia Nuestra Señora de la Paz.'
            ),
            'hero' => $this->hero(),
        ]);
    }

    private function hero(): string
    {
        ob_start(); ?>
        <section class="hero-inicio">
            <div class="container text-center">
                <p class="hero-antetitulo">Bienvenidos</p>
                <h1 class="hero-titulo"><?= e(Config::get('parroquia_nombre', APP_NAME)) ?></h1>
                <?php if (Config::tiene('parroquia_diocesis')): ?>
                <p class="hero-subtitulo"><?= e(Config::get('parroquia_diocesis')) ?></p>
                <?php endif; ?>
            </div>
        </section>
        <?php
        return (string) ob_get_clean();
    }
}
