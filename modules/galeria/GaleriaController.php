<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/modules/galeria/GaleriaModel.php';

class GaleriaController extends Controller
{
    private GaleriaModel $modelo;

    public function __construct()
    {
        $this->modelo = new GaleriaModel();
    }

    public function index(): void
    {
        $this->requirePermiso('galeria.ver');

        $filtro = in_array($this->getStr('filtro'), ['publicadas', 'ocultas'], true)
            ? $this->getStr('filtro') : 'todas';

        $this->render('galeria/lista', [
            'titulo'  => 'Galería',
            'listado' => $this->modelo->listar(max(1, $this->getInt('pagina', 1)), $filtro),
            'filtro'  => $filtro,
        ]);
    }

    public function subir(): void
    {
        $this->requirePermiso('galeria.crear');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->render('galeria/subir', ['titulo' => 'Subir fotografías']);
            return;
        }
        $this->validarCsrf();

        $errores = [];
        $rutas   = Upload::imagenes('fotos', 'galeria', 'galeria', $errores);

        if ($rutas) {
            $autorizacion = $this->postBool('autorizacion_imagen') === 1;
            $n = $this->modelo->crearLote($rutas, $autorizacion, (int) Auth::usuario()['id']);
            $this->auditoria('crear', 'galeria_imagenes', 0, $n . ' foto(s) subidas');
            Session::flash('success', $n . ' fotografía(s) subidas. Revísalas antes de publicarlas.');
        }
        if ($errores) {
            Session::flash('warning', 'Algunas fotos no se pudieron subir: ' . implode(' · ', $errores));
        }
        if (!$rutas && !$errores) {
            Session::flash('error', 'Selecciona al menos una fotografía.');
        }

        $this->redirect(url_admin('galeria'));
    }

    public function guardar(): void
    {
        $this->requirePermiso('galeria.editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('galeria'));
            return;
        }
        $this->validarCsrf();

        $id     = $this->postInt('id');
        $actual = $this->modelo->porId($id);
        if (!$actual) {
            Session::flash('error', 'No encontramos esa fotografía.');
            $this->redirect(url_admin('galeria'));
            return;
        }

        $puedePublicar = Auth::tienePermiso('galeria.publicar');

        $this->modelo->actualizar($id, [
            'titulo'              => $this->postStr('titulo') ?: null,
            'alt_texto'           => $this->postStr('alt_texto') ?: null,
            'autorizacion_imagen' => $this->postBool('autorizacion_imagen'),
            'publicada'           => $puedePublicar ? $this->postBool('publicada') : $actual['publicada'],
            'orden'               => $this->postInt('orden'),
        ]);
        $this->auditoria('editar', 'galeria_imagenes', $id);

        Session::flash('success', 'Fotografía actualizada.');
        $this->redirect(url_admin('galeria'));
    }

    public function eliminar(): void
    {
        $this->requirePermiso('galeria.eliminar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('galeria'));
            return;
        }
        $this->validarCsrf();

        $id    = $this->postInt('id');
        $imagen = $this->modelo->porId($id);
        if ($imagen) {
            Upload::borrar($imagen['archivo']);
            $this->modelo->eliminar($id);
            $this->auditoria('eliminar', 'galeria_imagenes', $id);
            Session::flash('success', 'Fotografía eliminada.');
        }

        $this->redirect(url_admin('galeria'));
    }
}
