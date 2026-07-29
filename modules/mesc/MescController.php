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

    // ── Ministros ────────────────────────────────────────────────────────

    public function ministros(): void
    {
        $this->requirePermiso('mesc.ver');

        $pastorales = $this->pastoralesDisponibles();
        $this->render('mesc/ministros_lista', [
            'titulo'     => 'Ministros MESC',
            'pastorales' => $pastorales,
            'ministros'  => $this->ministrosDeTodasLasPastorales($pastorales),
        ]);
    }

    public function ministroGuardar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('mesc', 'ministros'));
            return;
        }
        $this->validarCsrf();

        $id        = $this->postInt('id');
        $existente = $id ? $this->modelo->ministroPorId($id) : null;
        $this->requirePermiso($existente ? 'mesc.editar' : 'mesc.crear');

        $pastoralId = $existente ? (int) $existente['pastoral_id'] : $this->postInt('pastoral_id');
        $this->requireAlcancePastoral($pastoralId);

        $nombre = $this->postStr('nombre');
        if ($nombre === '') {
            Session::flash('error', 'El ministro necesita un nombre.');
            $this->redirect(url_admin('mesc', 'ministros'));
            return;
        }

        $datos = [
            'pastoral_id' => $pastoralId,
            'nombre'      => $nombre,
            'telefono'    => $this->postStr('telefono') ?: null,
            'activo'      => $this->postBool('activo'),
        ];

        if ($existente) {
            $this->modelo->actualizarMinistro($id, $datos);
            $this->auditoria('editar', 'mesc_ministros', $id, $nombre);
            Session::flash('success', 'Ministro actualizado.');
        } else {
            $id = $this->modelo->crearMinistro($datos);
            $this->auditoria('crear', 'mesc_ministros', $id, $nombre);
            Session::flash('success', 'Ministro agregado.');
        }

        $this->redirect(url_admin('mesc', 'ministros'));
    }

    public function ministroEliminar(): void
    {
        $this->requirePermiso('mesc.eliminar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('mesc', 'ministros'));
            return;
        }
        $this->validarCsrf();

        $id       = $this->postInt('id');
        $ministro = $this->modelo->ministroPorId($id);
        if ($ministro) {
            $this->requireAlcancePastoral((int) $ministro['pastoral_id']);
            $this->modelo->eliminarMinistro($id);
            $this->auditoria('eliminar', 'mesc_ministros', $id, $ministro['nombre']);
            Session::flash('success', 'Ministro eliminado.');
        }

        $this->redirect(url_admin('mesc', 'ministros'));
    }

    // ── Turnos ───────────────────────────────────────────────────────────

    public function turnos(): void
    {
        $this->requirePermiso('mesc.ver');

        $anio = $this->getInt('anio', (int) date('Y'));
        $mes  = $this->getInt('mes', (int) date('n'));
        if ($mes < 1 || $mes > 12) { $mes = (int) date('n'); }
        if ($anio < 2000 || $anio > 2100) { $anio = (int) date('Y'); }

        $mesAnterior   = $mes === 1 ? 12 : $mes - 1;
        $anioAnterior  = $mes === 1 ? $anio - 1 : $anio;
        $mesSiguiente  = $mes === 12 ? 1 : $mes + 1;
        $anioSiguiente = $mes === 12 ? $anio + 1 : $anio;

        $turnosDelMes = $this->modelo->turnosDelMes($anio, $mes, $this->filtroPastoralSql());

        $this->render('mesc/turnos', [
            'titulo'          => 'Calendario de turnos',
            'anio'            => $anio,
            'mes'             => $mes,
            'nombreMes'       => $this->nombreMes($mes) . ' ' . $anio,
            'semanas'         => $this->construirCalendarioTurnos($anio, $mes, $turnosDelMes),
            'urlMesAnterior'  => url_admin('mesc', 'turnos', ['anio' => $anioAnterior, 'mes' => $mesAnterior]),
            'urlMesSiguiente' => url_admin('mesc', 'turnos', ['anio' => $anioSiguiente, 'mes' => $mesSiguiente]),
        ]);
    }

    public function turnoNuevo(): void
    {
        $this->requirePermiso('mesc.crear');

        $pastorales = $this->pastoralesDisponibles();
        $this->render('mesc/turno_form', [
            'titulo'               => 'Nuevo turno',
            'turno'                => null,
            'pastorales'           => $pastorales,
            'ministrosPorPastoral' => $this->ministrosActivosDeTodasLasPastorales($pastorales),
            'asignados'            => [],
            'fechaSugerida'        => $this->getStr('fecha'),
            'colores'              => $this->modelo->coloresLiturgicos(),
        ]);
    }

    public function turnoEditar(): void
    {
        $this->requirePermiso('mesc.editar');

        $turno = $this->modelo->turnoPorId($this->getInt('id'));
        if (!$turno) {
            Session::flash('error', 'No encontramos ese turno.');
            $this->redirect(url_admin('mesc', 'turnos'));
            return;
        }
        $this->requireAlcancePastoral((int) $turno['pastoral_id']);

        $pastorales = $this->pastoralesDisponibles();
        $this->render('mesc/turno_form', [
            'titulo'               => $this->etiquetaTurno($turno),
            'turno'                => $turno,
            'pastorales'           => $pastorales,
            'ministrosPorPastoral' => $this->ministrosActivosDeTodasLasPastorales($pastorales),
            'asignados'            => array_map(
                static fn (array $m): int => (int) $m['id'],
                $this->modelo->ministrosDeTurno((int) $turno['id'])
            ),
            'fechaSugerida'        => '',
            'colores'              => $this->modelo->coloresLiturgicos(),
        ]);
    }

    public function turnoGuardar(): void
    {
        $id        = $this->postInt('id');
        $existente = $id ? $this->modelo->turnoPorId($id) : null;

        $this->requirePermiso($existente ? 'mesc.editar' : 'mesc.crear');
        if ($existente) {
            $this->requireAlcancePastoral((int) $existente['pastoral_id']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('mesc', 'turnos'));
            return;
        }
        $this->validarCsrf();

        $fecha = $this->postStr('fecha');
        if ($fecha === '') {
            Session::flash('error', 'El turno necesita una fecha.');
            $this->redirect($id ? url_admin('mesc', 'turno_editar', ['id' => $id]) : url_admin('mesc', 'turno_nuevo'));
            return;
        }

        try {
            $pastoralId = $existente ? (int) $existente['pastoral_id'] : $this->pastoralIdMescValidado();
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect($id ? url_admin('mesc', 'turno_editar', ['id' => $id]) : url_admin('mesc', 'turno_nuevo'));
            return;
        }

        $ministrosDisponibles = $this->modelo->ministrosActivos($pastoralId);
        $idsValidos           = array_map(static fn (array $m): int => (int) $m['id'], $ministrosDisponibles);
        $ministroIds          = array_values(array_intersect(
            array_map('intval', array_filter((array) ($_POST['ministros'] ?? []), 'is_numeric')),
            $idsValidos
        ));

        $colorId = $this->postIntONull('color_liturgico_id');
        if ($colorId !== null && !$this->modelo->colorLiturgicoPorId($colorId)) {
            $colorId = null;
        }

        $datos = [
            'pastoral_id'        => $pastoralId,
            'fecha'              => $fecha,
            'hora'               => $this->postStr('hora') ?: null,
            'color_liturgico_id' => $colorId,
        ];
        $etiqueta = fecha_larga($fecha) . ($datos['hora'] ? ', ' . hora_corta($datos['hora']) : '');

        if ($existente) {
            $this->modelo->actualizarTurno($id, $datos, $ministroIds);
            $this->auditoria('editar', 'mesc_turnos', $id, $etiqueta);
            Session::flash('success', 'Turno actualizado.');
        } else {
            $id = $this->modelo->crearTurno($datos, $ministroIds, (int) Auth::usuario()['id']);
            $this->auditoria('crear', 'mesc_turnos', $id, $etiqueta);
            Session::flash('success', 'Turno registrado.');
        }

        $this->redirect(url_admin('mesc', 'turnos', ['anio' => (int) substr($fecha, 0, 4), 'mes' => (int) substr($fecha, 5, 2)]));
    }

    public function turnoEliminar(): void
    {
        $this->requirePermiso('mesc.eliminar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('mesc', 'turnos'));
            return;
        }
        $this->validarCsrf();

        $id    = $this->postInt('id');
        $turno = $this->modelo->turnoPorId($id);
        if ($turno) {
            $this->requireAlcancePastoral((int) $turno['pastoral_id']);
            $this->modelo->eliminarTurno($id);
            $this->auditoria('eliminar', 'mesc_turnos', $id, $this->etiquetaTurno($turno));
            Session::flash('success', 'Turno eliminado.');
        }

        $this->redirect(url_admin('mesc', 'turnos'));
    }

    // ── Colores litúrgicos ───────────────────────────────────────────────
    // Catálogo de referencia (issue #3): a diferencia del resto del módulo,
    // no tiene alcance por pastoral —es un catálogo compartido por cualquier
    // pastoral que registre turnos—, así que solo exige el permiso mesc.*.

    public function colores(): void
    {
        $this->requirePermiso('mesc.ver');

        $this->render('mesc/colores_lista', [
            'titulo'  => 'Colores litúrgicos',
            'colores' => $this->modelo->coloresLiturgicos(),
        ]);
    }

    public function colorGuardar(): void
    {
        $id        = $this->postInt('id');
        $this->requirePermiso($id ? 'mesc.editar' : 'mesc.crear');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('mesc', 'colores'));
            return;
        }
        $this->validarCsrf();

        $nombre = $this->postStr('nombre');
        $hex    = $this->postStr('color_hex');
        $significado = $this->postStr('significado');
        if ($nombre === '' || !preg_match('/^#[0-9a-fA-F]{6}$/', $hex) || $significado === '') {
            Session::flash('error', 'El color necesita nombre, un tono válido (#rrggbb) y su significado.');
            $this->redirect(url_admin('mesc', 'colores'));
            return;
        }

        $datos = [
            'nombre'      => $nombre,
            'color_hex'   => $hex,
            'significado' => $significado,
            'orden'       => $this->postInt('orden'),
        ];

        $existente = $id ? $this->modelo->colorLiturgicoPorId($id) : null;
        if ($existente) {
            $this->modelo->actualizarColorLiturgico($id, $datos);
            $this->auditoria('editar', 'mesc_colores_liturgicos', $id, $nombre);
            Session::flash('success', 'Color actualizado.');
        } else {
            $id = $this->modelo->crearColorLiturgico($datos);
            $this->auditoria('crear', 'mesc_colores_liturgicos', $id, $nombre);
            Session::flash('success', 'Color agregado.');
        }

        $this->redirect(url_admin('mesc', 'colores'));
    }

    public function colorEliminar(): void
    {
        $this->requirePermiso('mesc.eliminar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('mesc', 'colores'));
            return;
        }
        $this->validarCsrf();

        $id    = $this->postInt('id');
        $color = $this->modelo->colorLiturgicoPorId($id);
        if ($color) {
            $this->modelo->eliminarColorLiturgico($id);
            $this->auditoria('eliminar', 'mesc_colores_liturgicos', $id, $color['nombre']);
            Session::flash('success', 'Color eliminado. Los turnos que lo usaban quedan sin color asignado.');
        }

        $this->redirect(url_admin('mesc', 'colores'));
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

    /** Identifica un turno por fecha y hora, ya que no tiene un campo de descripción propio. */
    private function etiquetaTurno(array $turno): string
    {
        $etiqueta = fecha_larga($turno['fecha']);
        return $turno['hora'] ? $etiqueta . ', ' . hora_corta($turno['hora']) : $etiqueta;
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

    /** @param array $pastorales de pastoralesDisponibles(): id => nombre, ya acotadas al alcance del usuario */
    private function ministrosDeTodasLasPastorales(array $pastorales): array
    {
        $porPastoral = [];
        foreach ($pastorales as $pastoral) {
            $porPastoral[(int) $pastoral['id']] = [
                'pastoral' => $pastoral,
                'ministros' => $this->modelo->ministros((int) $pastoral['id']),
            ];
        }
        return $porPastoral;
    }

    private function ministrosActivosDeTodasLasPastorales(array $pastorales): array
    {
        $porPastoral = [];
        foreach ($pastorales as $pastoral) {
            $porPastoral[(int) $pastoral['id']] = $this->modelo->ministrosActivos((int) $pastoral['id']);
        }
        return $porPastoral;
    }

    /** Cuadrícula del mes en semanas de 7 casillas, análoga a EventoPublicoController pero sobre fecha DATE. */
    private function construirCalendarioTurnos(int $anio, int $mes, array $turnosDelMes): array
    {
        $turnosPorDia = [];
        foreach ($turnosDelMes as $turno) {
            $dia = (int) substr((string) $turno['fecha'], 8, 2);
            $turnosPorDia[$dia][] = $turno;
        }

        $primerDia    = new DateTimeImmutable(sprintf('%04d-%02d-01', $anio, $mes));
        $diasEnMes    = (int) $primerDia->format('t');
        $diaSemanaIni = (int) $primerDia->format('w');
        $hoy          = date('Y-m-d');

        $semanas = [];
        $semana  = array_fill(0, $diaSemanaIni, null);

        for ($dia = 1; $dia <= $diasEnMes; $dia++) {
            $fecha    = sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
            $semana[] = [
                'dia'    => $dia,
                'fecha'  => $fecha,
                'turnos' => $turnosPorDia[$dia] ?? [],
                'hoy'    => $fecha === $hoy,
            ];
            if (count($semana) === 7) {
                $semanas[] = $semana;
                $semana    = [];
            }
        }
        if ($semana) {
            while (count($semana) < 7) {
                $semana[] = null;
            }
            $semanas[] = $semana;
        }
        return $semanas;
    }

    private function nombreMes(int $mes): string
    {
        $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
                  'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        return ucfirst($meses[$mes - 1] ?? '');
    }
}
