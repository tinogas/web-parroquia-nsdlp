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

        // Todo lo publicado a sus pastorales este mes. Cada sección se consulta
        // solo si puede verla: a quien no tiene avisos.ver no se le enseñan
        // avisos aquí por la puerta de atrás.
        $audiencia = $this->audienciaInterna();

        $this->render('panel/index', [
            'titulo'           => 'Panel',
            'cumpleanerosMes'  => (new PersonaModel())->cumpleanerosDelMes(),
            'comisionesMenu'   => $agrupadoMenu['comisiones'],
            'sueltasMenu'      => $agrupadoMenu['sueltas'],
            'avisosInternos'   => Auth::tienePermiso('avisos.ver')
                ? (new AvisoModel())->internosDelMes($audiencia) : [],
            'cursosInternos'   => Auth::tienePermiso('cursos.ver')
                ? (new CursoModel())->internosDelMes($audiencia) : [],
            'accesoAnterior'   => Session::get('usuario_acceso_anterior'),
        ]);
    }
}
