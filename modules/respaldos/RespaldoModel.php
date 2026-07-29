<?php
require_once BASE_PATH . '/core/Model.php';

/**
 * RespaldoModel — Volcado y restauración de la base de datos en PHP puro,
 * sin depender de mysqldump: un hosting de cPanel sin SSH tampoco suele
 * permitir exec() ni shell_exec(), así que hacerlo vía PDO es la única vía
 * portable. Ver docs/DESPLIEGUE.md.
 *
 * Solo se puede restaurar un respaldo que el propio panel generó y que sigue
 * físicamente en backups/ — no hay subida de un .sql externo. Esa es la
 * frontera deliberada: automatizar la restauración de un archivo arbitrario
 * reintroduciría por la puerta trasera el mismo riesgo que justifica no
 * tener SSH en primer lugar.
 */
class RespaldoModel extends Model
{
    private const DIR_REL = 'backups';
    private const LOTE    = 200;

    public function listar(): array
    {
        return $this->fetchAll(
            'SELECT r.*, u.nombre AS usuario_nombre
               FROM respaldos_log r
               LEFT JOIN usuarios u ON u.id = r.usuario_id
              ORDER BY r.created_at DESC'
        );
    }

    public function porId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM respaldos_log WHERE id = :id', [':id' => $id]);
    }

    /**
     * Genera el .sql y registra el resultado en el historial. Si algo falla a
     * medio dump, borra el archivo parcial y deja constancia del error: un
     * respaldo roto sin avisar es peor que no tener respaldo.
     *
     * @throws RuntimeException con un mensaje presentable al administrador
     */
    public function crear(int $usuarioId): array
    {
        $archivo = 'backup_' . DB_NAME . '_' . date('Ymd_His') . '.sql';
        $ruta    = $this->dirAbs() . $archivo;

        try {
            [$tablas, $registros] = $this->generarDump($ruta);

            $this->execute(
                'INSERT INTO respaldos_log (tipo, archivo, tamano_bytes, num_tablas, num_registros, usuario_id, estado)
                 VALUES (:tipo, :archivo, :tamano, :tablas, :registros, :uid, :estado)',
                [
                    ':tipo'      => 'respaldo',
                    ':archivo'   => $archivo,
                    ':tamano'    => filesize($ruta),
                    ':tablas'    => $tablas,
                    ':registros' => $registros,
                    ':uid'       => $usuarioId,
                    ':estado'    => 'completado',
                ]
            );

            return ['archivo' => $archivo];
        } catch (Throwable $e) {
            if (is_file($ruta)) {
                @unlink($ruta);
            }
            $this->execute(
                'INSERT INTO respaldos_log (tipo, archivo, usuario_id, estado, notas)
                 VALUES (:tipo, :archivo, :uid, :estado, :notas)',
                [':tipo' => 'respaldo', ':archivo' => $archivo, ':uid' => $usuarioId, ':estado' => 'error', ':notas' => $e->getMessage()]
            );
            throw new RuntimeException('No se pudo generar el respaldo: ' . $e->getMessage());
        }
    }

    /**
     * Restaura sobre la base de datos actual un respaldo ya existente.
     *
     * Antes de tocar nada, genera un respaldo de seguridad del estado
     * presente —la misma llamada que usa "Generar respaldo ahora"—: si la
     * restauración falla a medio camino, ese respaldo de seguridad es el
     * camino de vuelta. No hay transacción envolvente porque no serviría de
     * nada: el dump mezcla DDL (DROP/CREATE TABLE), que en MySQL confirma
     * de forma implícita y rompería cualquier transacción a mitad de camino.
     *
     * @throws RuntimeException con un mensaje presentable, indicando cuántas
     *         sentencias alcanzaron a ejecutarse y el respaldo de seguridad
     *         al que volver si la restauración falló a medio camino
     */
    public function restaurar(int $idRespaldoOrigen, int $usuarioId): array
    {
        $origen = $this->porId($idRespaldoOrigen);
        if (!$origen || $origen['tipo'] !== 'respaldo' || $origen['estado'] !== 'completado') {
            throw new RuntimeException('Ese respaldo no está disponible para restaurar.');
        }

        $rutaOrigen = $this->rutaArchivo($origen['archivo']);
        if (!$rutaOrigen || !is_file($rutaOrigen)) {
            throw new RuntimeException('El archivo de ese respaldo ya no existe en el servidor.');
        }

        $seguridad = $this->crear($usuarioId);

        $sql = file_get_contents($rutaOrigen);
        if ($sql === false) {
            throw new RuntimeException('No se pudo leer el archivo del respaldo.');
        }
        $sentencias = $this->dividirSentencias($sql);

        $ejecutadas = 0;
        try {
            foreach ($sentencias as $sentencia) {
                $this->db->exec($sentencia);
                $ejecutadas++;
            }
        } catch (Throwable $e) {
            $this->registrarRestauracion($origen['archivo'], $ejecutadas, $usuarioId, 'error', sprintf(
                'Falló en la sentencia %d de %d: %s — Respaldo de seguridad previo: %s',
                $ejecutadas + 1,
                count($sentencias),
                $e->getMessage(),
                $seguridad['archivo']
            ));
            throw new RuntimeException(sprintf(
                'Error al restaurar (la base puede haber quedado a medio actualizar): %s. '
                    . 'Puedes restaurar el respaldo de seguridad "%s" para volver al estado anterior.',
                $e->getMessage(),
                $seguridad['archivo']
            ));
        }

        $this->registrarRestauracion(
            $origen['archivo'],
            $ejecutadas,
            $usuarioId,
            'completado',
            'Respaldo de seguridad previo: ' . $seguridad['archivo']
        );

        return ['sentencias' => $ejecutadas, 'seguridad' => $seguridad['archivo']];
    }

    public function eliminar(int $id): bool
    {
        $fila = $this->porId($id);
        if (!$fila) {
            return false;
        }

        // Una fila de restauración no es dueña de ningún archivo: su columna
        // "archivo" solo referencia el .sql de un respaldo distinto, que esa
        // otra fila sí posee. Borrarla nunca debe tocar ese archivo.
        if ($fila['tipo'] === 'respaldo') {
            $ruta = $this->rutaArchivo($fila['archivo']);
            if ($ruta && is_file($ruta)) {
                @unlink($ruta);
            }
        }

        $this->execute('DELETE FROM respaldos_log WHERE id = :id', [':id' => $id]);
        return true;
    }

    /**
     * Ruta absoluta de un archivo del historial, o null si el nombre trae
     * separadores de ruta. Nunca se confía en el valor tal cual, aunque
     * siempre venga de la propia base de datos.
     */
    public function rutaArchivo(string $archivo): ?string
    {
        if ($archivo === '' || basename($archivo) !== $archivo) {
            return null;
        }
        return $this->dirAbs() . $archivo;
    }

    // ── Interno ─────────────────────────────────────────────────────────

    private function registrarRestauracion(string $archivo, int $ejecutadas, int $usuarioId, string $estado, string $notas): void
    {
        $this->execute(
            'INSERT INTO respaldos_log (tipo, archivo, num_registros, usuario_id, estado, notas)
             VALUES (:tipo, :archivo, :ejecutadas, :uid, :estado, :notas)',
            [
                ':tipo'       => 'restauracion',
                ':archivo'    => $archivo,
                ':ejecutadas' => $ejecutadas,
                ':uid'        => $usuarioId,
                ':estado'     => $estado,
                ':notas'      => $notas,
            ]
        );
    }

    /**
     * Separa un .sql en sentencias individuales, respetando los ';' que
     * aparezcan DENTRO de una cadena de texto (por ejemplo, el contenido de
     * un aviso que incluya un punto y coma). No se ejecuta con el
     * multi-statement de PDO: con sentencias preparadas nativas
     * (ATTR_EMULATE_PREPARES=false) no es confiable.
     *
     * Un comentario de línea ("-- …") que quede pegado, sin punto y coma
     * propio, a la sentencia siguiente no es un problema: MySQL los ignora
     * igual dentro de una sola llamada a exec().
     */
    private function dividirSentencias(string $sql): array
    {
        $sentencias     = [];
        $actual         = '';
        $dentroDeCadena = false;
        $largo          = strlen($sql);

        for ($i = 0; $i < $largo; $i++) {
            $car = $sql[$i];

            if ($dentroDeCadena) {
                $actual .= $car;
                if ($car === '\\' && $i + 1 < $largo) {
                    $actual .= $sql[++$i];
                    continue;
                }
                if ($car === "'") {
                    $dentroDeCadena = false;
                }
                continue;
            }

            if ($car === "'") {
                $dentroDeCadena = true;
                $actual .= $car;
                continue;
            }

            if ($car === ';') {
                $sentencia = trim($actual);
                if ($sentencia !== '') {
                    $sentencias[] = $sentencia;
                }
                $actual = '';
                continue;
            }

            $actual .= $car;
        }

        $sentencia = trim($actual);
        if ($sentencia !== '') {
            $sentencias[] = $sentencia;
        }

        return $sentencias;
    }

    /** @return array{0:int,1:int} [número de tablas, número de filas totales] */
    private function generarDump(string $ruta): array
    {
        $this->asegurarCarpeta();

        // respaldos_log queda fuera a propósito: es metadata de esta misma
        // herramienta, no contenido de la parroquia. Incluirla es
        // autodestructivo en una restauración — al restaurar un respaldo
        // viejo, su DROP TABLE + los INSERT de esa foto antigua borrarían la
        // fila del respaldo de SEGURIDAD que se acaba de crear segundos
        // antes (el archivo físico sobrevive en backups/, pero su registro
        // en el historial desaparecería, y con él la manera fácil de
        // encontrarlo desde el panel).
        $tablas = array_values(array_diff(
            $this->db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN),
            ['respaldos_log']
        ));

        $out = @fopen($ruta, 'w');
        if ($out === false) {
            throw new RuntimeException('no se pudo crear el archivo en ' . self::DIR_REL . '/.');
        }

        fwrite($out, "-- Respaldo de " . DB_NAME . " — " . date('Y-m-d H:i:s') . "\n");
        fwrite($out, "SET NAMES utf8mb4;\n");
        fwrite($out, "SET FOREIGN_KEY_CHECKS = 0;\n\n");

        $totalRegistros = 0;

        foreach ($tablas as $tabla) {
            $crear = $this->db->query("SHOW CREATE TABLE `{$tabla}`")->fetch();

            fwrite($out, "-- ------------------------------------------------------------\n");
            fwrite($out, "-- Tabla `{$tabla}`\n");
            fwrite($out, "-- ------------------------------------------------------------\n");
            fwrite($out, "DROP TABLE IF EXISTS `{$tabla}`;\n");
            fwrite($out, $crear['Create Table'] . ";\n\n");

            $totalRegistros += $this->volcarDatos($out, $tabla);
            fwrite($out, "\n");
        }

        fwrite($out, "SET FOREIGN_KEY_CHECKS = 1;\n");
        fclose($out);

        return [count($tablas), $totalRegistros];
    }

    /**
     * Vuelca los datos de una tabla en lotes de self::LOTE filas, para no
     * construir una sola sentencia INSERT gigante. fetch() en el bucle evita
     * además acumular toda la tabla en un arreglo de PHP a la vez.
     */
    private function volcarDatos($out, string $tabla): int
    {
        $stmt      = $this->db->query("SELECT * FROM `{$tabla}`");
        $columnas  = null;
        $lote      = [];
        $registros = 0;

        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $registros++;
            $columnas ??= array_keys($fila);

            $valores = array_map(
                fn ($valor) => $valor === null ? 'NULL' : $this->db->quote((string) $valor),
                $fila
            );
            $lote[] = '(' . implode(',', $valores) . ')';

            if (count($lote) >= self::LOTE) {
                $this->escribirLote($out, $tabla, $columnas, $lote);
                $lote = [];
            }
        }
        if ($lote) {
            $this->escribirLote($out, $tabla, $columnas, $lote);
        }

        return $registros;
    }

    private function escribirLote($out, string $tabla, array $columnas, array $filas): void
    {
        $listaColumnas = '`' . implode('`,`', $columnas) . '`';
        fwrite($out, "INSERT INTO `{$tabla}` ({$listaColumnas}) VALUES\n" . implode(",\n", $filas) . ";\n");
    }

    private function asegurarCarpeta(): void
    {
        $dir = $this->dirAbs();
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('no se pudo crear la carpeta ' . self::DIR_REL . '/.');
        }
    }

    private function dirAbs(): string
    {
        return rtrim(BASE_PATH, '/\\') . '/' . self::DIR_REL . '/';
    }
}
