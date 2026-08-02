<?php
class Model
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    protected function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    protected function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    protected function fetchOne(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row ?: null;
    }

    protected function fetchColumn(string $sql, array $params = []): mixed
    {
        return $this->query($sql, $params)->fetchColumn();
    }

    protected function execute(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    protected function lastInsertId(): int
    {
        return (int) $this->db->lastInsertId();
    }

    // ── Transacciones anidadas ──────────────────────────────────────────
    // PDO no soporta transacciones anidadas y todos los modelos comparten la
    // misma conexión singleton. Cuando un método transaccional invoca a otro,
    // ambos llamarían a beginTransaction sobre la misma conexión. El contador
    // ESTÁTICO abre y cierra la transacción real solo en el nivel más externo.
    // Si una operación interna hace rollback, se marca toda la transacción como
    // "rollback-only" y el commit externo la revierte, evitando confirmaciones
    // parciales.
    private static int  $txLevel        = 0;
    private static bool $txRollbackOnly = false;

    protected function beginTransaction(): void
    {
        if (self::$txLevel === 0) {
            $this->db->beginTransaction();
            self::$txRollbackOnly = false;
        }
        self::$txLevel++;
    }

    protected function commit(): void
    {
        if (self::$txLevel === 0) {
            return;
        }
        self::$txLevel--;
        if (self::$txLevel === 0) {
            if (self::$txRollbackOnly) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                self::$txRollbackOnly = false;
                throw new RuntimeException('Transacción revertida: una operación interna falló.');
            }
            $this->db->commit();
        }
    }

    protected function rollback(): void
    {
        if (self::$txLevel === 0) {
            return;
        }
        self::$txLevel--;
        if (self::$txLevel === 0) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            self::$txRollbackOnly = false;
        } else {
            // Nivel interno: aplazar el rollback real al nivel externo.
            self::$txRollbackOnly = true;
        }
    }

    /**
     * Paginación. Recibe el SELECT completo sin LIMIT y devuelve las filas de
     * la página junto con los totales para el parcial de paginación.
     */
    public function paginar(string $sql, array $params, int $pagina, int $porPagina = 20): array
    {
        $pagina = max(1, $pagina);
        $total  = (int) $this->fetchColumn("SELECT COUNT(*) FROM ({$sql}) AS _t", $params);
        $offset = ($pagina - 1) * $porPagina;
        $filas  = $this->fetchAll("{$sql} LIMIT {$porPagina} OFFSET {$offset}", $params);

        return [
            'filas'         => $filas,
            'total'         => $total,
            'pagina'        => $pagina,
            'por_pagina'    => $porPagina,
            'total_paginas' => max(1, (int) ceil($total / $porPagina)),
        ];
    }

    /**
     * Fragmento SQL y parámetros para acotar un listado a una lista de ids.
     * Lo usan avisos, eventos, cursos y galería para aplicar el alcance —por
     * pastoral y, en eventos y cursos, también por sede— sin repetir esta
     * lógica en cada modelo. La columna se pasa como argumento justamente
     * porque las dos dimensiones se filtran igual.
     *
     * null       = sin filtro (alcance global, ve todo)
     * []         = no debe ver nada (alcance limitado sin ninguna asignación)
     * [1,3]      = solo esos ids
     * [null,1,3] = esos ids y además las filas con la columna en NULL
     *
     * Un null dentro de la lista significa el contenido general —el que no es
     * de ninguna pastoral, o el que no es de ninguna sede—, que a veces hay que
     * incluir. Va por separado porque `IN (…)` nunca casa con NULL.
     *
     * @return array{0: string, 1: array} [condición SQL o cadena vacía, parámetros]
     */
    protected function condicionAlcance(?array $permitidos, string $columna = 'pastoral_id'): array
    {
        if ($permitidos === null) {
            return ['', []];
        }
        if (!$permitidos) {
            return ['1 = 0', []];
        }

        $generales = in_array(null, $permitidos, true);
        $ids       = array_values(array_filter(
            $permitidos,
            static fn ($id): bool => $id !== null
        ));

        if (!$ids) {
            return ["{$columna} IS NULL", []];
        }

        // El nombre del marcador sale de la columna: una misma consulta filtra
        // por pastoral y por sede a la vez, y con un prefijo fijo el segundo
        // juego de parámetros pisaría al primero.
        $prefijo = preg_replace('/[^a-z0-9]/i', '', $columna);

        $params     = [];
        $marcadores = [];
        foreach ($ids as $i => $id) {
            $clave         = ":{$prefijo}{$i}";
            $marcadores[]  = $clave;
            $params[$clave] = (int) $id;
        }
        $enLista = "{$columna} IN (" . implode(',', $marcadores) . ')';

        return [$generales ? "({$columna} IS NULL OR {$enLista})" : $enLista, $params];
    }

    /**
     * Folio consecutivo por año. Formato: BAU-2026-00001
     *
     * La columna de folio lleva índice UNIQUE: si dos peticiones concurrentes
     * generan el mismo número, el segundo INSERT falla en vez de duplicar.
     */
    protected function generarFolio(string $tabla, string $prefijo): string
    {
        $anno = date('Y');
        $n = (int) $this->fetchColumn(
            "SELECT COUNT(*) FROM {$tabla} WHERE YEAR(created_at) = :anno",
            [':anno' => $anno]
        );
        return sprintf('%s-%s-%05d', strtoupper($prefijo), $anno, $n + 1);
    }
}
