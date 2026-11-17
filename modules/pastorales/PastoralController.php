<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/modules/pastorales/PastoralModel.php';
require_once BASE_PATH . '/modules/centros/CentroModel.php';
require_once BASE_PATH . '/modules/personas/PersonaModel.php';
require_once BASE_PATH . '/core/EnlacePersonalRespuesta.php';
require_once BASE_PATH . '/core/CambiosTurno.php';

class PastoralController extends Controller
{
    use EnlacePersonalRespuesta;

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

        $this->render('pastorales/form', [
            'titulo'            => $pastoral['nombre'],
            'pastoral'          => $pastoral,
            'centros'           => (new CentroModel())->activos(),
            'personas'          => (new PersonaModel())->paraSelector(),
            'padresDisponibles' => $this->modelo->candidatosPadre((int) $pastoral['id']),
            'tieneHijos'        => $this->modelo->tieneHijos((int) $pastoral['id']),
            'actividades'       => $this->modelo->actividades((int) $pastoral['id']),
            'documentos'        => $this->modelo->documentos((int) $pastoral['id']),
            'scriptExtra'       => $this->scriptEditor(),
        ]);
    }

    /**
     * Mapa de pastorales en hoja aparte: página independiente sin el layout
     * del panel, igual que PersonaController::directorioImprimir() y
     * MescController::turnosImprimir(). Un árbol indentado —Comisión y,
     * debajo, sus dimensiones— de solo texto, no de cajitas conectadas por
     * líneas como el organigrama de personas: con Comisiones de hasta seis
     * hijas (Litúrgica), un árbol horizontal se haría demasiado ancho para
     * imprimirse en una hoja.
     *
     * Mismo alcance que index(): quien no tiene alcance global solo ve las
     * pastorales que administra.
     */
    public function mapaImprimir(): void
    {
        $this->requirePermiso('pastorales.ver');

        $agrupado = $this->modelo->agrupadoVisible(Auth::tieneAlcanceGlobal() ? null : Auth::pastoralesPermitidas());

        $this->renderSinLayout('pastorales/mapa_imprimir', [
            'comisiones' => $agrupado['comisiones'],
            'sueltas'    => $agrupado['sueltas'],
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

        // Quiénes están marcados en esta pastoral (persona_pastorales). Se
        // muestra a quien ya administra la pastoral —requireAlcancePastoral()
        // acaba de comprobarlo—, y no se le exige `personas.ver`: saber quién
        // está en tu propia pastoral es parte de coordinarla, y esos nombres y
        // cargos ya salen en el directorio público.
        //
        // Lo que sí exige permiso es el botón que lleva a la ficha, donde están
        // el domicilio, la fecha de nacimiento y las demás pastorales de esa
        // persona; y, aparte, el botón de WhatsApp, que pide
        // `personas.contactar`: poder escribirle a la gente de la pastoral es
        // parte de coordinarla, pero no es lo mismo que abrir su expediente, y
        // por eso son dos llaves y no una. El número no se imprime en pantalla,
        // viaja en el enlace. Ver el comentario de PERMISOS_COORDINACION en
        // config/app.php, que a propósito sigue sin incluir personas.ver.
        $personas = (new PersonaModel())->todas([(int) $pastoral['id']]);

        $this->render('pastorales/panel', [
            'titulo'        => $pastoral['nombre'],
            'pastoral'      => $pastoral,
            'comisionPadre' => $comisionPadre,
            'moduloDedicado' => MODULO_POR_PASTORAL[$pastoral['slug']] ?? null,
            'documentos'    => $this->modelo->documentos((int) $pastoral['id']),
            'personas'      => $personas,
            // Una Comisión suele tener a su gente marcada en las pastorales que
            // agrupa, no en ella misma: sin esto, su lista vacía parecería un
            // error en vez de lo normal.
            'agrupaOtras'   => $this->modelo->tieneHijos((int) $pastoral['id']),
            'puedeEditar'   => Auth::tienePermiso('pastorales.editar'),
            'puedeEditarPersonas' => Auth::tienePermiso('personas.editar'),
            // El enlace personal de cada integrante (core/EnlacePersonal.php):
            // quién puede mandarlo y cómo va el de cada quien.
            'puedeEnlace'   => Auth::tienePermiso('personas.enlace'),
            'enlaces'       => (new EnlacePersonal())->estados(array_column($personas, 'id')),
            'enlaceExplica' => EnlacePersonal::explicacion(...$this->comoLoVe($pastoral)),
        ]);
    }

    /**
     * Con qué nombre y si con turnos se le explica a alguien de esta pastoral
     * lo que ve con su enlace: en MESC y Proclamadores también sus turnos.
     *
     * @return array{0: string, 1: bool}
     */
    private function comoLoVe(array $pastoral): array
    {
        $turnos = CambiosTurno::moduloDePastoral($pastoral['slug']);
        return $turnos ? [$turnos['titulo'], true] : [$pastoral['nombre'], false];
    }

    /**
     * El enlace personal de alguien de esta pastoral, para el cuadro de la
     * lista «Quiénes están en esta pastoral» (POST, responde JSON): lo crea si
     * no existía, o genera uno nuevo con `regenerar`. Con él, esa persona ve
     * los avisos internos, documentos y eventos de sus pastorales, sin cuenta.
     * Lo puede mandar quien administra esta pastoral (personas.enlace y su
     * alcance), y solo a quien está marcado en ella.
     */
    public function enlacePersonal(): void
    {
        $this->requirePermiso('personas.enlace');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Este enlace se pide desde la lista.'], 405);
        }
        $this->validarCsrf();

        $pastoral = $this->modelo->porId($this->getInt('pastoral_id'));
        if (!$pastoral) {
            $this->json(['error' => 'No encontramos esa pastoral.'], 404);
        }
        $this->requireAlcancePastoral((int) $pastoral['id']);

        $personas = new PersonaModel();
        $persona  = $personas->porId($this->postInt('id'));
        if (!$persona || !in_array((int) $pastoral['id'], $personas->pastoralesDe((int) $persona['id']), true)) {
            $this->json(['error' => 'Esa persona ya no está marcada en esta pastoral.'], 404);
        }
        if (!$persona['activo']) {
            $this->json(['error' => "{$persona['nombre']} está inactiva: su enlace no le mostraría nada."], 422);
        }

        $this->responderEnlacePersonal((int) $persona['id'], $persona['nombre'], $persona['telefono'],
            EnlacePersonal::queVe(...$this->comoLoVe($pastoral)));
    }

    /*
     * PENDIENTE — comentado, no borrado: el botón que llamaba a esta acción
     * (uno por pastoral, en pastorales/views/lista.php) se retiró de la
     * interfaz porque ese mecanismo no era el que se buscaba para publicar
     * una pastoral en el menú. Queda aquí, junto con
     * PastoralModel::activarEnMenu() y Controller::requireAdminConPassword(),
     * a la espera de decidir el flujo correcto.
     *
     * Publicarla en el menú era deliberado y separado de guardar(): solo
     * Administrador, confirmando su contraseña.
     *
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
    */

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
        // Este teléfono se publica en el sitio, así que un dedazo lo ve
        // cualquiera que entre a la ficha de la pastoral.
        if (!telefono_valido($this->postStr('contacto_telefono'))) {
            Session::flash('error', 'El teléfono de contacto no tiene un formato válido. ' . TELEFONO_FORMATO);
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

        // Quién la coordina: las personas que hagan falta, elegidas del equipo
        // pastoral y en el orden en que se eligieron, más el nombre libre para
        // quien todavía no tiene ficha, que se suma al final. Ya no es "una
        // persona o una pareja": en Raíces coordinan Betesaida y Dante, que no
        // son matrimonio. Un matrimonio que coordina se elige como dos personas;
        // que lo son se sabe por sus fichas (PastoralModel::conCoordinacion()).
        $coordinadores = $this->coordinadoresElegidos();

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

        // El correo es de la pastoral, no de quien la coordina: se escribe aquí
        // y no se hereda de ninguna cuenta. Antes se copiaba del correo de
        // acceso del responsable —así se corrigió el caso de MESC, donde las dos
        // direcciones llevaban una letra distinta—, y eso resolvía la
        // discrepancia a costa de publicar en el sitio el correo personal de una
        // persona y de dejar la pastoral sin dirección propia: al cambiar de
        // coordinadora cambiaba también la dirección a la que la parroquia
        // llevaba años escribiendo. Ahora cada pastoral tiene la suya, que
        // sobrevive a los relevos; quien coordine puede poner la de su cuenta si
        // así lo quiere, pero es una decisión suya y explícita.
        $contactoEmail = $this->postStr('contacto_email') ?: null;

        $datos = [
            'centro_id'          => $this->postIntONull('centro_id'),
            'pastoral_padre_id'  => $pastoralPadreId,
            'slug'               => $slug,
            'nombre'             => $nombre,
            'descripcion_corta'  => $this->postStr('descripcion_corta') ?: null,
            'descripcion'        => SanitizadorHtml::limpiar($this->postHtml('descripcion')) ?: null,
            'imagen'             => $imagen,
            'icono'              => $this->postStr('icono') ?: 'bi-people',
            'coordinadores'           => $coordinadores,
            'responsable_nombre'      => $this->postStr('responsable_nombre') ?: null,
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

    /**
     * Lee los renglones de "Quién la coordina": ids de persona, en el orden del
     * formulario, sin vacíos ni repetidos (alguien elegido dos veces se queda
     * en su primer lugar). Un id que no es una ficha se descarta en silencio,
     * como el resto de selects opcionales: es defensa ante un POST manipulado,
     * no un caso de uso.
     *
     * @return int[]
     */
    private function coordinadoresElegidos(): array
    {
        $ids = array_values(array_unique(array_map(
            'intval',
            array_filter((array) ($_POST['coordinadores'] ?? []), static fn ($v): bool => is_string($v) && ctype_digit($v))
        )));
        $personas = new PersonaModel();
        return array_values(array_filter($ids, static fn (int $id): bool => $id > 0 && $personas->porId($id) !== null));
    }

    private function scriptEditor(): string
    {
        return '<script src="' . e(url_activo_versionado('assets/js/editor.js')) . '"></script>';
    }
}
