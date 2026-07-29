<?php
require_once BASE_PATH . '/core/ControllerPublico.php';
require_once BASE_PATH . '/modules/horarios/HorarioModel.php';
require_once BASE_PATH . '/modules/bloques/BloqueModel.php';

class HorarioPublicoController extends ControllerPublico
{
    public function index(): void
    {
        $this->render('horarios/publico/index', [
            'metaTitulo'      => 'Horarios',
            'metaDescripcion' => 'Horarios de misas, confesiones, adoración eucarística y oficina parroquial.',
            'urlCanonica'     => url_publica('horarios'),
            'porCentro'       => (new HorarioModel())->vigentesPorCentro(),
            'bloques'         => (new BloqueModel())->porZona('horarios'),
        ]);
    }
}
