<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/modules/sacramentos/SacramentoModel.php';

class SacramentoController extends Controller
{
    private SacramentoModel $modelo;

    public function __construct()
    {
        $this->modelo = new SacramentoModel();
    }

    public function index(): void
    {
        $this->requirePermiso('sacramentos.ver');

        $this->render('sacramentos/lista', [
            'titulo'      => 'Sacramentos',
            'sacramentos' => $this->modelo->todos(),
        ]);
    }

    public function editar(): void
    {
        $this->requirePermiso('sacramentos.editar');

        $sacramento = $this->modelo->porId($this->getInt('id'));
        if (!$sacramento) {
            Session::flash('error', 'No encontramos ese sacramento.');
            $this->redirect(url_admin('sacramentos'));
            return;
        }

        $this->render('sacramentos/form', [
            'titulo'      => $sacramento['nombre'],
            'sacramento'  => $sacramento,
            'scriptExtra' => $this->scriptEditor(),
        ]);
    }

    public function guardar(): void
    {
        $this->requirePermiso('sacramentos.editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('sacramentos'));
            return;
        }
        $this->validarCsrf();

        $id     = $this->postInt('id');
        $nombre = $this->postStr('nombre');
        if ($id === 0 || $nombre === '') {
            Session::flash('error', 'Falta el sacramento o el nombre.');
            $this->redirect(url_admin('sacramentos'));
            return;
        }

        $actual = $this->modelo->porId($id);
        if (!$actual) {
            Session::flash('error', 'No encontramos ese sacramento.');
            $this->redirect(url_admin('sacramentos'));
            return;
        }

        $imagen = $actual['imagen'];
        try {
            if (!empty($_POST['imagen_quitar'])) {
                Upload::borrar($imagen);
                $imagen = null;
            } else {
                $imagen = Upload::imagen('imagen', 'sacramentos', 'sacramento', $imagen);
            }
        } catch (RuntimeException $e) {
            Session::flash('warning', 'Se guardó, pero la imagen no: ' . $e->getMessage());
        }

        $this->modelo->actualizar($id, [
            'slug'        => $actual['slug'], // el slug del sacramento no se edita: define su URL
            'nombre'      => $nombre,
            'descripcion' => SanitizadorHtml::limpiar($this->postHtml('descripcion')) ?: null,
            'requisitos'  => SanitizadorHtml::limpiar($this->postHtml('requisitos')) ?: null,
            'documentos'  => SanitizadorHtml::limpiar($this->postHtml('documentos')) ?: null,
            'aportacion'  => $this->postStr('aportacion') ?: null,
            'imagen'      => $imagen,
            'orden'       => $this->postInt('orden'),
            'activo'      => $this->postBool('activo'),
        ]);
        $this->auditoria('editar', 'sacramentos', $id, $nombre);

        Session::flash('success', 'Sacramento actualizado.');
        $this->redirect(url_admin('sacramentos', 'editar', ['id' => $id]));
    }

    private function scriptEditor(): string
    {
        return '<script src="' . e(url_activo('assets/js/editor.js')) . '?v=' . e(APP_VERSION) . '"></script>';
    }
}
