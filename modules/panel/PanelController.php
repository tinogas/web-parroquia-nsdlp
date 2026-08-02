<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/modules/personas/PersonaModel.php';

class PanelController extends Controller
{
    public function index(): void
    {
        $this->requirePermiso('panel.ver');

        $this->render('panel/index', [
            'titulo'          => 'Panel',
            'cumpleanerosMes' => (new PersonaModel())->cumpleanerosDelMes(),
        ]);
    }
}
