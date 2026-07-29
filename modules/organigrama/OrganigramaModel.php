<?php
require_once BASE_PATH . '/core/Model.php';

/**
 * OrganigramaModel — Árbol autorreferenciado de hasta NIVEL_MAXIMO niveles.
 *
 * Un nodo puede apuntar a una persona, o ser solo un título de agrupación
 * ("Consejo Pastoral"). El árbol se arma en PHP a partir de las filas planas:
 * la tabla es pequeña (unas pocas decenas de nodos como máximo), así que no
 * hace falta CTE recursivo ni nada más elaborado. Ver docs/ARQUITECTURA.md
 */
class OrganigramaModel extends Model
{
    public const NIVEL_MAXIMO = 4;

    /** Filas planas con datos de la persona, para el listado de administración. */
    public function todos(): array
    {
        return $this->fetchAll(
            'SELECT n.*, p.nombre AS persona_nombre, p.foto AS persona_foto,
                    p.cargo AS persona_cargo, p.activo AS persona_activo
               FROM organigrama_nodos n
               LEFT JOIN personas p ON p.id = n.persona_id
              ORDER BY n.nivel, n.orden, n.titulo'
        );
    }

    public function porId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM organigrama_nodos WHERE id = :id', [':id' => $id]);
    }

    /** Árbol completo (activos e inactivos), para la vista de administración. */
    public function arbolAdmin(): array
    {
        return $this->construirArbol($this->todos());
    }

    /**
     * Árbol solo con nodos activos, para el sitio público. Si el nodo tiene
     * una persona asignada pero esa persona está inactiva, la vista debe
     * mostrar solo el título ("Párroco"), no el nombre: por eso aquí no se
     * filtra por persona_activo, se deja que la vista decida.
     */
    public function arbolPublico(): array
    {
        return $this->construirArbol($this->fetchAll(
            'SELECT n.*, p.nombre AS persona_nombre, p.foto AS persona_foto,
                    p.cargo AS persona_cargo, p.activo AS persona_activo
               FROM organigrama_nodos n
               LEFT JOIN personas p ON p.id = n.persona_id
              WHERE n.activo = 1
              ORDER BY n.orden, n.titulo'
        ));
    }

    /**
     * Opciones para el selector de "padre" al editar un nodo: todas menos el
     * propio nodo y sus descendientes, para no poder crear un ciclo.
     */
    public function paraSelectorPadre(?int $excluirId): array
    {
        $excluir = $excluirId ? $this->idsConDescendientes($excluirId) : [];
        return array_values(array_filter(
            $this->fetchAll('SELECT id, titulo, nivel FROM organigrama_nodos ORDER BY nivel, orden, titulo'),
            static fn (array $n): bool => !in_array((int) $n['id'], $excluir, true)
        ));
    }

    /** IDs de un nodo y de todos sus descendientes. Usado para evitar ciclos. */
    public function idsConDescendientes(int $id): array
    {
        $hijosDe = [];
        foreach ($this->fetchAll('SELECT id, padre_id FROM organigrama_nodos') as $fila) {
            if ($fila['padre_id'] !== null) {
                $hijosDe[(int) $fila['padre_id']][] = (int) $fila['id'];
            }
        }

        $resultado  = [$id];
        $pendientes = [$id];
        while ($pendientes) {
            $actual = array_pop($pendientes);
            foreach ($hijosDe[$actual] ?? [] as $hijo) {
                $resultado[]  = $hijo;
                $pendientes[] = $hijo;
            }
        }
        return $resultado;
    }

    /**
     * @throws RuntimeException si el padre elegido crearía un ciclo o excede
     *         el número de niveles permitido
     */
    public function crear(array $datos): int
    {
        $nivel = $this->calcularNivel($datos['padre_id'], null);
        $this->execute(
            'INSERT INTO organigrama_nodos (padre_id, titulo, persona_id, pastoral_id, nivel, orden, activo)
             VALUES (:padre, :titulo, :persona, :pastoral, :nivel, :orden, :activo)',
            [
                ':padre'    => $datos['padre_id'],
                ':titulo'   => $datos['titulo'],
                ':persona'  => $datos['persona_id'],
                ':pastoral' => $datos['pastoral_id'],
                ':nivel'    => $nivel,
                ':orden'    => $datos['orden'],
                ':activo'   => $datos['activo'],
            ]
        );
        return $this->lastInsertId();
    }

    /** @throws RuntimeException misma condición que crear() */
    public function actualizar(int $id, array $datos): int
    {
        $nivel = $this->calcularNivel($datos['padre_id'], $id);
        return $this->execute(
            'UPDATE organigrama_nodos
                SET padre_id = :padre, titulo = :titulo, persona_id = :persona,
                    pastoral_id = :pastoral, nivel = :nivel, orden = :orden, activo = :activo
              WHERE id = :id',
            [
                ':padre'    => $datos['padre_id'],
                ':titulo'   => $datos['titulo'],
                ':persona'  => $datos['persona_id'],
                ':pastoral' => $datos['pastoral_id'],
                ':nivel'    => $nivel,
                ':orden'    => $datos['orden'],
                ':activo'   => $datos['activo'],
                ':id'       => $id,
            ]
        );
    }

    /** Al borrar un nodo, sus hijos suben de nivel (quedan sin padre), no se borran en cascada. */
    public function eliminar(int $id): int
    {
        return $this->execute('DELETE FROM organigrama_nodos WHERE id = :id', [':id' => $id]);
    }

    private function construirArbol(array $filas): array
    {
        $porId = [];
        foreach ($filas as $fila) {
            $fila['hijos']       = [];
            $porId[(int) $fila['id']] = $fila;
        }

        $raices = [];
        foreach (array_keys($porId) as $id) {
            $padreId = $porId[$id]['padre_id'];
            if ($padreId !== null && isset($porId[(int) $padreId])) {
                $porId[(int) $padreId]['hijos'][] = &$porId[$id];
            } else {
                $raices[] = &$porId[$id];
            }
        }
        return $raices;
    }

    private function calcularNivel(?int $padreId, ?int $propioId): int
    {
        if ($padreId === null) {
            return 1;
        }

        if ($propioId !== null && in_array($padreId, $this->idsConDescendientes($propioId), true)) {
            throw new RuntimeException('Ese nodo no puede ser su propio padre ni el de uno de sus subordinados.');
        }

        $padre = $this->porId($padreId);
        if (!$padre) {
            throw new RuntimeException('El nodo seleccionado como superior ya no existe.');
        }

        $nivel = ((int) $padre['nivel']) + 1;
        if ($nivel > self::NIVEL_MAXIMO) {
            throw new RuntimeException('El organigrama admite hasta ' . self::NIVEL_MAXIMO . ' niveles.');
        }
        return $nivel;
    }
}
