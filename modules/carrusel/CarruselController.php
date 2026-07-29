<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/modules/carrusel/CarruselModel.php';

class CarruselController extends Controller
{
    private CarruselModel $modelo;

    public function __construct()
    {
        $this->modelo = new CarruselModel();
    }

    public function index(): void
    {
        $this->requirePermiso('carrusel.ver');

        $this->render('carrusel/lista', [
            'titulo'    => 'Carrusel de portada',
            'diapositivas' => $this->modelo->todos(),
        ]);
    }

    public function nuevo(): void
    {
        $this->requirePermiso('carrusel.editar');

        $this->render('carrusel/form', [
            'titulo'      => 'Nueva diapositiva',
            'diapositiva' => null,
        ]);
    }

    public function editar(): void
    {
        $this->requirePermiso('carrusel.editar');

        $diapositiva = $this->modelo->porId($this->getInt('id'));
        if (!$diapositiva) {
            Session::flash('error', 'No encontramos esa diapositiva.');
            $this->redirect(url_admin('carrusel'));
            return;
        }

        $this->render('carrusel/form', [
            'titulo'      => 'Editar diapositiva',
            'diapositiva' => $diapositiva,
        ]);
    }

    public function guardar(): void
    {
        $this->requirePermiso('carrusel.editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('carrusel'));
            return;
        }
        $this->validarCsrf();

        $id       = $this->postInt('id');
        $actual   = $id ? $this->modelo->porId($id) : null;
        $imagen   = $actual['imagen'] ?? null;

        try {
            if (!empty($_POST['imagen_quitar'])) {
                Upload::borrar($imagen);
                $imagen = null;
            } else {
                $imagen = Upload::imagen('imagen', 'carrusel', 'slide', $imagen);
            }
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect($id ? url_admin('carrusel', 'editar', ['id' => $id]) : url_admin('carrusel', 'nuevo'));
            return;
        }

        if (!$imagen) {
            Session::flash('error', 'La diapositiva necesita una imagen.');
            $this->redirect($id ? url_admin('carrusel', 'editar', ['id' => $id]) : url_admin('carrusel', 'nuevo'));
            return;
        }

        $datos = [
            'imagen'    => $imagen,
            'titulo'    => $this->postStr('titulo') ?: null,
            'subtitulo' => $this->postStr('subtitulo') ?: null,
            'enlace'    => $this->postStr('enlace') ?: null,
            'orden'     => $this->postInt('orden'),
            'activo'    => $this->postBool('activo'),
        ];

        if ($actual) {
            $this->modelo->actualizar($id, $datos);
            $this->auditoria('editar', 'carrusel', $id);
            Session::flash('success', 'Diapositiva actualizada.');
        } else {
            $id = $this->modelo->crear($datos);
            $this->auditoria('crear', 'carrusel', $id);
            Session::flash('success', 'Diapositiva agregada.');
        }

        $this->redirect(url_admin('carrusel'));
    }

    public function eliminar(): void
    {
        $this->requirePermiso('carrusel.editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('carrusel'));
            return;
        }
        $this->validarCsrf();

        $id          = $this->postInt('id');
        $diapositiva = $this->modelo->porId($id);
        if ($diapositiva) {
            Upload::borrar($diapositiva['imagen']);
            $this->modelo->eliminar($id);
            $this->auditoria('eliminar', 'carrusel', $id);
            Session::flash('success', 'Diapositiva eliminada.');
        }

        $this->redirect(url_admin('carrusel'));
    }
}
