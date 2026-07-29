<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/modules/bloques/BloqueModel.php';

class BloqueController extends Controller
{
    private BloqueModel $modelo;

    public function __construct()
    {
        $this->modelo = new BloqueModel();
    }

    public function index(): void
    {
        $this->requirePermiso('bloques.ver');

        // Agrupados por zona para que el listado se lea como el propio sitio.
        $porZona = [];
        foreach ($this->modelo->todos() as $bloque) {
            $porZona[$bloque['zona']][] = $bloque;
        }

        $this->render('bloques/lista', [
            'titulo'  => 'Textos del sitio',
            'porZona' => $porZona,
        ]);
    }

    public function editar(): void
    {
        $this->requirePermiso('bloques.editar');

        $bloque = $this->modelo->porId($this->getInt('id'));
        if (!$bloque) {
            Session::flash('error', 'No encontramos ese texto.');
            $this->redirect(url_admin('bloques'));
            return;
        }

        $this->render('bloques/form', [
            'titulo' => $bloque['titulo'] ?: $bloque['clave'],
            'bloque' => $bloque,
            // El editor solo se carga donde se usa, no en todo el panel.
            'scriptExtra' => '<script src="' . e(url_activo('assets/js/editor.js'))
                           . '?v=' . e(APP_VERSION) . '"></script>',
        ]);
    }

    public function guardar(): void
    {
        $this->requirePermiso('bloques.editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('bloques'));
            return;
        }
        $this->validarCsrf();

        $id     = $this->postInt('id');
        $bloque = $this->modelo->porId($id);

        if (!$bloque) {
            Session::flash('error', 'No encontramos ese texto.');
            $this->redirect(url_admin('bloques'));
            return;
        }

        $imagen = $bloque['imagen'];
        try {
            if (!empty($_POST['imagen_quitar'])) {
                Upload::borrar($imagen);
                $imagen = null;
            } else {
                $imagen = Upload::imagen('imagen', 'bloques', $bloque['clave'], $imagen);
            }
        } catch (RuntimeException $e) {
            Session::flash('warning', 'El texto se guardó, pero la imagen no: ' . $e->getMessage());
        }

        $this->modelo->actualizar($id, [
            'titulo' => $this->postStr('titulo') ?: null,
            // El contenido llega con formato del editor: se limpia con lista
            // blanca ANTES de guardarlo. En la vista se imprime sin escapar.
            'contenido' => SanitizadorHtml::limpiar($this->postHtml('contenido')) ?: null,
            'imagen'    => $imagen,
            'activo'    => $this->postBool('activo'),
        ], (int) Auth::usuario()['id']);

        $this->auditoria('editar', 'bloques_contenido', $id, $bloque['clave']);

        if (!Session::hayFlash()) {
            Session::flash('success', 'Texto actualizado.');
        }
        $this->redirect(url_admin('bloques'));
    }
}
