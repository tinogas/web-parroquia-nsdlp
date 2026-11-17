<?php
require_once BASE_PATH . '/core/Model.php';
require_once BASE_PATH . '/modules/pastorales/PastoralModel.php';
require_once BASE_PATH . '/modules/avisos/AvisoModel.php';

/**
 * MiModel — Lo que ve cada integrante en la página de su enlace personal
 * (MiController), fuera de los turnos: sus pastorales, lo que su pastoral
 * publica hacia dentro y lo que viene.
 *
 * Son las mismas reglas que ya sigue el panel con una cuenta de Consulta, para
 * que el enlace no enseñe ni más ni menos que eso:
 *   - avisos publicados hacia dentro (`publicado_interno`), todavía vigentes,
 *     de su pastoral o de la Comisión que la agrupa, los generales de la
 *     parroquia y los comunicados, que van a todo el mundo
 *     (AvisoModel::condicionSinLeer());
 *   - eventos de su pastoral, publicados o no: la agenda del panel también le
 *     enseña al equipo lo que todavía no sale en el sitio —las reuniones
 *     internas casi nunca salen—;
 *   - documentos activos y entradas publicadas del tablero de su pastoral.
 */
class MiModel extends Model
{
    /** Cuántos avisos se listan: los más recientes. */
    public const AVISOS = 10;

    /** Hasta cuántos eventos y con cuántos días de anticipación. */
    public const EVENTOS = 8;
    public const DIAS_EVENTOS = 120;

    /**
     * Las pastorales activas en las que está marcada en su ficha, por nombre,
     * con quién coordina cada una (PastoralModel::conCoordinacion()).
     */
    public function pastoralesDe(int $personaId): array
    {
        return (new PastoralModel())->conCoordinacion($this->fetchAll(
            'SELECT p.id, p.slug, p.nombre, p.descripcion_corta, p.icono, p.responsable_nombre,
                    p.dia_reunion, p.hora_reunion, p.lugar_reunion, p.whatsapp_grupo
               FROM persona_pastorales pp
               JOIN pastorales p ON p.id = pp.pastoral_id
              WHERE pp.persona_id = :persona AND p.activa = 1
              ORDER BY p.nombre',
            [':persona' => $personaId]
        ));
    }

    /**
     * Los avisos que le tocan leer, de todas sus pastorales a la vez, del más
     * reciente al más viejo.
     *
     * @param int[] $pastoralIds sus pastorales; se les suman las Comisiones que las agrupan
     */
    public function avisos(array $pastoralIds): array
    {
        $audiencia = (new PastoralModel())->conAncestros($pastoralIds);
        $params    = [':todos' => AvisoModel::TIPO_PARA_TODOS];
        $deSuyas   = '';
        if ($audiencia) {
            $marcas = [];
            foreach (array_values($audiencia) as $i => $id) {
                $marcas[]          = ":a{$i}";
                $params[":a{$i}"] = (int) $id;
            }
            $deSuyas = ' OR a.pastoral_id IN (' . implode(',', $marcas) . ')';
        }

        return $this->fetchAll(
            "SELECT a.id, a.titulo, a.resumen, a.contenido, a.imagen, a.archivo_pdf, a.tipo,
                    a.publicado, a.slug, a.publicado_interno_at, a.pastoral_id,
                    p.nombre AS pastoral_nombre
               FROM avisos a
               LEFT JOIN pastorales p ON p.id = a.pastoral_id
              WHERE a.publicado_interno = 1
                AND (a.vigente_hasta IS NULL OR a.vigente_hasta >= CURDATE())
                AND (a.pastoral_id IS NULL OR a.tipo = :todos{$deSuyas})
              ORDER BY a.publicado_interno_at DESC, a.id DESC
              LIMIT " . self::AVISOS,
            $params
        );
    }

    /** Lo que viene en su pastoral: de hoy a DIAS_EVENTOS días, los primeros EVENTOS. */
    public function eventos(int $pastoralId): array
    {
        return $this->fetchAll(
            'SELECT id, slug, titulo, lugar, fecha_inicio, fecha_fin, todo_el_dia, publicado
               FROM eventos
              WHERE pastoral_id = :pastoral
                AND COALESCE(fecha_fin, fecha_inicio) >= CURDATE()
                AND fecha_inicio < DATE_ADD(CURDATE(), INTERVAL ' . self::DIAS_EVENTOS . ' DAY)
              ORDER BY fecha_inicio
              LIMIT ' . self::EVENTOS,
            [':pastoral' => $pastoralId]
        );
    }

    public function documentos(int $pastoralId): array
    {
        return (new PastoralModel())->documentosActivos($pastoralId);
    }

    /** Las entradas publicadas del tablero que siguen vigentes. */
    public function tablero(int $pastoralId): array
    {
        return $this->fetchAll(
            'SELECT id, titulo, descripcion, fecha_inicio, fecha_fin
               FROM pastoral_tablero
              WHERE pastoral_id = :pastoral AND publicado = 1
                AND COALESCE(fecha_fin, fecha_inicio) >= CURDATE()
              ORDER BY fecha_inicio, orden',
            [':pastoral' => $pastoralId]
        );
    }
}
