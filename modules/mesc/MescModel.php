<?php
require_once BASE_PATH . '/core/Model.php';

/**
 * MescModel — Visitas a enfermos de los Ministros Extraordinarios de la
 * Comunión (issue #3), y las rutas que se arman a partir de ellas.
 *
 * Dato sensible: la sola presencia de un registro aquí revela que la persona
 * está enferma (LFPDPPP, dato de salud). Por eso este modelo, a diferencia de
 * avisos/eventos/galería, no tiene ninguna consulta pública: todo vive detrás
 * de requirePermiso('mesc.*') + requireAlcancePastoral(). Ver docs/PRIVACIDAD.md.
 */
class MescModel extends Model
{
    // ── Visitas ──────────────────────────────────────────────────────────

    public function listar(int $pagina, string $filtro = 'activas', ?array $pastoralesPermitidas = null): array
    {
        $condiciones = [];
        $params      = [];

        if ($filtro === 'activas') {
            $condiciones[] = 'v.activo = 1';
        } elseif ($filtro === 'inactivas') {
            $condiciones[] = 'v.activo = 0';
        }

        [$condicionPastoral, $paramsPastoral] = $this->condicionPastoral($pastoralesPermitidas, 'v.pastoral_id');
        if ($condicionPastoral !== '') {
            $condiciones[] = $condicionPastoral;
            $params += $paramsPastoral;
        }

        $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        return $this->paginar(
            "SELECT v.*, p.nombre AS pastoral_nombre
               FROM mesc_visitas v
               JOIN pastorales p ON p.id = v.pastoral_id
               {$where}
              ORDER BY v.nombre_enfermo",
            $params,
            $pagina,
            20
        );
    }

    public function porId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM mesc_visitas WHERE id = :id', [':id' => $id]);
    }

    /** Visitas activas visibles para el usuario, sin paginar: para elegir cuáles entran a una ruta. */
    public function activasPara(?array $pastoralesPermitidas): array
    {
        [$condicionPastoral, $params] = $this->condicionPastoral($pastoralesPermitidas, 'pastoral_id');
        $where = 'activo = 1' . ($condicionPastoral !== '' ? " AND {$condicionPastoral}" : '');

        return $this->fetchAll(
            "SELECT * FROM mesc_visitas WHERE {$where} ORDER BY nombre_enfermo",
            $params
        );
    }

    public function crear(array $datos, int $usuarioId): int
    {
        $this->execute(
            'INSERT INTO mesc_visitas
                (pastoral_id, nombre_enfermo, direccion, latitud, longitud, telefono,
                 solicitante_nombre, solicitante_parentesco, solicitante_telefono, notas, activo, usuario_id)
             VALUES
                (:pastoral, :nombre, :direccion, :lat, :lng, :telefono,
                 :solNombre, :solParentesco, :solTelefono, :notas, :activo, :usuario)',
            $this->parametros($datos) + [':usuario' => $usuarioId]
        );
        return $this->lastInsertId();
    }

    public function actualizar(int $id, array $datos): int
    {
        return $this->execute(
            'UPDATE mesc_visitas
                SET pastoral_id = :pastoral, nombre_enfermo = :nombre, direccion = :direccion,
                    latitud = :lat, longitud = :lng, telefono = :telefono,
                    solicitante_nombre = :solNombre, solicitante_parentesco = :solParentesco,
                    solicitante_telefono = :solTelefono, notas = :notas, activo = :activo,
                    updated_at = NOW()
              WHERE id = :id',
            $this->parametros($datos) + [':id' => $id]
        );
    }

    public function eliminar(int $id): int
    {
        return $this->execute('DELETE FROM mesc_visitas WHERE id = :id', [':id' => $id]);
    }

    private function parametros(array $datos): array
    {
        return [
            ':pastoral'      => $datos['pastoral_id'],
            ':nombre'        => $datos['nombre_enfermo'],
            ':direccion'     => $datos['direccion'],
            ':lat'           => $datos['latitud'],
            ':lng'           => $datos['longitud'],
            ':telefono'      => $datos['telefono'],
            ':solNombre'     => $datos['solicitante_nombre'],
            ':solParentesco' => $datos['solicitante_parentesco'],
            ':solTelefono'   => $datos['solicitante_telefono'],
            ':notas'         => $datos['notas'],
            ':activo'        => $datos['activo'],
        ];
    }

    // ── Rutas ────────────────────────────────────────────────────────────

    public function rutasDe(?array $pastoralesPermitidas): array
    {
        [$condicionPastoral, $params] = $this->condicionPastoral($pastoralesPermitidas, 'r.pastoral_id');
        $where = $condicionPastoral !== '' ? "WHERE {$condicionPastoral}" : '';

        return $this->fetchAll(
            "SELECT r.*, p.nombre AS pastoral_nombre, u.nombre AS autor,
                    (SELECT COUNT(*) FROM mesc_ruta_visitas rv WHERE rv.ruta_id = r.id) AS num_visitas
               FROM mesc_rutas r
               JOIN pastorales p ON p.id = r.pastoral_id
               LEFT JOIN usuarios u ON u.id = r.usuario_id
               {$where}
              ORDER BY r.created_at DESC",
            $params
        );
    }

    public function rutaPorId(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT r.*, p.nombre AS pastoral_nombre FROM mesc_rutas r
               JOIN pastorales p ON p.id = r.pastoral_id
              WHERE r.id = :id',
            [':id' => $id]
        );
    }

    /** Visitas de una ruta, en su orden actual (editable). */
    public function visitasDeRuta(int $rutaId): array
    {
        return $this->fetchAll(
            'SELECT v.*, rv.orden
               FROM mesc_ruta_visitas rv
               JOIN mesc_visitas v ON v.id = rv.visita_id
              WHERE rv.ruta_id = :ruta
              ORDER BY rv.orden',
            [':ruta' => $rutaId]
        );
    }

    /**
     * Crea la ruta con las visitas dadas, en el orden en que vienen en el
     * arreglo (ya calculado por ordenSugerido() o el orden que el usuario
     * eligió a mano en el formulario).
     */
    public function crearRuta(int $pastoralId, string $nombre, array $visitaIdsEnOrden, int $usuarioId): int
    {
        $this->beginTransaction();
        try {
            $this->execute(
                'INSERT INTO mesc_rutas (pastoral_id, nombre, usuario_id) VALUES (:pastoral, :nombre, :usuario)',
                [':pastoral' => $pastoralId, ':nombre' => $nombre, ':usuario' => $usuarioId]
            );
            $rutaId = $this->lastInsertId();
            foreach (array_values($visitaIdsEnOrden) as $orden => $visitaId) {
                $this->execute(
                    'INSERT INTO mesc_ruta_visitas (ruta_id, visita_id, orden) VALUES (:ruta, :visita, :orden)',
                    [':ruta' => $rutaId, ':visita' => $visitaId, ':orden' => $orden]
                );
            }
            $this->commit();
            return $rutaId;
        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    /** Reordena una ruta existente. $ordenPorVisitaId: visita_id => nuevo orden. */
    public function reordenarRuta(int $rutaId, array $ordenPorVisitaId): void
    {
        foreach ($ordenPorVisitaId as $visitaId => $orden) {
            $this->execute(
                'UPDATE mesc_ruta_visitas SET orden = :orden WHERE ruta_id = :ruta AND visita_id = :visita',
                [':orden' => $orden, ':ruta' => $rutaId, ':visita' => $visitaId]
            );
        }
    }

    public function eliminarRuta(int $id): int
    {
        return $this->execute('DELETE FROM mesc_rutas WHERE id = :id', [':id' => $id]);
    }

    /**
     * Orden sugerido por vecino más cercano (heurística greedy sobre
     * distancia Haversine), partiendo de $origen si se da (normalmente la
     * parroquia). Las visitas sin coordenadas —solo dirección a mano— no
     * tienen forma de ordenarse por cercanía, así que se agregan al final,
     * en el orden en que llegaron. Esto es una aproximación en línea recta,
     * no una ruta real por calles: ver el comentario en install.sql.
     *
     * @param array $visitas filas de mesc_visitas
     * @param array{lat: float, lng: float}|null $origen
     * @return array visitas reordenadas
     */
    public function ordenSugerido(array $visitas, ?array $origen = null): array
    {
        $conCoordenadas = array_values(array_filter(
            $visitas,
            static fn (array $v): bool => $v['latitud'] !== null && $v['longitud'] !== null
        ));
        $sinCoordenadas = array_values(array_filter(
            $visitas,
            static fn (array $v): bool => $v['latitud'] === null || $v['longitud'] === null
        ));

        $ordenadas = [];
        $actual    = $origen;
        $restantes = $conCoordenadas;

        while ($restantes) {
            if ($actual === null) {
                $siguiente = array_shift($restantes);
            } else {
                $indiceMasCercano = 0;
                $distanciaMinima  = INF;
                foreach ($restantes as $i => $visita) {
                    $distancia = self::distanciaHaversine(
                        (float) $actual['lat'], (float) $actual['lng'],
                        (float) $visita['latitud'], (float) $visita['longitud']
                    );
                    if ($distancia < $distanciaMinima) {
                        $distanciaMinima  = $distancia;
                        $indiceMasCercano = $i;
                    }
                }
                $siguiente = $restantes[$indiceMasCercano];
                array_splice($restantes, $indiceMasCercano, 1);
            }
            $ordenadas[] = $siguiente;
            $actual      = ['lat' => (float) $siguiente['latitud'], 'lng' => (float) $siguiente['longitud']];
        }

        return array_merge($ordenadas, $sinCoordenadas);
    }

    /** Distancia en línea recta entre dos puntos, en kilómetros. */
    public static function distanciaHaversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $radioTierra = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $radioTierra * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
