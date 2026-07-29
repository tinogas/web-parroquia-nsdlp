<?php
require_once BASE_PATH . '/core/Model.php';

/**
 * ContactoModel — Mensajes del formulario de contacto.
 *
 * Contienen datos personales de quien escribe. El sitio público solo inserta;
 * consultarlos es exclusivo del panel. Ver docs/PRIVACIDAD.md
 */
class ContactoModel extends Model
{
    public function crear(array $datos): int
    {
        $this->execute(
            'INSERT INTO mensajes_contacto
                (nombre, email, telefono, asunto, mensaje, ip, consentimiento, aviso_version)
             VALUES (:nombre, :email, :telefono, :asunto, :mensaje, :ip, 1, :aviso)',
            [
                ':nombre'   => $datos['nombre'],
                ':email'    => $datos['email'] !== '' ? $datos['email'] : null,
                ':telefono' => $datos['telefono'] !== '' ? $datos['telefono'] : null,
                ':asunto'   => $datos['asunto'] !== '' ? $datos['asunto'] : null,
                ':mensaje'  => $datos['mensaje'],
                ':ip'       => $datos['ip'],
                ':aviso'    => AVISO_VERSION,
            ]
        );
        return $this->lastInsertId();
    }

    /** Listado paginado para el panel. $filtro: 'todos' o 'no_leidos'. */
    public function listar(int $pagina, string $filtro = 'todos'): array
    {
        $sql = 'SELECT m.*, u.nombre AS atendido_por_nombre
                FROM mensajes_contacto m
                LEFT JOIN usuarios u ON u.id = m.atendido_por'
             . ($filtro === 'no_leidos' ? ' WHERE m.leido = 0' : '')
             . ' ORDER BY m.created_at DESC';

        return $this->paginar($sql, [], $pagina, 15);
    }

    public function porId(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT m.*, u.nombre AS atendido_por_nombre
             FROM mensajes_contacto m
             LEFT JOIN usuarios u ON u.id = m.atendido_por
             WHERE m.id = :id',
            [':id' => $id]
        );
    }

    public function marcarLeido(int $id): void
    {
        $this->execute('UPDATE mensajes_contacto SET leido = 1 WHERE id = :id', [':id' => $id]);
    }

    public function marcarRespondido(int $id, int $usuarioId): void
    {
        $this->execute(
            'UPDATE mensajes_contacto SET respondido = 1, atendido_por = :u WHERE id = :id',
            [':u' => $usuarioId, ':id' => $id]
        );
    }

    /** Nota interna de seguimiento: nunca se muestra en el sitio público. */
    public function guardarNota(int $id, string $nota): void
    {
        $this->execute(
            'UPDATE mensajes_contacto SET nota_interna = :nota WHERE id = :id',
            [':nota' => $nota !== '' ? $nota : null, ':id' => $id]
        );
    }

    public function eliminar(int $id): int
    {
        return $this->execute('DELETE FROM mensajes_contacto WHERE id = :id', [':id' => $id]);
    }

    public function noLeidos(): int
    {
        return (int) $this->fetchColumn('SELECT COUNT(*) FROM mensajes_contacto WHERE leido = 0');
    }
}
