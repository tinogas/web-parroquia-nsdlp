<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/modules/centros/CentroModel.php';

class CentroController extends Controller
{
    private CentroModel $modelo;

    public function __construct()
    {
        $this->modelo = new CentroModel();
    }

    public function index(): void
    {
        $this->requirePermiso('centros.ver');

        $this->render('centros/lista', [
            'titulo'  => 'Sede y centros',
            'centros' => $this->modelo->todos(),
        ]);
    }

    public function nuevo(): void
    {
        $this->requirePermiso('centros.editar');

        $this->render('centros/form', [
            'titulo' => 'Nuevo centro',
            'centro' => null,
        ]);
    }

    public function editar(): void
    {
        $this->requirePermiso('centros.editar');

        $centro = $this->modelo->porId($this->getInt('id'));
        if (!$centro) {
            Session::flash('error', 'No encontramos ese centro.');
            $this->redirect(url_admin('centros'));
            return;
        }

        $this->render('centros/form', [
            'titulo' => $centro['nombre'],
            'centro' => $centro,
        ]);
    }

    public function guardar(): void
    {
        $this->requirePermiso('centros.editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('centros'));
            return;
        }
        $this->validarCsrf();

        $id     = $this->postInt('id');
        $nombre = $this->postStr('nombre');
        $tipo   = $this->postStr('tipo');

        if ($nombre === '' || !isset(CentroModel::TIPOS[$tipo])) {
            Session::flash('error', 'Escribe el nombre y elige un tipo válido.');
            $this->redirect($id ? url_admin('centros', 'editar', ['id' => $id]) : url_admin('centros', 'nuevo'));
            return;
        }

        $actual = $id ? $this->modelo->porId($id) : null;
        $imagen = $actual['imagen'] ?? null;

        try {
            if (!empty($_POST['imagen_quitar'])) {
                Upload::borrar($imagen);
                $imagen = null;
            } else {
                $imagen = Upload::imagen('imagen', 'centros', 'centro', $imagen);
            }
        } catch (RuntimeException $e) {
            Session::flash('warning', 'Se guardó, pero la imagen no: ' . $e->getMessage());
        }

        $datos = [
            'tipo'        => $tipo,
            'nombre'      => $nombre,
            'direccion'   => $this->postStr('direccion') ?: null,
            'telefono'    => $this->postStr('telefono') ?: null,
            'descripcion' => $this->postStr('descripcion') ?: null,
            'imagen'      => $imagen,
            'orden'       => $this->postInt('orden'),
            'activo'      => $this->postBool('activo'),
        ];

        if ($actual) {
            $this->modelo->actualizar($id, $datos);
            $this->auditoria('editar', 'centros', $id, $nombre);
            if (!Session::hayFlash()) {
                Session::flash('success', 'Datos actualizados.');
            }
        } else {
            $id = $this->modelo->crear($datos);
            $this->auditoria('crear', 'centros', $id, $nombre);
            Session::flash('success', 'Centro agregado.');
        }

        $this->redirect(url_admin('centros'));
    }

    public function eliminar(): void
    {
        $this->requirePermiso('centros.editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('centros'));
            return;
        }
        $this->validarCsrf();

        $id     = $this->postInt('id');
        $centro = $this->modelo->porId($id);

        if ($centro) {
            Upload::borrar($centro['imagen']);
            $this->modelo->eliminar($id);
            $this->auditoria('eliminar', 'centros', $id, $centro['nombre']);
            Session::flash('success', 'Centro eliminado.');
        }

        $this->redirect(url_admin('centros'));
    }
}
