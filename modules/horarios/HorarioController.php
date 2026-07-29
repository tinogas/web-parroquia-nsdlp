<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/modules/horarios/HorarioModel.php';
require_once BASE_PATH . '/modules/centros/CentroModel.php';

class HorarioController extends Controller
{
    private HorarioModel $modelo;

    public function __construct()
    {
        $this->modelo = new HorarioModel();
    }

    public function index(): void
    {
        $this->requirePermiso('horarios.ver');

        $this->render('horarios/lista', [
            'titulo'   => 'Horarios',
            'horarios' => $this->modelo->todos(),
        ]);
    }

    public function nuevo(): void
    {
        $this->requirePermiso('horarios.editar');

        $this->render('horarios/form', [
            'titulo'  => 'Nuevo horario',
            'horario' => null,
            'centros' => (new CentroModel())->activos(),
        ]);
    }

    public function editar(): void
    {
        $this->requirePermiso('horarios.editar');

        $horario = $this->modelo->porId($this->getInt('id'));
        if (!$horario) {
            Session::flash('error', 'No encontramos ese horario.');
            $this->redirect(url_admin('horarios'));
            return;
        }

        $this->render('horarios/form', [
            'titulo'  => 'Editar horario',
            'horario' => $horario,
            'centros' => (new CentroModel())->activos(),
        ]);
    }

    public function guardar(): void
    {
        $this->requirePermiso('horarios.editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('horarios'));
            return;
        }
        $this->validarCsrf();

        $id  = $this->postInt('id');
        $dia = $this->postInt('dia_semana');
        $hora = $this->postStr('hora');

        if (!isset(HorarioModel::TIPOS[$this->postStr('tipo')]) || $dia < 0 || $dia > 6 || $hora === '') {
            Session::flash('error', 'Revisa el tipo, el día y la hora: son obligatorios.');
            $this->redirect($id ? url_admin('horarios', 'editar', ['id' => $id]) : url_admin('horarios', 'nuevo'));
            return;
        }

        $datos = [
            'centro_id'     => $this->postIntONull('centro_id'),
            'tipo'          => $this->postStr('tipo'),
            'dia_semana'    => $dia,
            'hora'          => $hora,
            'hora_fin'      => $this->postStr('hora_fin') ?: null,
            'lugar'         => $this->postStr('lugar') ?: null,
            'nota'          => $this->postStr('nota') ?: null,
            'vigente_desde' => $this->postStr('vigente_desde') ?: null,
            'vigente_hasta' => $this->postStr('vigente_hasta') ?: null,
            'orden'         => $this->postInt('orden'),
            'activo'        => $this->postBool('activo'),
        ];

        if ($id && $this->modelo->porId($id)) {
            $this->modelo->actualizar($id, $datos);
            $this->auditoria('editar', 'horarios', $id);
            Session::flash('success', 'Horario actualizado.');
        } else {
            $id = $this->modelo->crear($datos);
            $this->auditoria('crear', 'horarios', $id);
            Session::flash('success', 'Horario agregado.');
        }

        $this->redirect(url_admin('horarios'));
    }

    public function eliminar(): void
    {
        $this->requirePermiso('horarios.editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('horarios'));
            return;
        }
        $this->validarCsrf();

        $id = $this->postInt('id');
        $this->modelo->eliminar($id);
        $this->auditoria('eliminar', 'horarios', $id);

        Session::flash('success', 'Horario eliminado.');
        $this->redirect(url_admin('horarios'));
    }
}
