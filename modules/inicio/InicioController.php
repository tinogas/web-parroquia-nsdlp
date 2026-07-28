<?php
require_once BASE_PATH . '/core/ControllerPublico.php';
require_once BASE_PATH . '/modules/bloques/BloqueModel.php';
require_once BASE_PATH . '/modules/horarios/HorarioModel.php';

class InicioController extends ControllerPublico
{
    public function index(): void
    {
        // Próximos eventos y últimos avisos se agregan en la etapa 5.
        $this->render('inicio/publico/index', [
            'metaTitulo'      => '',
            'metaDescripcion' => Config::get(
                'meta_descripcion',
                'Horarios de misas, sacramentos, pastorales y avisos de la Parroquia Nuestra Señora de la Paz.'
            ),
            'urlCanonica'    => url_publica('inicio'),
            'hero'           => $this->hero(),
            'bloques'        => (new BloqueModel())->porZona('inicio'),
            'proximasMisas'  => (new HorarioModel())->proximasMisas(3),
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
