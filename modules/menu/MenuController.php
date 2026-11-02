<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/modules/menu/MenuModel.php';

/**
 * Contenido → Menú del sitio.
 *
 * Una sola lista con las secciones fijas y las páginas libres: qué se muestra
 * en el menú público, en qué orden y con qué nombre. Inicio y Contacto son
 * fijos y no aparecen aquí. Ver MenuModel y menu_del_sitio().
 */
class MenuController extends Controller
{
    private MenuModel $modelo;

    public function __construct()
    {
        $this->modelo = new MenuModel();
    }

    public function index(): void
    {
        $this->requirePermiso('menu.ver');

        $this->render('menu/index', [
            'titulo'        => 'Menú del sitio',
            'entradas'      => $this->modelo->entradas(),
            'cursosApagado' => Config::get('cursos_activo', '1') !== '1',
            'puedeEditar'   => Auth::tienePermiso('menu.editar'),
            'scriptExtra'   => '<script src="' . e(url_activo('assets/js/menu_sitio.js'))
                             . '?v=' . e(APP_VERSION) . '"></script>',
        ]);
    }

    public function guardar(): void
    {
        $this->requirePermiso('menu.editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('menu'));
            return;
        }
        $this->validarCsrf();

        $orden     = array_map('strval', (array) ($_POST['orden'] ?? []));
        $visibles  = array_map('boolval', (array) ($_POST['visible'] ?? []));
        $etiquetas = array_map('strval', (array) ($_POST['etiqueta'] ?? []));

        $this->modelo->guardar($orden, $visibles, $etiquetas);
        $this->auditoria('editar', 'menu_sitio', 0, 'Menú del sitio');
        Session::flash('success', 'Menú guardado. Así se ve ya en el sitio.');

        $this->redirect(url_admin('menu'));
    }
}
