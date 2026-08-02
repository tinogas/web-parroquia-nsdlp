<?php
require_once BASE_PATH . '/core/ControllerPublico.php';
require_once BASE_PATH . '/core/Calendario.php';
require_once BASE_PATH . '/modules/eventos/EventoModel.php';
require_once BASE_PATH . '/modules/pastorales/PastoralModel.php';

/**
 * El calendario del sitio. Los periodos, sus saltos y sus cuadrículas los arma
 * `Calendario`, compartido con la agenda interna del panel; aquí queda lo
 * propio de esta cara: qué eventos se leen —solo los publicados— y a dónde
 * enlazan.
 */
class EventoPublicoController extends ControllerPublico
{
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
        [$desde, $hasta] = Calendario::limites($vista, $ancla);

        $eventos = [];
        foreach ((new EventoModel())->entreFechas($desde, $hasta, $pastoral ? (int) $pastoral['id'] : null) as $evento) {
            foreach (Calendario::diasDelItem($evento, $desde, $hasta) as $fecha) {
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
        [$desde, $hasta] = Calendario::limites($vista, $ancla);

        $eventos = (new EventoModel())->entreFechas($desde, $hasta, $pastoralId);
        $porDia  = Calendario::repartirPorDia($eventos, $desde, $hasta);
        $comunes = $pastoral ? ['pastoral' => $pastoral['slug']] : [];

        return [
            'vista'        => $vista,
            'vistas'       => Calendario::VISTAS,
            'fecha'        => $ancla->format('Y-m-d'),
            'titulo'       => Calendario::titulo($vista, $ancla),
            'dias'         => Calendario::diasDelPeriodo($vista, $desde, $hasta, $porDia),
            'semanas'      => $vista === 'mes'  ? Calendario::cuadriculaDelMes($ancla, $porDia)  : [],
            'meses'        => $vista === 'anio' ? Calendario::cuadriculaDelAnio($ancla, $porDia) : [],
            // Eventos distintos, no días ocupados: uno de nueve días es un
            // evento, aunque se dibuje en nueve casillas.
            'totalEventos' => count($eventos),
            'urlAnterior'  => $this->urlDelPeriodo($vista, Calendario::desplazar($vista, $ancla, -1), $comunes),
            'urlSiguiente' => $this->urlDelPeriodo($vista, Calendario::desplazar($vista, $ancla, 1), $comunes),
            'urlHoy'       => $this->urlDelPeriodo($vista, new DateTimeImmutable('today'), $comunes),
            'urlsVista'    => $this->urlsDeVista($ancla, $comunes),
            'incluyeHoy'   => date('Y-m-d') >= $desde && date('Y-m-d') <= $hasta,
        ];
    }

    /** ?vista=… y la fecha que ancla el periodo. */
    private function periodoSolicitado(): array
    {
        return [Calendario::vistaSolicitada($_GET), Calendario::fechaSolicitada($_GET)];
    }

    private function urlDelPeriodo(string $vista, DateTimeImmutable $fecha, array $comunes): string
    {
        return url_publica('eventos', ['vista' => $vista, 'fecha' => $fecha->format('Y-m-d')] + $comunes);
    }

    /** Una URL por modo sobre la misma fecha, para el selector de vista. */
    private function urlsDeVista(DateTimeImmutable $ancla, array $comunes): array
    {
        $urls = [];
        foreach (array_keys(Calendario::VISTAS) as $vista) {
            $urls[$vista] = $this->urlDelPeriodo($vista, $ancla, $comunes);
        }
        return $urls;
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
