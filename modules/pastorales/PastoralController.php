<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/modules/pastorales/PastoralModel.php';

class PastoralController extends Controller
{
    private PastoralModel $modelo;

    public function __construct()
    {
        $this->modelo = new PastoralModel();
    }

    public function index(): void
    {
        $this->requirePermiso('pastorales.ver');

        $todas = $this->modelo->todas();
        if (!Auth::tieneAlcanceGlobal()) {
            $permitidas = Auth::pastoralesPermitidas();
            $todas = array_values(array_filter(
                $todas,
                static fn (array $p): bool => in_array((int) $p['id'], $permitidas, true)
            ));
        }

        $this->render('pastorales/lista', [
            'titulo'     => 'Pastorales',
            'pastorales' => $todas,
        ]);
    }

    public function nueva(): void
    {
        $this->requirePermiso('pastorales.crear');

        $this->render('pastorales/form', [
            'titulo'   => 'Nueva pastoral',
            'pastoral' => null,
        ]);
    }

    public function editar(): void
    {
        $this->requirePermiso('pastorales.editar');

        $pastoral = $this->modelo->porId($this->getInt('id'));
        if (!$pastoral) {
            Session::flash('error', 'No encontramos esa pastoral.');
            $this->redirect(url_admin('pastorales'));
            return;
        }
        $this->requireAlcancePastoral((int) $pastoral['id']);

        $this->render('pastorales/form', [
            'titulo'      => $pastoral['nombre'],
            'pastoral'    => $pastoral,
            'actividades' => $this->modelo->actividades((int) $pastoral['id']),
            'scriptExtra' => $this->scriptEditor(),
        ]);
    }

    public function guardar(): void
    {
        $id       = $this->postInt('id');
        $existente = $id ? $this->modelo->porId($id) : null;

        $this->requirePermiso($existente ? 'pastorales.editar' : 'pastorales.crear');
        if ($existente) {
            $this->requireAlcancePastoral((int) $existente['id']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('pastorales'));
            return;
        }
        $this->validarCsrf();

        $nombre = $this->postStr('nombre');
        if ($nombre === '') {
            Session::flash('error', 'La pastoral necesita un nombre.');
            $this->redirect($id ? url_admin('pastorales', 'editar', ['id' => $id]) : url_admin('pastorales', 'nueva'));
            return;
        }

        $slugPedido = $this->postStr('slug');
        $slug = $slugPedido !== ''
            ? Slug::unico($slugPedido, 'pastorales', $id ?: null)
            : ($existente ? $existente['slug'] : Slug::unico($nombre, 'pastorales'));

        $imagen = $existente['imagen'] ?? null;
        try {
            if (!empty($_POST['imagen_quitar'])) {
                Upload::borrar($imagen);
                $imagen = null;
            } else {
                $imagen = Upload::imagen('imagen', 'pastorales', 'pastoral', $imagen);
            }
        } catch (RuntimeException $e) {
            Session::flash('warning', 'La pastoral se guardó, pero la imagen no: ' . $e->getMessage());
        }

        $datos = [
            'slug'               => $slug,
            'nombre'             => $nombre,
            'descripcion_corta'  => $this->postStr('descripcion_corta') ?: null,
            'descripcion'        => SanitizadorHtml::limpiar($this->postHtml('descripcion')) ?: null,
            'imagen'             => $imagen,
            'icono'              => $this->postStr('icono') ?: 'bi-people',
            'responsable_nombre' => $this->postStr('responsable_nombre') ?: null,
            'contacto_email'     => $this->postStr('contacto_email') ?: null,
            'contacto_telefono'  => $this->postStr('contacto_telefono') ?: null,
            'dia_reunion'        => $this->postStr('dia_reunion') ?: null,
            'hora_reunion'       => $this->postStr('hora_reunion') ?: null,
            'lugar_reunion'      => $this->postStr('lugar_reunion') ?: null,
            'acepta_voluntarios' => $this->postBool('acepta_voluntarios'),
            'orden'              => $this->postInt('orden'),
            // Un coordinador puede tener alcance global de facto si un admin se
            // lo concede, pero de entrada solo admin/editor deciden si una
            // pastoral está activa en el sitio.
            'activa'             => Auth::tieneAlcanceGlobal() ? $this->postBool('activa') : ($existente['activa'] ?? 1),
        ];

        if ($existente) {
            $this->modelo->actualizar($id, $datos);
            $this->auditoria('editar', 'pastorales', $id, $nombre);
            Session::flash('success', 'Pastoral actualizada.');
        } else {
            $id = $this->modelo->crear($datos);
            $this->auditoria('crear', 'pastorales', $id, $nombre);
            Session::flash('success', 'Pastoral creada.');
        }

        $this->redirect(url_admin('pastorales', 'editar', ['id' => $id]));
    }

    public function eliminar(): void
    {
        $this->requirePermiso('pastorales.eliminar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('pastorales'));
            return;
        }
        $this->validarCsrf();

        $id       = $this->postInt('id');
        $pastoral = $this->modelo->porId($id);
        if ($pastoral) {
            Upload::borrar($pastoral['imagen']);
            $this->modelo->eliminar($id);
            $this->auditoria('eliminar', 'pastorales', $id, $pastoral['nombre']);
            Session::flash('success', 'Pastoral eliminada.');
        }

        $this->redirect(url_admin('pastorales'));
    }

    // ── Actividades ─────────────────────────────────────────────────────

    public function actividadGuardar(): void
    {
        $this->requirePermiso('actividades.crear');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('pastorales'));
            return;
        }
        $this->validarCsrf();

        $pastoralId = $this->postInt('pastoral_id');
        $pastoral   = $this->modelo->porId($pastoralId);
        if (!$pastoral) {
            Session::flash('error', 'No encontramos esa pastoral.');
            $this->redirect(url_admin('pastorales'));
            return;
        }
        $this->requireAlcancePastoral($pastoralId);

        $id = $this->postInt('id');
        if ($id) {
            $this->requirePermiso('actividades.editar');
        }

        $titulo = $this->postStr('titulo');
        if ($titulo === '') {
            Session::flash('error', 'La actividad necesita un título.');
            $this->redirect(url_admin('pastorales', 'editar', ['id' => $pastoralId]));
            return;
        }

        $datos = [
            'pastoral_id' => $pastoralId,
            'titulo'      => $titulo,
            'descripcion' => $this->postStr('descripcion') ?: null,
            'tipo'        => isset(PastoralModel::TIPOS_ACTIVIDAD[$this->postStr('tipo')])
                             ? $this->postStr('tipo') : 'comunitaria',
            'orden'       => $this->postInt('orden'),
            'activa'      => $this->postBool('activa'),
        ];

        if ($id && $this->modelo->actividadPorId($id)) {
            $this->modelo->actualizarActividad($id, $datos);
            $this->auditoria('editar', 'pastoral_actividades', $id, $titulo);
            Session::flash('success', 'Actividad actualizada.');
        } else {
            $id = $this->modelo->crearActividad($datos);
            $this->auditoria('crear', 'pastoral_actividades', $id, $titulo);
            Session::flash('success', 'Actividad agregada.');
        }

        $this->redirect(url_admin('pastorales', 'editar', ['id' => $pastoralId]));
    }

    public function actividadEliminar(): void
    {
        $this->requirePermiso('actividades.eliminar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('pastorales'));
            return;
        }
        $this->validarCsrf();

        $id        = $this->postInt('id');
        $actividad = $this->modelo->actividadPorId($id);
        if (!$actividad) {
            $this->redirect(url_admin('pastorales'));
            return;
        }
        $this->requireAlcancePastoral((int) $actividad['pastoral_id']);

        $this->modelo->eliminarActividad($id);
        $this->auditoria('eliminar', 'pastoral_actividades', $id);
        Session::flash('success', 'Actividad eliminada.');

        $this->redirect(url_admin('pastorales', 'editar', ['id' => $actividad['pastoral_id']]));
    }

    private function scriptEditor(): string
    {
        return '<script src="' . e(url_activo('assets/js/editor.js')) . '?v=' . e(APP_VERSION) . '"></script>';
    }
}
