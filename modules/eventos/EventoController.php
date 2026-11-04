<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/modules/eventos/EventoModel.php';
require_once BASE_PATH . '/modules/pastorales/PastoralModel.php';
require_once BASE_PATH . '/modules/centros/CentroModel.php';
require_once BASE_PATH . '/core/Calendario.php';

class EventoController extends Controller
{
    private EventoModel $modelo;

    public function __construct()
    {
        $this->modelo = new EventoModel();
    }

    public function index(): void
    {
        $this->requirePermiso('eventos.ver');

        $filtro = in_array($this->getStr('filtro'), ['publicados', 'borradores'], true)
            ? $this->getStr('filtro') : 'todos';

        // Cada quien trabaja sobre lo de su pastoral y su sede, más lo general de
        // la parroquia; lo de otra pastoral o de otra comunidad no sale en este
        // listado. Para ver todo lo que hay programado —que es lo que hace falta
        // para no pisarse el salón— está la agenda interna, que no recorta nada.
        $pastorales = $this->pastoralesDelFiltro();
        $centros    = $this->centrosDelFiltro();
        [$filtroPastoral, $idsPastoral] = $this->filtroPastoral($pastorales);
        [$filtroCentro,   $idsCentro]   = $this->filtroCentro($centros);
        $idsPastoral = $this->pastoralesVisibles($idsPastoral);
        $idsCentro   = $this->centrosVisibles($idsCentro);
        $anios = $this->modelo->aniosConEventos($idsPastoral, $idsCentro);

        // Un valor fuera de rango se ignora y el listado vuelve a mostrarlo todo:
        // es un filtro, no una búsqueda que deba fallar con un error.
        $anio = $this->getInt('anio', 0);
        $anio = in_array($anio, $anios, true) ? $anio : null;
        $mes  = $this->getInt('mes', 0);
        $mes  = ($mes >= 1 && $mes <= 12) ? $mes : null;
        $dia  = $this->getInt('dia', 0);
        $dia  = ($dia >= 1 && $dia <= 31) ? $dia : null;

        // Un 31 de febrero no existe: se descarta el día y queda el mes, que es
        // lo que la persona tenía delante antes de cambiar de mes en el selector.
        if ($dia !== null && $mes !== null && !checkdate($mes, $dia, $anio ?? 2000)) {
            $dia = null;
        }

        $this->render('eventos/lista', [
            'titulo'  => 'Eventos',
            'listado' => $this->modelo->listar(
                max(1, $this->getInt('pagina', 1)),
                $filtro,
                $idsPastoral,
                $idsCentro,
                $anio,
                $mes,
                $dia
            ),
            'filtro'          => $filtro,
            'anio'            => $anio,
            'mes'             => $mes,
            'dia'             => $dia,
            'anios'           => $anios,
            'diasDelMes'      => $this->diasDelMes($anio, $mes),
            'descripcionFecha' => $this->descripcionFecha($anio, $mes, $dia),
            'pastorales'      => $pastorales,
            'filtroPastoral'  => $filtroPastoral,
            'centros'         => $centros,
            'filtroCentro'    => $filtroCentro,
            'tieneAlcance'    => Auth::pastoralesPermitidas() !== [],
        ]);
    }

    // ── Calendario y propuestas ─────────────────────────────────────────────

    /** Color de un evento sin color propio: el mismo azul del sitio y de la agenda. */
    private const COLOR_EVENTO = '#1e4d8b';

    /**
     * Los eventos en calendario, con la misma cuadrícula que el sitio (día,
     * semana, mes y año) pero con los borradores a la vista, con borde
     * punteado. Es la Agenda recortada a eventos y con un filtro de estado
     * de servidor: la Agenda junta cursos y solo deja ocultar borradores en
     * pantalla, y aquí se quiere poder quedarse con «solo lo propuesto».
     *
     * Como la Agenda, no recorta por alcance: mirar el calendario completo es
     * lo que evita que dos pastorales tomen el mismo salón. El enlace a
     * editar solo se dibuja sobre lo propio (urlDeEdicion()).
     */
    public function calendario(): void
    {
        $this->requirePermiso('eventos.ver');

        $estado     = $this->estadoSolicitado('todos');
        $pastorales = (new PastoralModel())->paraSelector();
        $centros    = (new CentroModel())->activos();
        [$filtroPastoral, $idsPastoral] = $this->filtroPastoral($pastorales);
        [$filtroCentro,   $idsCentro]   = $this->filtroCentro($centros);

        $vista = Calendario::vistaSolicitada($_GET);
        $ancla = Calendario::fechaSolicitada($_GET);
        [$desde, $hasta] = Calendario::limites($vista, $ancla);

        $items = array_map(
            [$this, 'itemDeCalendario'],
            $this->delEstado($this->modelo->agenda($desde, $hasta, $idsPastoral, $idsCentro), $estado)
        );
        $porDia  = Calendario::repartirPorDia($items, $desde, $hasta);
        $comunes = ($estado !== 'todos' ? ['estado' => $estado] : [])
                 + ($filtroPastoral !== '' ? ['pastoral' => $filtroPastoral] : [])
                 + ($filtroCentro !== '' ? ['centro' => $filtroCentro] : []);

        $urlDe = static fn (string $v, DateTimeImmutable $f): string =>
            url_admin('eventos', 'calendario', ['vista' => $v, 'fecha' => $f->format('Y-m-d')] + $comunes);
        $urlsVista = [];
        foreach (array_keys(Calendario::VISTAS) as $unaVista) {
            $urlsVista[$unaVista] = $urlDe($unaVista, $ancla);
        }

        $this->render('eventos/calendario', [
            'titulo'         => 'Eventos — Calendario',
            'calRuta'        => ['eventos', 'calendario'],
            'vista'          => $vista,
            'vistas'         => Calendario::VISTAS,
            'fecha'          => $ancla->format('Y-m-d'),
            'periodo'        => Calendario::titulo($vista, $ancla),
            'dias'           => Calendario::diasDelPeriodo($vista, $desde, $hasta, $porDia),
            'semanas'        => $vista === 'mes'  ? Calendario::cuadriculaDelMes($ancla, $porDia)  : [],
            'meses'          => $vista === 'anio' ? Calendario::cuadriculaDelAnio($ancla, $porDia) : [],
            // Eventos distintos, no días ocupados.
            'total'          => count($items),
            'urlAnterior'    => $urlDe($vista, Calendario::desplazar($vista, $ancla, -1)),
            'urlSiguiente'   => $urlDe($vista, Calendario::desplazar($vista, $ancla, 1)),
            'urlHoy'         => $urlDe($vista, new DateTimeImmutable('today')),
            'urlsVista'      => $urlsVista,
            'incluyeHoy'     => date('Y-m-d') >= $desde && date('Y-m-d') <= $hasta,
            'comunes'        => $comunes,
            'estado'         => $estado,
            'pastorales'     => $pastorales,
            'filtroPastoral' => $filtroPastoral,
            'centros'        => $centros,
            'filtroCentro'   => $filtroCentro,
            'tieneAlcance'   => Auth::pastoralesPermitidas() !== [],
        ]);
    }

    /**
     * Lo que cada pastoral tiene programado para un año —por omisión el que
     * viene—, en una tabla de pastorales por meses. Es la pantalla para
     * revisar el calendario del año siguiente: las pastorales capturan sus
     * fechas como borrador y aquí se ven todas juntas, con las que todavía
     * no han propuesto nada.
     *
     * Quien tiene alcance global ve todas las pastorales; una coordinación,
     * solo las suyas (y no la lista de quién falta, que no le toca).
     */
    public function propuestas(): void
    {
        $this->requirePermiso('eventos.ver');

        $hoy    = (int) date('Y');
        $anio   = $this->getInt('anio', $hoy + 1);
        $anio   = ($anio >= $hoy - 1 && $anio <= $hoy + 3) ? $anio : $hoy + 1;
        $estado = $this->estadoSolicitado('borradores');

        $centros = (new CentroModel())->activos();
        [$filtroCentro, $idsCentro] = $this->filtroCentro($centros);

        $global     = Auth::tieneAlcanceGlobal();
        $permitidas = $global ? null : Auth::pastoralesPermitidas();

        $eventos = $this->delEstado(
            $this->modelo->agenda($anio . '-01-01', $anio . '-12-31', $permitidas, $idsCentro),
            $estado
        );

        // [pastoral_id, o 0 para lo general de la parroquia][mes] => eventos.
        $celdas = [];
        foreach ($eventos as $evento) {
            // Un evento que empezó en diciembre del año anterior y sigue en
            // enero se pinta en enero, que es donde cae dentro de este año.
            $mes = (int) substr((string) $evento['fecha_inicio'], 0, 4) < $anio
                ? 1
                : (int) substr((string) $evento['fecha_inicio'], 5, 2);
            $evento['url'] = $this->urlDeEdicion($evento);
            $celdas[(int) ($evento['pastoral_id'] ?? 0)][$mes][] = $evento;
        }

        $agrupado = (new PastoralModel())->agrupadoVisible($permitidas);

        // Las pastorales activas que no tienen nada en el año. Solo para quien
        // ve todas: a una coordinación no le toca llevar esa cuenta. Las
        // Comisiones no cuentan —casi nunca programan a su nombre, lo hacen
        // sus dimensiones—, y contarlas como «faltan» sería ruido.
        $sinPropuestas = [];
        if ($global) {
            $hojas = $agrupado['sueltas'];
            foreach ($agrupado['comisiones'] as $grupo) {
                array_push($hojas, ...$grupo['hijas']);
            }
            foreach ($hojas as $pastoral) {
                if (!empty($pastoral['activa']) && empty($celdas[(int) $pastoral['id']])) {
                    $sinPropuestas[] = $pastoral;
                }
            }
            usort($sinPropuestas, static fn (array $a, array $b): int => strcmp($a['nombre'], $b['nombre']));
        }

        $this->render('eventos/propuestas', [
            'titulo'        => 'Eventos — Propuestas ' . $anio,
            'anio'          => $anio,
            'anios'         => range($hoy - 1, $hoy + 3),
            'estado'        => $estado,
            'centros'       => $centros,
            'filtroCentro'  => $filtroCentro,
            'agrupado'      => $agrupado,
            'celdas'        => $celdas,
            'global'        => $global,
            'total'         => count($eventos),
            'conPropuestas' => count(array_filter(array_keys($celdas), static fn (int $id): bool => $id !== 0)),
            'sinPropuestas' => $sinPropuestas,
        ]);
    }

    /** ?estado= todos | publicados | borradores; cualquier otra cosa, el de omisión. */
    private function estadoSolicitado(string $omision): string
    {
        $estado = $this->getStr('estado');
        return in_array($estado, ['todos', 'publicados', 'borradores'], true) ? $estado : $omision;
    }

    private function delEstado(array $eventos, string $estado): array
    {
        if ($estado === 'todos') {
            return $eventos;
        }
        $quierePublicados = $estado === 'publicados';
        return array_values(array_filter(
            $eventos,
            static fn (array $e): bool => (bool) $e['publicado'] === $quierePublicados
        ));
    }

    /** Un evento de EventoModel::agenda() con la forma que esperan las vistas del calendario. */
    private function itemDeCalendario(array $evento): array
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
            'estado'          => $evento['publicado'] ? 'publico' : 'borrador',
            'pastoral_id'     => $evento['pastoral_id'] !== null ? (int) $evento['pastoral_id'] : null,
            'pastoral_nombre' => $evento['pastoral_nombre'],
            'centro_nombre'   => $evento['centro_nombre'],
            'url'             => $this->urlDeEdicion($evento),
        ];
    }

    /**
     * El formulario del evento, o null si quien mira no puede editarlo: un
     * enlace que acaba en «no tienes permiso» es peor que no tener enlace. La
     * misma regla que AgendaController::urlDeEdicion().
     */
    private function urlDeEdicion(array $evento): ?string
    {
        $puede = Auth::tienePermiso('eventos.editar')
              && Auth::puedeSobrePastoral($evento['pastoral_id'] !== null ? (int) $evento['pastoral_id'] : null)
              && Auth::puedeSobreCentro($evento['centro_id'] !== null ? (int) $evento['centro_id'] : null);

        return $puede ? url_admin('eventos', 'editar', ['id' => (int) $evento['id']]) : null;
    }

    /**
     * Cuántos días ofrecer en el selector: los del mes elegido, o 31 mientras no
     * haya mes. 2000 es bisiesto, así que sin año un 29 de febrero sigue siendo
     * elegible.
     */
    private function diasDelMes(?int $anio, ?int $mes): int
    {
        if ($mes === null) {
            return 31;
        }
        return (int) date('t', (int) mktime(0, 0, 0, $mes, 1, $anio ?? 2000));
    }

    /** «el 21 de noviembre de 2026», «en noviembre de cualquier año»… */
    private function descripcionFecha(?int $anio, ?int $mes, ?int $dia): string
    {
        if ($anio === null && $mes === null && $dia === null) {
            return '';
        }

        $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
                  'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        $nombreMes = $mes !== null ? $meses[$mes - 1] : null;

        // Sin mes, el día solo se puede decir en plural («los días 16»), y no se
        // le añade «de cualquier año» porque ya sonaría a trabalenguas.
        if ($dia !== null && $nombreMes === null) {
            return "los días {$dia}" . ($anio !== null ? " de {$anio}" : '');
        }
        if ($nombreMes === null) {
            return "en {$anio}";
        }

        $texto = $dia !== null ? "el {$dia} de {$nombreMes}" : "en {$nombreMes}";
        return $texto . ($anio !== null ? " de {$anio}" : ' de cualquier año');
    }

    public function nuevo(): void
    {
        $this->requirePermiso('eventos.crear');

        $this->render('eventos/form', array_merge($this->opcionesPastoral(), $this->opcionesCentro(), [
            'titulo'                    => 'Nuevo evento',
            'evento'                    => null,
            'pastoralIdPreseleccionado' => $this->pastoralIdPreseleccionado(),
            'scriptExtra'               => $this->scriptEditor(),
        ]));
    }

    public function editar(): void
    {
        $this->requirePermiso('eventos.editar');

        $evento = $this->modelo->porId($this->getInt('id'));
        if (!$evento) {
            Session::flash('error', 'No encontramos ese evento.');
            $this->redirect(url_admin('eventos'));
            return;
        }
        $this->requireAlcanceContenido(
            $evento['pastoral_id'] !== null ? (int) $evento['pastoral_id'] : null,
            $evento['centro_id'] !== null ? (int) $evento['centro_id'] : null
        );

        $this->render('eventos/form', array_merge($this->opcionesPastoral(), $this->opcionesCentro(), [
            'titulo'      => $evento['titulo'],
            'evento'      => $evento,
            'scriptExtra' => $this->scriptEditor(),
        ]));
    }

    public function guardar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('eventos'));
            return;
        }
        $this->validarCsrf();

        $id        = $this->postInt('id');
        $existente = $id ? $this->modelo->porId($id) : null;
        $this->requirePermiso($existente ? 'eventos.editar' : 'eventos.crear');
        if ($existente) {
            $this->requireAlcanceContenido(
                $existente['pastoral_id'] !== null ? (int) $existente['pastoral_id'] : null,
                $existente['centro_id'] !== null ? (int) $existente['centro_id'] : null
            );
        }

        $titulo = $this->postStr('titulo');
        $inicio = $this->postStr('fecha_inicio');

        if ($titulo === '' || $inicio === '') {
            Session::flash('error', 'El evento necesita título y fecha de inicio.');
            $this->redirect($id ? url_admin('eventos', 'editar', ['id' => $id]) : url_admin('eventos', 'nuevo'));
            return;
        }

        try {
            $pastoralId = $this->pastoralIdValidado();
            $centroId   = $this->centroIdValidado();
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect($id ? url_admin('eventos', 'editar', ['id' => $id]) : url_admin('eventos', 'nuevo'));
            return;
        }

        $slugPedido = $this->postStr('slug');
        $slug = $slugPedido !== ''
            ? Slug::unico($slugPedido, 'eventos', $id ?: null)
            : ($existente ? $existente['slug'] : Slug::unico($titulo, 'eventos'));

        $imagen = $existente['imagen'] ?? null;
        try {
            $imagen = $this->procesarImagen($imagen);
        } catch (RuntimeException $e) {
            Session::flash('warning', 'El evento se guardó, pero la imagen no: ' . $e->getMessage());
        }

        $fin = $this->postStr('fecha_fin');

        $puedePublicar = Auth::tienePermiso('eventos.publicar');
        $publicado = $puedePublicar ? $this->postBool('publicado') : ($existente['publicado'] ?? 0);

        $datos = [
            'slug'         => $slug,
            'titulo'       => $titulo,
            'descripcion'  => SanitizadorHtml::limpiar($this->postHtml('descripcion')) ?: null,
            'imagen'       => $imagen,
            'lugar'        => $this->postStr('lugar') ?: null,
            'fecha_inicio' => str_replace('T', ' ', $inicio) . ':00',
            'fecha_fin'    => $fin !== '' ? str_replace('T', ' ', $fin) . ':00' : null,
            'todo_el_dia'  => $this->postBool('todo_el_dia'),
            'pastoral_id'  => $pastoralId,
            'centro_id'    => $centroId,
            'color'        => $this->postStr('color') ?: '#1e4d8b',
            'publicado'    => $publicado,
        ];

        if ($existente) {
            $this->modelo->actualizar($id, $datos);
            $this->auditoria('editar', 'eventos', $id, $titulo);
            if (!Session::hayFlash()) { Session::flash('success', 'Evento actualizado.'); }
        } else {
            $id = $this->modelo->crear($datos, (int) Auth::usuario()['id']);
            $this->auditoria('crear', 'eventos', $id, $titulo);
            Session::flash('success', $puedePublicar ? 'Evento creado.' : 'Evento enviado como borrador para revisión.');
        }

        $this->redirect(url_admin('eventos'));
    }

    public function eliminar(): void
    {
        $this->requirePermiso('eventos.eliminar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('eventos'));
            return;
        }
        $this->validarCsrf();

        $id     = $this->postInt('id');
        $evento = $this->modelo->porId($id);
        if ($evento) {
            $this->requireAlcanceContenido(
                $evento['pastoral_id'] !== null ? (int) $evento['pastoral_id'] : null,
                $evento['centro_id'] !== null ? (int) $evento['centro_id'] : null
            );
            Upload::borrar($evento['imagen']);
            $this->modelo->eliminar($id);
            $this->auditoria('eliminar', 'eventos', $id, $evento['titulo']);
            Session::flash('success', 'Evento eliminado.');
        }

        $this->redirect(url_admin('eventos'));
    }

    private function procesarImagen(?string $actual): ?string
    {
        if (!empty($_POST['imagen_quitar'])) {
            Upload::borrar($actual);
            return null;
        }
        return Upload::imagen('imagen', 'eventos', 'evento', $actual);
    }

    private function scriptEditor(): string
    {
        return '<script src="' . e(url_activo_versionado('assets/js/editor.js')) . '"></script>';
    }
}
