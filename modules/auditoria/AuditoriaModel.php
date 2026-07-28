<?php
require_once BASE_PATH . '/core/Model.php';

/**
 * AuditoriaModel — Lectura de la bitácora. Nadie escribe aquí directamente:
 * toda fila la inserta Controller::auditoria(), desde cualquier módulo.
 */
class AuditoriaModel extends Model
{
    public function listar(int $pagina, array $filtros): array
    {
        $condiciones = [];
        $params      = [];

        if (!empty($filtros['usuario_id'])) {
            $condiciones[] = 'a.usuario_id = :usuario_id';
            $params[':usuario_id'] = (int) $filtros['usuario_id'];
        }
        if (!empty($filtros['tipo_accion'])) {
            $condiciones[] = 'a.accion = :accion';
            $params[':accion'] = $filtros['tipo_accion'];
        }
        if (!empty($filtros['tabla_ref'])) {
            $condiciones[] = 'a.tabla_ref = :tabla_ref';
            $params[':tabla_ref'] = $filtros['tabla_ref'];
        }
        if (!empty($filtros['desde'])) {
            $condiciones[] = 'a.created_at >= :desde';
            $params[':desde'] = $filtros['desde'] . ' 00:00:00';
        }
        if (!empty($filtros['hasta'])) {
            $condiciones[] = 'a.created_at <= :hasta';
            $params[':hasta'] = $filtros['hasta'] . ' 23:59:59';
        }

        $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        return $this->paginar(
            "SELECT a.*, u.nombre AS usuario_nombre
               FROM auditoria a
               LEFT JOIN usuarios u ON u.id = a.usuario_id
               {$where}
              ORDER BY a.created_at DESC",
            $params,
            $pagina,
            30
        );
    }

    /** Para el selector de usuario del filtro: solo quienes tienen alguna fila. */
    public function usuariosConActividad(): array
    {
        return $this->fetchAll(
            'SELECT DISTINCT u.id, u.nombre
               FROM auditoria a
               JOIN usuarios u ON u.id = a.usuario_id
              ORDER BY u.nombre'
        );
    }

    /** Para el selector de acción del filtro: solo las que realmente ocurrieron. */
    public function accionesUsadas(): array
    {
        return array_column(
            $this->fetchAll('SELECT DISTINCT accion FROM auditoria ORDER BY accion'),
            'accion'
        );
    }

    /** Para el selector de tabla del filtro: solo las que realmente tienen registros. */
    public function tablasUsadas(): array
    {
        return array_column(
            $this->fetchAll(
                "SELECT DISTINCT tabla_ref FROM auditoria WHERE tabla_ref IS NOT NULL ORDER BY tabla_ref"
            ),
            'tabla_ref'
        );
    }
}
