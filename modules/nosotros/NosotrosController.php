<?php
require_once BASE_PATH . '/core/ControllerPublico.php';
require_once BASE_PATH . '/modules/bloques/BloqueModel.php';

class NosotrosController extends ControllerPublico
{
    public function index(): void
    {
        $this->render('nosotros/publico/index', [
            'metaTitulo'      => 'Quiénes somos',
            'metaDescripcion' => 'Historia, misión, visión y valores de la Parroquia Nuestra Señora de la Paz.',
            'urlCanonica'     => url_publica('nosotros'),
            'bloques'         => (new BloqueModel())->porZona('nosotros'),
        ]);
    }
}
