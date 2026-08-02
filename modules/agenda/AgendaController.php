<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/core/Calendario.php';
require_once BASE_PATH . '/modules/eventos/EventoModel.php';
require_once BASE_PATH . '/modules/cursos/CursoModel.php';
require_once BASE_PATH . '/modules/pastorales/PastoralModel.php';
require_once BASE_PATH . '/modules/centros/CentroModel.php';

/**
 * AgendaController — El calendario interno de la parroquia.
 *
 * Dos diferencias con el calendario del sitio, y las dos son la razón de que
 * exista:
 *
 * 1. Muestra **eventos y cursos juntos**, y los muestra estén publicados o no.
 *    El sitio público enseña lo publicado; aquí el equipo ve también lo que
 *    todavía es borrador, que es justamente lo que hay que coordinar.
 * 2. **Todas las pastorales y todas las sedes se ven entre sí.** Ninguna
 *    consulta de esta pantalla se recorta por el alcance de quien mira: este es
 *    el sitio donde el equipo comprueba que no hay dos cosas a la misma hora en
 *    el mismo salón, y para eso hace falta verlo todo. El alcance decide qué se
 *    puede *tocar*, no qué se puede *mirar*, así que el enlace de editar solo se
 *    dibuja sobre lo propio —y el controlador de destino lo vuelve a comprobar
 *    con requireAlcanceContenido(), que es el límite de verdad—. Los listados de
 *    eventos y cursos sí recortan: allí se trabaja, aquí se coordina.
 *
 * Sin AJAX, igual que el calendario de turnos MESC y a diferencia del público:
 * cada cambio de periodo es un enlace normal que recarga. El panel no tiene el
 * tráfico que justifique la complejidad.
 */
class AgendaController extends Controller
{
    /** Los cursos no tienen columna de color; van todos con el dorado de la casa. */
    private const COLOR_CURSO = '#c9a227';
    private const COLOR_EVENTO = '#1e4d8b';

    public function index(): void
    {
        $this->requirePermiso('agenda.ver');

        // Los dos selectores ofrecen el catálogo entero, no el alcance de quien
        // mira: aquí se ve todo, y filtrar es una comodidad, no un permiso.
        $pastorales = (new PastoralModel())->paraSelector();
        $centros    = (new CentroModel())->activos();
        [$filtro,       $idsPastoral] = $this->filtroPastoral($pastorales);
        [$filtroCentro, $idsCentro]   = $this->filtroCentro($centros);

        $vista = Calendario::vistaSolicitada($_GET);
        $ancla = Calendario::fechaSolicitada($_GET);
        [$desde, $hasta] = Calendario::limites($vista, $ancla);

        $items = array_merge(
            array_map([$this, 'itemDeEvento'], (new EventoModel())->agenda($desde, $hasta, $idsPastoral, $idsCentro)),
            array_map([$this, 'itemDeCurso'],  (new CursoModel())->agenda($desde, $hasta, $idsPastoral, $idsCentro))
        );
        $porDia  = Calendario::repartirPorDia($items, $desde, $hasta);
        $comunes = ($filtro !== '' ? ['pastoral' => $filtro] : [])
                 + ($filtroCentro !== '' ? ['centro' => $filtroCentro] : []);

        $this->render('agenda/index', [
            'titulo'       => 'Agenda',
            'vista'        => $vista,
            'vistas'       => Calendario::VISTAS,
            'fecha'        => $ancla->format('Y-m-d'),
            'periodo'      => Calendario::titulo($vista, $ancla),
            'dias'         => Calendario::diasDelPeriodo($vista, $desde, $hasta, $porDia),
            'semanas'      => $vista === 'mes'  ? Calendario::cuadriculaDelMes($ancla, $porDia)  : [],
            'meses'        => $vista === 'anio' ? Calendario::cuadriculaDelAnio($ancla, $porDia) : [],
            // Registros distintos, no días ocupados: un curso de tres meses es
            // un curso, aunque se dibuje en noventa casillas.
            'total'        => count($items),
            'urlAnterior'  => $this->urlDelPeriodo($vista, Calendario::desplazar($vista, $ancla, -1), $comunes),
            'urlSiguiente' => $this->urlDelPeriodo($vista, Calendario::desplazar($vista, $ancla, 1), $comunes),
            'urlHoy'       => $this->urlDelPeriodo($vista, new DateTimeImmutable('today'), $comunes),
            'urlsVista'    => $this->urlsDeVista($ancla, $comunes),
            'incluyeHoy'   => date('Y-m-d') >= $desde && date('Y-m-d') <= $hasta,
            'comunes'      => $comunes,
            'pastorales'   => $pastorales,
            'filtro'       => $filtro,
            'centros'      => $centros,
            'filtroCentro' => $filtroCentro,
            'tieneAlcance' => Auth::pastoralesPermitidas() !== [],
            // Un curso sin fecha de inicio no cabe en ninguna casilla. En vez de
            // desaparecerlo en silencio, se avisa debajo del calendario.
            'sinFechas'    => (new CursoModel())->sinFechas($idsPastoral, $idsCentro),
        ]);
    }

    // ── Normalización a la forma que espera Calendario ──────────────────────

    /**
     * Un evento ya viene con la forma que Calendario necesita; solo se le
     * añade de qué tipo es y a dónde lleva su casilla.
     */
    private function itemDeEvento(array $evento): array
    {
        return [
            'tipo'            => 'evento',
            'id'              => (int) $evento['id'],
            'titulo'          => $evento['titulo'],
            'fecha_inicio'    => $evento['fecha_inicio'],
            'fecha_fin'       => $evento['fecha_fin'],
            'todo_el_dia'     => (int) $evento['todo_el_dia'],
            'lugar'           => $evento['lugar'],
            'color'           => $evento['color'] ?: self::COLOR_EVENTO,
            'publicado'       => (int) $evento['publicado'],
            'pastoral_id'     => $evento['pastoral_id'] !== null ? (int) $evento['pastoral_id'] : null,
            'pastoral_nombre' => $evento['pastoral_nombre'],
            'centro_nombre'   => $evento['centro_nombre'],
            'url'             => $this->urlDeEdicion('eventos', (int) $evento['id'], $evento['pastoral_id'], $evento['centro_id']),
        ];
    }

    /**
     * `cursos.fecha_inicio` y `fecha_fin` son DATE, sin hora: un curso ocupa
     * días enteros, así que entra como «todo el día» y su horario ("martes de
     * 19:00 a 21:00") viaja como texto en el detalle, que es como está escrito.
     */
    private function itemDeCurso(array $curso): array
    {
        return [
            'tipo'            => 'curso',
            'id'              => (int) $curso['id'],
            'titulo'          => $curso['titulo'],
            'fecha_inicio'    => $curso['fecha_inicio'] . ' 00:00:00',
            'fecha_fin'       => $curso['fecha_fin'] ? $curso['fecha_fin'] . ' 00:00:00' : null,
            'todo_el_dia'     => 1,
            'lugar'           => $curso['lugar'],
            'horario'         => $curso['horario'],
            'color'           => self::COLOR_CURSO,
            'publicado'       => (int) $curso['publicado'],
            'pastoral_id'     => $curso['pastoral_id'] !== null ? (int) $curso['pastoral_id'] : null,
            'pastoral_nombre' => $curso['pastoral_nombre'],
            'centro_nombre'   => $curso['centro_nombre'],
            'url'             => $this->urlDeEdicion('cursos', (int) $curso['id'], $curso['pastoral_id'], $curso['centro_id']),
        ];
    }

    /**
     * A dónde lleva una casilla, o null si esta persona no puede editar ese
     * registro. Un enlace que va a acabar en «no tienes permiso» es peor que no
     * tener enlace: es la misma regla que ya se aplicó al calendario de turnos
     * de MESC cuando aparecieron los roles de consulta.
     *
     * Se comprueban las dos mitades del alcance, pastoral y sede: la catequesis
     * de otra comunidad se ve en el calendario, pero sin lápiz.
     */
    private function urlDeEdicion(string $modulo, int $id, $pastoralId, $centroId): ?string
    {
        $puede = Auth::tienePermiso($modulo . '.editar')
              && Auth::puedeSobrePastoral($pastoralId !== null ? (int) $pastoralId : null)
              && Auth::puedeSobreCentro($centroId !== null ? (int) $centroId : null);

        return $puede ? url_admin($modulo, 'editar', ['id' => $id]) : null;
    }

    // ── URLs de navegación ──────────────────────────────────────────────────

    private function urlDelPeriodo(string $vista, DateTimeImmutable $fecha, array $comunes): string
    {
        return url_admin('agenda', '', ['vista' => $vista, 'fecha' => $fecha->format('Y-m-d')] + $comunes);
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
}
