<?php
require_once BASE_PATH . '/core/Model.php';

/**
 * PastoralModel — Coro, catequesis, caridad, jóvenes, ministros MESC...
 *
 * Es la entidad sobre la que gira el alcance del rol coordinador: ver
 * docs/ARQUITECTURA.md, sección "El alcance por pastoral es ortogonal a la
 * matriz". Este modelo no aplica ningún filtro de alcance por sí mismo —
 * cualquiera puede LEER el catálogo de pastorales— son avisos, eventos,
 * galería y actividades quienes sí lo hacen.
 */
class PastoralModel extends Model
{
    public function todas(): array
    {
        return $this->fetchAll('SELECT * FROM pastorales ORDER BY orden, nombre');
    }

    public function porId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM pastorales WHERE id = :id', [':id' => $id]);
    }

    public function porSlugActiva(string $slug): ?array
    {
        return $this->fetchOne('SELECT * FROM pastorales WHERE slug = :slug AND activa = 1', [':slug' => $slug]);
    }

    /** Para el sitio público. */
    public function activas(): array
    {
        return $this->fetchAll('SELECT * FROM pastorales WHERE activa = 1 ORDER BY orden, nombre');
    }

    /** id => nombre, para selectores (formularios de avisos, eventos, galería). */
    public function paraSelector(): array
    {
        return $this->fetchAll('SELECT id, nombre FROM pastorales WHERE activa = 1 ORDER BY nombre');
    }

    /**
     * Igual que paraSelector(), pero sin las Comisiones (las que agrupan
     * hijas): una Comisión no organiza nada operativo por sí misma, y el
     * alcance de un coordinador no se hereda de Comisión a hija
     * (Auth::pastoralesPermitidas() no mira pastoral_padre_id), así que
     * marcársela ahí no le daría acceso a nada real. Para el checklist de
     * "qué pastorales administra" al dar de alta un usuario.
     */
    public function sinComisiones(): array
    {
        return $this->fetchAll(
            'SELECT id, nombre FROM pastorales p
              WHERE activa = 1
                AND NOT EXISTS (SELECT 1 FROM pastorales h WHERE h.pastoral_padre_id = p.id)
              ORDER BY nombre'
        );
    }

    public function crear(array $datos): int
    {
        $this->execute(
            'INSERT INTO pastorales
                (centro_id, pastoral_padre_id, slug, nombre, descripcion_corta, descripcion, imagen, icono,
                 responsable_nombre, responsable_persona_id,
                 contacto_email, contacto_telefono, dia_reunion, hora_reunion, lugar_reunion,
                 acepta_voluntarios, organiza_parejas, orden, activa)
             VALUES
                (:centro, :padre, :slug, :nombre, :descCorta, :desc, :imagen, :icono,
                 :responsable, :responsablePersona,
                 :email, :telefono, :diaReunion, :horaReunion, :lugarReunion,
                 :voluntarios, :parejas, :orden, :activa)',
            $this->parametros($datos)
        );
        return $this->lastInsertId();
    }

    public function actualizar(int $id, array $datos): int
    {
        return $this->execute(
            'UPDATE pastorales
                SET centro_id = :centro, pastoral_padre_id = :padre, slug = :slug, nombre = :nombre,
                    descripcion_corta = :descCorta,
                    descripcion = :desc, imagen = :imagen, icono = :icono,
                    responsable_nombre = :responsable, responsable_persona_id = :responsablePersona,
                    contacto_email = :email,
                    contacto_telefono = :telefono, dia_reunion = :diaReunion,
                    hora_reunion = :horaReunion, lugar_reunion = :lugarReunion,
                    acepta_voluntarios = :voluntarios, organiza_parejas = :parejas,
                    orden = :orden, activa = :activa
              WHERE id = :id',
            $this->parametros($datos) + [':id' => $id]
        );
    }

    /** Candidatas a "Comisión padre" en el selector: sin padre propio (máximo 2 niveles), sin incluirse a sí misma. */
    public function candidatosPadre(?int $excluirId = null): array
    {
        $condiciones = ['pastoral_padre_id IS NULL'];
        $params      = [];
        if ($excluirId !== null) {
            $condiciones[] = 'id != :excluir';
            $params[':excluir'] = $excluirId;
        }
        return $this->fetchAll(
            'SELECT id, nombre FROM pastorales WHERE ' . implode(' AND ', $condiciones) . ' ORDER BY nombre',
            $params
        );
    }

    /**
     * Esos ids más las Comisiones que los agrupan, sin repetidos. Es la
     * audiencia de lectura de Auth::pastoralesAudiencia(): quien está en
     * Proclamadores tiene que poder leer lo que se publique en Litúrgica.
     *
     * Una sola consulta y no un recorrido recursivo porque la jerarquía es de
     * dos niveles exactos y así está garantizado (ver candidatosPadre()): el
     * padre de una pastoral nunca tiene padre a su vez.
     *
     * @param  array $ids Pastorales asignadas a la cuenta
     * @return array Esas mismas más sus padres
     */
    public function conAncestros(array $ids): array
    {
        if (!$ids) {
            return [];
        }

        $marcadores = [];
        $params     = [];
        foreach (array_values($ids) as $i => $id) {
            $clave          = ":id{$i}";
            $marcadores[]   = $clave;
            $params[$clave] = (int) $id;
        }

        $padres = $this->fetchAll(
            'SELECT DISTINCT pastoral_padre_id FROM pastorales
              WHERE id IN (' . implode(',', $marcadores) . ') AND pastoral_padre_id IS NOT NULL',
            $params
        );

        return array_values(array_unique(array_merge(
            array_map('intval', array_values($ids)),
            array_map(static fn (array $f): int => (int) $f['pastoral_padre_id'], $padres)
        )));
    }

    /**
     * Agrupa estos ids de pastoral (asignados a una cuenta o a una ficha) por
     * su Comisión padre, para listados compactos (Usuarios, equipo pastoral):
     * mismo criterio de agrupado que agrupadoVisible(), pero sobre un
     * conjunto arbitrario de ids en vez de "todas recortadas a un alcance".
     *
     * Trae también el padre de cada id aunque el padre en sí no esté entre
     * los ids —nunca lo está: una Comisión no se asigna directamente—, solo
     * para tener su nombre como encabezado. agrupar() ya deja fuera, sin
     * ayuda extra, cualquier hija de esa Comisión que no esté en $ids: la
     * consulta no la trae.
     */
    public function agruparIds(array $ids): array
    {
        if (!$ids) {
            return ['comisiones' => [], 'sueltas' => []];
        }

        // Dos marcadores por id, no uno reutilizado: con
        // PDO::ATTR_EMULATE_PREPARES en false (ver config/database.php), MySQL
        // usa prepared statements nativos, que no admiten el mismo parámetro
        // nombrado dos veces en la misma consulta.
        $marcadoresA = [];
        $marcadoresB = [];
        $params      = [];
        foreach (array_values($ids) as $i => $id) {
            $marcadoresA[]      = ":idA{$i}";
            $marcadoresB[]      = ":idB{$i}";
            $params[":idA{$i}"] = (int) $id;
            $params[":idB{$i}"] = (int) $id;
        }

        $filas = $this->fetchAll(
            "SELECT id, nombre, pastoral_padre_id FROM pastorales
              WHERE id IN (" . implode(',', $marcadoresA) . ")
                 OR id IN (
                       SELECT DISTINCT pastoral_padre_id FROM pastorales
                        WHERE id IN (" . implode(',', $marcadoresB) . ") AND pastoral_padre_id IS NOT NULL
                    )
              ORDER BY orden, nombre",
            $params
        );

        return $this->agrupar($filas);
    }

    public function tieneHijos(int $id): bool
    {
        return (bool) $this->fetchColumn(
            'SELECT 1 FROM pastorales WHERE pastoral_padre_id = :id LIMIT 1',
            [':id' => $id]
        );
    }

    /**
     * Publica la pastoral en el bloque "Pastorales y comisiones" del menú del
     * panel. Deliberadamente no hay `desactivarEnMenu()`: no se pidió, y
     * agregar el interruptor simétrico sin un caso de uso real solo abriría
     * una pregunta de UX (¿qué le pasa a quien ya la tenía marcada de
     * favorita, a los enlaces ya compartidos?) que nadie necesita responder
     * todavía. Ver PastoralController::menuActivar().
     */
    public function activarEnMenu(int $id): int
    {
        return $this->execute('UPDATE pastorales SET visible_en_menu = 1 WHERE id = :id', [':id' => $id]);
    }

    /** Pastorales hijas activas de una Comisión, para su ficha pública. */
    public function hijasActivas(int $padreId): array
    {
        return $this->fetchAll(
            'SELECT * FROM pastorales WHERE pastoral_padre_id = :id AND activa = 1 ORDER BY orden, nombre',
            [':id' => $padreId]
        );
    }

    /** Para el panel: todas, agrupadas en [padre => [hijas...]] + sueltas aparte. */
    public function todasAgrupadas(): array
    {
        return $this->agrupar($this->todas());
    }

    /** Para el sitio público: solo activas, mismo agrupamiento. */
    public function activasAgrupadas(): array
    {
        return $this->agrupar($this->activas());
    }

    /**
     * `todasAgrupadas()` recortado al alcance de quien lo pide: $permitidas
     * son los ids de pastoral que puede ver, o null para alcance global (ve
     * todo). Reutilizado por PastoralController::index() (la lista completa
     * de administración) y por PanelController::index() (que además lo
     * recorta a lo publicado en el menú, ver soloEnMenu()) — así ambos
     * aplican el mismo criterio de "la Comisión-padre se conserva como
     * encabezado de solo lectura si el usuario ve al menos una hija, aunque
     * no vea a la Comisión en sí".
     */
    public function agrupadoVisible(?array $permitidas): array
    {
        $agrupado = $this->todasAgrupadas();
        if ($permitidas === null) {
            return $agrupado;
        }
        return $this->recortarGrupos($agrupado, static fn (array $p): bool => in_array((int) $p['id'], $permitidas, true));
    }

    /** Recorta un agrupado ya resuelto a solo lo publicado en el menú (visible_en_menu = 1). */
    public function soloEnMenu(array $agrupado): array
    {
        return $this->recortarGrupos($agrupado, static fn (array $p): bool => (bool) $p['visible_en_menu']);
    }

    /** @param callable(array): bool $criterio */
    private function recortarGrupos(array $agrupado, callable $criterio): array
    {
        $agrupado['comisiones'] = array_values(array_filter(array_map(
            static function (array $grupo) use ($criterio): ?array {
                $hijas = array_values(array_filter($grupo['hijas'], $criterio));
                return $hijas ? ['padre' => $grupo['padre'], 'hijas' => $hijas] : null;
            },
            $agrupado['comisiones']
        )));
        $agrupado['sueltas'] = array_values(array_filter($agrupado['sueltas'], $criterio));
        return $agrupado;
    }

    /**
     * Agrupa una lista ya ordenada (por orden, nombre) en Comisiones con sus
     * hijas y pastorales sueltas, sin volver a ordenar nada.
     *
     * @return array{comisiones: array<array{padre: array, hijas: array}>, sueltas: array}
     */
    private function agrupar(array $pastorales): array
    {
        $porId = [];
        foreach ($pastorales as $fila) {
            $porId[(int) $fila['id']] = $fila;
        }

        $comisiones = [];
        $sueltas    = [];
        foreach ($pastorales as $fila) {
            if ($fila['pastoral_padre_id'] !== null) {
                continue;
            }
            $hijas = array_values(array_filter(
                $pastorales,
                static fn (array $h): bool => (int) ($h['pastoral_padre_id'] ?? 0) === (int) $fila['id']
            ));
            if ($hijas) {
                $comisiones[] = ['padre' => $fila, 'hijas' => $hijas];
            } else {
                $sueltas[] = $fila;
            }
        }

        return ['comisiones' => $comisiones, 'sueltas' => $sueltas];
    }

    /**
     * Borrado físico de la fila (no un desactivado): `pastoral_actividades` y
     * `pastoral_documentos` se van con ella en cascada, pero avisos, eventos
     * y cursos (`ON DELETE SET NULL`) no se borran, quedan sin pastoral, como
     * contenido parroquial general. Por eso PastoralController::eliminar()
     * exige confirmar con contraseña de Administrador.
     */
    public function eliminar(int $id): int
    {
        return $this->execute('DELETE FROM pastorales WHERE id = :id', [':id' => $id]);
    }

    // ── Actividades ─────────────────────────────────────────────────────
    // OJO, no confundir con el tablero de más abajo: esto es la lista fija de
    // "qué hace la pastoral" —con `tipo`, sin fechas, la que se lee en su
    // página pública—, y el tablero son actividades con fecha y publicación.
    // Las dos se llamaban "actividades" hasta que la segunda pasó a
    // `pastoral_tablero`; ver docs/migraciones/2026-09-04-tablero-y-documentos-compartidos.sql

    public const TIPOS_ACTIVIDAD = [
        'comunitaria'  => 'Comunitaria',
        'apoyo_social' => 'Apoyo social',
        'formacion'    => 'Formación',
        'liturgica'    => 'Litúrgica',
    ];

    public function actividades(int $pastoralId): array
    {
        return $this->fetchAll(
            'SELECT * FROM pastoral_actividades WHERE pastoral_id = :id ORDER BY orden, id',
            [':id' => $pastoralId]
        );
    }

    public function actividadesActivas(int $pastoralId): array
    {
        return $this->fetchAll(
            'SELECT * FROM pastoral_actividades WHERE pastoral_id = :id AND activa = 1 ORDER BY orden, id',
            [':id' => $pastoralId]
        );
    }

    public function actividadPorId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM pastoral_actividades WHERE id = :id', [':id' => $id]);
    }

    public function crearActividad(array $datos): int
    {
        $this->execute(
            'INSERT INTO pastoral_actividades (pastoral_id, titulo, descripcion, tipo, orden, activa)
             VALUES (:pastoral, :titulo, :descripcion, :tipo, :orden, :activa)',
            [
                ':pastoral'    => $datos['pastoral_id'],
                ':titulo'      => $datos['titulo'],
                ':descripcion' => $datos['descripcion'],
                ':tipo'        => $datos['tipo'],
                ':orden'       => $datos['orden'],
                ':activa'      => $datos['activa'],
            ]
        );
        return $this->lastInsertId();
    }

    public function actualizarActividad(int $id, array $datos): int
    {
        return $this->execute(
            'UPDATE pastoral_actividades
                SET titulo = :titulo, descripcion = :descripcion, tipo = :tipo,
                    orden = :orden, activa = :activa
              WHERE id = :id',
            [
                ':titulo'      => $datos['titulo'],
                ':descripcion' => $datos['descripcion'],
                ':tipo'        => $datos['tipo'],
                ':orden'       => $datos['orden'],
                ':activa'      => $datos['activa'],
                ':id'          => $id,
            ]
        );
    }

    public function eliminarActividad(int $id): int
    {
        return $this->execute('DELETE FROM pastoral_actividades WHERE id = :id', [':id' => $id]);
    }

    // ── Tablero de actividades con fecha ────────────────────────────────
    // Actividades con vigencia y publicación, como un mini-`eventos`, a
    // diferencia de la lista fija de arriba. Vive en `pastoral_tablero`, sin
    // prefijo de módulo: nació dentro de Catequesis y lo estrenó
    // Proclamadores, pero la pantalla es la misma para cualquier pastoral que
    // la necesite, así que los dos módulos entran por aquí en vez de repetir
    // estas cinco consultas en su propio modelo. Lo mismo vale para los
    // documentos de abajo.

    public function tablero(int $pastoralId): array
    {
        return $this->fetchAll(
            'SELECT * FROM pastoral_tablero WHERE pastoral_id = :id ORDER BY fecha_inicio DESC, orden',
            [':id' => $pastoralId]
        );
    }

    public function entradaTablero(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM pastoral_tablero WHERE id = :id', [':id' => $id]);
    }

    public function crearEntradaTablero(array $datos, int $usuarioId): int
    {
        $this->execute(
            'INSERT INTO pastoral_tablero
                (pastoral_id, titulo, descripcion, fecha_inicio, fecha_fin, publicado, orden, usuario_id)
             VALUES (:pastoral, :titulo, :descripcion, :inicio, :fin, :publicado, :orden, :usuario)',
            $this->parametrosTablero($datos) + [':pastoral' => $datos['pastoral_id'], ':usuario' => $usuarioId]
        );
        return $this->lastInsertId();
    }

    public function actualizarEntradaTablero(int $id, array $datos): int
    {
        return $this->execute(
            'UPDATE pastoral_tablero
                SET titulo = :titulo, descripcion = :descripcion, fecha_inicio = :inicio,
                    fecha_fin = :fin, publicado = :publicado, orden = :orden
              WHERE id = :id',
            $this->parametrosTablero($datos) + [':id' => $id]
        );
    }

    public function eliminarEntradaTablero(int $id): int
    {
        return $this->execute('DELETE FROM pastoral_tablero WHERE id = :id', [':id' => $id]);
    }

    /** Sin :pastoral ni :usuario: la entrada no cambia de pastoral ni de autor al editarse. */
    private function parametrosTablero(array $datos): array
    {
        return [
            ':titulo'      => $datos['titulo'],
            ':descripcion' => $datos['descripcion'],
            ':inicio'      => $datos['fecha_inicio'],
            ':fin'         => $datos['fecha_fin'],
            ':publicado'   => $datos['publicado'],
            ':orden'       => $datos['orden'],
        ];
    }

    // ── Documentos descargables (issue #3) ──────────────────────────────
    // Tabla compartida, igual que el tablero: el panel básico de cualquier
    // pastoral y los módulos dedicados escriben en la misma lista, así que un
    // documento subido desde un sitio se ve desde el otro. Hubo un
    // `catequesis_documentos` idéntico a esta tabla hasta la migración citada
    // arriba, y eran dos listas ciegas la una a la otra.

    public function documentos(int $pastoralId): array
    {
        return $this->fetchAll(
            'SELECT * FROM pastoral_documentos WHERE pastoral_id = :id ORDER BY orden, id',
            [':id' => $pastoralId]
        );
    }

    public function documentosActivos(int $pastoralId): array
    {
        return $this->fetchAll(
            'SELECT * FROM pastoral_documentos WHERE pastoral_id = :id AND activo = 1 ORDER BY orden, id',
            [':id' => $pastoralId]
        );
    }

    public function documentoPorId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM pastoral_documentos WHERE id = :id', [':id' => $id]);
    }

    public function crearDocumento(array $datos, int $usuarioId): int
    {
        $this->execute(
            'INSERT INTO pastoral_documentos (pastoral_id, titulo, archivo, orden, activo, usuario_id)
             VALUES (:pastoral, :titulo, :archivo, :orden, :activo, :usuario)',
            [
                ':pastoral' => $datos['pastoral_id'],
                ':titulo'   => $datos['titulo'],
                ':archivo'  => $datos['archivo'],
                ':orden'    => $datos['orden'],
                ':activo'   => $datos['activo'],
                ':usuario'  => $usuarioId,
            ]
        );
        return $this->lastInsertId();
    }

    public function eliminarDocumento(int $id): int
    {
        return $this->execute('DELETE FROM pastoral_documentos WHERE id = :id', [':id' => $id]);
    }

    // ── Parejas dentro de la pastoral ───────────────────────────────────
    // Solo para las que se organizan así (pastorales.organiza_parejas):
    // Matrimonios y AMA trabajan con parejas, no con personas sueltas. La
    // pertenencia sigue viniendo de persona_pastorales —esto no da de alta a
    // nadie, solo liga a dos que ya están—, y nadie está obligado a tener
    // pareja. El porqué de la tabla, en
    // docs/migraciones/2026-09-05-parejas-por-pastoral.sql

    /** Las parejas de la pastoral, cada una con el nombre y la foto de los dos. */
    public function parejas(int $pastoralId): array
    {
        return $this->fetchAll(
            'SELECT pj.id, pj.persona_a_id, pj.persona_b_id,
                    a.nombre AS nombre_a, a.foto AS foto_a, a.activo AS activo_a,
                    b.nombre AS nombre_b, b.foto AS foto_b, b.activo AS activo_b
               FROM pastoral_parejas pj
               JOIN personas a ON a.id = pj.persona_a_id
               JOIN personas b ON b.id = pj.persona_b_id
              WHERE pj.pastoral_id = :id
              ORDER BY a.nombre, b.nombre',
            [':id' => $pastoralId]
        );
    }

    public function parejaPorId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM pastoral_parejas WHERE id = :id', [':id' => $id]);
    }

    /**
     * La pareja en la que esa persona ya está dentro de la pastoral, si la hay.
     * Mira las dos columnas porque el orden lo decide el id, no quién se eligió
     * primero en el formulario: es la comprobación que ninguna clave de la
     * tabla puede hacer, y sin ella alguien acabaría emparejado dos veces.
     */
    public function personaEmparejada(int $pastoralId, int $personaId): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM pastoral_parejas
              WHERE pastoral_id = :pastoral
                AND (persona_a_id = :personaA OR persona_b_id = :personaB)',
            // Dos marcadores para la misma persona, y no uno repetido: la
            // conexión va con PDO::ATTR_EMULATE_PREPARES en false (ver
            // config/database.php) y ahí un :nombre solo puede aparecer una vez.
            [':pastoral' => $pastoralId, ':personaA' => $personaId, ':personaB' => $personaId]
        );
    }

    public function crearPareja(int $pastoralId, int $personaA, int $personaB): int
    {
        // Siempre el id menor primero, para que "él con ella" y "ella con él"
        // no puedan ser dos filas distintas.
        $this->execute(
            'INSERT INTO pastoral_parejas (pastoral_id, persona_a_id, persona_b_id)
             VALUES (:pastoral, :a, :b)',
            [
                ':pastoral' => $pastoralId,
                ':a'        => min($personaA, $personaB),
                ':b'        => max($personaA, $personaB),
            ]
        );
        return $this->lastInsertId();
    }

    public function eliminarPareja(int $id): int
    {
        return $this->execute('DELETE FROM pastoral_parejas WHERE id = :id', [':id' => $id]);
    }

    private function parametros(array $datos): array
    {
        return [
            ':centro'      => $datos['centro_id'],
            ':padre'       => $datos['pastoral_padre_id'],
            ':slug'        => $datos['slug'],
            ':nombre'      => $datos['nombre'],
            ':descCorta'   => $datos['descripcion_corta'],
            ':desc'        => $datos['descripcion'],
            ':imagen'      => $datos['imagen'],
            ':icono'       => $datos['icono'],
            ':responsable'         => $datos['responsable_nombre'],
            ':responsablePersona'  => $datos['responsable_persona_id'],
            ':email'       => $datos['contacto_email'],
            ':telefono'    => $datos['contacto_telefono'],
            ':diaReunion'  => $datos['dia_reunion'],
            ':horaReunion' => $datos['hora_reunion'],
            ':lugarReunion'=> $datos['lugar_reunion'],
            ':voluntarios' => $datos['acepta_voluntarios'],
            ':parejas'     => $datos['organiza_parejas'],
            ':orden'       => $datos['orden'],
            ':activa'      => $datos['activa'],
        ];
    }
}
