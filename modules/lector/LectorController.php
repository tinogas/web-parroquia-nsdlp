<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/modules/lector/LectorModel.php';
require_once BASE_PATH . '/modules/pastorales/PastoralModel.php';

class LectorController extends Controller
{
    private LectorModel $modelo;

    public function __construct()
    {
        $this->modelo = new LectorModel();
    }

    // ── Turnos (pantalla principal del módulo) ──────────────────────────

    public function index(): void
    {
        $this->requirePermiso('lector.ver');

        $anio = $this->getInt('anio', (int) date('Y'));
        $mes  = $this->getInt('mes', (int) date('n'));
        if ($mes < 1 || $mes > 12) { $mes = (int) date('n'); }
        if ($anio < 2000 || $anio > 2100) { $anio = (int) date('Y'); }

        $mesAnterior   = $mes === 1 ? 12 : $mes - 1;
        $anioAnterior  = $mes === 1 ? $anio - 1 : $anio;
        $mesSiguiente  = $mes === 12 ? 1 : $mes + 1;
        $anioSiguiente = $mes === 12 ? $anio + 1 : $anio;

        $turnosDelMes = $this->modelo->turnosDelMes($anio, $mes, $this->filtroPastoralSql());

        $this->render('lector/turnos', [
            'titulo'          => 'Calendario de lectores',
            'anio'            => $anio,
            'mes'             => $mes,
            'nombreMes'       => $this->nombreMes($mes) . ' ' . $anio,
            'semanas'         => $this->construirCalendarioTurnos($anio, $mes, $turnosDelMes),
            'urlMesAnterior'  => url_admin('lector', '', ['anio' => $anioAnterior, 'mes' => $mesAnterior]),
            'urlMesSiguiente' => url_admin('lector', '', ['anio' => $anioSiguiente, 'mes' => $mesSiguiente]),
        ]);
    }

    public function turnoNuevo(): void
    {
        $this->requirePermiso('lector.crear');

        $pastorales = $this->pastoralesDisponibles();
        $this->render('lector/turno_form', [
            'titulo'              => 'Nuevo turno',
            'turno'               => null,
            'pastorales'          => $pastorales,
            'lectoresPorPastoral' => $this->lectoresActivosDeTodasLasPastorales($pastorales),
            'asignados'           => [],
            'fechaSugerida'       => $this->getStr('fecha'),
            'colores'             => $this->modelo->coloresLiturgicos(),
        ]);
    }

    public function turnoEditar(): void
    {
        $this->requirePermiso('lector.editar');

        $turno = $this->modelo->turnoPorId($this->getInt('id'));
        if (!$turno) {
            Session::flash('error', 'No encontramos ese turno.');
            $this->redirect(url_admin('lector'));
            return;
        }
        $this->requireAlcancePastoral((int) $turno['pastoral_id']);

        $pastorales = $this->pastoralesDisponibles();
        $this->render('lector/turno_form', [
            'titulo'              => $turno['descripcion'],
            'turno'               => $turno,
            'pastorales'          => $pastorales,
            'lectoresPorPastoral' => $this->lectoresActivosDeTodasLasPastorales($pastorales),
            'asignados'           => array_map(
                static fn (array $l): int => (int) $l['id'],
                $this->modelo->lectoresDeTurno((int) $turno['id'])
            ),
            'fechaSugerida'       => '',
            'colores'             => $this->modelo->coloresLiturgicos(),
        ]);
    }

    public function turnoGuardar(): void
    {
        $id        = $this->postInt('id');
        $existente = $id ? $this->modelo->turnoPorId($id) : null;

        $this->requirePermiso($existente ? 'lector.editar' : 'lector.crear');
        if ($existente) {
            $this->requireAlcancePastoral((int) $existente['pastoral_id']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('lector'));
            return;
        }
        $this->validarCsrf();

        $fecha       = $this->postStr('fecha');
        $descripcion = $this->postStr('descripcion');
        if ($fecha === '' || $descripcion === '') {
            Session::flash('error', 'El turno necesita fecha y descripción.');
            $this->redirect($id ? url_admin('lector', 'turno_editar', ['id' => $id]) : url_admin('lector', 'turno_nuevo'));
            return;
        }

        try {
            $pastoralId = $existente ? (int) $existente['pastoral_id'] : $this->pastoralIdLectorValidado();
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect($id ? url_admin('lector', 'turno_editar', ['id' => $id]) : url_admin('lector', 'turno_nuevo'));
            return;
        }

        $lectoresDisponibles = $this->modelo->lectoresActivos($pastoralId);
        $idsValidos          = array_map(static fn (array $l): int => (int) $l['id'], $lectoresDisponibles);
        $lectorIds           = array_values(array_intersect(
            array_map('intval', array_filter((array) ($_POST['lectores'] ?? []), 'is_numeric')),
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
            'descripcion'        => $descripcion,
            'color_liturgico_id' => $colorId,
        ];

        if ($existente) {
            $this->modelo->actualizarTurno($id, $datos, $lectorIds);
            $this->auditoria('editar', 'lector_turnos', $id, $descripcion);
            Session::flash('success', 'Turno actualizado.');
        } else {
            $id = $this->modelo->crearTurno($datos, $lectorIds, (int) Auth::usuario()['id']);
            $this->auditoria('crear', 'lector_turnos', $id, $descripcion);
            Session::flash('success', 'Turno registrado.');
        }

        $this->redirect(url_admin('lector', '', ['anio' => (int) substr($fecha, 0, 4), 'mes' => (int) substr($fecha, 5, 2)]));
    }

    public function turnoEliminar(): void
    {
        $this->requirePermiso('lector.eliminar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('lector'));
            return;
        }
        $this->validarCsrf();

        $id    = $this->postInt('id');
        $turno = $this->modelo->turnoPorId($id);
        if ($turno) {
            $this->requireAlcancePastoral((int) $turno['pastoral_id']);
            $this->modelo->eliminarTurno($id);
            $this->auditoria('eliminar', 'lector_turnos', $id, $turno['descripcion']);
            Session::flash('success', 'Turno eliminado.');
        }

        $this->redirect(url_admin('lector'));
    }

    // ── Lectores (catálogo) ──────────────────────────────────────────────

    public function lectores(): void
    {
        $this->requirePermiso('lector.ver');

        $pastorales = $this->pastoralesDisponibles();
        $this->render('lector/lectores_lista', [
            'titulo'     => 'Catálogo de lectores',
            'pastorales' => $pastorales,
            'lectores'   => $this->lectoresDeTodasLasPastorales($pastorales),
        ]);
    }

    public function lectorGuardar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('lector', 'lectores'));
            return;
        }
        $this->validarCsrf();

        $id        = $this->postInt('id');
        $existente = $id ? $this->modelo->lectorPorId($id) : null;
        $this->requirePermiso($existente ? 'lector.editar' : 'lector.crear');

        $pastoralId = $existente ? (int) $existente['pastoral_id'] : $this->postInt('pastoral_id');
        $this->requireAlcancePastoral($pastoralId);

        $nombre = $this->postStr('nombre');
        if ($nombre === '') {
            Session::flash('error', 'El lector necesita un nombre.');
            $this->redirect(url_admin('lector', 'lectores'));
            return;
        }

        $datos = [
            'pastoral_id' => $pastoralId,
            'nombre'      => $nombre,
            'telefono'    => $this->postStr('telefono') ?: null,
            'email'       => $this->postStr('email') ?: null,
            'orden'       => $this->postInt('orden'),
            'activo'      => $this->postBool('activo'),
        ];

        if ($existente) {
            $this->modelo->actualizarLector($id, $datos);
            $this->auditoria('editar', 'lector_lectores', $id, $nombre);
            Session::flash('success', 'Lector actualizado.');
        } else {
            $id = $this->modelo->crearLector($datos);
            $this->auditoria('crear', 'lector_lectores', $id, $nombre);
            Session::flash('success', 'Lector agregado.');
        }

        $this->redirect(url_admin('lector', 'lectores'));
    }

    public function lectorEliminar(): void
    {
        $this->requirePermiso('lector.eliminar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('lector', 'lectores'));
            return;
        }
        $this->validarCsrf();

        $id     = $this->postInt('id');
        $lector = $this->modelo->lectorPorId($id);
        if ($lector) {
            $this->requireAlcancePastoral((int) $lector['pastoral_id']);
            $this->modelo->eliminarLector($id);
            $this->auditoria('eliminar', 'lector_lectores', $id, $lector['nombre']);
            Session::flash('success', 'Lector eliminado.');
        }

        $this->redirect(url_admin('lector', 'lectores'));
    }

    // ── Privados ─────────────────────────────────────────────────────────

    /** A diferencia de avisos/eventos, aquí la pastoral SIEMPRE es obligatoria: nunca "general". */
    private function pastoralIdLectorValidado(): int
    {
        $enviado = $this->postIntONull('pastoral_id');
        if ($enviado === null) {
            throw new RuntimeException('Selecciona la pastoral de Lectores.');
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

    private function lectoresDeTodasLasPastorales(array $pastorales): array
    {
        $porPastoral = [];
        foreach ($pastorales as $pastoral) {
            $porPastoral[(int) $pastoral['id']] = [
                'pastoral' => $pastoral,
                'lectores' => $this->modelo->lectores((int) $pastoral['id']),
            ];
        }
        return $porPastoral;
    }

    private function lectoresActivosDeTodasLasPastorales(array $pastorales): array
    {
        $porPastoral = [];
        foreach ($pastorales as $pastoral) {
            $porPastoral[(int) $pastoral['id']] = $this->modelo->lectoresActivos((int) $pastoral['id']);
        }
        return $porPastoral;
    }

    /** Cuadrícula del mes en semanas de 7 casillas, calcada de MescController. */
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
