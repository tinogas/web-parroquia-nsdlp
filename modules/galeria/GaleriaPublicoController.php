<?php
require_once BASE_PATH . '/core/ControllerPublico.php';
require_once BASE_PATH . '/modules/galeria/GaleriaModel.php';

class GaleriaPublicoController extends ControllerPublico
{
    public function index(): void
    {
        $this->render('galeria/publico/index', [
            'metaTitulo'      => 'Galería',
            'metaDescripcion' => 'Fotografías de la vida de la Parroquia Nuestra Señora de la Paz.',
            'urlCanonica'     => url_publica('galeria'),
            'listado'         => (new GaleriaModel())->publicadas(max(1, $this->getInt('pagina', 1))),
            'scriptExtra'     => '<script src="' . e(url_activo('assets/js/lightbox_galeria.js'))
                                . '?v=' . e(APP_VERSION) . '"></script>',
        ]);
    }
}
