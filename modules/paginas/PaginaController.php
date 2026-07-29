<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/modules/paginas/PaginaModel.php';

class PaginaController extends Controller
{
    private PaginaModel $modelo;

    public function __construct()
    {
        $this->modelo = new PaginaModel();
    }

    public function index(): void
    {
        $this->requirePermiso('paginas.ver');

        $this->render('paginas/lista', [
            'titulo'  => 'Páginas',
            'paginas' => $this->modelo->todas(),
        ]);
    }

    public function nueva(): void
    {
        $this->requirePermiso('paginas.editar');

        $this->render('paginas/form', [
            'titulo'      => 'Nueva página',
            'pagina'      => null,
            'scriptExtra' => $this->scriptEditor(),
        ]);
    }

    public function editar(): void
    {
        $this->requirePermiso('paginas.editar');

        $pagina = $this->modelo->porId($this->getInt('id'));
        if (!$pagina) {
            Session::flash('error', 'No encontramos esa página.');
            $this->redirect(url_admin('paginas'));
            return;
        }

        $this->render('paginas/form', [
            'titulo'      => $pagina['titulo'],
            'pagina'      => $pagina,
            'scriptExtra' => $this->scriptEditor(),
        ]);
    }

    public function guardar(): void
    {
        $this->requirePermiso('paginas.editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('paginas'));
            return;
        }
        $this->validarCsrf();

        $id     = $this->postInt('id');
        $titulo = $this->postStr('titulo');

        if ($titulo === '') {
            Session::flash('error', 'La página necesita un título.');
            $this->redirect($id ? url_admin('paginas', 'editar', ['id' => $id]) : url_admin('paginas', 'nueva'));
            return;
        }

        $existente = $id ? $this->modelo->porId($id) : null;

        // El slug no se regenera al cambiar el título: rompería los enlaces ya
        // compartidos. Solo se calcula al crear, o si el usuario lo edita.
        $slugPedido = $this->postStr('slug');
        if ($existente && in_array($existente['slug'], PaginaModel::PROTEGIDAS, true)) {
            $slug = $existente['slug'];   // El aviso de privacidad conserva su dirección.
        } elseif ($slugPedido !== '') {
            $slug = Slug::unico($slugPedido, 'paginas', $id ?: null);
        } elseif ($existente) {
            $slug = $existente['slug'];
        } else {
            $slug = Slug::unico($titulo, 'paginas');
        }

        $datos = [
            'slug'   => $slug,
            'titulo' => $titulo,
            // Contenido con formato: se limpia con lista blanca antes de guardar.
            'contenido'        => SanitizadorHtml::limpiar($this->postHtml('contenido')) ?: null,
            'meta_descripcion' => $this->postStr('meta_descripcion') ?: null,
            'en_menu'          => $this->postBool('en_menu'),
            'orden'            => $this->postInt('orden'),
            'publicada'        => $this->postBool('publicada'),
        ];

        $usuarioId = (int) Auth::usuario()['id'];

        if ($existente) {
            $this->modelo->actualizar($id, $datos, $usuarioId);
            $this->auditoria('editar', 'paginas', $id, $slug);
            Session::flash('success', 'Página actualizada.');
        } else {
            $id = $this->modelo->crear($datos, $usuarioId);
            $this->auditoria('crear', 'paginas', $id, $slug);
            Session::flash('success', 'Página creada.');
        }

        $this->redirect(url_admin('paginas'));
    }

    public function eliminar(): void
    {
        $this->requirePermiso('paginas.editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('paginas'));
            return;
        }
        $this->validarCsrf();

        $id     = $this->postInt('id');
        $pagina = $this->modelo->porId($id);

        if (!$pagina) {
            Session::flash('error', 'No encontramos esa página.');
        } elseif (in_array($pagina['slug'], PaginaModel::PROTEGIDAS, true)) {
            // El aviso de privacidad es obligatorio por ley: se puede editar,
            // pero no borrar desde el panel.
            Session::flash('error', 'Esa página no se puede eliminar porque el sitio la necesita.');
        } else {
            $this->modelo->eliminar($id);
            $this->auditoria('eliminar', 'paginas', $id, $pagina['slug']);
            Session::flash('success', 'Página eliminada.');
        }

        $this->redirect(url_admin('paginas'));
    }

    private function scriptEditor(): string
    {
        return '<script src="' . e(url_activo('assets/js/editor.js'))
             . '?v=' . e(APP_VERSION) . '"></script>';
    }
}
