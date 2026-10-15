<?php
require_once BASE_PATH . '/core/ControllerPublico.php';
require_once BASE_PATH . '/modules/bloques/BloqueModel.php';
require_once BASE_PATH . '/modules/evangelio/EvangelioModel.php';
require_once BASE_PATH . '/modules/horarios/HorarioModel.php';
require_once BASE_PATH . '/modules/carrusel/CarruselModel.php';
require_once BASE_PATH . '/modules/avisos/AvisoModel.php';
require_once BASE_PATH . '/modules/eventos/EventoModel.php';
require_once BASE_PATH . '/modules/cursos/CursoModel.php';
require_once BASE_PATH . '/modules/centros/CentroModel.php';
require_once BASE_PATH . '/modules/sacramentos/SacramentoModel.php';
require_once BASE_PATH . '/modules/pastorales/PastoralModel.php';

class InicioController extends ControllerPublico
{
    /**
     * La portada sigue, sección por sección, la propuesta de rediseño: la
     * cabecera editorial con las próximas misas, los horarios de la semana,
     * la fiesta patronal cuando se acerca, sacramentos, pastorales, la agenda,
     * quiénes somos y contacto. Antes de los horarios va el evangelio del día
     * con la reflexión del párroco, y entre la agenda y quiénes somos, lo
     * demás que publica el panel y la propuesta no tenía: avisos, cursos y
     * Facebook.
     *
     * Las secciones van a todo el ancho, con su propio fondo, así que la
     * vista no se mete en el contenedor del layout (sinContenedor).
     */
    public function index(): void
    {
        $bloques       = (new BloqueModel())->porZona('inicio');
        $diapositivas  = (new CarruselModel())->activas();
        $horarios      = (new HorarioModel())->vigentesDeLaSemana();
        $centros       = (new CentroModel())->activosCompletos();
        $cortos        = CentroModel::nombresCortos($centros, $horarios);
        $proximasMisas = $this->conCuando((new HorarioModel())->proximasMisas(3));
        $eventos       = new EventoModel();

        $this->render('inicio/publico/index', [
            'metaTitulo'      => '',
            'metaDescripcion' => Config::get(
                'meta_descripcion',
                'Horarios de misas, sacramentos, pastorales y avisos de la Parroquia Nuestra Señora de la Paz.'
            ),
            'urlCanonica'      => url_publica('inicio'),
            'sinContenedor'    => true,
            'hero'             => $diapositivas
                ? $this->heroCarrusel($diapositivas)
                : $this->heroEditorial($bloques, $centros, $cortos, $proximasMisas),
            'portadaEditorial' => !$diapositivas,
            'bloques'          => $bloques,
            'bloquesNosotros'  => (new BloqueModel())->porZona('nosotros'),
            'horarios'         => $horarios,
            'centros'          => $centros,
            'nombresCortos'    => $cortos,
            'proximasMisas'    => $proximasMisas,
            'fiesta'           => $eventos->fiestaPatronalProxima(),
            'sacramentos'      => (new SacramentoModel())->activos(),
            'pastorales'       => (new PastoralModel())->activasAgrupadas(),
            'agenda'           => $eventos->proximos(9),
            'evangelioHoy'     => (new EvangelioModel())->deHoy(),
            'avisosRecientes'  => (new AvisoModel())->recientes(3),
            'proximosCursos'   => (new CursoModel())->proximos(3),
            'jsonLd'           => $this->datosEstructurados(),
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
        // schema.org espera el teléfono en E.164, no como se escribe en la
        // papeleta: '+526622207214', no '(662) 220 7214'.
        if ($telefonoE164 = telefono_internacional(Config::get('telefono'))) {
            $datos['telephone'] = '+' . $telefonoE164;
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

    /**
     * A cada misa le pone 'cuando': "En 25 min", "Hoy", "Mañana" o el nombre
     * del día. proximasMisas() ya las trae en orden a partir de ahora.
     */
    private function conCuando(array $misas): array
    {
        $ahora   = new DateTimeImmutable();
        $diaHoy  = (int) $ahora->format('w');
        $minHoy  = (int) $ahora->format('G') * 60 + (int) $ahora->format('i');

        foreach ($misas as &$misa) {
            [$h, $m]   = array_map('intval', explode(':', $misa['hora']));
            $diasHasta = ((int) $misa['dia_semana'] - $diaHoy + 7) % 7;
            $faltan    = $diasHasta * 1440 + $h * 60 + $m - $minHoy;
            if ($faltan < 0) {
                $faltan += 7 * 1440;   // Ya pasó hoy: es la de la semana entrante.
            }

            $misa['cuando'] = match (true) {
                $faltan < 60                       => 'En ' . max(1, $faltan) . ' min',
                $faltan < 1440 && $diasHasta === 0 => 'Hoy',
                $diasHasta === 1                   => 'Mañana',
                default                            => ucfirst(nombre_dia((int) $misa['dia_semana'])),
            };
        }
        unset($misa);

        return $misas;
    }

    /**
     * Portada editorial, como en la propuesta: ilustración apaisada que se
     * funde con el fondo; debajo, el lema a la izquierda y el título, la
     * presentación y los dos botones a la derecha; al pie, las próximas misas.
     *
     * La ilustración es provisional: al cambiar assets/img/portada.svg por una
     * foto del templo, la portada la toma sin tocar código. La presentación
     * sale del bloque "Presentación" del panel si se captura; mientras esté
     * vacío, va el texto de la propuesta.
     */
    private function heroEditorial(array $bloques, array $centros, array $cortos, array $proximasMisas): string
    {
        $numeros = [1 => 'una', 2 => 'dos', 3 => 'tres', 4 => 'cuatro', 5 => 'cinco', 6 => 'seis'];
        $nombres = array_map(static fn (array $c): string => $cortos[(int) $c['id']], $centros);
        $ultimo  = array_pop($nombres);
        $sedes   = $nombres ? implode(', ', $nombres) . ' y ' . $ultimo : (string) $ultimo;

        // La parroquia pertenece a la Arquidiócesis de Hermosillo; no hay un
        // campo de configuración para la arquidiócesis, solo para el decanato.
        $lema = implode(' · ', array_filter([rtrim(Config::get('parroquia_diocesis'), '. '), 'Arquidiócesis de Hermosillo'])) . '.';
        if (count($centros) > 1) {
            $lema .= ' Una parroquia, ' . ($numeros[count($centros)] ?? count($centros)) . ' sedes: ' . $sedes . '.';
        }

        $presentacion = $bloques['inicio_intro']['contenido'] ?? '';

        ob_start(); ?>
        <section class="portada-editorial">
            <div class="portada-imagen aparece">
                <img src="<?= e(url_activo('assets/img/portada.svg')) ?>"
                     alt="Ilustración del templo al atardecer, con el Cerro de la Campana al fondo"
                     width="1800" height="800" decoding="async">
            </div>
            <div class="container portada-texto">
                <div class="row g-4">
                    <div class="col-lg-4 aparece aparece-2">
                        <p class="portada-lema"><?= e($lema) ?></p>
                    </div>
                    <div class="col-lg-6 offset-lg-2 aparece aparece-3">
                        <h1 class="portada-titulo">Una comunidad en camino hacia la santidad</h1>
                        <?php if ($presentacion !== ''): ?>
                        <?php /* Contenido ya saneado con lista blanca al guardarse. */ ?>
                        <div class="portada-descripcion contenido-editorial"><?= $presentacion ?></div>
                        <?php else: ?>
                        <p class="portada-descripcion">
                            Bienvenidos, hermanos. Aquí encuentras los horarios de misa y confesiones, lo que
                            necesitas para cada sacramento y cómo sumarte a alguna de nuestras pastorales.
                        </p>
                        <?php endif; ?>
                        <div class="portada-acciones">
                            <a href="#horarios" class="btn btn-primary btn-lg px-4 fs-6">Ver horarios de misa</a>
                            <a href="#sacramentos" class="enlace-flecha">
                                Requisitos de sacramentos <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php if ($proximasMisas): ?>
                <div class="mt-5 aparece aparece-3">
                    <?php require BASE_PATH . '/modules/inicio/views/publico/proximas_misas.php'; ?>
                </div>
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
