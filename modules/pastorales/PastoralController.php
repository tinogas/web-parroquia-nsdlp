<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/modules/pastorales/PastoralModel.php';
require_once BASE_PATH . '/modules/centros/CentroModel.php';
require_once BASE_PATH . '/modules/personas/PersonaModel.php';
require_once BASE_PATH . '/modules/usuarios/UsuarioModel.php';

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

        $agrupado = $this->modelo->agrupadoVisible(Auth::tieneAlcanceGlobal() ? null : Auth::pastoralesPermitidas());

        $this->render('pastorales/lista', [
            'titulo'     => 'Pastorales',
            'comisiones' => $agrupado['comisiones'],
            'sueltas'    => $agrupado['sueltas'],
        ]);
    }

    public function nueva(): void
    {
        $this->requirePermiso('pastorales.crear');

        $this->render('pastorales/form', [
            'titulo'            => 'Nueva pastoral',
            'pastoral'          => null,
            'centros'           => (new CentroModel())->activos(),
            'personas'          => (new PersonaModel())->paraSelector(),
            'responsableCuenta' => null,
            'padresDisponibles' => $this->modelo->candidatosPadre(),
            'tieneHijos'        => false,
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

        $responsableCuenta = $pastoral['responsable_persona_id']
            ? (new UsuarioModel())->porPersona((int) $pastoral['responsable_persona_id'])
            : null;

        $this->render('pastorales/form', [
            'titulo'            => $pastoral['nombre'],
            'pastoral'          => $pastoral,
            'centros'           => (new CentroModel())->activos(),
            'personas'          => (new PersonaModel())->paraSelector(),
            'responsableCuenta' => $responsableCuenta,
            'padresDisponibles' => $this->modelo->candidatosPadre((int) $pastoral['id']),
            'tieneHijos'        => $this->modelo->tieneHijos((int) $pastoral['id']),
            'actividades'       => $this->modelo->actividades((int) $pastoral['id']),
            'documentos'        => $this->modelo->documentos((int) $pastoral['id']),
            'scriptExtra'       => $this->scriptEditor(),
        ]);
    }

    /**
     * Panel básico: avisos, eventos, cursos y documentos de una pastoral,
     * todos genéricos por pastoral_id —solo se enlaza a ellos ya filtrados,
     * no se duplica su CRUD—, salvo documentos, que se gestionan aquí mismo
     * porque ya vivían en este mismo Controller (ver documentoGuardar()).
     */
    public function panel(): void
    {
        $this->requirePermiso('pastorales.ver');

        $pastoral = $this->modelo->porId($this->getInt('id'));
        if (!$pastoral) {
            Session::flash('error', 'No encontramos esa pastoral.');
            $this->redirect(url_admin('pastorales'));
            return;
        }
        $this->requireAlcancePastoral((int) $pastoral['id']);

        $comisionPadre = $pastoral['pastoral_padre_id']
            ? $this->modelo->porId((int) $pastoral['pastoral_padre_id'])
            : null;

        $this->render('pastorales/panel', [
            'titulo'        => $pastoral['nombre'],
            'pastoral'      => $pastoral,
            'comisionPadre' => $comisionPadre,
            'moduloDedicado' => MODULO_POR_PASTORAL[$pastoral['slug']] ?? null,
            'documentos'    => $this->modelo->documentos((int) $pastoral['id']),
            'puedeEditar'   => Auth::tienePermiso('pastorales.editar'),
        ]);
    }

    /**
     * Publicarla en el menú es deliberado y separado de guardar(): solo
     * Administrador, confirmando su contraseña. Ver
     * Controller::requireAdminConPassword() y PastoralModel::activarEnMenu().
     */
    public function menuActivar(): void
    {
        $this->requirePermiso('pastorales.editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('pastorales'));
            return;
        }
        $this->validarCsrf();
        $this->requireAdminConPassword();

        $id       = $this->postInt('id');
        $pastoral = $this->modelo->porId($id);
        if (!$pastoral) {
            $this->redirect(url_admin('pastorales'));
            return;
        }

        $this->modelo->activarEnMenu($id);
        $this->auditoria('activar_menu', 'pastorales', $id, $pastoral['nombre']);
        Session::flash('success', '«' . $pastoral['nombre'] . '» ya aparece en el menú del panel.');

        $this->redirect(url_admin('pastorales'));
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

        // El responsable se elige del equipo pastoral; si no está ahí todavía,
        // el nombre libre de abajo es el respaldo. Con persona elegida, su
        // nombre manda sobre el campo de texto (que se ignora) y su correo de
        // acceso —si tiene cuenta— manda sobre el de contacto: es la misma
        // regla que corrige el caso real de MESC, donde el correo de contacto
        // llevaba una letra distinta al de la cuenta de la coordinadora.
        $responsablePersonaId = $this->postIntONull('responsable_persona_id');
        $responsablePersona   = $responsablePersonaId
            ? (new PersonaModel())->porId($responsablePersonaId) : null;
        if ($responsablePersonaId && !$responsablePersona) {
            $responsablePersonaId = null;   // id inválido: se ignora en silencio, como el resto de selects opcionales
        }

        // Máximo 2 niveles: el padre elegido no puede ser ella misma, no puede
        // ya tener su propio padre (evita un 3er nivel), y esta pastoral no
        // puede a la vez agrupar hijas y tener un padre. La UI ya solo ofrece
        // candidatos válidos (PastoralModel::candidatosPadre()) y oculta el
        // selector si tieneHijos, así que esto es defensa ante un POST
        // manipulado, no un caso de uso real — se ignora en silencio, como el
        // resto de selects opcionales.
        $pastoralPadreId = $this->postIntONull('pastoral_padre_id');
        if ($pastoralPadreId !== null) {
            $padre  = $this->modelo->porId($pastoralPadreId);
            $valido = $padre && $pastoralPadreId !== $id && $padre['pastoral_padre_id'] === null
                   && !($id && $this->modelo->tieneHijos($id));
            if (!$valido) {
                $pastoralPadreId = null;
            }
        }

        if ($responsablePersona) {
            $responsableNombre = $responsablePersona['nombre'];
            $cuentaResponsable = (new UsuarioModel())->porPersona((int) $responsablePersona['id']);
            $contactoEmail     = $cuentaResponsable ? $cuentaResponsable['email'] : ($this->postStr('contacto_email') ?: null);
        } else {
            $responsableNombre = $this->postStr('responsable_nombre') ?: null;
            $contactoEmail     = $this->postStr('contacto_email') ?: null;
        }

        $datos = [
            'centro_id'          => $this->postIntONull('centro_id'),
            'pastoral_padre_id'  => $pastoralPadreId,
            'slug'               => $slug,
            'nombre'             => $nombre,
            'descripcion_corta'  => $this->postStr('descripcion_corta') ?: null,
            'descripcion'        => SanitizadorHtml::limpiar($this->postHtml('descripcion')) ?: null,
            'imagen'             => $imagen,
            'icono'              => $this->postStr('icono') ?: 'bi-people',
            'responsable_nombre'      => $responsableNombre,
            'responsable_persona_id'  => $responsablePersonaId,
            'contacto_email'          => $contactoEmail,
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
        // Borra la fila de verdad (ver PastoralModel::eliminar()), no un
        // desactivado: solo Administrador, confirmando su contraseña.
        $this->requireAdminConPassword();

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

    // ── Documentos descargables ─────────────────────────────────────────

    public function documentoGuardar(): void
    {
        $this->requirePermiso('documentos.crear');

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

        $titulo = $this->postStr('titulo');
        if ($titulo === '') {
            Session::flash('error', 'El documento necesita un título.');
            $this->redirect(url_admin('pastorales', 'editar', ['id' => $pastoralId]));
            return;
        }

        try {
            $archivo = Upload::documento('archivo', 'pastorales', 'documento');
        } catch (RuntimeException $e) {
            Session::flash('error', 'No se pudo subir el documento: ' . $e->getMessage());
            $this->redirect(url_admin('pastorales', 'editar', ['id' => $pastoralId]));
            return;
        }
        if (!$archivo) {
            Session::flash('error', 'Elige un archivo PDF para subir.');
            $this->redirect(url_admin('pastorales', 'editar', ['id' => $pastoralId]));
            return;
        }

        $id = $this->modelo->crearDocumento([
            'pastoral_id' => $pastoralId,
            'titulo'      => $titulo,
            'archivo'     => $archivo,
            'orden'       => $this->postInt('orden'),
            'activo'      => 1,
        ], (int) Auth::usuario()['id']);
        $this->auditoria('crear', 'pastoral_documentos', $id, $titulo);
        Session::flash('success', 'Documento agregado.');

        $this->redirect(url_admin('pastorales', 'editar', ['id' => $pastoralId]));
    }

    public function documentoEliminar(): void
    {
        $this->requirePermiso('documentos.eliminar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('pastorales'));
            return;
        }
        $this->validarCsrf();

        $id        = $this->postInt('id');
        $documento = $this->modelo->documentoPorId($id);
        if (!$documento) {
            $this->redirect(url_admin('pastorales'));
            return;
        }
        $this->requireAlcancePastoral((int) $documento['pastoral_id']);

        Upload::borrar($documento['archivo']);
        $this->modelo->eliminarDocumento($id);
        $this->auditoria('eliminar', 'pastoral_documentos', $id, $documento['titulo']);
        Session::flash('success', 'Documento eliminado.');

        $this->redirect(url_admin('pastorales', 'editar', ['id' => $documento['pastoral_id']]));
    }

    private function scriptEditor(): string
    {
        return '<script src="' . e(url_activo('assets/js/editor.js')) . '?v=' . e(APP_VERSION) . '"></script>';
    }
}
