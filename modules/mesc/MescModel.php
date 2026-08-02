<?php
require_once BASE_PATH . '/core/Model.php';

/**
 * MescModel — Visitas a enfermos de los Ministros Extraordinarios de la
 * Comunión (issue #3), las rutas que se arman a partir de ellas, sus
 * ministros y sus turnos.
 *
 * Exclusivo de la pastoral "Ministro Extraordinario de la Sagrada Comunión":
 * pastoralId() la resuelve por su slug (no por un id fijo en PHP: los id se
 * generan al crear la pastoral desde el panel, no se siembran en
 * install.sql), igual que CatequesisModel/LectorModel (revisión de módulos:
 * MESC era la excepción con selector multi-pastoral, y por eso dejaba
 * agregar ministros o visitas bajo cualquier otra pastoral que administrara
 * el usuario).
 *
 * Dato sensible: la sola presencia de un registro aquí revela que la persona
 * está enferma (LFPDPPP, dato de salud). Por eso este modelo, a diferencia de
 * avisos/eventos/galería, no tiene ninguna consulta pública: todo vive detrás
 * de requirePermiso('mesc.*') + requireAlcancePastoral(). Ver docs/PRIVACIDAD.md.
 */
class MescModel extends Model
{
    public function pastoralId(): ?int
    {
        return $this->fetchColumn(
            'SELECT id FROM pastorales WHERE slug = :slug',
            [':slug' => PASTORAL_MESC]
        ) ?: null;
    }

    // ── Visitas ──────────────────────────────────────────────────────────

    public function listar(int $pagina, string $filtro, int $pastoralId): array
    {
        $condiciones = ['v.pastoral_id = :pastoral'];
        $params      = [':pastoral' => $pastoralId];

        if ($filtro === 'activas') {
            $condiciones[] = 'v.activo = 1';
        } elseif ($filtro === 'inactivas') {
            $condiciones[] = 'v.activo = 0';
        }

        $where = 'WHERE ' . implode(' AND ', $condiciones);

        return $this->paginar(
            "SELECT v.* FROM mesc_visitas v {$where} ORDER BY v.nombre_enfermo",
            $params,
            $pagina,
            20
        );
    }

    public function porId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM mesc_visitas WHERE id = :id', [':id' => $id]);
    }

    /** Visitas activas de la pastoral, sin paginar: para elegir cuáles entran a una ruta. */
    public function activasPara(int $pastoralId): array
    {
        return $this->fetchAll(
            'SELECT * FROM mesc_visitas WHERE activo = 1 AND pastoral_id = :pastoral ORDER BY nombre_enfermo',
            [':pastoral' => $pastoralId]
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

    public function rutasDe(int $pastoralId): array
    {
        return $this->fetchAll(
            'SELECT r.*, u.nombre AS autor,
                    (SELECT COUNT(*) FROM mesc_ruta_visitas rv WHERE rv.ruta_id = r.id) AS num_visitas
               FROM mesc_rutas r
               LEFT JOIN usuarios u ON u.id = r.usuario_id
              WHERE r.pastoral_id = :pastoral
              ORDER BY r.created_at DESC',
            [':pastoral' => $pastoralId]
        );
    }

    public function rutaPorId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM mesc_rutas WHERE id = :id', [':id' => $id]);
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

    // ── Ministros ────────────────────────────────────────────────────────

    /**
     * Para el catálogo del panel: además del nombre corto propio del módulo,
     * trae el nombre completo de su ficha del equipo pastoral cuando está
     * vinculado (`nombre_completo`, NULL si no lo está). Las dos cosas se
     * listan juntas para poder ver de un vistazo quién es cada «Tino» y a
     * quién le falta ficha.
     */
    public function ministros(int $pastoralId): array
    {
        return $this->fetchAll(
            'SELECT m.*, p.nombre AS nombre_completo
               FROM mesc_ministros m
               LEFT JOIN personas p ON p.id = m.persona_id
              WHERE m.pastoral_id = :id
              ORDER BY m.nombre',
            [':id' => $pastoralId]
        );
    }

    public function ministrosActivos(int $pastoralId): array
    {
        return $this->fetchAll(
            'SELECT * FROM mesc_ministros WHERE pastoral_id = :id AND activo = 1 ORDER BY nombre',
            [':id' => $pastoralId]
        );
    }

    public function ministroPorId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM mesc_ministros WHERE id = :id', [':id' => $id]);
    }

    public function crearMinistro(array $datos): int
    {
        $this->execute(
            'INSERT INTO mesc_ministros (pastoral_id, persona_id, nombre, telefono, activo)
             VALUES (:pastoral, :persona, :nombre, :telefono, :activo)',
            [
                ':pastoral' => $datos['pastoral_id'],
                ':persona'  => $datos['persona_id'],
                ':nombre'   => $datos['nombre'],
                ':telefono' => $datos['telefono'],
                ':activo'   => $datos['activo'],
            ]
        );
        return $this->lastInsertId();
    }

    public function actualizarMinistro(int $id, array $datos): int
    {
        return $this->execute(
            'UPDATE mesc_ministros
                SET persona_id = :persona, nombre = :nombre, telefono = :telefono, activo = :activo
              WHERE id = :id',
            [
                ':persona'  => $datos['persona_id'],
                ':nombre'   => $datos['nombre'],
                ':telefono' => $datos['telefono'],
                ':activo'   => $datos['activo'],
                ':id'       => $id,
            ]
        );
    }

    public function eliminarMinistro(int $id): int
    {
        return $this->execute('DELETE FROM mesc_ministros WHERE id = :id', [':id' => $id]);
    }

    // ── Turnos ───────────────────────────────────────────────────────────

    /** Turnos del mes de la pastoral, con los nombres de sus ministros ya concatenados, para el calendario. */
    public function turnosDelMes(int $anio, int $mes, int $pastoralId): array
    {
        $inicio        = sprintf('%04d-%02d-01', $anio, $mes);
        $siguienteAnio = $mes === 12 ? $anio + 1 : $anio;
        $siguienteMes  = $mes === 12 ? 1 : $mes + 1;
        $fin           = sprintf('%04d-%02d-01', $siguienteAnio, $siguienteMes);

        return $this->fetchAll(
            "SELECT t.*, c.nombre AS color_nombre, c.color_hex,
                    (SELECT GROUP_CONCAT(m.nombre ORDER BY m.nombre SEPARATOR ', ')
                       FROM mesc_turno_ministros tm JOIN mesc_ministros m ON m.id = tm.ministro_id
                      WHERE tm.turno_id = t.id) AS ministros_nombres
               FROM mesc_turnos t
               LEFT JOIN mesc_colores_liturgicos c ON c.id = t.color_liturgico_id
              WHERE t.fecha >= :inicio AND t.fecha < :fin AND t.pastoral_id = :pastoral
              ORDER BY t.fecha, t.hora",
            [':inicio' => $inicio, ':fin' => $fin, ':pastoral' => $pastoralId]
        );
    }

    public function turnoPorId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM mesc_turnos WHERE id = :id', [':id' => $id]);
    }

    public function ministrosDeTurno(int $turnoId): array
    {
        return $this->fetchAll(
            'SELECT m.* FROM mesc_turno_ministros tm
               JOIN mesc_ministros m ON m.id = tm.ministro_id
              WHERE tm.turno_id = :turno
              ORDER BY m.nombre',
            [':turno' => $turnoId]
        );
    }

    public function crearTurno(array $datos, array $ministroIds, int $usuarioId): int
    {
        $this->beginTransaction();
        try {
            $this->execute(
                'INSERT INTO mesc_turnos (pastoral_id, fecha, hora, descripcion, color_liturgico_id, usuario_id)
                 VALUES (:pastoral, :fecha, :hora, :descripcion, :color, :usuario)',
                [
                    ':pastoral'    => $datos['pastoral_id'],
                    ':fecha'       => $datos['fecha'],
                    ':hora'        => $datos['hora'],
                    ':descripcion' => $datos['descripcion'],
                    ':color'       => $datos['color_liturgico_id'] ?? null,
                    ':usuario'     => $usuarioId,
                ]
            );
            $turnoId = $this->lastInsertId();
            $this->sincronizarMinistrosDeTurno($turnoId, $ministroIds);
            $this->commit();
            return $turnoId;
        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function actualizarTurno(int $id, array $datos, array $ministroIds): int
    {
        $this->beginTransaction();
        try {
            $filas = $this->execute(
                'UPDATE mesc_turnos
                    SET fecha = :fecha, hora = :hora, descripcion = :descripcion, color_liturgico_id = :color
                  WHERE id = :id',
                [
                    ':fecha'       => $datos['fecha'],
                    ':hora'        => $datos['hora'],
                    ':descripcion' => $datos['descripcion'],
                    ':color'       => $datos['color_liturgico_id'] ?? null,
                    ':id'          => $id,
                ]
            );
            $this->sincronizarMinistrosDeTurno($id, $ministroIds);
            $this->commit();
            return $filas;
        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function eliminarTurno(int $id): int
    {
        return $this->execute('DELETE FROM mesc_turnos WHERE id = :id', [':id' => $id]);
    }

    // ── Colores litúrgicos ───────────────────────────────────────────────

    public function coloresLiturgicos(): array
    {
        return $this->fetchAll('SELECT * FROM mesc_colores_liturgicos ORDER BY orden, nombre');
    }

    public function colorLiturgicoPorId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM mesc_colores_liturgicos WHERE id = :id', [':id' => $id]);
    }

    public function crearColorLiturgico(array $datos): int
    {
        $this->execute(
            'INSERT INTO mesc_colores_liturgicos (nombre, color_hex, significado, orden)
             VALUES (:nombre, :hex, :significado, :orden)',
            [
                ':nombre'      => $datos['nombre'],
                ':hex'         => $datos['color_hex'],
                ':significado' => $datos['significado'],
                ':orden'       => $datos['orden'],
            ]
        );
        return $this->lastInsertId();
    }

    public function actualizarColorLiturgico(int $id, array $datos): int
    {
        return $this->execute(
            'UPDATE mesc_colores_liturgicos SET nombre = :nombre, color_hex = :hex, significado = :significado, orden = :orden
              WHERE id = :id',
            [
                ':nombre'      => $datos['nombre'],
                ':hex'         => $datos['color_hex'],
                ':significado' => $datos['significado'],
                ':orden'       => $datos['orden'],
                ':id'          => $id,
            ]
        );
    }

    public function eliminarColorLiturgico(int $id): int
    {
        return $this->execute('DELETE FROM mesc_colores_liturgicos WHERE id = :id', [':id' => $id]);
    }

    private function sincronizarMinistrosDeTurno(int $turnoId, array $ministroIds): void
    {
        $this->execute('DELETE FROM mesc_turno_ministros WHERE turno_id = :id', [':id' => $turnoId]);
        foreach (array_unique($ministroIds) as $ministroId) {
            $this->execute(
                'INSERT INTO mesc_turno_ministros (turno_id, ministro_id) VALUES (:turno, :ministro)',
                [':turno' => $turnoId, ':ministro' => $ministroId]
            );
        }
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
