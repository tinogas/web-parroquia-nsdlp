<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/modules/evangelio/EvangelioModel.php';

/**
 * EvangelioController — El evangelio del día y la reflexión del párroco.
 *
 * Calca PaginaController casi entero: contenido editorial de toda la
 * parroquia, sin alcance por pastoral, un solo booleano de publicación que
 * cualquiera con `evangelio.editar` cambia libremente. Sin
 * requireAlcancePastoral(): esta tabla no tiene pastoral_id.
 */
class EvangelioController extends Controller
{
    private EvangelioModel $modelo;

    public function __construct()
    {
        $this->modelo = new EvangelioModel();
    }

    public function index(): void
    {
        $this->requirePermiso('evangelio.ver');

        $this->render('evangelio/lista', [
            'titulo'  => 'Evangelio del día',
            'listado' => $this->modelo->listar(max(1, $this->getInt('pagina', 1))),
        ]);
    }

    public function nuevo(): void
    {
        $this->requirePermiso('evangelio.editar');

        $this->render('evangelio/form', [
            'titulo'      => 'Nueva entrada',
            'entrada'     => null,
            // Sugerencia razonable: lo normal es capturar la de hoy, no una
            // fecha al azar. Sigue siendo un campo editable, no un valor fijo.
            'fechaSugerida' => date('Y-m-d'),
            'scriptExtra' => $this->scriptEditor(),
        ]);
    }

    public function editar(): void
    {
        $this->requirePermiso('evangelio.editar');

        $entrada = $this->modelo->porId($this->getInt('id'));
        if (!$entrada) {
            Session::flash('error', 'No encontramos esa entrada.');
            $this->redirect(url_admin('evangelio'));
            return;
        }

        $this->render('evangelio/form', [
            'titulo'        => 'Evangelio del ' . $entrada['fecha'],
            'entrada'       => $entrada,
            'fechaSugerida' => $entrada['fecha'],
            'scriptExtra'   => $this->scriptEditor(),
        ]);
    }

    public function guardar(): void
    {
        $this->requirePermiso('evangelio.editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('evangelio'));
            return;
        }
        $this->validarCsrf();

        $id        = $this->postInt('id');
        $fecha     = $this->postStr('fecha');
        $evangelio = SanitizadorHtml::limpiar($this->postHtml('evangelio'));

        if ($fecha === '' || $evangelio === '') {
            Session::flash('error', 'La fecha y el texto del evangelio son obligatorios.');
            $this->redirect($id ? url_admin('evangelio', 'editar', ['id' => $id]) : url_admin('evangelio', 'nuevo'));
            return;
        }

        $chocaCon = $this->modelo->porFecha($fecha);

        if ($id === 0 && $chocaCon) {
            // Alta que coincide con una fecha ya capturada: se actualiza esa
            // fila en vez de fallar con un error de duplicado — no hay una
            // segunda entidad real que se esté perdiendo, es la misma
            // intención de siempre («publicar el evangelio de hoy»).
            $id = (int) $chocaCon['id'];
            Session::flash('info', 'Ya existía una entrada para esa fecha: se actualizó en vez de crear otra.');
        } elseif ($id !== 0 && $chocaCon && (int) $chocaCon['id'] !== $id) {
            // Edición que reasigna la fecha a una que pertenece a OTRA fila
            // distinta: aquí sí hay dos textos capturados por separado, así
            // que no se fusionan solos. Se rechaza sin tocar ninguna de las dos.
            Session::flash('error', 'Ya existe otra entrada para esa fecha.');
            $this->redirect(url_admin('evangelio', 'editar', ['id' => $id]));
            return;
        }

        $datos = [
            'fecha'     => $fecha,
            'evangelio' => $evangelio,
            'reflexion' => SanitizadorHtml::limpiar($this->postHtml('reflexion')) ?: null,
            'publicado' => $this->postBool('publicado'),
        ];

        $usuarioId = (int) Auth::usuario()['id'];

        if ($id) {
            $this->modelo->actualizar($id, $datos, $usuarioId);
            $this->auditoria('editar', 'evangelios_dia', $id, $fecha);
            Session::flash('success', 'Entrada actualizada.');
        } else {
            $id = $this->modelo->crear($datos, $usuarioId);
            $this->auditoria('crear', 'evangelios_dia', $id, $fecha);
            Session::flash('success', 'Entrada creada.');
        }

        $this->redirect(url_admin('evangelio'));
    }

    public function eliminar(): void
    {
        $this->requirePermiso('evangelio.editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('evangelio'));
            return;
        }
        $this->validarCsrf();

        $id      = $this->postInt('id');
        $entrada = $this->modelo->porId($id);

        if (!$entrada) {
            Session::flash('error', 'No encontramos esa entrada.');
        } else {
            $this->modelo->eliminar($id);
            $this->auditoria('eliminar', 'evangelios_dia', $id, $entrada['fecha']);
            Session::flash('success', 'Entrada eliminada.');
        }

        $this->redirect(url_admin('evangelio'));
    }

    private function scriptEditor(): string
    {
        return '<script src="' . e(url_activo('assets/js/editor.js'))
             . '?v=' . e(APP_VERSION) . '"></script>';
    }
}
