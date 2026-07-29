<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/modules/mesc/MescModel.php';
require_once BASE_PATH . '/modules/pastorales/PastoralModel.php';

class MescController extends Controller
{
    private MescModel $modelo;

    public function __construct()
    {
        $this->modelo = new MescModel();
    }

    public function index(): void
    {
        $this->requirePermiso('mesc.ver');

        $filtro = in_array($this->getStr('filtro'), ['activas', 'inactivas'], true)
            ? $this->getStr('filtro') : 'activas';

        $this->render('mesc/lista', [
            'titulo'  => 'MESC — Visitas a enfermos',
            'listado' => $this->modelo->listar(max(1, $this->getInt('pagina', 1)), $filtro, $this->filtroPastoralSql()),
            'filtro'  => $filtro,
        ]);
    }

    public function nueva(): void
    {
        $this->requirePermiso('mesc.crear');

        $this->render('mesc/form', [
            'titulo'      => 'Nueva visita',
            'visita'      => null,
            'pastorales'  => $this->pastoralesDisponibles(),
            'scriptExtra' => $this->scriptMapa(),
        ]);
    }

    public function editar(): void
    {
        $this->requirePermiso('mesc.editar');

        $visita = $this->modelo->porId($this->getInt('id'));
        if (!$visita) {
            Session::flash('error', 'No encontramos esa visita.');
            $this->redirect(url_admin('mesc'));
            return;
        }
        $this->requireAlcancePastoral((int) $visita['pastoral_id']);

        $this->render('mesc/form', [
            'titulo'      => $visita['nombre_enfermo'],
            'visita'      => $visita,
            'pastorales'  => $this->pastoralesDisponibles(),
            'scriptExtra' => $this->scriptMapa(),
        ]);
    }

    public function guardar(): void
    {
        $id       = $this->postInt('id');
        $existente = $id ? $this->modelo->porId($id) : null;

        $this->requirePermiso($existente ? 'mesc.editar' : 'mesc.crear');
        if ($existente) {
            $this->requireAlcancePastoral((int) $existente['pastoral_id']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('mesc'));
            return;
        }
        $this->validarCsrf();

        $nombreEnfermo = $this->postStr('nombre_enfermo');
        $direccion     = $this->postStr('direccion');

        if ($nombreEnfermo === '' || $direccion === '') {
            Session::flash('error', 'La visita necesita el nombre del enfermo y una dirección.');
            $this->redirect($id ? url_admin('mesc', 'editar', ['id' => $id]) : url_admin('mesc', 'nueva'));
            return;
        }

        try {
            $pastoralId = $this->pastoralIdMescValidado();
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect($id ? url_admin('mesc', 'editar', ['id' => $id]) : url_admin('mesc', 'nueva'));
            return;
        }

        $lat = $this->postStr('latitud');
        $lng = $this->postStr('longitud');

        $datos = [
            'pastoral_id'            => $pastoralId,
            'nombre_enfermo'         => $nombreEnfermo,
            'direccion'              => $direccion,
            'latitud'                => $lat !== '' ? (float) $lat : null,
            'longitud'               => $lng !== '' ? (float) $lng : null,
            'telefono'               => $this->postStr('telefono') ?: null,
            'solicitante_nombre'     => $this->postStr('solicitante_nombre') ?: null,
            'solicitante_parentesco' => $this->postStr('solicitante_parentesco') ?: null,
            'solicitante_telefono'   => $this->postStr('solicitante_telefono') ?: null,
            'notas'                  => $this->postStr('notas') ?: null,
            'activo'                 => $this->postBool('activo'),
        ];

        if ($existente) {
            $this->modelo->actualizar($id, $datos);
            $this->auditoria('editar', 'mesc_visitas', $id, $nombreEnfermo);
            Session::flash('success', 'Visita actualizada.');
        } else {
            $id = $this->modelo->crear($datos, (int) Auth::usuario()['id']);
            $this->auditoria('crear', 'mesc_visitas', $id, $nombreEnfermo);
            Session::flash('success', 'Visita registrada.');
        }

        $this->redirect(url_admin('mesc'));
    }

    public function eliminar(): void
    {
        $this->requirePermiso('mesc.eliminar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('mesc'));
            return;
        }
        $this->validarCsrf();

        $id     = $this->postInt('id');
        $visita = $this->modelo->porId($id);
        if ($visita) {
            $this->requireAlcancePastoral((int) $visita['pastoral_id']);
            $this->modelo->eliminar($id);
            $this->auditoria('eliminar', 'mesc_visitas', $id, $visita['nombre_enfermo']);
            Session::flash('success', 'Visita eliminada.');
        }

        $this->redirect(url_admin('mesc'));
    }

    // ── Rutas ────────────────────────────────────────────────────────────

    public function rutas(): void
    {
        $this->requirePermiso('mesc.ver');

        $this->render('mesc/rutas_lista', [
            'titulo' => 'Rutas de visita',
            'rutas'  => $this->modelo->rutasDe($this->filtroPastoralSql()),
        ]);
    }

    public function rutaNueva(): void
    {
        $this->requirePermiso('mesc.crear');

        $this->render('mesc/ruta_nueva', [
            'titulo'   => 'Generar ruta de visitas',
            'visitas'  => $this->modelo->activasPara($this->filtroPastoralSql()),
        ]);
    }

    public function rutaGenerar(): void
    {
        $this->requirePermiso('mesc.crear');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('mesc', 'rutas'));
            return;
        }
        $this->validarCsrf();

        $idsElegidos = array_values(array_unique(array_map('intval',
            array_filter((array) ($_POST['visitas'] ?? []), 'is_numeric')
        )));
        if (!$idsElegidos) {
            Session::flash('error', 'Elige al menos una visita para generar la ruta.');
            $this->redirect(url_admin('mesc', 'ruta_nueva'));
            return;
        }

        // Revalidado contra el alcance real: nunca se confía en qué IDs venían marcados en el POST.
        $disponibles = $this->modelo->activasPara($this->filtroPastoralSql());
        $porId       = [];
        foreach ($disponibles as $visita) {
            $porId[(int) $visita['id']] = $visita;
        }
        $visitas = [];
        foreach ($idsElegidos as $id) {
            if (isset($porId[$id])) {
                $visitas[] = $porId[$id];
            }
        }
        if (!$visitas) {
            Session::flash('error', 'Esas visitas ya no están disponibles.');
            $this->redirect(url_admin('mesc', 'ruta_nueva'));
            return;
        }

        $pastoralId = (int) $visitas[0]['pastoral_id'];
        foreach ($visitas as $visita) {
            $this->requireAlcancePastoral((int) $visita['pastoral_id']);
        }

        $origen = $this->origenParroquia();
        $ordenadas = $this->modelo->ordenSugerido($visitas, $origen);
        $idsEnOrden = array_map(static fn (array $v): int => (int) $v['id'], $ordenadas);

        $nombre = $this->postStr('nombre') ?: ('Ruta del ' . fecha_larga(date('Y-m-d')));
        $rutaId = $this->modelo->crearRuta($pastoralId, $nombre, $idsEnOrden, (int) Auth::usuario()['id']);
        $this->auditoria('crear', 'mesc_rutas', $rutaId, $nombre);

        Session::flash('success', 'Ruta generada con ' . count($idsEnOrden) . ' visita(s). Puedes reordenarla antes de exportarla.');
        $this->redirect(url_admin('mesc', 'ruta_editar', ['id' => $rutaId]));
    }

    public function rutaEditar(): void
    {
        $this->requirePermiso('mesc.ver');

        $ruta = $this->modelo->rutaPorId($this->getInt('id'));
        if (!$ruta) {
            Session::flash('error', 'No encontramos esa ruta.');
            $this->redirect(url_admin('mesc', 'rutas'));
            return;
        }
        $this->requireAlcancePastoral((int) $ruta['pastoral_id']);

        $this->render('mesc/ruta_editar', [
            'titulo'  => $ruta['nombre'],
            'ruta'    => $ruta,
            'visitas' => $this->modelo->visitasDeRuta((int) $ruta['id']),
        ]);
    }

    public function rutaReordenar(): void
    {
        $this->requirePermiso('mesc.crear');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('mesc', 'rutas'));
            return;
        }
        $this->validarCsrf();

        $rutaId = $this->postInt('ruta_id');
        $ruta   = $this->modelo->rutaPorId($rutaId);
        if (!$ruta) {
            $this->redirect(url_admin('mesc', 'rutas'));
            return;
        }
        $this->requireAlcancePastoral((int) $ruta['pastoral_id']);

        $ordenPorVisitaId = [];
        foreach ((array) ($_POST['orden'] ?? []) as $visitaId => $orden) {
            if (is_numeric($visitaId) && is_numeric($orden)) {
                $ordenPorVisitaId[(int) $visitaId] = (int) $orden;
            }
        }
        $this->modelo->reordenarRuta($rutaId, $ordenPorVisitaId);
        Session::flash('success', 'Orden actualizado.');

        $this->redirect(url_admin('mesc', 'ruta_editar', ['id' => $rutaId]));
    }

    public function rutaExportar(): void
    {
        $this->requirePermiso('mesc.ver');

        $ruta = $this->modelo->rutaPorId($this->getInt('id'));
        if (!$ruta) {
            $this->redirect(url_admin('mesc', 'rutas'));
            return;
        }
        $this->requireAlcancePastoral((int) $ruta['pastoral_id']);

        $visitas = $this->modelo->visitasDeRuta((int) $ruta['id']);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . Slug::generar($ruta['nombre']) . '.csv"');
        echo "\xEF\xBB\xBF"; // BOM: para que Excel abra el UTF-8 sin destrozar los acentos.

        $salida = fopen('php://output', 'w');
        fputcsv($salida, ['Orden', 'Enfermo', 'Dirección', 'Teléfono', 'Solicitante', 'Parentesco', 'Tel. solicitante', 'Notas']);
        foreach ($visitas as $i => $visita) {
            fputcsv($salida, [
                $i + 1,
                $visita['nombre_enfermo'],
                $visita['direccion'],
                $visita['telefono'],
                $visita['solicitante_nombre'],
                $visita['solicitante_parentesco'],
                $visita['solicitante_telefono'],
                $visita['notas'],
            ]);
        }
        fclose($salida);
        exit;
    }

    public function rutaEliminar(): void
    {
        $this->requirePermiso('mesc.eliminar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('mesc', 'rutas'));
            return;
        }
        $this->validarCsrf();

        $id   = $this->postInt('id');
        $ruta = $this->modelo->rutaPorId($id);
        if ($ruta) {
            $this->requireAlcancePastoral((int) $ruta['pastoral_id']);
            $this->modelo->eliminarRuta($id);
            $this->auditoria('eliminar', 'mesc_rutas', $id, $ruta['nombre']);
            Session::flash('success', 'Ruta eliminada.');
        }

        $this->redirect(url_admin('mesc', 'rutas'));
    }

    // ── Privados ─────────────────────────────────────────────────────────

    /** A diferencia de avisos/eventos, aquí la pastoral SIEMPRE es obligatoria: nunca "general". */
    private function pastoralIdMescValidado(): int
    {
        $enviado = $this->postIntONull('pastoral_id');
        if ($enviado === null) {
            throw new RuntimeException('Selecciona la pastoral de MESC.');
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

    /** Coordenadas de la parroquia, como punto de partida de la ruta. Null si no están configuradas. */
    private function origenParroquia(): ?array
    {
        $lat = Config::get('latitud', '');
        $lng = Config::get('longitud', '');
        return ($lat !== '' && $lng !== '') ? ['lat' => (float) $lat, 'lng' => (float) $lng] : null;
    }

    /** Leaflet + OpenStreetMap por CDN, sin llave de API: ver el comentario de CSP en .htaccess. */
    private function scriptMapa(): string
    {
        return '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">'
             . '<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>'
             . '<script src="' . e(url_activo('assets/js/mapa_mesc.js')) . '?v=' . e(APP_VERSION) . '"></script>';
    }
}
