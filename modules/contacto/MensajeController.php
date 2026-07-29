<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/modules/contacto/ContactoModel.php';

/** Panel: bandeja de mensajes recibidos por el formulario de contacto. */
class MensajeController extends Controller
{
    private ContactoModel $modelo;

    public function __construct()
    {
        $this->modelo = new ContactoModel();
    }

    public function index(): void
    {
        $this->requirePermiso('mensajes.ver');

        $filtro = $this->getStr('filtro') === 'no_leidos' ? 'no_leidos' : 'todos';
        $pagina = max(1, $this->getInt('pagina', 1));

        // Lectura de datos personales: se audita también el listado, no solo
        // el detalle. Ver docs/PRIVACIDAD.md
        $this->auditoria('consultar', 'mensajes_contacto', 0, 'Listado, filtro: ' . $filtro);

        $this->render('contacto/lista', [
            'titulo'   => 'Mensajes',
            'listado'  => $this->modelo->listar($pagina, $filtro),
            'filtro'   => $filtro,
            'noLeidos' => $this->modelo->noLeidos(),
        ]);
    }

    public function ver(): void
    {
        $this->requirePermiso('mensajes.ver');

        $mensaje = $this->modelo->porId($this->getInt('id'));
        if (!$mensaje) {
            Session::flash('error', 'No encontramos ese mensaje.');
            $this->redirect(url_admin('mensajes'));
            return;
        }

        if (!$mensaje['leido']) {
            $this->modelo->marcarLeido($mensaje['id']);
            $mensaje['leido'] = 1;
        }
        $this->auditoria('consultar', 'mensajes_contacto', $mensaje['id']);

        $this->render('contacto/ver', [
            'titulo'  => 'Mensaje de ' . $mensaje['nombre'],
            'mensaje' => $mensaje,
        ]);
    }

    public function marcarRespondido(): void
    {
        $this->requirePermiso('mensajes.editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('mensajes'));
            return;
        }
        $this->validarCsrf();

        $id = $this->postInt('id');
        $this->modelo->marcarRespondido($id, (int) Auth::usuario()['id']);
        $this->auditoria('editar', 'mensajes_contacto', $id, 'Marcado como atendido');

        Session::flash('success', 'Mensaje marcado como atendido.');
        $this->redirect(url_admin('mensajes', 'ver', ['id' => $id]));
    }

    public function guardarNota(): void
    {
        $this->requirePermiso('mensajes.editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('mensajes'));
            return;
        }
        $this->validarCsrf();

        $id = $this->postInt('id');
        $this->modelo->guardarNota($id, $this->postStr('nota_interna'));
        $this->auditoria('editar', 'mensajes_contacto', $id, 'Nota interna actualizada');

        Session::flash('success', 'Nota guardada.');
        $this->redirect(url_admin('mensajes', 'ver', ['id' => $id]));
    }

    public function eliminar(): void
    {
        $this->requirePermiso('mensajes.editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('mensajes'));
            return;
        }
        $this->validarCsrf();

        $id = $this->postInt('id');
        $this->modelo->eliminar($id);
        $this->auditoria('eliminar', 'mensajes_contacto', $id);

        Session::flash('success', 'Mensaje eliminado.');
        $this->redirect(url_admin('mensajes'));
    }
}
