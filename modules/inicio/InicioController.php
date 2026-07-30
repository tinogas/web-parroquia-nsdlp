<?php
require_once BASE_PATH . '/core/ControllerPublico.php';
require_once BASE_PATH . '/modules/bloques/BloqueModel.php';
require_once BASE_PATH . '/modules/horarios/HorarioModel.php';
require_once BASE_PATH . '/modules/carrusel/CarruselModel.php';
require_once BASE_PATH . '/modules/avisos/AvisoModel.php';
require_once BASE_PATH . '/modules/eventos/EventoModel.php';
require_once BASE_PATH . '/modules/cursos/CursoModel.php';

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
            'avisosRecientes' => (new AvisoModel())->recientes(3),
            'proximosEventos' => (new EventoModel())->proximos(3),
            'proximosCursos'  => (new CursoModel())->proximos(3),
            'jsonLd'          => $this->datosEstructurados(),
        ]);
    }

    /**
     * Datos estructurados schema.org/Church para la portada. Solo se incluyen
     * los campos que de verdad están capturados en configuración: un
     * "telephone": "" vacío se lee como dato de mala calidad, no como ausente.
     */
    private function datosEstructurados(): array
    {
        $datos = [
            '@context' => 'https://schema.org',
            '@type'    => 'Church',
            'name'     => Config::get('parroquia_nombre', APP_NAME),
            'url'      => url_absoluta(url_publica('inicio')),
        ];

        if (Config::tiene('logo')) {
            $datos['logo'] = url_absoluta(url_activo(Config::get('logo')));
        }
        if (Config::tiene('telefono')) {
            $datos['telephone'] = Config::get('telefono');
        }
        if (Config::tiene('email')) {
            $datos['email'] = Config::get('email');
        }

        if (Config::tiene('direccion') || Config::tiene('ciudad')) {
            $datos['address'] = array_filter([
                '@type'           => 'PostalAddress',
                'streetAddress'   => Config::get('direccion') ?: null,
                'addressLocality' => Config::get('ciudad') ?: null,
                'postalCode'      => Config::get('cp') ?: null,
                'addressCountry'  => 'MX',
            ]);
        }

        if (Config::tiene('latitud') && Config::tiene('longitud')) {
            $datos['geo'] = [
                '@type'     => 'GeoCoordinates',
                'latitude'  => (float) Config::get('latitud'),
                'longitude' => (float) Config::get('longitud'),
            ];
        }

        $redes = array_filter([
            Config::get('facebook') ?: null,
            Config::get('instagram') ?: null,
            Config::get('youtube') ?: null,
        ]);
        if ($redes) {
            $datos['sameAs'] = array_values($redes);
        }

        return $datos;
    }

    private function hero(): string
    {
        $diapositivas = (new CarruselModel())->activas();
        return $diapositivas ? $this->heroCarrusel($diapositivas) : $this->heroEstatico();
    }

    /** Sin diapositivas cargadas, la portada muestra el saludo de siempre. */
    private function heroEstatico(): string
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

    private function heroCarrusel(array $diapositivas): string
    {
        ob_start(); ?>
        <div id="carruselInicio" class="carousel slide carrusel-inicio" data-bs-ride="carousel" data-bs-touch="true">
            <div class="carousel-indicators">
                <?php foreach ($diapositivas as $indice => $diapositiva): ?>
                <button type="button" data-bs-target="#carruselInicio" data-bs-slide-to="<?= $indice ?>"
                        class="<?= $indice === 0 ? 'active' : '' ?>"
                        aria-current="<?= $indice === 0 ? 'true' : 'false' ?>"
                        aria-label="Diapositiva <?= $indice + 1 ?>"></button>
                <?php endforeach; ?>
            </div>
            <div class="carousel-inner">
                <?php foreach ($diapositivas as $indice => $diapositiva): ?>
                <div class="carousel-item <?= $indice === 0 ? 'active' : '' ?>">
                    <img src="<?= e(url_activo($diapositiva['imagen'])) ?>" class="d-block w-100" alt="">
                    <?php if ($diapositiva['titulo'] || $diapositiva['subtitulo']): ?>
                    <div class="carousel-caption">
                        <?php if ($diapositiva['titulo']): ?>
                        <h2 class="fw-bold"><?= e($diapositiva['titulo']) ?></h2>
                        <?php endif; ?>
                        <?php if ($diapositiva['subtitulo']): ?>
                        <p><?= e($diapositiva['subtitulo']) ?></p>
                        <?php endif; ?>
                        <?php if ($diapositiva['enlace']): ?>
                        <a href="<?= e($diapositiva['enlace']) ?>" class="btn btn-dorado btn-sm mt-2">Ver más</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if (count($diapositivas) > 1): ?>
            <button class="carousel-control-prev" type="button" data-bs-target="#carruselInicio" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Anterior</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#carruselInicio" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Siguiente</span>
            </button>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
