<?php
require_once BASE_PATH . '/core/ControllerPublico.php';
require_once BASE_PATH . '/modules/eventos/EventoModel.php';
require_once BASE_PATH . '/modules/pastorales/PastoralModel.php';

class EventoPublicoController extends ControllerPublico
{
    /** Modos del calendario público: unidad que se muestra y se navega de una vez. */
    private const VISTAS = [
        'dia'    => 'Día',
        'semana' => 'Semana',
        'mes'    => 'Mes',
        'anio'   => 'Año',
    ];
    private const VISTA_POR_OMISION = 'mes';

    public function index(): void
    {
        $pastoral   = $this->pastoralSolicitada();
        $pastoralId = $pastoral ? (int) $pastoral['id'] : null;

        $this->render('eventos/publico/index', $this->calendario($pastoral, $pastoralId) + [
            'metaTitulo'      => $pastoral ? 'Eventos de ' . $pastoral['nombre'] : 'Eventos',
            'metaDescripcion' => 'Calendario de celebraciones y actividades de la Parroquia Nuestra Señora de la Paz.',
            'urlCanonica'     => url_publica('eventos', $pastoral ? ['pastoral' => $pastoral['slug']] : []),
            'proximos'        => (new EventoModel())->proximos(6, $pastoralId),
            'pastoral'        => $pastoral,
            'scriptExtra'     => '<script src="' . e(url_activo('assets/js/calendario.js'))
                               . '?v=' . e(APP_VERSION) . '"></script>',
        ]);
    }

    /**
     * El calendario solo, sin el resto de la página. Es lo que pide
     * `calendario.js` al cambiar de periodo o de vista: así el HTML lo sigue
     * armando PHP una sola vez y el JavaScript no tiene que saber dibujar
     * cuatro cuadrículas distintas.
     */
    public function fragmento(): void
    {
        $pastoral = $this->pastoralSolicitada();
        $this->renderSinLayout(
            'eventos/publico/calendario',
            $this->calendario($pastoral, $pastoral ? (int) $pastoral['id'] : null)
                + ['pastoral' => $pastoral]
        );
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

    /**
     * Endpoint JSON con los eventos del periodo mostrado. Nombrado "datos" y no
     * "json": Controller ya tiene un método json() para emitir la respuesta, y
     * una acción de ruta con el mismo nombre lo taparía.
     *
     * Devuelve una entrada por cada día que ocupa cada evento, así que quien lo
     * consuma no necesita saber nada de rangos.
     */
    public function datos(): void
    {
        $pastoral = $this->pastoralSolicitada();
        [$vista, $ancla] = $this->periodoSolicitado();
        [$desde, $hasta] = $this->limitesDelPeriodo($vista, $ancla);

        $eventos = [];
        foreach ((new EventoModel())->entreFechas($desde, $hasta, $pastoral ? (int) $pastoral['id'] : null) as $evento) {
            foreach ($this->diasDelEvento($evento, $desde, $hasta) as $fecha) {
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

        $this->json([
            'vista'   => $vista,
            'desde'   => $desde,
            'hasta'   => $hasta,
            'anio'    => (int) $ancla->format('Y'),
            'mes'     => (int) $ancla->format('n'),
            'eventos' => $eventos,
        ]);
    }

    // ── Armado del calendario ───────────────────────────────────────────────

    /**
     * Todo lo que necesitan las plantillas del calendario: la vista pedida, su
     * título, la estructura ya repartida por días y las URLs de navegación.
     */
    private function calendario(?array $pastoral, ?int $pastoralId): array
    {
        [$vista, $ancla] = $this->periodoSolicitado();
        [$desde, $hasta] = $this->limitesDelPeriodo($vista, $ancla);

        $eventos = (new EventoModel())->entreFechas($desde, $hasta, $pastoralId);
        $porDia  = $this->repartirPorDia($eventos, $desde, $hasta);
        $comunes = $pastoral ? ['pastoral' => $pastoral['slug']] : [];

        return [
            'vista'        => $vista,
            'vistas'       => self::VISTAS,
            'fecha'        => $ancla->format('Y-m-d'),
            'titulo'       => $this->tituloDelPeriodo($vista, $ancla),
            'dias'         => $this->diasDelPeriodo($vista, $desde, $hasta, $porDia),
            'semanas'      => $vista === 'mes'  ? $this->cuadriculaDelMes($ancla, $porDia)  : [],
            'meses'        => $vista === 'anio' ? $this->cuadriculaDelAnio($ancla, $porDia) : [],
            // Eventos distintos, no días ocupados: uno de nueve días es un
            // evento, aunque se dibuje en nueve casillas.
            'totalEventos' => count($eventos),
            'urlAnterior'  => $this->urlDelPeriodo($vista, $this->desplazar($vista, $ancla, -1), $comunes),
            'urlSiguiente' => $this->urlDelPeriodo($vista, $this->desplazar($vista, $ancla, 1), $comunes),
            'urlHoy'       => $this->urlDelPeriodo($vista, new DateTimeImmutable('today'), $comunes),
            'urlsVista'    => $this->urlsDeVista($ancla, $comunes),
            'incluyeHoy'   => date('Y-m-d') >= $desde && date('Y-m-d') <= $hasta,
        ];
    }

    /** ?vista=… y la fecha que ancla el periodo. */
    private function periodoSolicitado(): array
    {
        $vista = strtolower(trim((string) ($_GET['vista'] ?? '')));
        if (!isset(self::VISTAS[$vista])) {
            $vista = self::VISTA_POR_OMISION;
        }
        return [$vista, $this->fechaSolicitada()];
    }

    /**
     * La fecha de referencia. Se admite ?fecha=Y-m-d y también el ?anio=&mes=
     * de siempre, para que los enlaces que ya circulan sigan funcionando.
     */
    private function fechaSolicitada(): DateTimeImmutable
    {
        $fecha = trim((string) ($_GET['fecha'] ?? ''));
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha, $partes)) {
            [, $a, $m, $d] = array_map('intval', $partes);
            if (checkdate($m, $d, $a) && $a >= 2000 && $a <= 2100) {
                return new DateTimeImmutable($fecha);
            }
        }

        $anio = $this->getInt('anio', (int) date('Y'));
        $mes  = $this->getInt('mes', (int) date('n'));
        $dia  = $this->getInt('dia', 0);
        if ($mes < 1 || $mes > 12) {
            $mes = (int) date('n');
        }
        if ($anio < 2000 || $anio > 2100) {
            $anio = (int) date('Y');
        }
        if ($dia < 1 || !checkdate($mes, $dia, $anio)) {
            // Sin día explícito: hoy si es el mes en curso, y si no el día 1,
            // que es lo que espera quien llega desde un enlace ?anio=&mes=.
            $dia = ($anio === (int) date('Y') && $mes === (int) date('n')) ? (int) date('j') : 1;
        }
        return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $anio, $mes, $dia));
    }

    /** Primer y último día del periodo, como 'Y-m-d' y ambos inclusive. */
    private function limitesDelPeriodo(string $vista, DateTimeImmutable $ancla): array
    {
        switch ($vista) {
            case 'dia':
                return [$ancla->format('Y-m-d'), $ancla->format('Y-m-d')];
            case 'semana':
                $domingo = $this->domingoDeLaSemana($ancla);
                return [$domingo->format('Y-m-d'), $domingo->modify('+6 days')->format('Y-m-d')];
            case 'anio':
                return [$ancla->format('Y') . '-01-01', $ancla->format('Y') . '-12-31'];
            case 'mes':
            default:
                return [$ancla->format('Y-m-01'), $ancla->format('Y-m-t')];
        }
    }

    /**
     * La semana empieza en domingo, igual que la cuadrícula del mes y que
     * `horarios.dia_semana`. format('w') ya da 0 para domingo.
     */
    private function domingoDeLaSemana(DateTimeImmutable $fecha): DateTimeImmutable
    {
        return $fecha->modify('-' . (int) $fecha->format('w') . ' days');
    }

    /** Mueve la fecha ancla un periodo completo hacia atrás o hacia delante. */
    private function desplazar(string $vista, DateTimeImmutable $ancla, int $pasos): DateTimeImmutable
    {
        $salto = ($pasos < 0 ? '-' : '+') . abs($pasos);
        switch ($vista) {
            case 'dia':
                return $ancla->modify("{$salto} days");
            case 'semana':
                return $ancla->modify("{$salto} weeks");
            case 'anio':
                // Al 1 de enero antes de saltar: «29 de febrero -1 año» no existe
                // y PHP lo corre al 1 de marzo.
                return $ancla->setDate((int) $ancla->format('Y'), 1, 1)->modify("{$salto} years");
            case 'mes':
            default:
                // 'first day of' antes de sumar: sobre un 31, «+1 month» se va al
                // día 1 o 3 del mes siguiente en vez de al mes siguiente.
                return $ancla->modify('first day of this month')->modify("{$salto} months");
        }
    }

    private function tituloDelPeriodo(string $vista, DateTimeImmutable $ancla): string
    {
        [$desde, $hasta] = $this->limitesDelPeriodo($vista, $ancla);
        switch ($vista) {
            case 'dia':
                return ucfirst(fecha_con_dia($desde)) . ' de ' . $ancla->format('Y');
            case 'semana':
                $ini = new DateTimeImmutable($desde);
                $fin = new DateTimeImmutable($hasta);
                // El mes y el año solo se repiten si la semana los cruza.
                $izquierda = (int) $ini->format('j')
                    . ($ini->format('n') !== $fin->format('n')
                        ? ' de ' . $this->nombreMes((int) $ini->format('n')) : '')
                    . ($ini->format('Y') !== $fin->format('Y') ? ' de ' . $ini->format('Y') : '');
                return $izquierda . ' al ' . (int) $fin->format('j') . ' de '
                     . $this->nombreMes((int) $fin->format('n')) . ' de ' . $fin->format('Y');
            case 'anio':
                return $ancla->format('Y');
            case 'mes':
            default:
                return ucfirst($this->nombreMes((int) $ancla->format('n'))) . ' ' . $ancla->format('Y');
        }
    }

    private function urlDelPeriodo(string $vista, DateTimeImmutable $fecha, array $comunes): string
    {
        return url_publica('eventos', ['vista' => $vista, 'fecha' => $fecha->format('Y-m-d')] + $comunes);
    }

    /** Una URL por modo sobre la misma fecha, para el selector de vista. */
    private function urlsDeVista(DateTimeImmutable $ancla, array $comunes): array
    {
        $urls = [];
        foreach (array_keys(self::VISTAS) as $vista) {
            $urls[$vista] = $this->urlDelPeriodo($vista, $ancla, $comunes);
        }
        return $urls;
    }

    // ── Reparto de los eventos por día ──────────────────────────────────────

    /**
     * 'Y-m-d' => eventos de ese día. Un evento de varios días aparece en todos
     * los que le tocan dentro del periodo, no solo en el de inicio.
     */
    private function repartirPorDia(array $eventos, string $desde, string $hasta): array
    {
        $porDia = [];
        foreach ($eventos as $evento) {
            foreach ($this->diasDelEvento($evento, $desde, $hasta) as $fecha) {
                $porDia[$fecha][] = $evento;
            }
        }
        return $porDia;
    }

    /**
     * Días ('Y-m-d') que un evento ocupa dentro del periodo mostrado,
     * recortando su rango [fecha_inicio, fecha_fin] a los límites recibidos. Un
     * evento de un solo día devuelve un único elemento; uno de varios días
     * devuelve uno por cada día que le toca a este periodo en particular.
     */
    private function diasDelEvento(array $evento, string $desde, string $hasta): array
    {
        $inicio = substr((string) $evento['fecha_inicio'], 0, 10);
        $fin    = $evento['fecha_fin'] ? substr((string) $evento['fecha_fin'], 0, 10) : $inicio;

        $primero = max($inicio, $desde);
        $ultimo  = min($fin, $hasta);
        if ($primero > $ultimo) {
            return [];
        }

        $dias   = [];
        $cursor = new DateTimeImmutable($primero);
        $limite = new DateTimeImmutable($ultimo);
        while ($cursor <= $limite) {
            $dias[] = $cursor->format('Y-m-d');
            $cursor = $cursor->modify('+1 day');
        }
        return $dias;
    }

    /**
     * Los días del periodo, uno por uno y en orden. Lo usan las vistas de día y
     * de semana, que listan cada jornada en vez de dibujar una cuadrícula.
     */
    private function diasDelPeriodo(string $vista, string $desde, string $hasta, array $porDia): array
    {
        if ($vista !== 'dia' && $vista !== 'semana') {
            return [];
        }
        $hoy    = date('Y-m-d');
        $dias   = [];
        $cursor = new DateTimeImmutable($desde);
        $limite = new DateTimeImmutable($hasta);
        while ($cursor <= $limite) {
            $fecha  = $cursor->format('Y-m-d');
            $dias[] = [
                'fecha'     => $fecha,
                'dia'       => (int) $cursor->format('j'),
                'nombreDia' => nombre_dia((int) $cursor->format('w')),
                'nombreMes' => $this->nombreMes((int) $cursor->format('n')),
                'eventos'   => $this->ordenarPorHora($porDia[$fecha] ?? []),
                'hoy'       => $fecha === $hoy,
            ];
            $cursor = $cursor->modify('+1 day');
        }
        return $dias;
    }

    /**
     * Dentro de un día, primero lo que tiene hora y al final lo de todo el día.
     * La consulta ordena por fecha_inicio, que en un evento de varios días es la
     * de su primer día y no la del día que se está mostrando.
     */
    private function ordenarPorHora(array $eventos): array
    {
        usort($eventos, static function (array $a, array $b): int {
            $ha = $a['todo_el_dia'] ? '99:99' : substr((string) $a['fecha_inicio'], 11, 5);
            $hb = $b['todo_el_dia'] ? '99:99' : substr((string) $b['fecha_inicio'], 11, 5);
            return [$ha, (string) $a['titulo']] <=> [$hb, (string) $b['titulo']];
        });
        return $eventos;
    }

    /**
     * Cuadrícula del mes en semanas de 7 casillas (domingo primero, igual que
     * horarios.dia_semana). Una casilla es null si cae fuera del mes.
     */
    private function cuadriculaDelMes(DateTimeImmutable $ancla, array $porDia): array
    {
        $primerDia = $ancla->modify('first day of this month');
        $diasEnMes = (int) $primerDia->format('t');
        $hoy       = date('Y-m-d');

        $semanas = [];
        $semana  = array_fill(0, (int) $primerDia->format('w'), null);

        for ($dia = 1; $dia <= $diasEnMes; $dia++) {
            $fecha    = $primerDia->modify('+' . ($dia - 1) . ' days')->format('Y-m-d');
            $semana[] = [
                'dia'     => $dia,
                'fecha'   => $fecha,
                'eventos' => $this->ordenarPorHora($porDia[$fecha] ?? []),
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

    /**
     * Los doce meses del año como mini-cuadrículas. Cada día lleva cuántos
     * eventos tiene, no la lista: en una casilla de esa medida no cabe ningún
     * título, así que el día se marca y se enlaza a la vista de día.
     */
    private function cuadriculaDelAnio(DateTimeImmutable $ancla, array $porDia): array
    {
        $anio  = (int) $ancla->format('Y');
        $hoy   = date('Y-m-d');
        $meses = [];

        for ($mes = 1; $mes <= 12; $mes++) {
            $primerDia = new DateTimeImmutable(sprintf('%04d-%02d-01', $anio, $mes));
            $celdas    = array_fill(0, (int) $primerDia->format('w'), null);
            $total     = 0;

            for ($dia = 1; $dia <= (int) $primerDia->format('t'); $dia++) {
                $fecha    = sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
                $cuantos  = count($porDia[$fecha] ?? []);
                $total   += $cuantos;
                $celdas[] = [
                    'dia'     => $dia,
                    'fecha'   => $fecha,
                    'cuantos' => $cuantos,
                    'hoy'     => $fecha === $hoy,
                    'color'   => $cuantos ? ($porDia[$fecha][0]['color'] ?: '#1e4d8b') : null,
                ];
            }
            while (count($celdas) % 7 !== 0) {
                $celdas[] = null;
            }

            $meses[] = [
                'mes'     => $mes,
                'nombre'  => ucfirst($this->nombreMes($mes)),
                'semanas' => array_chunk($celdas, 7),
                'total'   => $total,
            ];
        }
        return $meses;
    }

    // ── Parámetros y utilidades ─────────────────────────────────────────────

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

    private function nombreMes(int $mes): string
    {
        $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
                  'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        return $meses[$mes - 1] ?? '';
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
}
