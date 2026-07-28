<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/modules/organigrama/OrganigramaModel.php';
require_once BASE_PATH . '/modules/personas/PersonaModel.php';
require_once BASE_PATH . '/modules/pastorales/PastoralModel.php';

class OrganigramaController extends Controller
{
    private OrganigramaModel $modelo;

    public function __construct()
    {
        $this->modelo = new OrganigramaModel();
    }

    public function index(): void
    {
        $this->requirePermiso('organigrama.ver');

        $this->render('organigrama/lista', [
            'titulo' => 'Organigrama',
            'arbol'  => $this->modelo->arbolAdmin(),
        ]);
    }

    public function nuevo(): void
    {
        $this->requirePermiso('organigrama.editar');

        $this->render('organigrama/form', [
            'titulo'               => 'Nuevo nodo',
            'nodo'                 => null,
            'padrePreseleccionado' => $this->getInt('padre_id') ?: null,
            'padres'               => $this->modelo->paraSelectorPadre(null),
            'personas'             => (new PersonaModel())->paraSelector(),
            'pastorales'           => (new PastoralModel())->paraSelector(),
        ]);
    }

    public function editar(): void
    {
        $this->requirePermiso('organigrama.editar');

        $id   = $this->getInt('id');
        $nodo = $this->modelo->porId($id);
        if (!$nodo) {
            Session::flash('error', 'No encontramos ese nodo.');
            $this->redirect(url_admin('organigrama'));
            return;
        }

        $this->render('organigrama/form', [
            'titulo'               => 'Editar: ' . $nodo['titulo'],
            'nodo'                 => $nodo,
            'padrePreseleccionado' => null,
            'padres'               => $this->modelo->paraSelectorPadre($id),
            'personas'             => (new PersonaModel())->paraSelector(),
            'pastorales'           => (new PastoralModel())->paraSelector(),
        ]);
    }

    public function guardar(): void
    {
        $this->requirePermiso('organigrama.editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('organigrama'));
            return;
        }
        $this->validarCsrf();

        $id     = $this->postInt('id');
        $titulo = $this->postStr('titulo');

        if ($titulo === '') {
            Session::flash('error', 'El nodo necesita un título.');
            $this->redirect($id ? url_admin('organigrama', 'editar', ['id' => $id]) : url_admin('organigrama', 'nuevo'));
            return;
        }

        $datos = [
            'padre_id'    => $this->postIntONull('padre_id'),
            'titulo'      => $titulo,
            'persona_id'  => $this->postIntONull('persona_id'),
            'pastoral_id' => $this->postIntONull('pastoral_id'),
            'orden'       => $this->postInt('orden'),
            'activo'      => $this->postBool('activo'),
        ];

        try {
            if ($id && $this->modelo->porId($id)) {
                $this->modelo->actualizar($id, $datos);
                $this->auditoria('editar', 'organigrama_nodos', $id, $titulo);
                Session::flash('success', 'Nodo actualizado.');
            } else {
                $id = $this->modelo->crear($datos);
                $this->auditoria('crear', 'organigrama_nodos', $id, $titulo);
                Session::flash('success', 'Nodo agregado.');
            }
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect($id ? url_admin('organigrama', 'editar', ['id' => $id]) : url_admin('organigrama', 'nuevo'));
            return;
        }

        $this->redirect(url_admin('organigrama'));
    }

    public function eliminar(): void
    {
        $this->requirePermiso('organigrama.editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('organigrama'));
            return;
        }
        $this->validarCsrf();

        $id = $this->postInt('id');
        $this->modelo->eliminar($id);
        $this->auditoria('eliminar', 'organigrama_nodos', $id);

        Session::flash('success', 'Nodo eliminado. Sus subordinados, si tenía, pasaron al primer nivel.');
        $this->redirect(url_admin('organigrama'));
    }
}
