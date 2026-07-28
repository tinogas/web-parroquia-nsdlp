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
