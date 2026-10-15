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

    /** Orden del tipo en la página pública: misas arriba, confesiones al final. */
    private const ORDEN_PUBLICO = ['misa', 'confesion', 'adoracion', 'oficina', 'otro'];

    /** @param ?array $centros Filtro opcional por sede/centro: null = todas, [] = ninguna. */
    public function todos(?array $centros = null): array
    {
        [$condicionCentro, $paramsCentro] = $this->condicionAlcance($centros, 'h.centro_id');
        $where = $condicionCentro !== '' ? "WHERE {$condicionCentro}" : '';

        return $this->fetchAll(
            "SELECT h.*, c.nombre AS centro_nombre
               FROM horarios h
               LEFT JOIN centros c ON c.id = h.centro_id
               {$where}
              ORDER BY FIELD(h.tipo, " . $this->campoOrden(array_keys(self::TIPOS)) . "), h.dia_semana, h.hora",
            $paramsCentro
        );
    }

    /**
     * Todos los horarios vigentes hoy, agrupados por sede/centro y, dentro de
     * cada una, por tipo —para el horario imprimible por sede
     * (HorarioController::horariosImprimir())—. Mismo criterio de vigencia
     * que vigentesPorTipo() (activo y dentro de vigente_desde/vigente_hasta),
     * pero agrupado por centro en primer lugar, no por tipo.
     *
     * Sin centro asignado cae en su propio grupo, al final (`ORDER BY
     * centro_id IS NULL`): no se pierde, pero tampoco se mezcla con una sede
     * de verdad. Dentro de cada centro, de domingo a sábado y de la mañana a
     * la noche —el orden en que ya se capturan en el admin, a diferencia de
     * vigentesPorTipo(), que reordena a partir del lunes para el sitio
     * público—.
     *
     * `lugar_principal` es el lugar más repetido entre los horarios de esa
     * sede (empate: el primero que aparece), para mostrarlo una sola vez bajo
     * el nombre de la sede en vez de en cada renglón. Es una sola porque en la
     * práctica sí lo es —dentro de una sede, misa, confesión y adoración
     * comparten capilla—, pero no siempre: "otro" (Rosario, catequesis...)
     * suele anotar el nombre de la sede entera, no la capilla, y una sede sin
     * centro agrupa varias Casas de Oración con lugares distintos entre sí. En
     * los dos casos la vista se queda con el lugar propio de ese renglón en
     * vez de asumir el principal; ver `con_sede` (falso = no hay uno solo que
     * asumir) y comparar `lugar` contra `lugar_principal` fila por fila.
     *
     * @return array<int, array{nombre: string, con_sede: bool, lugar_principal: ?string, horarios: array}>
     *         Indexado por centro_id (0 = sin sede asignada).
     */
    public function todosPorCentro(): array
    {
        $filas = $this->fetchAll(
            "SELECT h.*, c.nombre AS centro_nombre
               FROM horarios h
               LEFT JOIN centros c ON c.id = h.centro_id
              WHERE h.activo = 1
                AND (h.vigente_desde IS NULL OR h.vigente_desde <= CURDATE())
                AND (h.vigente_hasta IS NULL OR h.vigente_hasta >= CURDATE())
              ORDER BY (h.centro_id IS NULL), c.orden, c.nombre,
                       FIELD(h.tipo, " . $this->campoOrden(self::ORDEN_PUBLICO) . "), h.dia_semana, h.hora"
        );

        $porCentro = [];
        foreach ($filas as $fila) {
            $clave = $fila['centro_id'] !== null ? (int) $fila['centro_id'] : 0;
            $porCentro[$clave]['nombre']   ??= $fila['centro_nombre'] ?? 'Sin sede asignada';
            $porCentro[$clave]['con_sede'] ??= $fila['centro_id'] !== null;
            $porCentro[$clave]['horarios'][] = $fila;
        }

        foreach ($porCentro as &$grupo) {
            $grupo['lugar_principal'] = null;
            if (!$grupo['con_sede']) {
                continue; // Varias Casas de Oración distintas: no hay un "principal" que asumir.
            }
            $conteo = [];
            foreach ($grupo['horarios'] as $h) {
                if (!empty($h['lugar'])) {
                    $conteo[$h['lugar']] = ($conteo[$h['lugar']] ?? 0) + 1;
                }
            }
            if ($conteo) {
                arsort($conteo);
                $grupo['lugar_principal'] = array_key_first($conteo);
            }
        }
        unset($grupo);

        return $porCentro;
    }

    public function porId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM horarios WHERE id = :id', [':id' => $id]);
    }

    /**
     * Para el sitio público: activos y vigentes hoy, agrupados por tipo
     * (misas arriba, confesiones al final — ORDEN_PUBLICO, no el orden de
     * TIPOS que usa el admin), con un filtro opcional de sede/centro.
     *
     * $centroId null = todos los centros (el filtro "Todos" del selector
     * público); un id concreto acota a esa sola sede/centro.
     *
     * Dentro de cada tipo los horarios quedan ordenados de lunes a domingo
     * —no domingo primero, que es como queda dia_semana tal cual— y de la
     * mañana a la noche; MOD(dia_semana + 6, 7) convierte 0=domingo…6=sábado
     * en 0=lunes…6=domingo para el ORDER BY, sin tocar el valor guardado. La
     * vista muestra el centro de cada horario (con "Todos" seleccionado
     * puede haber varias sedes/centros mezclados dentro de un mismo tipo).
     *
     * El array devuelto es tipo => horarios; el orden de sus claves es el
     * de inserción (el de la consulta, ya misa-primero), así que la vista
     * solo debe recorrerlo con foreach, nunca reordenarlo.
     */
    public function vigentesPorTipo(?int $centroId = null): array
    {
        $condicionCentro = $centroId !== null ? 'AND h.centro_id = :centro' : '';

        $filas = $this->fetchAll(
            "SELECT h.*, c.nombre AS centro_nombre
               FROM horarios h
               LEFT JOIN centros c ON c.id = h.centro_id
              WHERE h.activo = 1
                AND (h.vigente_desde IS NULL OR h.vigente_desde <= CURDATE())
                AND (h.vigente_hasta IS NULL OR h.vigente_hasta >= CURDATE())
                {$condicionCentro}
              ORDER BY FIELD(h.tipo, " . $this->campoOrden(self::ORDEN_PUBLICO) . "),
                       MOD(h.dia_semana + 6, 7), h.hora",
            $centroId !== null ? [':centro' => $centroId] : []
        );

        $porTipo = [];
        foreach ($filas as $fila) {
            $porTipo[$fila['tipo']][] = $fila;
        }
        return $porTipo;
    }

    /**
     * Los mismos de vigentesPorTipo(), en una sola lista de domingo a sábado y
     * de la mañana a la noche: es como los recorre la semana en pestañas de
     * la portada y de la página de Horarios.
     */
    public function vigentesDeLaSemana(): array
    {
        $horarios = [];
        foreach ($this->vigentesPorTipo(null) as $delTipo) {
            array_push($horarios, ...$delTipo);
        }
        usort($horarios, static fn (array $a, array $b): int
            => [(int) $a['dia_semana'], $a['hora']] <=> [(int) $b['dia_semana'], $b['hora']]);
        return $horarios;
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
                (centro_id, tipo, dia_semana, hora, hora_fin, lugar, nota, vigente_desde, vigente_hasta, orden, activo, tiene_coro)
             VALUES (:centro, :tipo, :dia, :hora, :horaFin, :lugar, :nota, :desde, :hasta, :orden, :activo, :tieneCoro)',
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
                    orden = :orden, activo = :activo, tiene_coro = :tieneCoro
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
            ':tieneCoro' => $datos['tiene_coro'],
        ];
    }

    /** Convierte una lista de valores en la sublista literal que pide FIELD() para un ORDER BY a medida. */
    private function campoOrden(array $orden): string
    {
        return implode(',', array_map(
            static fn (string $t): string => "'" . $t . "'",
            $orden
        ));
    }
}
