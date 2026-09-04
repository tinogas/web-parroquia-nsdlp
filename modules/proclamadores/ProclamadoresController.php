<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/modules/proclamadores/ProclamadoresModel.php';
require_once BASE_PATH . '/modules/personas/PersonaModel.php';

/**
 * ProclamadoresController — Exclusivo de la pastoral "Proclamadores" (se
 * llamaba "Lectores"; el slug sigue siendo 'liturgia' —ver
 * PASTORAL_PROCLAMADORES en config/app.php—): ninguna acción muestra ni
 * acepta otra pastoral, igual que MESC y Catequesis. pastoralIdOFallar()
 * resuelve esa única pastoral y corta el flujo con un mensaje claro si
 * todavía no existe.
 *
 * Tiene las mismas pantallas que MESC menos las visitas a enfermos y sus
 * rutas: calendario de turnos, hoja imprimible, catálogo de quienes proclaman
 * y colores litúrgicos.
 */
class ProclamadoresController extends Controller
{
    private ProclamadoresModel $modelo;

    public function __construct()
    {
        $this->modelo = new ProclamadoresModel();
    }

    // ── Turnos (pantalla principal del módulo) ──────────────────────────

    public function index(): void
    {
        $this->requirePermiso('proclamadores.ver');

        $pastoralId = $this->pastoralIdOFallar();
        if ($pastoralId === null) {
            return;
        }

        [$anio, $mes] = $this->anioMesPedidos();

        $mesAnterior   = $mes === 1 ? 12 : $mes - 1;
        $anioAnterior  = $mes === 1 ? $anio - 1 : $anio;
        $mesSiguiente  = $mes === 12 ? 1 : $mes + 1;
        $anioSiguiente = $mes === 12 ? $anio + 1 : $anio;

        $turnosDelMes = $this->modelo->turnosDelMes($anio, $mes, $pastoralId);

        $this->render('proclamadores/turnos', [
            'titulo'          => 'Calendario de proclamadores',
            'anio'            => $anio,
            'mes'             => $mes,
            'nombreMes'       => $this->nombreMes($mes) . ' ' . $anio,
            'semanas'         => $this->construirCalendarioTurnos($anio, $mes, $turnosDelMes),
            'urlMesAnterior'  => url_admin('proclamadores', '', ['anio' => $anioAnterior, 'mes' => $mesAnterior]),
            'urlMesSiguiente' => url_admin('proclamadores', '', ['anio' => $anioSiguiente, 'mes' => $mesSiguiente]),
            'urlImprimir'     => url_admin('proclamadores', 'turnos_imprimir', ['anio' => $anio, 'mes' => $mes]),
        ]);
    }

    /**
     * El calendario del mes en hoja aparte, para repartirlo impreso o
     * compartirlo como imagen. Misma solución que
     * MescController::turnosImprimir(): página independiente sin el layout
     * del panel, sin librería de PDF —el proyecto no admite dependencias en
     * el servidor—, y el propio navegador imprime, guarda como PDF o captura.
     */
    public function turnosImprimir(): void
    {
        $this->requirePermiso('proclamadores.ver');

        $pastoralId = $this->pastoralIdOFallar();
        if ($pastoralId === null) {
            return;
        }

        [$anio, $mes] = $this->anioMesPedidos();

        $turnosDelMes = $this->modelo->turnosDelMes($anio, $mes, $pastoralId);

        $this->renderSinLayout('proclamadores/turnos_imprimir', [
            'nombreMes' => $this->nombreMes($mes) . ' ' . $anio,
            'semanas'   => $this->construirCalendarioTurnos($anio, $mes, $turnosDelMes),
            'urlVolver' => url_admin('proclamadores', '', ['anio' => $anio, 'mes' => $mes]),
        ]);
    }

    public function turnoNuevo(): void
    {
        $this->requirePermiso('proclamadores.crear');

        $pastoralId = $this->pastoralIdOFallar();
        if ($pastoralId === null) {
            return;
        }

        $this->render('proclamadores/turno_form', [
            'titulo'        => 'Nuevo turno',
            'turno'         => null,
            'pastoralId'    => $pastoralId,
            'proclamadores' => $this->modelo->proclamadoresActivos($pastoralId),
            'asignados'     => [],
            'fechaSugerida' => $this->getStr('fecha'),
            'colores'       => $this->modelo->coloresLiturgicos(),
        ]);
    }

    public function turnoEditar(): void
    {
        $this->requirePermiso('proclamadores.editar');

        $turno = $this->modelo->turnoPorId($this->getInt('id'));
        if (!$turno) {
            Session::flash('error', 'No encontramos ese turno.');
            $this->redirect(url_admin('proclamadores'));
            return;
        }
        $this->requireAlcancePastoral((int) $turno['pastoral_id']);

        $this->render('proclamadores/turno_form', [
            'titulo'        => $turno['descripcion'],
            'turno'         => $turno,
            'pastoralId'    => (int) $turno['pastoral_id'],
            'proclamadores' => $this->modelo->proclamadoresActivos((int) $turno['pastoral_id']),
            'asignados'     => array_map(
                static fn (array $p): int => (int) $p['id'],
                $this->modelo->proclamadoresDeTurno((int) $turno['id'])
            ),
            'fechaSugerida' => '',
            'colores'       => $this->modelo->coloresLiturgicos(),
        ]);
    }

    public function turnoGuardar(): void
    {
        $id        = $this->postInt('id');
        $existente = $id ? $this->modelo->turnoPorId($id) : null;

        $this->requirePermiso($existente ? 'proclamadores.editar' : 'proclamadores.crear');
        if ($existente) {
            $this->requireAlcancePastoral((int) $existente['pastoral_id']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('proclamadores'));
            return;
        }
        $this->validarCsrf();

        $fecha       = $this->postStr('fecha');
        $descripcion = $this->postStr('descripcion');
        if ($fecha === '' || $descripcion === '') {
            Session::flash('error', 'El turno necesita fecha y descripción.');
            $this->redirect($id
                ? url_admin('proclamadores', 'turno_editar', ['id' => $id])
                : url_admin('proclamadores', 'turno_nuevo'));
            return;
        }

        $pastoralId = $existente ? (int) $existente['pastoral_id'] : $this->pastoralIdOFallar();
        if ($pastoralId === null) {
            return;
        }

        $disponibles     = $this->modelo->proclamadoresActivos($pastoralId);
        $idsValidos      = array_map(static fn (array $p): int => (int) $p['id'], $disponibles);
        $proclamadorIds  = array_values(array_intersect(
            array_map('intval', array_filter((array) ($_POST['proclamadores'] ?? []), 'is_numeric')),
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
            $this->modelo->actualizarTurno($id, $datos, $proclamadorIds);
            $this->auditoria('editar', 'proclamadores_turnos', $id, $descripcion);
            Session::flash('success', 'Turno actualizado.');
        } else {
            $id = $this->modelo->crearTurno($datos, $proclamadorIds, (int) Auth::usuario()['id']);
            $this->auditoria('crear', 'proclamadores_turnos', $id, $descripcion);
            Session::flash('success', 'Turno registrado.');
        }

        $this->redirect(url_admin('proclamadores', '', [
            'anio' => (int) substr($fecha, 0, 4),
            'mes'  => (int) substr($fecha, 5, 2),
        ]));
    }

    public function turnoEliminar(): void
    {
        $this->requirePermiso('proclamadores.eliminar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('proclamadores'));
            return;
        }
        $this->validarCsrf();

        $id    = $this->postInt('id');
        $turno = $this->modelo->turnoPorId($id);
        if ($turno) {
            $this->requireAlcancePastoral((int) $turno['pastoral_id']);
            $this->modelo->eliminarTurno($id);
            $this->auditoria('eliminar', 'proclamadores_turnos', $id, $turno['descripcion']);
            Session::flash('success', 'Turno eliminado.');
        }

        $this->redirect(url_admin('proclamadores'));
    }

    // ── Proclamadores (catálogo) ─────────────────────────────────────────

    /**
     * La acción se llama `catalogo` y no `proclamadores` como pedirían MESC
     * (`/admin/mesc/ministros`) y Catequesis: ahí la entidad y el módulo se
     * llaman distinto, aquí no, y `/admin/proclamadores/proclamadores` no
     * dice nada que no dijera ya la primera mitad.
     */
    public function catalogo(): void
    {
        $this->requirePermiso('proclamadores.ver');

        $pastoralId = $this->pastoralIdOFallar();
        if ($pastoralId === null) {
            return;
        }

        $this->render('proclamadores/proclamadores_lista', [
            'titulo'        => 'Catálogo de proclamadores',
            'pastoralId'    => $pastoralId,
            'proclamadores' => $this->modelo->proclamadores($pastoralId),
            'personas'      => (new PersonaModel())->paraSelector(),
            'preferencias'  => ProclamadoresModel::PREFERENCIAS,
        ]);
    }

    public function proclamadorGuardar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('proclamadores', 'catalogo'));
            return;
        }
        $this->validarCsrf();

        $id        = $this->postInt('id');
        $existente = $id ? $this->modelo->proclamadorPorId($id) : null;
        $this->requirePermiso($existente ? 'proclamadores.editar' : 'proclamadores.crear');

        $pastoralId = $existente ? (int) $existente['pastoral_id'] : $this->pastoralIdOFallar();
        if ($pastoralId === null) {
            return;
        }
        $this->requireAlcancePastoral($pastoralId);

        // El proclamador se elige del equipo pastoral; si todavía no está ahí,
        // los campos libres de abajo son el respaldo. Mismo patrón que el
        // responsable de una pastoral, los ministros de MESC y los
        // catequistas.
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
            Session::flash('error', 'El proclamador necesita un nombre, o elige a alguien del equipo pastoral.');
            $this->redirect(url_admin('proclamadores', 'catalogo'));
            return;
        }

        $datos = [
            'pastoral_id'  => $pastoralId,
            'persona_id'   => $personaId,
            'nombre'       => $nombre,
            'telefono'     => $telefono,
            'email'        => $email,
            'preferencias' => $this->preferenciasPedidas(),
            'orden'        => $this->postInt('orden'),
            'activo'       => $this->postBool('activo'),
        ];

        if ($existente) {
            $this->modelo->actualizarProclamador($id, $datos);
            $this->auditoria('editar', 'proclamadores', $id, $nombre);
            Session::flash('success', 'Proclamador actualizado.');
        } else {
            $id = $this->modelo->crearProclamador($datos);
            $this->auditoria('crear', 'proclamadores', $id, $nombre);
            Session::flash('success', 'Proclamador agregado.');
        }

        $this->redirect(url_admin('proclamadores', 'catalogo'));
    }

    public function proclamadorEliminar(): void
    {
        $this->requirePermiso('proclamadores.eliminar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('proclamadores', 'catalogo'));
            return;
        }
        $this->validarCsrf();

        $id           = $this->postInt('id');
        $proclamador  = $this->modelo->proclamadorPorId($id);
        if ($proclamador) {
            $this->requireAlcancePastoral((int) $proclamador['pastoral_id']);
            $this->modelo->eliminarProclamador($id);
            $this->auditoria('eliminar', 'proclamadores', $id, $proclamador['nombre']);
            Session::flash('success', 'Proclamador eliminado.');
        }

        $this->redirect(url_admin('proclamadores', 'catalogo'));
    }

    // ── Colores litúrgicos ───────────────────────────────────────────────
    // Catálogo compartido con MESC (tabla `colores_liturgicos`): las mismas
    // tres acciones están en MescController, sobre la misma tabla y con sus
    // propios permisos. Se repiten aquí a propósito: quien coordina
    // Proclamadores no administra MESC, y sin esta pantalla dependía de que
    // alguien más le diera de alta un color. A diferencia del resto del
    // módulo no tiene alcance por pastoral —un color litúrgico no es de
    // nadie—, así que solo exige el permiso proclamadores.*.

    public function colores(): void
    {
        $this->requirePermiso('proclamadores.ver');

        $this->render('proclamadores/colores_lista', [
            'titulo'  => 'Colores litúrgicos',
            'colores' => $this->modelo->coloresLiturgicos(),
        ]);
    }

    public function colorGuardar(): void
    {
        $id = $this->postInt('id');
        $this->requirePermiso($id ? 'proclamadores.editar' : 'proclamadores.crear');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('proclamadores', 'colores'));
            return;
        }
        $this->validarCsrf();

        $nombre      = $this->postStr('nombre');
        $hex         = $this->postStr('color_hex');
        $significado = $this->postStr('significado');
        if ($nombre === '' || !preg_match('/^#[0-9a-fA-F]{6}$/', $hex) || $significado === '') {
            Session::flash('error', 'El color necesita nombre, un tono válido (#rrggbb) y su significado.');
            $this->redirect(url_admin('proclamadores', 'colores'));
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
            $this->auditoria('editar', 'colores_liturgicos', $id, $nombre);
            Session::flash('success', 'Color actualizado.');
        } else {
            $id = $this->modelo->crearColorLiturgico($datos);
            $this->auditoria('crear', 'colores_liturgicos', $id, $nombre);
            Session::flash('success', 'Color agregado.');
        }

        $this->redirect(url_admin('proclamadores', 'colores'));
    }

    public function colorEliminar(): void
    {
        $this->requirePermiso('proclamadores.eliminar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('proclamadores', 'colores'));
            return;
        }
        $this->validarCsrf();

        $id    = $this->postInt('id');
        $color = $this->modelo->colorLiturgicoPorId($id);
        if ($color) {
            $this->modelo->eliminarColorLiturgico($id);
            $this->auditoria('eliminar', 'colores_liturgicos', $id, $color['nombre']);
            Session::flash('success', 'Color eliminado. Los turnos que lo usaban quedan sin color asignado.');
        }

        $this->redirect(url_admin('proclamadores', 'colores'));
    }

    // ── Privados ─────────────────────────────────────────────────────────

    /**
     * Resuelve la pastoral de Proclamadores, la única que administra este
     * módulo. Si todavía no existe (instalación nueva) o el usuario no
     * tiene alcance sobre ella, corta el flujo con un mensaje claro.
     */
    private function pastoralIdOFallar(): ?int
    {
        $pastoralId = $this->modelo->pastoralId();
        if ($pastoralId === null) {
            Session::flash('error', 'Todavía no existe la pastoral "Proclamadores". Créala primero desde Pastorales.');
            $this->redirect(url_admin('pastorales'));
            return null;
        }
        if (!Auth::puedeSobrePastoral($pastoralId)) {
            Session::flash('error', 'No administras la pastoral de Proclamadores.');
            $this->redirect(url_admin('panel'));
            return null;
        }
        return $pastoralId;
    }

    /** Las preferencias marcadas en el formulario, filtradas contra el catálogo. */
    private function preferenciasPedidas(): array
    {
        return array_values(array_intersect(
            array_map('strval', (array) ($_POST['preferencias'] ?? [])),
            array_keys(ProclamadoresModel::PREFERENCIAS)
        ));
    }

    /** Mes y año de la cadena de consulta, acotados al rango que el calendario sabe dibujar. */
    private function anioMesPedidos(): array
    {
        $anio = $this->getInt('anio', (int) date('Y'));
        $mes  = $this->getInt('mes', (int) date('n'));
        if ($mes < 1 || $mes > 12)      { $mes  = (int) date('n'); }
        if ($anio < 2000 || $anio > 2100) { $anio = (int) date('Y'); }
        return [$anio, $mes];
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
