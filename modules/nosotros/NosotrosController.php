<?php
require_once BASE_PATH . '/core/ControllerPublico.php';
require_once BASE_PATH . '/modules/bloques/BloqueModel.php';
require_once BASE_PATH . '/modules/personas/PersonaModel.php';
require_once BASE_PATH . '/modules/organigrama/OrganigramaModel.php';

class NosotrosController extends ControllerPublico
{
    public function index(): void
    {
        // Válvula de escape: si hay una imagen cargada, se muestra esa y no
        // hace falta construir el árbol. Ver docs/ARQUITECTURA.md
        $organigramaImagen = Config::get('organigrama_imagen');
        $organigramaArbol  = $organigramaImagen === '' ? (new OrganigramaModel())->arbolPublico() : [];

        $this->render('nosotros/publico/index', [
            'metaTitulo'         => 'Quiénes somos',
            'metaDescripcion'    => 'Historia, misión, visión, valores y equipo pastoral de la Parroquia Nuestra Señora de la Paz.',
            'urlCanonica'        => url_publica('nosotros'),
            'bloques'            => (new BloqueModel())->porZona('nosotros'),
            'equipo'             => (new PersonaModel())->activasPorTipo(),
            'organigramaImagen'  => $organigramaImagen,
            'organigramaArbol'   => $organigramaArbol,
        ]);
    }
}
