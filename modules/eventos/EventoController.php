<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/modules/eventos/EventoModel.php';

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

        $alcance = $this->filtroPastoralSql();
        $anios   = $this->modelo->aniosConEventos($alcance);

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
                $alcance,
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
        ]);
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

        $this->render('eventos/form', array_merge($this->opcionesPastoral(), [
            'titulo'      => 'Nuevo evento',
            'evento'      => null,
            'scriptExtra' => $this->scriptEditor(),
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
        $this->requireAlcancePastoral($evento['pastoral_id'] !== null ? (int) $evento['pastoral_id'] : null);

        $this->render('eventos/form', array_merge($this->opcionesPastoral(), [
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
            $this->requireAlcancePastoral($existente['pastoral_id'] !== null ? (int) $existente['pastoral_id'] : null);
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
            $this->requireAlcancePastoral($evento['pastoral_id'] !== null ? (int) $evento['pastoral_id'] : null);
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
        return '<script src="' . e(url_activo('assets/js/editor.js')) . '?v=' . e(APP_VERSION) . '"></script>';
    }
}
