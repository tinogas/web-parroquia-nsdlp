<?php
require_once BASE_PATH . '/core/Model.php';

/**
 * HorarioModel — Recurrencia semanal de misas, confesiones, adoración y
 * horario de oficina. No son eventos: no tienen fecha, tienen día de la
 * semana y hora. Ver docs/ARQUITECTURA.md
 */
class HorarioModel extends Model
{
    /** tipo => [nombre visible, icono] */
    public const TIPOS = [
        'misa'      => ['Misa',                    'bi-clock-fill'],
        'confesion' => ['Confesión',                'bi-chat-heart'],
        'adoracion' => ['Adoración eucarística',    'bi-brightness-high'],
        'oficina'   => ['Oficina parroquial',       'bi-building'],
        'otro'      => ['Otro',                     'bi-calendar3'],
    ];

    public function todos(): array
    {
        return $this->fetchAll(
            "SELECT h.*, c.nombre AS centro_nombre
               FROM horarios h
               LEFT JOIN centros c ON c.id = h.centro_id
              ORDER BY FIELD(h.tipo, " . $this->ordenTipos() . "), h.dia_semana, h.hora"
        );
    }

    public function porId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM horarios WHERE id = :id', [':id' => $id]);
    }

    /**
     * Para el sitio público: activos y vigentes hoy, agrupados por sede o
     * centro (issue #3) y, dentro de cada uno, por día —de lunes a domingo,
     * no domingo primero, que es como queda dia_semana ordenado tal cual— y
     * de la mañana a la noche dentro de cada día. MOD(dia_semana + 6, 7) es
     * el truco: convierte 0=domingo…6=sábado en 0=lunes…6=domingo para el
     * ORDER BY, sin tocar el valor guardado. Un horario sin centro asignado
     * se agrupa aparte, al final, bajo la clave 'sin_centro'.
     *
     * 'dias' es un array asociativo día => horarios; el orden de sus claves
     * es el de inserción (el de la consulta, ya lunes-primero), así que la
     * vista solo debe recorrerlo con foreach, nunca reordenarlo con ksort.
     */
    public function vigentesPorCentro(): array
    {
        $filas = $this->fetchAll(
            "SELECT h.*, c.nombre AS centro_nombre, c.tipo AS centro_tipo
               FROM horarios h
               LEFT JOIN centros c ON c.id = h.centro_id
              WHERE h.activo = 1
                AND (h.vigente_desde IS NULL OR h.vigente_desde <= CURDATE())
                AND (h.vigente_hasta IS NULL OR h.vigente_hasta >= CURDATE())
              ORDER BY (h.centro_id IS NULL), FIELD(c.tipo, 'sede', 'centro'), c.orden, c.nombre,
                       MOD(h.dia_semana + 6, 7), h.hora"
        );

        $porCentro = [];
        foreach ($filas as $fila) {
            $claveCentro = $fila['centro_id'] !== null ? (int) $fila['centro_id'] : 'sin_centro';
            if (!isset($porCentro[$claveCentro])) {
                $porCentro[$claveCentro] = [
                    'nombre' => $fila['centro_nombre'] ?? 'Otros horarios',
                    'tipo'   => $fila['centro_tipo'],
                    'dias'   => [],
                ];
            }
            $claveDia = (int) $fila['dia_semana'];
            $porCentro[$claveCentro]['dias'][$claveDia][] = $fila;
        }
        return $porCentro;
    }

    /**
     * Las próximas misas a partir de ahora, para destacar en la portada.
     *
     * El orden por día-de-la-semana-que-se-repite con "si ya pasó hoy, es en
     * 7 días" es más simple de calcular en PHP que en SQL sin repetir el
     * mismo parámetro nombrado dos veces, algo que PDO no admite con
     * ATTR_EMULATE_PREPARES en false. La tabla es pequeña —una parroquia no
     * tiene más de unas decenas de horarios—, así que ordenar aquí no cuesta nada.
     */
    public function proximasMisas(int $limite = 3): array
    {
        $filas = $this->fetchAll(
            "SELECT * FROM horarios
              WHERE tipo = 'misa' AND activo = 1
                AND (vigente_desde IS NULL OR vigente_desde <= CURDATE())
                AND (vigente_hasta IS NULL OR vigente_hasta >= CURDATE())"
        );

        $ahora      = new DateTimeImmutable();
        $diaActual  = (int) $ahora->format('w');   // 0=domingo … 6=sábado, igual que la BD
        $horaActual = $ahora->format('H:i:s');

        foreach ($filas as &$fila) {
            $diasHasta = ($fila['dia_semana'] - $diaActual + 7) % 7;
            if ($diasHasta === 0 && $fila['hora'] <= $horaActual) {
                $diasHasta = 7;   // Ya pasó hoy: la próxima es la de la semana entrante.
            }
            $fila['_orden'] = $diasHasta * 1440 + self::minutosDelDia($fila['hora']);
        }
        unset($fila);

        usort($filas, static fn (array $a, array $b): int => $a['_orden'] <=> $b['_orden']);

        return array_slice(array_map(
            static function (array $f): array {
                unset($f['_orden']);
                return $f;
            },
            $filas
        ), 0, max(1, $limite));
    }

    private static function minutosDelDia(string $hora): int
    {
        [$h, $m] = array_map('intval', explode(':', $hora));
        return $h * 60 + $m;
    }

    public function crear(array $datos): int
    {
        $this->execute(
            'INSERT INTO horarios
                (centro_id, tipo, dia_semana, hora, hora_fin, lugar, nota, vigente_desde, vigente_hasta, orden, activo)
             VALUES (:centro, :tipo, :dia, :hora, :horaFin, :lugar, :nota, :desde, :hasta, :orden, :activo)',
            $this->parametros($datos)
        );
        return $this->lastInsertId();
    }

    public function actualizar(int $id, array $datos): int
    {
        return $this->execute(
            'UPDATE horarios
                SET centro_id = :centro, tipo = :tipo, dia_semana = :dia, hora = :hora, hora_fin = :horaFin,
                    lugar = :lugar, nota = :nota, vigente_desde = :desde, vigente_hasta = :hasta,
                    orden = :orden, activo = :activo
              WHERE id = :id',
            $this->parametros($datos) + [':id' => $id]
        );
    }

    public function eliminar(int $id): int
    {
        return $this->execute('DELETE FROM horarios WHERE id = :id', [':id' => $id]);
    }

    private function parametros(array $datos): array
    {
        return [
            ':centro'  => $datos['centro_id'],
            ':tipo'    => $datos['tipo'],
            ':dia'     => $datos['dia_semana'],
            ':hora'    => $datos['hora'],
            ':horaFin' => $datos['hora_fin'],
            ':lugar'   => $datos['lugar'],
            ':nota'    => $datos['nota'],
            ':desde'   => $datos['vigente_desde'],
            ':hasta'   => $datos['vigente_hasta'],
            ':orden'   => $datos['orden'],
            ':activo'  => $datos['activo'],
        ];
    }

    private function ordenTipos(): string
    {
        return implode(',', array_map(
            static fn (string $t): string => "'" . $t . "'",
            array_keys(self::TIPOS)
        ));
    }
}
