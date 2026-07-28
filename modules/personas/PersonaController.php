<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/modules/personas/PersonaModel.php';

class PersonaController extends Controller
{
    private PersonaModel $modelo;

    public function __construct()
    {
        $this->modelo = new PersonaModel();
    }

    public function index(): void
    {
        $this->requirePermiso('personas.ver');

        $this->render('personas/lista', [
            'titulo'   => 'Equipo pastoral',
            'personas' => $this->modelo->todas(),
        ]);
    }

    public function nueva(): void
    {
        $this->requirePermiso('personas.editar');

        $this->render('personas/form', [
            'titulo'  => 'Nueva persona',
            'persona' => null,
        ]);
    }

    public function editar(): void
    {
        $this->requirePermiso('personas.editar');

        $persona = $this->modelo->porId($this->getInt('id'));
        if (!$persona) {
            Session::flash('error', 'No encontramos a esa persona.');
            $this->redirect(url_admin('personas'));
            return;
        }

        $this->render('personas/form', [
            'titulo'  => $persona['nombre'],
            'persona' => $persona,
        ]);
    }

    public function guardar(): void
    {
        $this->requirePermiso('personas.editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('personas'));
            return;
        }
        $this->validarCsrf();

        $id     = $this->postInt('id');
        $nombre = $this->postStr('nombre');

        if ($nombre === '' || !isset(PersonaModel::TIPOS[$this->postStr('tipo')])) {
            Session::flash('error', 'Escribe el nombre y elige un tipo válido.');
            $this->redirect($id ? url_admin('personas', 'editar', ['id' => $id]) : url_admin('personas', 'nueva'));
            return;
        }

        $actual = $id ? $this->modelo->porId($id) : null;
        $foto   = $actual['foto'] ?? null;

        try {
            if (!empty($_POST['foto_quitar'])) {
                Upload::borrar($foto);
                $foto = null;
            } else {
                $foto = Upload::imagen('foto', 'personas', 'persona', $foto);
            }
        } catch (RuntimeException $e) {
            Session::flash('warning', 'Se guardó, pero la foto no: ' . $e->getMessage());
        }

        $datos = [
            'nombre'    => $nombre,
            'cargo'     => $this->postStr('cargo') ?: null,
            'tipo'      => $this->postStr('tipo'),
            'semblanza' => $this->postStr('semblanza') ?: null,
            'foto'      => $foto,
            'email'     => $this->postStr('email') ?: null,
            'telefono'  => $this->postStr('telefono') ?: null,
            'orden'     => $this->postInt('orden'),
            'activo'    => $this->postBool('activo'),
        ];

        if ($actual) {
            $this->modelo->actualizar($id, $datos);
            $this->auditoria('editar', 'personas', $id, $nombre);
            if (!Session::hayFlash()) {
                Session::flash('success', 'Datos actualizados.');
            }
        } else {
            $id = $this->modelo->crear($datos);
            $this->auditoria('crear', 'personas', $id, $nombre);
            Session::flash('success', 'Persona agregada.');
        }

        $this->redirect(url_admin('personas'));
    }

    public function eliminar(): void
    {
        $this->requirePermiso('personas.editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('personas'));
            return;
        }
        $this->validarCsrf();

        $id      = $this->postInt('id');
        $persona = $this->modelo->porId($id);

        if ($persona) {
            Upload::borrar($persona['foto']);
            $this->modelo->eliminar($id);
            $this->auditoria('eliminar', 'personas', $id, $persona['nombre']);
            Session::flash('success', 'Persona eliminada. Si aparecía en el organigrama, ese lugar quedó sin asignar.');
        }

        $this->redirect(url_admin('personas'));
    }
}
