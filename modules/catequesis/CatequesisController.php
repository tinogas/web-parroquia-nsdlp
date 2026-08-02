<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/modules/catequesis/CatequesisModel.php';
require_once BASE_PATH . '/modules/personas/PersonaModel.php';

/**
 * CatequesisController — Igual que MESC y Lector, este módulo es exclusivo
 * de la pastoral de Catecismo: ninguna acción muestra ni acepta otra
 * pastoral. pastoralIdOFallar() resuelve esa única pastoral y corta el
 * flujo con un mensaje claro si todavía no existe (instalación nueva, antes
 * de crear la pastoral "Catecismo" desde el módulo de Pastorales).
 */
class CatequesisController extends Controller
{
    private CatequesisModel $modelo;

    public function __construct()
    {
        $this->modelo = new CatequesisModel();
    }

    // ── Catequistas (pantalla principal del módulo) ─────────────────────

    public function index(): void
    {
        $this->requirePermiso('catequesis.ver');

        $pastoralId = $this->pastoralIdOFallar();
        if ($pastoralId === null) {
            return;
        }

        $this->render('catequesis/catequistas_lista', [
            'titulo'      => 'Catequesis — Catequistas',
            'pastoralId'  => $pastoralId,
            'catequistas' => $this->modelo->catequistas($pastoralId),
            'personas'    => (new PersonaModel())->paraSelector(),
        ]);
    }

    public function catequistaGuardar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('catequesis'));
            return;
        }
        $this->validarCsrf();

        $id        = $this->postInt('id');
        $existente = $id ? $this->modelo->catequistaPorId($id) : null;
        $this->requirePermiso($existente ? 'catequesis.editar' : 'catequesis.crear');

        $pastoralId = $existente ? (int) $existente['pastoral_id'] : $this->pastoralIdOFallar();
        if ($pastoralId === null) {
            return;
        }
        $this->requireAlcancePastoral($pastoralId);

        // El catequista se elige del equipo pastoral; si todavía no está ahí,
        // los campos libres de abajo son el respaldo. Mismo patrón que el
        // responsable de una pastoral y que los ministros de MESC.
        $personaId = $this->postIntONull('persona_id');
        $persona   = $personaId ? (new PersonaModel())->porId($personaId) : null;
        if ($persona) {
            $nombre   = $persona['nombre'];
            $telefono = $persona['telefono'];
            $email    = $persona['email'];
        } else {
            $personaId = null;
            $nombre    = $this->postStr('nombre');
            $telefono  = $this->postStr('telefono') ?: null;
            $email     = $this->postStr('email') ?: null;
        }

        if ($nombre === '') {
            Session::flash('error', 'El catequista necesita un nombre, o elige a alguien del equipo pastoral.');
            $this->redirect(url_admin('catequesis'));
            return;
        }

        $datos = [
            'pastoral_id' => $pastoralId,
            'persona_id'  => $personaId,
            'nombre'      => $nombre,
            'telefono'    => $telefono,
            'email'       => $email,
            'orden'       => $this->postInt('orden'),
            'activo'      => $this->postBool('activo'),
        ];

        if ($existente) {
            $this->modelo->actualizarCatequista($id, $datos);
            $this->auditoria('editar', 'catequesis_catequistas', $id, $nombre);
            Session::flash('success', 'Catequista actualizado.');
        } else {
            $id = $this->modelo->crearCatequista($datos);
            $this->auditoria('crear', 'catequesis_catequistas', $id, $nombre);
            Session::flash('success', 'Catequista agregado.');
        }

        $this->redirect(url_admin('catequesis'));
    }

    public function catequistaEliminar(): void
    {
        $this->requirePermiso('catequesis.eliminar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('catequesis'));
            return;
        }
        $this->validarCsrf();

        $id         = $this->postInt('id');
        $catequista = $this->modelo->catequistaPorId($id);
        if ($catequista) {
            $this->requireAlcancePastoral((int) $catequista['pastoral_id']);
            $this->modelo->eliminarCatequista($id);
            $this->auditoria('eliminar', 'catequesis_catequistas', $id, $catequista['nombre']);
            Session::flash('success', 'Catequista eliminado.');
        }

        $this->redirect(url_admin('catequesis'));
    }

    // ── Periodos ─────────────────────────────────────────────────────────

    public function periodos(): void
    {
        $this->requirePermiso('catequesis.ver');

        $pastoralId = $this->pastoralIdOFallar();
        if ($pastoralId === null) {
            return;
        }

        $this->render('catequesis/periodos_lista', [
            'titulo'   => 'Catequesis — Periodos',
            'periodos' => $this->modelo->periodos($pastoralId),
        ]);
    }

    public function periodoVer(): void
    {
        $this->requirePermiso('catequesis.ver');

        $periodo = $this->modelo->periodoPorId($this->getInt('id'));
        if (!$periodo) {
            Session::flash('error', 'No encontramos ese periodo.');
            $this->redirect(url_admin('catequesis', 'periodos'));
            return;
        }
        $this->requireAlcancePastoral((int) $periodo['pastoral_id']);

        $asignados    = $this->modelo->catequistasDePeriodo((int) $periodo['id']);
        $idsAsignados = $this->modelo->catequistaIdsDePeriodo((int) $periodo['id']);
        $disponibles  = array_values(array_filter(
            $this->modelo->catequistasActivos((int) $periodo['pastoral_id']),
            static fn (array $c): bool => !in_array((int) $c['id'], $idsAsignados, true)
        ));

        $this->render('catequesis/periodo_ver', [
            'titulo'      => $periodo['nombre'],
            'periodo'     => $periodo,
            'asignados'   => $asignados,
            'disponibles' => $disponibles,
        ]);
    }

    public function periodoGuardar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('catequesis', 'periodos'));
            return;
        }
        $this->validarCsrf();

        $id        = $this->postInt('id');
        $existente = $id ? $this->modelo->periodoPorId($id) : null;
        $this->requirePermiso($existente ? 'catequesis.editar' : 'catequesis.crear');

        $pastoralId = $existente ? (int) $existente['pastoral_id'] : $this->pastoralIdOFallar();
        if ($pastoralId === null) {
            return;
        }
        $this->requireAlcancePastoral($pastoralId);

        $nombre      = $this->postStr('nombre');
        $fechaInicio = $this->postStr('fecha_inicio');
        $fechaFin    = $this->postStr('fecha_fin');

        if ($nombre === '' || $fechaInicio === '' || $fechaFin === '') {
            Session::flash('error', 'El periodo necesita nombre, fecha de inicio y fecha de término.');
            $this->redirect(url_admin('catequesis', 'periodos'));
            return;
        }
        if ($fechaFin < $fechaInicio) {
            Session::flash('error', 'La fecha de término no puede ser anterior a la de inicio.');
            $this->redirect(url_admin('catequesis', 'periodos'));
            return;
        }

        $datos = [
            'pastoral_id'  => $pastoralId,
            'nombre'       => $nombre,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin'    => $fechaFin,
            'activo'       => $this->postBool('activo'),
        ];

        if ($existente) {
            $this->modelo->actualizarPeriodo($id, $datos);
            $this->auditoria('editar', 'catequesis_periodos', $id, $nombre);
            Session::flash('success', 'Periodo actualizado.');
        } else {
            $id = $this->modelo->crearPeriodo($datos);
            $this->auditoria('crear', 'catequesis_periodos', $id, $nombre);
            Session::flash('success', 'Periodo agregado.');
        }

        $this->redirect(url_admin('catequesis', 'periodos'));
    }

    public function periodoEliminar(): void
    {
        $this->requirePermiso('catequesis.eliminar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('catequesis', 'periodos'));
            return;
        }
        $this->validarCsrf();

        $id      = $this->postInt('id');
        $periodo = $this->modelo->periodoPorId($id);
        if ($periodo) {
            $this->requireAlcancePastoral((int) $periodo['pastoral_id']);
            $this->modelo->eliminarPeriodo($id);
            $this->auditoria('eliminar', 'catequesis_periodos', $id, $periodo['nombre']);
            Session::flash('success', 'Periodo eliminado.');
        }

        $this->redirect(url_admin('catequesis', 'periodos'));
    }

    /** Agrega (o cambia el grado de) un catequista dentro de un periodo. */
    public function periodoAsignar(): void
    {
        $this->requirePermiso('catequesis.editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('catequesis', 'periodos'));
            return;
        }
        $this->validarCsrf();

        $periodoId = $this->postInt('periodo_id');
        $periodo   = $this->modelo->periodoPorId($periodoId);
        if (!$periodo) {
            $this->redirect(url_admin('catequesis', 'periodos'));
            return;
        }
        $this->requireAlcancePastoral((int) $periodo['pastoral_id']);

        $catequistaId = $this->postInt('catequista_id');
        $grado        = $this->postStr('grado');
        $catequista   = $this->modelo->catequistaPorId($catequistaId);

        if (!$catequista || (int) $catequista['pastoral_id'] !== (int) $periodo['pastoral_id']
            || !isset(CatequesisModel::GRADOS[$grado])
        ) {
            Session::flash('error', 'Elige un catequista y un grado válidos.');
            $this->redirect(url_admin('catequesis', 'periodo_ver', ['id' => $periodoId]));
            return;
        }

        $this->modelo->asignarCatequista($periodoId, $catequistaId, $grado);
        $this->auditoria('editar', 'catequesis_periodo_catequistas', $periodoId, $catequista['nombre'] . ' — ' . CatequesisModel::GRADOS[$grado]);
        Session::flash('success', 'Catequista asignado al periodo.');

        $this->redirect(url_admin('catequesis', 'periodo_ver', ['id' => $periodoId]));
    }

    public function periodoDesasignar(): void
    {
        $this->requirePermiso('catequesis.editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('catequesis', 'periodos'));
            return;
        }
        $this->validarCsrf();

        $periodoId = $this->postInt('periodo_id');
        $periodo   = $this->modelo->periodoPorId($periodoId);
        if ($periodo) {
            $this->requireAlcancePastoral((int) $periodo['pastoral_id']);
            $this->modelo->quitarCatequistaDePeriodo($periodoId, $this->postInt('catequista_id'));
            Session::flash('success', 'Catequista quitado del periodo.');
        }

        $this->redirect(url_admin('catequesis', 'periodo_ver', ['id' => $periodoId]));
    }

    // ── Actividades (tablero/calendario) ────────────────────────────────

    public function actividades(): void
    {
        $this->requirePermiso('catequesis.ver');

        $pastoralId = $this->pastoralIdOFallar();
        if ($pastoralId === null) {
            return;
        }

        $this->render('catequesis/actividades_lista', [
            'titulo'      => 'Catequesis — Actividades',
            'pastoralId'  => $pastoralId,
            'actividades' => $this->modelo->actividades($pastoralId),
        ]);
    }

    public function actividadGuardar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('catequesis', 'actividades'));
            return;
        }
        $this->validarCsrf();

        $id        = $this->postInt('id');
        $existente = $id ? $this->modelo->actividadPorId($id) : null;
        $this->requirePermiso($existente ? 'catequesis.editar' : 'catequesis.crear');

        $pastoralId = $existente ? (int) $existente['pastoral_id'] : $this->pastoralIdOFallar();
        if ($pastoralId === null) {
            return;
        }
        $this->requireAlcancePastoral($pastoralId);

        $titulo      = $this->postStr('titulo');
        $fechaInicio = $this->postStr('fecha_inicio');
        $fechaFin    = $this->postStr('fecha_fin') ?: null;

        if ($titulo === '' || $fechaInicio === '') {
            Session::flash('error', 'La actividad necesita título y fecha de inicio.');
            $this->redirect(url_admin('catequesis', 'actividades'));
            return;
        }
        if ($fechaFin !== null && $fechaFin < $fechaInicio) {
            Session::flash('error', 'La fecha de término no puede ser anterior a la de inicio.');
            $this->redirect(url_admin('catequesis', 'actividades'));
            return;
        }

        $datos = [
            'pastoral_id'  => $pastoralId,
            'titulo'       => $titulo,
            'descripcion'  => trim(strip_tags((string) ($_POST['descripcion'] ?? ''))) ?: null,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin'    => $fechaFin,
            'publicado'    => $this->postBool('publicado'),
            'orden'        => $this->postInt('orden'),
        ];

        if ($existente) {
            $this->modelo->actualizarActividad($id, $datos);
            $this->auditoria('editar', 'catequesis_actividades', $id, $titulo);
            Session::flash('success', 'Actividad actualizada.');
        } else {
            $id = $this->modelo->crearActividad($datos, (int) Auth::usuario()['id']);
            $this->auditoria('crear', 'catequesis_actividades', $id, $titulo);
            Session::flash('success', 'Actividad agregada.');
        }

        $this->redirect(url_admin('catequesis', 'actividades'));
    }

    public function actividadEliminar(): void
    {
        $this->requirePermiso('catequesis.eliminar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('catequesis', 'actividades'));
            return;
        }
        $this->validarCsrf();

        $id        = $this->postInt('id');
        $actividad = $this->modelo->actividadPorId($id);
        if ($actividad) {
            $this->requireAlcancePastoral((int) $actividad['pastoral_id']);
            $this->modelo->eliminarActividad($id);
            $this->auditoria('eliminar', 'catequesis_actividades', $id, $actividad['titulo']);
            Session::flash('success', 'Actividad eliminada.');
        }

        $this->redirect(url_admin('catequesis', 'actividades'));
    }

    // ── Documentos ───────────────────────────────────────────────────────

    public function documentos(): void
    {
        $this->requirePermiso('catequesis.ver');

        $pastoralId = $this->pastoralIdOFallar();
        if ($pastoralId === null) {
            return;
        }

        $this->render('catequesis/documentos_lista', [
            'titulo'     => 'Catequesis — Documentos',
            'pastoralId' => $pastoralId,
            'documentos' => $this->modelo->documentos($pastoralId),
        ]);
    }

    public function documentoGuardar(): void
    {
        $this->requirePermiso('catequesis.crear');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('catequesis', 'documentos'));
            return;
        }
        $this->validarCsrf();

        $pastoralId = $this->pastoralIdOFallar();
        if ($pastoralId === null) {
            return;
        }
        $this->requireAlcancePastoral($pastoralId);

        $titulo = $this->postStr('titulo');
        if ($titulo === '') {
            Session::flash('error', 'El documento necesita un título.');
            $this->redirect(url_admin('catequesis', 'documentos'));
            return;
        }

        try {
            $archivo = Upload::documento('archivo', 'catequesis', 'documento');
        } catch (RuntimeException $e) {
            Session::flash('error', 'No se pudo subir el documento: ' . $e->getMessage());
            $this->redirect(url_admin('catequesis', 'documentos'));
            return;
        }
        if (!$archivo) {
            Session::flash('error', 'Elige un archivo PDF para subir.');
            $this->redirect(url_admin('catequesis', 'documentos'));
            return;
        }

        $id = $this->modelo->crearDocumento([
            'pastoral_id' => $pastoralId,
            'titulo'      => $titulo,
            'archivo'     => $archivo,
            'orden'       => $this->postInt('orden'),
            'activo'      => 1,
        ], (int) Auth::usuario()['id']);
        $this->auditoria('crear', 'catequesis_documentos', $id, $titulo);
        Session::flash('success', 'Documento agregado.');

        $this->redirect(url_admin('catequesis', 'documentos'));
    }

    public function documentoEliminar(): void
    {
        $this->requirePermiso('catequesis.eliminar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('catequesis', 'documentos'));
            return;
        }
        $this->validarCsrf();

        $id        = $this->postInt('id');
        $documento = $this->modelo->documentoPorId($id);
        if ($documento) {
            $this->requireAlcancePastoral((int) $documento['pastoral_id']);
            Upload::borrar($documento['archivo']);
            $this->modelo->eliminarDocumento($id);
            $this->auditoria('eliminar', 'catequesis_documentos', $id, $documento['titulo']);
            Session::flash('success', 'Documento eliminado.');
        }

        $this->redirect(url_admin('catequesis', 'documentos'));
    }

    // ── Privados ─────────────────────────────────────────────────────────

    /**
     * Resuelve la pastoral de Catecismo, la única que administra este
     * módulo. Si todavía no existe (instalación nueva) o el usuario no
     * tiene alcance sobre ella, corta el flujo con un mensaje claro en vez
     * de un error a medias más adelante.
     */
    private function pastoralIdOFallar(): ?int
    {
        $pastoralId = $this->modelo->pastoralId();
        if ($pastoralId === null) {
            Session::flash('error', 'Todavía no existe la pastoral "Catecismo". Créala primero desde Pastorales.');
            $this->redirect(url_admin('pastorales'));
            return null;
        }
        if (!Auth::puedeSobrePastoral($pastoralId)) {
            Session::flash('error', 'No administras la pastoral de Catecismo.');
            $this->redirect(url_admin('panel'));
            return null;
        }
        return $pastoralId;
    }
}
