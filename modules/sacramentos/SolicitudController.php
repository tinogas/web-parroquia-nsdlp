<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/modules/sacramentos/SolicitudModel.php';
require_once BASE_PATH . '/modules/sacramentos/SacramentoModel.php';

/**
 * SolicitudController — Bandeja de solicitudes de sacramentos.
 *
 * Trata datos personales, con frecuencia de menores: toda lectura se audita,
 * no solo la escritura. Ver docs/PRIVACIDAD.md
 */
class SolicitudController extends Controller
{
    private SolicitudModel $modelo;

    public function __construct()
    {
        $this->modelo = new SolicitudModel();
    }

    public function index(): void
    {
        $this->requirePermiso('solicitudes.ver');

        $estado       = $this->getStr('estado') ?: 'todos';
        $sacramentoId = $this->getInt('sacramento_id') ?: null;
        $pagina       = max(1, $this->getInt('pagina', 1));

        $this->auditoria('consultar', 'solicitudes_sacramento', 0, "Listado, estado: {$estado}");

        $this->render('sacramentos/solicitudes_lista', [
            'titulo'      => 'Solicitudes de sacramentos',
            'listado'     => $this->modelo->listar($pagina, $estado, $sacramentoId),
            'estado'      => $estado,
            'sacramentoId'=> $sacramentoId,
            'sacramentos' => (new SacramentoModel())->todos(),
        ]);
    }

    public function ver(): void
    {
        $this->requirePermiso('solicitudes.ver');

        $solicitud = $this->modelo->porId($this->getInt('id'));
        if (!$solicitud) {
            Session::flash('error', 'No encontramos esa solicitud.');
            $this->redirect(url_admin('solicitudes'));
            return;
        }

        $this->auditoria('consultar', 'solicitudes_sacramento', (int) $solicitud['id'], $solicitud['folio']);

        $this->render('sacramentos/solicitud_ver', [
            'titulo'    => 'Solicitud ' . $solicitud['folio'],
            'solicitud' => $solicitud,
            'bitacora'  => $this->modelo->bitacora((int) $solicitud['id']),
            'datosExtra'=> $solicitud['datos_extra'] ? json_decode($solicitud['datos_extra'], true) : [],
            'campos'    => (new SacramentoModel())->campos((int) $solicitud['sacramento_id']),
        ]);
    }

    public function cambiarEstado(): void
    {
        $this->requirePermiso('solicitudes.cambiar_estado');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('solicitudes'));
            return;
        }
        $this->validarCsrf();

        $id = $this->postInt('id');
        if (!$this->modelo->porId($id)) {
            Session::flash('error', 'No encontramos esa solicitud.');
            $this->redirect(url_admin('solicitudes'));
            return;
        }

        $estado = $this->postStr('estado');
        if (!isset(SolicitudModel::ESTADOS[$estado])) {
            Session::flash('error', 'Estado no válido.');
            $this->redirect(url_admin('solicitudes', 'ver', ['id' => $id]));
            return;
        }

        $this->modelo->cambiarEstado($id, $estado, $this->postStr('comentario'), (int) Auth::usuario()['id']);
        $this->auditoria('editar', 'solicitudes_sacramento', $id, 'Estado → ' . $estado);

        Session::flash('success', 'Estado actualizado.');
        $this->redirect(url_admin('solicitudes', 'ver', ['id' => $id]));
    }

    /** CSV de la bandeja actual (con los mismos filtros que el listado). */
    public function exportar(): void
    {
        $this->requirePermiso('solicitudes.exportar');

        $estado       = $this->getStr('estado') ?: 'todos';
        $sacramentoId = $this->getInt('sacramento_id') ?: null;

        // El listado normal pagina a 20; para exportar se piden todas las
        // páginas de una vez. Una parroquia no acumula tantas solicitudes
        // como para que esto sea un problema de memoria.
        $primera      = $this->modelo->listar(1, $estado, $sacramentoId);
        $filas        = $primera['filas'];
        $totalPaginas = $primera['total_paginas'];
        for ($p = 2; $p <= $totalPaginas; $p++) {
            $filas = array_merge($filas, $this->modelo->listar($p, $estado, $sacramentoId)['filas']);
        }

        $this->auditoria('exportar', 'solicitudes_sacramento', 0, "CSV, estado: {$estado}, " . count($filas) . ' filas');

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="solicitudes_' . date('Y-m-d') . '.csv"');

        $salida = fopen('php://output', 'w');
        fputs($salida, "\xEF\xBB\xBF"); // BOM: para que Excel detecte UTF-8.
        fputcsv($salida, ['Folio', 'Sacramento', 'Solicitante', 'Es menor', 'Teléfono', 'Correo',
                           'Fecha preferida', 'Estado', 'Atendida por', 'Creada']);
        foreach ($filas as $fila) {
            fputcsv($salida, [
                $fila['folio'],
                $fila['sacramento_nombre'],
                $fila['nombre_solicitante'],
                $fila['es_menor'] ? 'Sí' : 'No',
                $fila['telefono'],
                $fila['email'],
                $fila['fecha_preferida'],
                SolicitudModel::ESTADOS[$fila['estado']] ?? $fila['estado'],
                $fila['atendida_por_nombre'],
                $fila['created_at'],
            ]);
        }
        fclose($salida);
        exit;
    }

    /** Anonimiza las solicitudes cerradas más viejas que el plazo de retención configurado. */
    public function purgar(): void
    {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(url_admin('solicitudes'));
            return;
        }
        $this->validarCsrf();

        $meses = (int) Config::get('retencion_meses_solicitudes', '36');
        $n     = $this->modelo->purgarVencidas(max(1, $meses));
        $this->auditoria('editar', 'solicitudes_sacramento', 0, "Purga: {$n} registro(s) anonimizados");

        Session::flash('success', $n > 0
            ? "Se anonimizaron {$n} solicitud(es) que ya cumplieron el plazo de retención."
            : 'No había solicitudes vencidas para anonimizar.');
        $this->redirect(url_admin('solicitudes'));
    }
}
