<?php
require_once BASE_PATH . '/core/ControllerPublico.php';
require_once BASE_PATH . '/modules/eventos/EventoModel.php';
require_once BASE_PATH . '/modules/pastorales/PastoralModel.php';

class EventoPublicoController extends ControllerPublico
{
    public function index(): void
    {
        [$anio, $mes] = $this->mesSolicitado();
        $pastoral      = $this->pastoralSolicitada();
        $pastoralId    = $pastoral ? (int) $pastoral['id'] : null;
        $modelo        = new EventoModel();
        $eventosDelMes = $modelo->delMes($anio, $mes, $pastoralId);

        $mesAnterior   = $mes === 1 ? 12 : $mes - 1;
        $anioAnterior  = $mes === 1 ? $anio - 1 : $anio;
        $mesSiguiente  = $mes === 12 ? 1 : $mes + 1;
        $anioSiguiente = $mes === 12 ? $anio + 1 : $anio;
        $paramsPastoral = $pastoral ? ['pastoral' => $pastoral['slug']] : [];

        $this->render('eventos/publico/index', [
            'metaTitulo'      => $pastoral ? 'Eventos de ' . $pastoral['nombre'] : 'Eventos',
            'metaDescripcion' => 'Calendario de celebraciones y actividades de la Parroquia Nuestra Señora de la Paz.',
            'urlCanonica'     => url_publica('eventos', $paramsPastoral),
            'anio'            => $anio,
            'mes'             => $mes,
            'nombreMes'       => $this->nombreMes($mes) . ' ' . $anio,
            'semanas'         => $this->construirCalendario($anio, $mes, $eventosDelMes),
            'proximos'        => $modelo->proximos(6, $pastoralId),
            'pastoral'        => $pastoral,
            'urlMesAnterior'  => url_publica('eventos', ['anio' => $anioAnterior, 'mes' => $mesAnterior] + $paramsPastoral),
            'urlMesSiguiente' => url_publica('eventos', ['anio' => $anioSiguiente, 'mes' => $mesSiguiente] + $paramsPastoral),
            'scriptExtra'     => '<script src="' . e(url_activo('assets/js/calendario.js'))
                               . '?v=' . e(APP_VERSION) . '"></script>',
        ]);
    }

    public function ver(): void
    {
        $slug   = $this->slug();
        $evento = $slug !== '' ? (new EventoModel())->porSlugPublicado($slug) : null;

        if (!$evento) {
            $this->noEncontrado();
            return;
        }

        $this->render('eventos/publico/detalle', [
            'metaTitulo'      => $evento['titulo'],
            'metaDescripcion' => resumen($evento['descripcion']),
            'ogImagen'        => $evento['imagen'] ?: null,
            'urlCanonica'     => url_publica('eventos', ['slug' => $evento['slug']]),
            'evento'          => $evento,
            'jsonLd'          => $this->datosEstructurados($evento),
        ]);
    }

    /** Datos estructurados schema.org/Event, para que aparezca como evento en los buscadores. */
    private function datosEstructurados(array $evento): array
    {
        $datos = [
            '@context'            => 'https://schema.org',
            '@type'                => 'Event',
            'name'                 => $evento['titulo'],
            'startDate'            => date('c', strtotime($evento['fecha_inicio'])),
            'eventAttendanceMode'  => 'https://schema.org/OfflineEventAttendanceMode',
            'eventStatus'          => 'https://schema.org/EventScheduled',
            'organizer'            => [
                '@type' => 'Organization',
                'name'  => Config::get('parroquia_nombre', APP_NAME),
                'url'   => url_absoluta(url_publica('inicio')),
            ],
        ];

        if ($evento['fecha_fin']) {
            $datos['endDate'] = date('c', strtotime($evento['fecha_fin']));
        }
        if ($evento['descripcion']) {
            $datos['description'] = resumen($evento['descripcion'], 500);
        }
        if ($evento['imagen']) {
            $datos['image'] = url_absoluta(url_activo($evento['imagen']));
        }

        $datos['location'] = $evento['lugar']
            ? ['@type' => 'Place', 'name' => $evento['lugar']]
            : ['@type' => 'VirtualLocation', 'url' => url_absoluta(url_publica('eventos', ['slug' => $evento['slug']]))];

        return $datos;
    }

    /**
     * Endpoint JSON que alimenta el calendario en JavaScript. Nombrado
     * "datos" y no "json": Controller ya tiene un método json() para emitir
     * la respuesta, y una acción de ruta con el mismo nombre lo taparía.
     */
    public function datos(): void
    {
        [$anio, $mes] = $this->mesSolicitado();
        $pastoral   = $this->pastoralSolicitada();
        $pastoralId = $pastoral ? (int) $pastoral['id'] : null;

        [$primerDiaMes, $ultimoDiaMes] = $this->limitesDelMes($anio, $mes);

        $eventos = [];
        foreach ((new EventoModel())->delMes($anio, $mes, $pastoralId) as $evento) {
            foreach ($this->diasDelEventoEnMes($evento, $primerDiaMes, $ultimoDiaMes) as $fecha) {
                $eventos[] = [
                    'id'     => (int) $evento['id'],
                    'titulo' => $evento['titulo'],
                    'fecha'  => $fecha,
                    'hora'   => $evento['todo_el_dia'] ? null : substr((string) $evento['fecha_inicio'], 11, 5),
                    'lugar'  => $evento['lugar'],
                    'color'  => $evento['color'] ?: '#1e4d8b',
                    'url'    => url_publica('eventos', ['slug' => $evento['slug']]),
                ];
            }
        }

        $this->json(['anio' => $anio, 'mes' => $mes, 'eventos' => $eventos]);
    }

    private function mesSolicitado(): array
    {
        $anio = $this->getInt('anio', (int) date('Y'));
        $mes  = $this->getInt('mes', (int) date('n'));
        if ($mes < 1 || $mes > 12) {
            $mes = (int) date('n');
        }
        if ($anio < 2000 || $anio > 2100) {
            $anio = (int) date('Y');
        }
        return [$anio, $mes];
    }

    /** ?pastoral=slug (issue #3): acota el calendario general al de una sola pastoral. */
    private function pastoralSolicitada(): ?array
    {
        $slug = strtolower(trim((string) ($_GET['pastoral'] ?? '')));
        if ($slug === '' || !preg_match('/^[a-z0-9\-]{1,80}$/', $slug)) {
            return null;
        }
        return (new PastoralModel())->porSlugActiva($slug);
    }

    private function slug(): string
    {
        $slug = strtolower(trim((string) ($_GET['slug'] ?? '')));
        return preg_match('/^[a-z0-9\-]{1,160}$/', $slug) ? $slug : '';
    }

    /** Primer y último día del mes, como 'Y-m-d'. */
    private function limitesDelMes(int $anio, int $mes): array
    {
        $primerDia = new DateTimeImmutable(sprintf('%04d-%02d-01', $anio, $mes));
        $ultimoDia = $primerDia->modify('last day of this month');
        return [$primerDia->format('Y-m-d'), $ultimoDia->format('Y-m-d')];
    }

    /**
     * Días ('Y-m-d') que un evento ocupa dentro del mes mostrado, recortando
     * su rango [fecha_inicio, fecha_fin] a los límites de ese mes. Un evento
     * de un solo día devuelve un único elemento; uno de varios días devuelve
     * uno por cada día que le toca a este mes en particular.
     */
    private function diasDelEventoEnMes(array $evento, string $primerDiaMes, string $ultimoDiaMes): array
    {
        $inicio = substr((string) $evento['fecha_inicio'], 0, 10);
        $fin    = $evento['fecha_fin'] ? substr((string) $evento['fecha_fin'], 0, 10) : $inicio;

        $desde = max($inicio, $primerDiaMes);
        $hasta = min($fin, $ultimoDiaMes);
        if ($desde > $hasta) {
            return [];
        }

        $dias   = [];
        $cursor = new DateTimeImmutable($desde);
        $limite = new DateTimeImmutable($hasta);
        while ($cursor <= $limite) {
            $dias[] = $cursor->format('Y-m-d');
            $cursor = $cursor->modify('+1 day');
        }
        return $dias;
    }

    /**
     * Cuadrícula del mes en semanas de 7 casillas (domingo primero, igual que
     * horarios.dia_semana). Una casilla es null si cae fuera del mes.
     */
    private function construirCalendario(int $anio, int $mes, array $eventosDelMes): array
    {
        [$primerDiaMes, $ultimoDiaMes] = $this->limitesDelMes($anio, $mes);

        $eventosPorDia = [];
        foreach ($eventosDelMes as $evento) {
            foreach ($this->diasDelEventoEnMes($evento, $primerDiaMes, $ultimoDiaMes) as $fecha) {
                $eventosPorDia[(int) substr($fecha, 8, 2)][] = $evento;
            }
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
                'dia'     => $dia,
                'fecha'   => $fecha,
                'eventos' => $eventosPorDia[$dia] ?? [],
                'hoy'     => $fecha === $hoy,
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
