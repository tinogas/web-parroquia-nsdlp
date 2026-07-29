<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/modules/catequesis/CatequesisModel.php';
require_once BASE_PATH . '/modules/pastorales/PastoralModel.php';

class CatequesisController extends Controller
{
    private CatequesisModel $modelo;

    public function __construct()
    {
        $this->modelo = new CatequesisModel();
    }

    // ── Maestros (pantalla principal del módulo) ────────────────────────

    public function index(): void
    {
        $this->requirePermiso('catequesis.ver');

        $pastorales = $this->pastoralesDisponibles();
        $this->render('catequesis/maestros_lista', [
            'titulo'      => 'Catequesis — Maestros',
            'pastorales'  => $pastorales,
            'maestros'    => $this->porTodasLasPastorales($pastorales, [$this->modelo, 'maestros']),
        ]);
    }

    public function maestroGuardar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('catequesis'));
            return;
        }
        $this->validarCsrf();

        $id        = $this->postInt('id');
        $existente = $id ? $this->modelo->maestroPorId($id) : null;
        $this->requirePermiso($existente ? 'catequesis.editar' : 'catequesis.crear');

        if ($existente) {
            $this->requireAlcancePastoral((int) $existente['pastoral_id']);
        }

        $nombre     = $this->postStr('nombre');
        $sacramento = $this->postStr('sacramento');
        if ($nombre === '' || !isset(CatequesisModel::SACRAMENTOS[$sacramento])) {
            Session::flash('error', 'El maestro necesita nombre y sacramento.');
            $this->redirect(url_admin('catequesis'));
            return;
        }

        try {
            $pastoralId = $existente ? (int) $existente['pastoral_id'] : $this->pastoralIdCatequesisValidado();
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect(url_admin('catequesis'));
            return;
        }

        $datos = [
            'pastoral_id' => $pastoralId,
            'nombre'      => $nombre,
            'sacramento'  => $sacramento,
            'telefono'    => $this->postStr('telefono') ?: null,
            'email'       => $this->postStr('email') ?: null,
            'orden'       => $this->postInt('orden'),
            'activo'      => $this->postBool('activo'),
        ];

        if ($existente) {
            $this->modelo->actualizarMaestro($id, $datos);
            $this->auditoria('editar', 'catequesis_maestros', $id, $nombre);
            Session::flash('success', 'Maestro actualizado.');
        } else {
            $id = $this->modelo->crearMaestro($datos);
            $this->auditoria('crear', 'catequesis_maestros', $id, $nombre);
            Session::flash('success', 'Maestro agregado.');
        }

        $this->redirect(url_admin('catequesis'));
    }

    public function maestroEliminar(): void
    {
        $this->requirePermiso('catequesis.eliminar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('catequesis'));
            return;
        }
        $this->validarCsrf();

        $id      = $this->postInt('id');
        $maestro = $this->modelo->maestroPorId($id);
        if ($maestro) {
            $this->requireAlcancePastoral((int) $maestro['pastoral_id']);
            $this->modelo->eliminarMaestro($id);
            $this->auditoria('eliminar', 'catequesis_maestros', $id, $maestro['nombre']);
            Session::flash('success', 'Maestro eliminado.');
        }

        $this->redirect(url_admin('catequesis'));
    }

    // ── Actividades (tablero/calendario) ────────────────────────────────

    public function actividades(): void
    {
        $this->requirePermiso('catequesis.ver');

        $pastorales = $this->pastoralesDisponibles();
        $this->render('catequesis/actividades_lista', [
            'titulo'      => 'Catequesis — Actividades',
            'pastorales'  => $pastorales,
            'actividades' => $this->porTodasLasPastorales($pastorales, [$this->modelo, 'actividades']),
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

        if ($existente) {
            $this->requireAlcancePastoral((int) $existente['pastoral_id']);
        }

        $titulo       = $this->postStr('titulo');
        $fechaInicio  = $this->postStr('fecha_inicio');
        $fechaFin     = $this->postStr('fecha_fin') ?: null;

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

        try {
            $pastoralId = $existente ? (int) $existente['pastoral_id'] : $this->pastoralIdCatequesisValidado();
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
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

        $id         = $this->postInt('id');
        $actividad  = $this->modelo->actividadPorId($id);
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

        $pastorales = $this->pastoralesDisponibles();
        $this->render('catequesis/documentos_lista', [
            'titulo'      => 'Catequesis — Documentos',
            'pastorales'  => $pastorales,
            'documentos'  => $this->porTodasLasPastorales($pastorales, [$this->modelo, 'documentos']),
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

        try {
            $pastoralId = $this->pastoralIdCatequesisValidado();
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect(url_admin('catequesis', 'documentos'));
            return;
        }

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

    /** A diferencia de avisos/eventos, aquí la pastoral SIEMPRE es obligatoria: nunca "general". */
    private function pastoralIdCatequesisValidado(): int
    {
        $enviado = $this->postIntONull('pastoral_id');
        if ($enviado === null) {
            throw new RuntimeException('Selecciona la pastoral de Catequesis.');
        }
        if (Auth::tieneAlcanceGlobal() || in_array($enviado, Auth::pastoralesPermitidas(), true)) {
            return $enviado;
        }
        throw new RuntimeException('Selecciona una de tus pastorales.');
    }

    private function pastoralesDisponibles(): array
    {
        $todas = (new PastoralModel())->paraSelector();
        if (Auth::tieneAlcanceGlobal()) {
            return $todas;
        }
        $propias = Auth::pastoralesPermitidas();
        return array_values(array_filter($todas, static fn (array $p): bool => in_array((int) $p['id'], $propias, true)));
    }

    /** @param array $pastorales de pastoralesDisponibles(): ya acotadas al alcance del usuario */
    private function porTodasLasPastorales(array $pastorales, callable $consulta): array
    {
        $porPastoral = [];
        foreach ($pastorales as $pastoral) {
            $porPastoral[(int) $pastoral['id']] = [
                'pastoral' => $pastoral,
                'filas'    => $consulta((int) $pastoral['id']),
            ];
        }
        return $porPastoral;
    }
}
