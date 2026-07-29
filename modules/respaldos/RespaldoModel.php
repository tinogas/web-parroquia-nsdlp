<?php
require_once BASE_PATH . '/core/Model.php';

/**
 * RespaldoModel — Volcado de la base de datos en PHP puro, sin depender de
 * mysqldump: un hosting de cPanel sin SSH tampoco suele permitir exec() ni
 * shell_exec(), así que un dump nativo vía PDO es la única vía portable.
 * Ver docs/DESPLIEGUE.md.
 *
 * No hay restauración automática: para restaurar, se importa el .sql desde
 * phpMyAdmin. Automatizarla sería reintroducir por la puerta trasera el mismo
 * riesgo que justifica no tener SSH en primer lugar.
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
                'INSERT INTO respaldos_log (archivo, tamano_bytes, num_tablas, num_registros, usuario_id, estado)
                 VALUES (:archivo, :tamano, :tablas, :registros, :uid, :estado)',
                [
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
                'INSERT INTO respaldos_log (archivo, usuario_id, estado, notas)
                 VALUES (:archivo, :uid, :estado, :notas)',
                [':archivo' => $archivo, ':uid' => $usuarioId, ':estado' => 'error', ':notas' => $e->getMessage()]
            );
            throw new RuntimeException('No se pudo generar el respaldo: ' . $e->getMessage());
        }
    }

    public function eliminar(int $id): bool
    {
        $fila = $this->porId($id);
        if (!$fila) {
            return false;
        }

        $ruta = $this->rutaArchivo($fila['archivo']);
        if ($ruta && is_file($ruta)) {
            @unlink($ruta);
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

    /** @return array{0:int,1:int} [número de tablas, número de filas totales] */
    private function generarDump(string $ruta): array
    {
        $this->asegurarCarpeta();

        $tablas = $this->db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

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
