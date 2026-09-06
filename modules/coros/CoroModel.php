<?php
require_once BASE_PATH . '/core/Model.php';

/**
 * CoroModel — Los coros de la parroquia y quiénes cantan en ellos.
 *
 * Es el más pequeño de los cuatro módulos de pastoral dedicada, y el único sin
 * calendario de turnos: aquí la asignación no es un rol mensual sino
 * permanente —el coro de las 12:00 canta todos los domingos—, así que tampoco
 * hay colores litúrgicos ni hoja imprimible. Las actividades y los documentos
 * se gestionan desde el panel básico de la pastoral, no se duplican aquí.
 *
 * Un coro NO tiene nombre propio: es el de su misa. De ahí la FK a `horarios`,
 * que MescModel y ProclamadoresModel evitan a propósito en sus turnos —un
 * turno cubre una ocurrencia concreta ("el domingo 3 de agosto") y `horarios`
 * es recurrencia semanal, así que atarlo ahí no resolvería la fecha—. Aquí es
 * al revés: el coro ES la recurrencia, y "los domingos a las 12:00" es
 * exactamente la fila de `horarios` que ya existe.
 */
class CoroModel extends Model
{
    /** Domingo, en la convención de `horarios.dia_semana` (0=domingo … 6=sábado). */
    public const DIA_DOMINGO = 0;

    /** La única pastoral que administra este módulo, resuelta por slug (no por id fijo: los id no se siembran en install.sql). */
    public function pastoralId(): ?int
    {
        return $this->fetchColumn(
            'SELECT id FROM pastorales WHERE slug = :slug',
            [':slug' => PASTORAL_COROS]
        ) ?: null;
    }

    // ── Horarios de misa dominical ───────────────────────────────────────
    // La fuente de identidad de un coro. Se leen de `horarios` tal cual: este
    // módulo no crea ni edita horarios, eso sigue siendo del módulo Horarios.

    /**
     * Las misas del domingo que hoy están activas, con el nombre de su sede.
     * Ordenadas por centro y hora, que es como se leen en la portada.
     *
     * Sin filtrar por vigente_desde/vigente_hasta a propósito: un horario de
     * temporada —la misa de Urbi, que cambia de hora entre verano e invierno—
     * sigue siendo el mismo coro todo el año, y esconderlo fuera de su
     * temporada dejaría a ese coro sin dónde aparecer medio año.
     */
    public function horariosDominicales(): array
    {
        return $this->fetchAll(
            "SELECT h.*, c.nombre AS centro_nombre
               FROM horarios h
               LEFT JOIN centros c ON c.id = h.centro_id
              WHERE h.tipo = 'misa' AND h.dia_semana = :dia AND h.activo = 1
              ORDER BY c.orden, c.nombre, h.hora",
            [':dia' => self::DIA_DOMINGO]
        );
    }

    /** Revalidación en servidor de lo que llega por POST: que exista y sea misa dominical activa. */
    public function horarioDominicalPorId(int $id): ?array
    {
        return $this->fetchOne(
            "SELECT h.*, c.nombre AS centro_nombre
               FROM horarios h
               LEFT JOIN centros c ON c.id = h.centro_id
              WHERE h.id = :id AND h.tipo = 'misa' AND h.dia_semana = :dia AND h.activo = 1",
            [':id' => $id, ':dia' => self::DIA_DOMINGO]
        );
    }

    // ── Coros ────────────────────────────────────────────────────────────

    /**
     * Los coros de la pastoral, con su encargado y cuántos integrantes tienen.
     * Una sola consulta con subconsulta agregada, como
     * ProclamadoresModel::turnosDelMes(), en vez de un COUNT por fila.
     *
     * Indexado por `horario_id` porque la portada recorre los horarios
     * dominicales, no los coros: así sabe de un vistazo cuál ya tiene coro.
     */
    public function corosPorHorario(int $pastoralId): array
    {
        $filas = $this->fetchAll(
            'SELECT co.*, e.nombre AS encargado_nombre,
                    (SELECT COUNT(*) FROM coro_coristas cc WHERE cc.coro_id = co.id) AS total_coristas
               FROM coros co
               LEFT JOIN coristas e ON e.id = co.encargado_id
              WHERE co.pastoral_id = :pastoral',
            [':pastoral' => $pastoralId]
        );

        $porHorario = [];
        foreach ($filas as $fila) {
            $porHorario[(int) $fila['horario_id']] = $fila;
        }
        return $porHorario;
    }

    public function coroPorId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM coros WHERE id = :id', [':id' => $id]);
    }

    /** Para avisar con un mensaje claro antes de que salte uq_cor_horario. */
    public function coroPorHorario(int $horarioId): ?array
    {
        return $this->fetchOne('SELECT * FROM coros WHERE horario_id = :horario', [':horario' => $horarioId]);
    }

    public function crearCoro(array $datos): int
    {
        $this->execute(
            'INSERT INTO coros (pastoral_id, horario_id, encargado_id, nota, activo)
             VALUES (:pastoral, :horario, :encargado, :nota, :activo)',
            [
                ':pastoral'  => $datos['pastoral_id'],
                ':horario'   => $datos['horario_id'],
                ':encargado' => $datos['encargado_id'],
                ':nota'      => $datos['nota'],
                ':activo'    => $datos['activo'],
            ]
        );
        return $this->lastInsertId();
    }

    /**
     * Sin :pastoral ni :horario. El primero es fijo —este módulo es de una sola
     * pastoral— y el segundo es la identidad del coro, que no se cambia: para
     * mover un coro de misa se borra y se crea, que además es lo que uno espera
     * que pase con sus integrantes. Incluir un marcador que el SQL no declara
     * rompe la sentencia con PDO::ATTR_EMULATE_PREPARES en false; es el mismo
     * cuidado que documenta ProclamadoresModel::parametrosProclamador().
     */
    public function actualizarCoro(int $id, array $datos): int
    {
        return $this->execute(
            'UPDATE coros SET encargado_id = :encargado, nota = :nota, activo = :activo WHERE id = :id',
            [
                ':encargado' => $datos['encargado_id'],
                ':nota'      => $datos['nota'],
                ':activo'    => $datos['activo'],
                ':id'        => $id,
            ]
        );
    }

    public function eliminarCoro(int $id): int
    {
        return $this->execute('DELETE FROM coros WHERE id = :id', [':id' => $id]);
    }

    // ── Coristas ─────────────────────────────────────────────────────────

    public function coristas(int $pastoralId): array
    {
        return $this->fetchAll(
            'SELECT * FROM coristas WHERE pastoral_id = :id ORDER BY nombre',
            [':id' => $pastoralId]
        );
    }

    public function coristasActivos(int $pastoralId): array
    {
        return $this->fetchAll(
            'SELECT * FROM coristas WHERE pastoral_id = :id AND activo = 1 ORDER BY nombre',
            [':id' => $pastoralId]
        );
    }

    public function coristaPorId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM coristas WHERE id = :id', [':id' => $id]);
    }

    public function crearCorista(array $datos): int
    {
        $this->execute(
            'INSERT INTO coristas (pastoral_id, persona_id, nombre, telefono, email, voz, instrumento, orden, activo)
             VALUES (:pastoral, :persona, :nombre, :telefono, :email, :voz, :instrumento, :orden, :activo)',
            $this->parametrosCorista($datos) + [':pastoral' => $datos['pastoral_id']]
        );
        return $this->lastInsertId();
    }

    public function actualizarCorista(int $id, array $datos): int
    {
        return $this->execute(
            'UPDATE coristas
                SET persona_id = :persona, nombre = :nombre, telefono = :telefono,
                    email = :email, voz = :voz, instrumento = :instrumento,
                    orden = :orden, activo = :activo
              WHERE id = :id',
            $this->parametrosCorista($datos) + [':id' => $id]
        );
    }

    public function eliminarCorista(int $id): int
    {
        return $this->execute('DELETE FROM coristas WHERE id = :id', [':id' => $id]);
    }

    /**
     * Sin :pastoral, por el mismo motivo que en actualizarCoro().
     *
     * `voz` e `instrumento` son texto libre —ver el comentario de la tabla en
     * install.sql— y viajan tal cual, con la cadena vacía guardada como NULL:
     * «no se le ha preguntado» y «no hace ninguna» no se distinguen aquí, y
     * fingir que sí con dos valores distintos sería inventarse un matiz que
     * nadie captura. A diferencia del nombre y el contacto, estas dos no las
     * pisa PersonaModel::sincronizarPersonal(): no están en la ficha del
     * equipo pastoral.
     */
    private function parametrosCorista(array $datos): array
    {
        return [
            ':persona'     => $datos['persona_id'],
            ':nombre'      => $datos['nombre'],
            ':telefono'    => $datos['telefono'],
            ':email'       => $datos['email'],
            ':voz'         => $datos['voz'],
            ':instrumento' => $datos['instrumento'],
            ':orden'       => $datos['orden'],
            ':activo'      => $datos['activo'],
        ];
    }

    // ── Quién canta en qué coro ──────────────────────────────────────────

    /** Los ids de los coros en los que canta esta persona. */
    public function corosDeCorista(int $coristaId): array
    {
        return array_map('intval', array_column($this->fetchAll(
            'SELECT coro_id FROM coro_coristas WHERE corista_id = :corista',
            [':corista' => $coristaId]
        ), 'coro_id'));
    }

    /** Los ids de quienes cantan en este coro. */
    public function coristasDeCoro(int $coroId): array
    {
        return array_map('intval', array_column($this->fetchAll(
            'SELECT corista_id FROM coro_coristas WHERE coro_id = :coro',
            [':coro' => $coroId]
        ), 'corista_id'));
    }

    /**
     * Los coros de cada corista, indexados por corista_id, para que el listado
     * pinte sus etiquetas sin una consulta por fila.
     */
    public function corosDeCadaCorista(int $pastoralId): array
    {
        $filas = $this->fetchAll(
            'SELECT cc.corista_id, cc.coro_id
               FROM coro_coristas cc
               JOIN coristas cr ON cr.id = cc.corista_id
              WHERE cr.pastoral_id = :pastoral',
            [':pastoral' => $pastoralId]
        );

        $porCorista = [];
        foreach ($filas as $fila) {
            $porCorista[(int) $fila['corista_id']][] = (int) $fila['coro_id'];
        }
        return $porCorista;
    }

    /**
     * Los integrantes de cada coro, indexados por coro_id y con lo que hace
     * cada quien (voz e instrumento), en una sola consulta para todo el
     * listado. Gemela de corosDeCadaCorista() en la dirección contraria.
     *
     * Trae también a los inactivos del catálogo, a diferencia del selector del
     * formulario: si alguien se dio de baja pero sigue marcado en el coro de
     * las 12, esconderlo de la lista haría pensar que ese coro tiene un
     * integrante menos de los que la columna cuenta.
     */
    public function integrantesDeCadaCoro(int $pastoralId): array
    {
        $filas = $this->fetchAll(
            'SELECT cc.coro_id, cr.id, cr.nombre, cr.voz, cr.instrumento, cr.activo
               FROM coro_coristas cc
               JOIN coristas cr ON cr.id = cc.corista_id
              WHERE cr.pastoral_id = :pastoral
              ORDER BY cr.nombre',
            [':pastoral' => $pastoralId]
        );

        $porCoro = [];
        foreach ($filas as $fila) {
            $porCoro[(int) $fila['coro_id']][] = $fila;
        }
        return $porCoro;
    }

    /**
     * Fija en qué coros canta esta persona: borrar y reinsertar, como
     * ProclamadoresModel::sincronizarProclamadoresDeTurno().
     */
    public function sincronizarCorosDeCorista(int $coristaId, array $coroIds): void
    {
        $coroIds = array_values(array_unique(array_map('intval', $coroIds)));

        $this->beginTransaction();
        try {
            $this->execute('DELETE FROM coro_coristas WHERE corista_id = :corista', [':corista' => $coristaId]);
            foreach ($coroIds as $coroId) {
                $this->execute(
                    'INSERT INTO coro_coristas (coro_id, corista_id) VALUES (:coro, :corista)',
                    [':coro' => $coroId, ':corista' => $coristaId]
                );
            }
            $this->limpiarEncargadosHuerfanos();
            $this->commit();
        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    /** Fija quiénes cantan en este coro. Misma mecánica, en la otra dirección. */
    public function sincronizarCoristasDeCoro(int $coroId, array $coristaIds): void
    {
        $coristaIds = array_values(array_unique(array_map('intval', $coristaIds)));

        $this->beginTransaction();
        try {
            $this->execute('DELETE FROM coro_coristas WHERE coro_id = :coro', [':coro' => $coroId]);
            foreach ($coristaIds as $coristaId) {
                $this->execute(
                    'INSERT INTO coro_coristas (coro_id, corista_id) VALUES (:coro, :corista)',
                    [':coro' => $coroId, ':corista' => $coristaId]
                );
            }
            $this->limpiarEncargadosHuerfanos();
            $this->commit();
        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Deja sin encargado a todo coro cuyo encargado ya no canta en él.
     *
     * Se llama después de cada sincronización en vez de calcular a mano qué
     * coros quedaron afectados: es una sola sentencia y así ninguna ruta de
     * código puede olvidarse de un caso. La FK `ON DELETE SET NULL` solo cubre
     * borrar a esa persona del catálogo; quitarla de un coro que encabezaba lo
     * dejaría con un encargado que ya no pertenece, que es justamente lo que el
     * módulo promete que no pasa.
     *
     * El NOT EXISTS correlacionado, y no un NOT IN con subconsulta sobre
     * `coros`, porque MySQL no deja leer en la misma sentencia la tabla que el
     * UPDATE está escribiendo.
     */
    private function limpiarEncargadosHuerfanos(): void
    {
        $this->execute(
            'UPDATE coros
                SET encargado_id = NULL
              WHERE encargado_id IS NOT NULL
                AND NOT EXISTS (
                    SELECT 1 FROM coro_coristas cc
                     WHERE cc.coro_id = coros.id AND cc.corista_id = coros.encargado_id
                )'
        );
    }
}
