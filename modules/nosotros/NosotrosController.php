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

    /**
     * Vista de impresión/PDF del organigrama: página independiente, sin el
     * layout del sitio, para abrirse en pestaña nueva. Sin librería de PDF:
     * se imprime o se guarda como PDF con el diálogo del propio navegador.
     */
    public function organigramaImprimir(): void
    {
        $organigramaImagen = Config::get('organigrama_imagen');
        $organigramaArbol  = $organigramaImagen === '' ? (new OrganigramaModel())->arbolPublico() : [];

        $this->renderSinLayout('nosotros/publico/organigrama_imprimir', [
            'organigramaImagen' => $organigramaImagen,
            'organigramaArbol'  => $organigramaArbol,
        ]);
    }
}
