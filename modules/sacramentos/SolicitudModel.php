<?php
require_once BASE_PATH . '/core/Model.php';

/**
 * SolicitudModel — Solicitudes de sacramentos.
 *
 * La tabla más delicada del sistema: datos personales, con frecuencia de
 * menores. Ver docs/PRIVACIDAD.md antes de tocar este archivo.
 */
class SolicitudModel extends Model
{
    public const ESTADOS = [
        'pendiente'   => 'Pendiente',
        'en_revision' => 'En revisión',
        'aprobada'    => 'Aprobada',
        'rechazada'   => 'Rechazada',
        'cancelada'   => 'Cancelada',
        'completada'  => 'Completada',
    ];

    /** Estados que ya no requieren seguimiento activo, elegibles para anonimizar tras el plazo de retención. */
    private const ESTADOS_CERRADOS = ['rechazada', 'cancelada', 'completada'];

    public function listar(int $pagina, string $estado = 'todos', ?int $sacramentoId = null): array
    {
        $condiciones = [];
        $params      = [];

        if (isset(self::ESTADOS[$estado])) {
            $condiciones[] = 's.estado = :estado';
            $params[':estado'] = $estado;
        }
        if ($sacramentoId) {
            $condiciones[] = 's.sacramento_id = :sac';
            $params[':sac'] = $sacramentoId;
        }
        $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        return $this->paginar(
            "SELECT s.*, sa.nombre AS sacramento_nombre, u.nombre AS atendida_por_nombre
               FROM solicitudes_sacramento s
               JOIN sacramentos sa ON sa.id = s.sacramento_id
               LEFT JOIN usuarios u ON u.id = s.atendida_por
               {$where}
              ORDER BY s.created_at DESC",
            $params,
            $pagina,
            20
        );
    }

    public function porId(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT s.*, sa.nombre AS sacramento_nombre, sa.slug AS sacramento_slug
               FROM solicitudes_sacramento s
               JOIN sacramentos sa ON sa.id = s.sacramento_id
              WHERE s.id = :id',
            [':id' => $id]
        );
    }

    public function contarPendientes(): int
    {
        return (int) $this->fetchColumn(
            "SELECT COUNT(*) FROM solicitudes_sacramento WHERE estado IN ('pendiente', 'en_revision')"
        );
    }

    /**
     * Crea la solicitud y devuelve su folio. El folio usa el prefijo de las
     * primeras 3 letras del slug del sacramento en mayúsculas.
     */
    public function crear(array $datos): string
    {
        $folio = $this->generarFolio('solicitudes_sacramento', $datos['prefijo_folio']);

        $this->execute(
            'INSERT INTO solicitudes_sacramento
                (folio, sacramento_id, nombre_solicitante, fecha_nacimiento, es_menor,
                 telefono, email, direccion, tutor_nombre, tutor_parentesco, tutor_telefono,
                 fecha_preferida, notas, datos_extra, consentimiento, consentimiento_ip,
                 aviso_version, origen)
             VALUES
                (:folio, :sacramento, :nombre, :fechaNac, :esMenor,
                 :telefono, :email, :direccion, :tutorNombre, :tutorParentesco, :tutorTelefono,
                 :fechaPref, :notas, :datosExtra, :consentimiento, :consentimientoIp,
                 :avisoVersion, :origen)',
            [
                ':folio'            => $folio,
                ':sacramento'       => $datos['sacramento_id'],
                ':nombre'           => $datos['nombre_solicitante'],
                ':fechaNac'         => $datos['fecha_nacimiento'] ?: null,
                ':esMenor'          => $datos['es_menor'],
                ':telefono'         => $datos['telefono'] ?: null,
                ':email'            => $datos['email'] ?: null,
                ':direccion'        => $datos['direccion'] ?: null,
                ':tutorNombre'      => $datos['tutor_nombre'] ?: null,
                ':tutorParentesco'  => $datos['tutor_parentesco'] ?: null,
                ':tutorTelefono'    => $datos['tutor_telefono'] ?: null,
                ':fechaPref'        => $datos['fecha_preferida'] ?: null,
                ':notas'            => $datos['notas'] ?: null,
                ':datosExtra'       => $datos['datos_extra'] !== [] ? json_encode($datos['datos_extra'], JSON_UNESCAPED_UNICODE) : null,
                ':consentimiento'   => 1,
                ':consentimientoIp' => $datos['ip'],
                ':avisoVersion'     => AVISO_VERSION,
                ':origen'           => $datos['origen'] ?? 'web',
            ]
        );

        return $folio;
    }

    public function cambiarEstado(int $id, string $estadoNuevo, string $comentario, int $usuarioId): void
    {
        $actual = $this->porId($id);
        if (!$actual || !isset(self::ESTADOS[$estadoNuevo])) {
            return;
        }

        $this->beginTransaction();
        try {
            $this->execute(
                'UPDATE solicitudes_sacramento
                    SET estado = :estado, motivo_estado = :motivo, atendida_por = :usuario, atendida_at = NOW()
                  WHERE id = :id',
                [':estado' => $estadoNuevo, ':motivo' => $comentario ?: null, ':usuario' => $usuarioId, ':id' => $id]
            );
            $this->execute(
                'INSERT INTO solicitudes_bitacora (solicitud_id, usuario_id, estado_anterior, estado_nuevo, comentario)
                 VALUES (:solicitud, :usuario, :anterior, :nuevo, :comentario)',
                [
                    ':solicitud' => $id,
                    ':usuario'   => $usuarioId,
                    ':anterior'  => $actual['estado'],
                    ':nuevo'     => $estadoNuevo,
                    ':comentario'=> $comentario ?: null,
                ]
            );
            $this->commit();
        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function bitacora(int $solicitudId): array
    {
        return $this->fetchAll(
            'SELECT b.*, u.nombre AS usuario_nombre
               FROM solicitudes_bitacora b
               LEFT JOIN usuarios u ON u.id = b.usuario_id
              WHERE b.solicitud_id = :id
              ORDER BY b.created_at, b.id',
            [':id' => $solicitudId]
        );
    }

    /**
     * Anonimiza las solicitudes cerradas (rechazada/cancelada/completada) más
     * viejas que el plazo de retención: vacía los datos que identifican a la
     * persona y conserva folio, sacramento, estado y fechas para estadística.
     * No es un DELETE: es la única operación de este tipo en el sistema. Ver
     * docs/PRIVACIDAD.md
     */
    public function purgarVencidas(int $mesesRetencion): int
    {
        $marcadores = implode(',', array_fill(0, count(self::ESTADOS_CERRADOS), '?'));
        $params     = self::ESTADOS_CERRADOS;
        $params[]   = $mesesRetencion;

        return $this->execute(
            "UPDATE solicitudes_sacramento
                SET nombre_solicitante = 'Registro anonimizado', fecha_nacimiento = NULL,
                    telefono = NULL, email = NULL, direccion = NULL,
                    tutor_nombre = NULL, tutor_parentesco = NULL, tutor_telefono = NULL,
                    notas = NULL, datos_extra = NULL
              WHERE estado IN ({$marcadores})
                AND nombre_solicitante <> 'Registro anonimizado'
                AND created_at < DATE_SUB(NOW(), INTERVAL ? MONTH)",
            $params
        );
    }
}
