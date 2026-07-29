<?php
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/modules/auditoria/AuditoriaModel.php';

/**
 * AuditoriaController — Bandeja de solo lectura de la bitácora del sistema.
 *
 * A diferencia de solicitudes e inscripciones, aquí no hay una pantalla de
 * detalle que audite su propia consulta: el listado YA ES la auditoría, y
 * dejar una fila por cada vez que alguien lo revisa no aporta nada. Solo la
 * exportación queda registrada, igual que en el resto del panel.
 */
class AuditoriaController extends Controller
{
    private AuditoriaModel $modelo;

    public function __construct()
    {
        $this->modelo = new AuditoriaModel();
    }

    public function index(): void
    {
        $this->requirePermiso('auditoria.ver');

        $filtros = $this->filtrosDesdeGet();

        $this->render('auditoria/lista', [
            'titulo'   => 'Auditoría',
            'listado'  => $this->modelo->listar(max(1, $this->getInt('pagina', 1)), $filtros),
            'filtros'  => $filtros,
            'usuarios' => $this->modelo->usuariosConActividad(),
            'acciones' => $this->modelo->accionesUsadas(),
            'tablas'   => $this->modelo->tablasUsadas(),
        ]);
    }

    public function exportar(): void
    {
        $this->requirePermiso('auditoria.exportar');

        $filtros      = $this->filtrosDesdeGet();
        $primera      = $this->modelo->listar(1, $filtros);
        $filas        = $primera['filas'];
        $totalPaginas = $primera['total_paginas'];
        for ($p = 2; $p <= $totalPaginas; $p++) {
            $filas = array_merge($filas, $this->modelo->listar($p, $filtros)['filas']);
        }

        $this->auditoria('exportar', 'auditoria', 0, 'CSV, ' . count($filas) . ' filas');

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="auditoria_' . date('Y-m-d') . '.csv"');

        $salida = fopen('php://output', 'w');
        fputs($salida, "\xEF\xBB\xBF");
        fputcsv($salida, ['Fecha', 'Usuario', 'Actuando como (admin)', 'Acción', 'Tabla', 'Registro', 'IP', 'Descripción']);
        foreach ($filas as $fila) {
            fputcsv($salida, [
                $fila['created_at'],
                $fila['usuario_nombre'] ?? '—',
                $fila['admin_real_nombre'] ?? '',
                $fila['accion'],
                $fila['tabla_ref'] ?? '',
                $fila['registro_id'] ?? '',
                $fila['ip'] ?? '',
                $fila['descripcion'] ?? '',
            ]);
        }
        fclose($salida);
        exit;
    }

    /**
     * El filtro de acción NO puede llamarse "accion" en la URL: ese nombre ya
     * lo usa el propio Router para elegir el método a invocar
     * (?accion=crear intentaría llamar a Controller::crear(), que no existe,
     * y devolvería 404 en vez del listado filtrado). Por eso el parámetro
     * público es "tipo_accion", aunque la columna de la tabla se llame accion.
     */
    private function filtrosDesdeGet(): array
    {
        return [
            'usuario_id'  => $this->getInt('usuario_id') ?: null,
            'tipo_accion' => $this->getStr('tipo_accion') ?: null,
            'tabla_ref'   => $this->getStr('tabla_ref') ?: null,
            'desde'       => $this->getStr('desde') ?: null,
            'hasta'       => $this->getStr('hasta') ?: null,
        ];
    }
}
