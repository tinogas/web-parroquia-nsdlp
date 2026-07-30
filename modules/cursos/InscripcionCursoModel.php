<?php
require_once BASE_PATH . '/core/Model.php';

/**
 * InscripcionCursoModel — Inscripciones a cursos, con cupo y lista de espera.
 *
 * `uq_ins_curso_email` evita que la misma persona se inscriba dos veces al
 * mismo curso; por eso el correo es obligatorio aquí, a diferencia de otros
 * formularios públicos donde basta teléfono o correo.
 */
class InscripcionCursoModel extends Model
{
    public const ESTADOS = [
        'pendiente'    => 'Pendiente',
        'confirmada'   => 'Confirmada',
        'lista_espera' => 'Lista de espera',
        'cancelada'    => 'Cancelada',
    ];

    public function listar(int $pagina, ?int $cursoId = null, string $estado = 'todos'): array
    {
        $condiciones = [];
        $params      = [];

        if ($cursoId) {
            $condiciones[] = 'i.curso_id = :curso';
            $params[':curso'] = $cursoId;
        }
        if (isset(self::ESTADOS[$estado])) {
            $condiciones[] = 'i.estado = :estado';
            $params[':estado'] = $estado;
        }
        $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        return $this->paginar(
            "SELECT i.*, c.titulo AS curso_titulo
               FROM inscripciones_curso i
               JOIN cursos c ON c.id = i.curso_id
               {$where}
              ORDER BY i.created_at DESC",
            $params,
            $pagina,
            20
        );
    }

    public function porId(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT i.*, c.titulo AS curso_titulo, c.cupo
               FROM inscripciones_curso i
               JOIN cursos c ON c.id = i.curso_id
              WHERE i.id = :id',
            [':id' => $id]
        );
    }

    /** Ya inscrito con ese correo a este curso (para el mensaje de "ya estás inscrito"). */
    public function yaInscrito(int $cursoId, string $email): bool
    {
        return (bool) $this->fetchColumn(
            "SELECT COUNT(*) FROM inscripciones_curso
              WHERE curso_id = :curso AND email = :email AND estado <> 'cancelada'",
            [':curso' => $cursoId, ':email' => $email]
        );
    }

    /** Inscritos que sí ocupan un lugar del cupo (no la lista de espera ni las canceladas). */
    public function contarActivas(int $cursoId): int
    {
        return (int) $this->fetchColumn(
            "SELECT COUNT(*) FROM inscripciones_curso
              WHERE curso_id = :curso AND estado IN ('pendiente', 'confirmada')",
            [':curso' => $cursoId]
        );
    }

    /**
     * Inscribe y decide sola si hay lugar o si toca lista de espera,
     * comparando contra el cupo dentro de la misma transacción para que dos
     * inscripciones simultáneas no rebasen el cupo por una carrera.
     */
    public function crear(array $datos, ?int $cupo): string
    {
        $folio = null;

        $this->beginTransaction();
        try {
            $activos = $this->contarActivas((int) $datos['curso_id']);
            $hayLugar = $cupo === null || $activos < $cupo;

            $folio = $this->generarFolio('inscripciones_curso', 'CUR');

            $this->execute(
                'INSERT INTO inscripciones_curso
                    (folio, curso_id, nombre, fecha_nacimiento, es_menor, telefono, email, centro,
                     tutor_nombre, tutor_parentesco, tutor_telefono, estado,
                     consentimiento, consentimiento_ip, aviso_version, notas)
                 VALUES
                    (:folio, :curso, :nombre, :fechaNac, :esMenor, :telefono, :email, :centro,
                     :tutorNombre, :tutorParentesco, :tutorTelefono, :estado,
                     :consentimiento, :consentimientoIp, :avisoVersion, :notas)',
                [
                    ':folio'            => $folio,
                    ':curso'            => $datos['curso_id'],
                    ':nombre'           => $datos['nombre'],
                    ':fechaNac'         => $datos['fecha_nacimiento'] ?: null,
                    ':esMenor'          => $datos['es_menor'],
                    ':telefono'         => $datos['telefono'] ?: null,
                    ':email'            => $datos['email'],
                    ':centro'           => $datos['centro'] ?: null,
                    ':tutorNombre'      => $datos['tutor_nombre'] ?: null,
                    ':tutorParentesco'  => $datos['tutor_parentesco'] ?: null,
                    ':tutorTelefono'    => $datos['tutor_telefono'] ?: null,
                    ':estado'           => $hayLugar ? 'pendiente' : 'lista_espera',
                    ':consentimiento'   => 1,
                    ':consentimientoIp' => $datos['ip'],
                    ':avisoVersion'     => AVISO_VERSION,
                    ':notas'            => $datos['notas'] ?: null,
                ]
            );
            $this->commit();
        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }

        return $folio;
    }

    public function cambiarEstado(int $id, string $estado): int
    {
        return $this->execute(
            'UPDATE inscripciones_curso SET estado = :estado WHERE id = :id',
            [':estado' => $estado, ':id' => $id]
        );
    }

    public function eliminar(int $id): int
    {
        return $this->execute('DELETE FROM inscripciones_curso WHERE id = :id', [':id' => $id]);
    }
}
