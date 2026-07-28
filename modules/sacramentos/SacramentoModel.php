<?php
require_once BASE_PATH . '/core/Model.php';

/**
 * SacramentoModel — Catálogo de sacramentos y sus campos configurables.
 *
 * `sacramento_campos` es lo que permite que el párroco agregue "nombre del
 * padrino" a Confirmación sin que nadie toque el esquema. Ver
 * docs/ARQUITECTURA.md, sección "Formularios de sacramento configurables".
 */
class SacramentoModel extends Model
{
    /**
     * Prefijo de folio por sacramento. Explícito y no derivado del slug: dos
     * slugs distintos pueden compartir las mismas tres primeras letras
     * ("confirmacion" y "confesion" darían ambos "CON"), y eso confundiría a
     * la secretaría al leer los folios en la bandeja.
     */
    private const PREFIJOS_FOLIO = [
        'bautizo'          => 'BAU',
        'primera-comunion' => 'PCO',
        'confirmacion'     => 'CNF',
        'matrimonio'       => 'MAT',
        'confesion'        => 'CNS',
        'uncion-enfermos'  => 'UNC',
    ];

    public const TIPOS_CAMPO = [
        'texto'     => 'Texto corto',
        'textarea'  => 'Texto largo',
        'fecha'     => 'Fecha',
        'telefono'  => 'Teléfono',
        'email'     => 'Correo electrónico',
        'seleccion' => 'Lista de opciones',
        'checkbox'  => 'Casilla (sí/no)',
    ];

    public function todos(): array
    {
        return $this->fetchAll('SELECT * FROM sacramentos ORDER BY orden, nombre');
    }

    public function porId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM sacramentos WHERE id = :id', [':id' => $id]);
    }

    public function porSlugActivo(string $slug): ?array
    {
        return $this->fetchOne('SELECT * FROM sacramentos WHERE slug = :slug AND activo = 1', [':slug' => $slug]);
    }

    public function activos(): array
    {
        return $this->fetchAll('SELECT * FROM sacramentos WHERE activo = 1 ORDER BY orden, nombre');
    }

    public function crear(array $datos): int
    {
        $this->execute(
            'INSERT INTO sacramentos
                (slug, nombre, descripcion, requisitos, documentos, aportacion, imagen,
                 acepta_solicitudes, requiere_tutor, orden, activo)
             VALUES
                (:slug, :nombre, :descripcion, :requisitos, :documentos, :aportacion, :imagen,
                 :acepta, :tutor, :orden, :activo)',
            $this->parametros($datos)
        );
        return $this->lastInsertId();
    }

    public function actualizar(int $id, array $datos): int
    {
        return $this->execute(
            'UPDATE sacramentos
                SET slug = :slug, nombre = :nombre, descripcion = :descripcion,
                    requisitos = :requisitos, documentos = :documentos, aportacion = :aportacion,
                    imagen = :imagen, acepta_solicitudes = :acepta, requiere_tutor = :tutor,
                    orden = :orden, activo = :activo
              WHERE id = :id',
            $this->parametros($datos) + [':id' => $id]
        );
    }

    public function eliminar(int $id): int
    {
        return $this->execute('DELETE FROM sacramentos WHERE id = :id', [':id' => $id]);
    }

    // ── Campos configurables ────────────────────────────────────────────

    public function campos(int $sacramentoId): array
    {
        return $this->fetchAll(
            'SELECT * FROM sacramento_campos WHERE sacramento_id = :id ORDER BY orden, id',
            [':id' => $sacramentoId]
        );
    }

    public function camposActivos(int $sacramentoId): array
    {
        return $this->fetchAll(
            'SELECT * FROM sacramento_campos WHERE sacramento_id = :id AND activo = 1 ORDER BY orden, id',
            [':id' => $sacramentoId]
        );
    }

    public function campoPorId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM sacramento_campos WHERE id = :id', [':id' => $id]);
    }

    public function crearCampo(array $datos): int
    {
        $this->execute(
            'INSERT INTO sacramento_campos
                (sacramento_id, nombre_campo, etiqueta, tipo, opciones, requerido, dato_sensible, orden, activo)
             VALUES
                (:sacramento, :nombreCampo, :etiqueta, :tipo, :opciones, :requerido, :sensible, :orden, :activo)',
            $this->parametrosCampo($datos)
        );
        return $this->lastInsertId();
    }

    public function actualizarCampo(int $id, array $datos): int
    {
        return $this->execute(
            'UPDATE sacramento_campos
                SET etiqueta = :etiqueta, tipo = :tipo, opciones = :opciones,
                    requerido = :requerido, dato_sensible = :sensible, orden = :orden, activo = :activo
              WHERE id = :id',
            [
                ':etiqueta' => $datos['etiqueta'],
                ':tipo'     => $datos['tipo'],
                ':opciones' => $datos['opciones'],
                ':requerido'=> $datos['requerido'],
                ':sensible' => $datos['dato_sensible'],
                ':orden'    => $datos['orden'],
                ':activo'   => $datos['activo'],
                ':id'       => $id,
            ]
        );
    }

    public function eliminarCampo(int $id): int
    {
        return $this->execute('DELETE FROM sacramento_campos WHERE id = :id', [':id' => $id]);
    }

    /** nombre_campo derivado de la etiqueta: "Nombre del padrino" → "nombre_del_padrino". */
    public static function slugCampo(string $etiqueta): string
    {
        $limpio = Slug::generar($etiqueta, 40);
        return str_replace('-', '_', $limpio);
    }

    /**
     * Prefijo de folio para un sacramento. Los seis del catálogo original
     * tienen un código fijo; uno nuevo que se cree desde el panel recibe una
     * derivación genérica de su slug (con el riesgo, aceptado, de coincidir
     * con otro si comparten las tres primeras letras).
     */
    public static function prefijoFolio(string $slug): string
    {
        if (isset(self::PREFIJOS_FOLIO[$slug])) {
            return self::PREFIJOS_FOLIO[$slug];
        }
        $letras = strtoupper(substr(str_replace('-', '', $slug), 0, 3));
        return $letras !== '' ? $letras : 'SAC';
    }

    private function parametros(array $datos): array
    {
        return [
            ':slug'        => $datos['slug'],
            ':nombre'      => $datos['nombre'],
            ':descripcion' => $datos['descripcion'],
            ':requisitos'  => $datos['requisitos'],
            ':documentos'  => $datos['documentos'],
            ':aportacion'  => $datos['aportacion'],
            ':imagen'      => $datos['imagen'],
            ':acepta'      => $datos['acepta_solicitudes'],
            ':tutor'       => $datos['requiere_tutor'],
            ':orden'       => $datos['orden'],
            ':activo'      => $datos['activo'],
        ];
    }

    private function parametrosCampo(array $datos): array
    {
        return [
            ':sacramento'  => $datos['sacramento_id'],
            ':nombreCampo' => $datos['nombre_campo'],
            ':etiqueta'    => $datos['etiqueta'],
            ':tipo'        => $datos['tipo'],
            ':opciones'    => $datos['opciones'],
            ':requerido'   => $datos['requerido'],
            ':sensible'    => $datos['dato_sensible'],
            ':orden'       => $datos['orden'],
            ':activo'      => $datos['activo'],
        ];
    }
}
