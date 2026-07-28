<?php
require_once BASE_PATH . '/core/ControllerPublico.php';
require_once BASE_PATH . '/modules/bloques/BloqueModel.php';

class InicioController extends ControllerPublico
{
    public function index(): void
    {
        // Horarios destacados, próximos eventos y últimos avisos se agregan en
        // las etapas 4 y 5, cuando esos módulos existan.
        $this->render('inicio/publico/index', [
            'metaTitulo'      => '',
            'metaDescripcion' => Config::get(
                'meta_descripcion',
                'Horarios de misas, sacramentos, pastorales y avisos de la Parroquia Nuestra Señora de la Paz.'
            ),
            'urlCanonica' => url_publica('inicio'),
            'hero'        => $this->hero(),
            'bloques'     => (new BloqueModel())->porZona('inicio'),
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
