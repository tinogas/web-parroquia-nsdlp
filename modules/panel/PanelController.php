<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/modules/personas/PersonaModel.php';
require_once BASE_PATH . '/modules/pastorales/PastoralModel.php';
require_once BASE_PATH . '/modules/avisos/AvisoModel.php';
require_once BASE_PATH . '/modules/cursos/CursoModel.php';

class PanelController extends Controller
{
    public function index(): void
    {
        $this->requirePermiso('panel.ver');

        // Mismo criterio de alcance que PastoralController::index(), recortado
        // además a lo que un Administrador ya publicó en el menú
        // (visible_en_menu) — ver PastoralModel::agrupadoVisible()/soloEnMenu().
        $modeloPastoral = new PastoralModel();
        $agrupadoMenu = $modeloPastoral->soloEnMenu(
            $modeloPastoral->agrupadoVisible(Auth::tieneAlcanceGlobal() ? null : Auth::pastoralesPermitidas())
        );

        // Lo último que se ha publicado hacia dentro para sus pastorales. Solo
        // se consulta si puede ver esa sección: a quien no tiene avisos.ver no
        // se le enseñan avisos aquí por la puerta de atrás.
        $audiencia = $this->audienciaInterna();

        $this->render('panel/index', [
            'titulo'           => 'Panel',
            'cumpleanerosMes'  => (new PersonaModel())->cumpleanerosDelMes(),
            'comisionesMenu'   => $agrupadoMenu['comisiones'],
            'sueltasMenu'      => $agrupadoMenu['sueltas'],
            'avisosInternos'   => Auth::tienePermiso('avisos.ver')
                ? (new AvisoModel())->internosPara($audiencia) : [],
            'cursosInternos'   => Auth::tienePermiso('cursos.ver')
                ? (new CursoModel())->internosPara($audiencia) : [],
            'accesoAnterior'   => Session::get('usuario_acceso_anterior'),
        ]);
    }
}
