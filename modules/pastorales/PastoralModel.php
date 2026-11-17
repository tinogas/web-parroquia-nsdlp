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
    /** Hasta dónde baja la jerarquía: Comisión → dimensión → grupo. Ver motivoPadreInvalido(). */
    public const NIVEL_MAXIMO = 3;

    // Las cinco consultas que devuelven la ficha completa de una pastoral la
    // entregan ya con quién la coordina (conCoordinacion()): son las que llegan
    // a una vista, y así ninguna pantalla tiene que acordarse de pedirlo aparte.

    public function todas(): array
    {
        return $this->conCoordinacion($this->fetchAll('SELECT * FROM pastorales ORDER BY orden, nombre'));
    }

    public function porId(int $id): ?array
    {
        return $this->unaConCoordinacion($this->fetchOne('SELECT * FROM pastorales WHERE id = :id', [':id' => $id]));
    }

    public function porSlugActiva(string $slug): ?array
    {
        return $this->unaConCoordinacion(
            $this->fetchOne('SELECT * FROM pastorales WHERE slug = :slug AND activa = 1', [':slug' => $slug])
        );
    }

    /** Para el sitio público. */
    public function activas(): array
    {
        return $this->conCoordinacion($this->fetchAll('SELECT * FROM pastorales WHERE activa = 1 ORDER BY orden, nombre'));
    }

    // ── Quién coordina ──────────────────────────────────────────────────
    // De una a las personas que haga falta, en `pastoral_coordinadores`, más
    // —opcional— un nombre escrito a mano en `responsable_nombre` para quien
    // todavía no tiene ficha en el equipo pastoral. Antes era una persona o una
    // pareja, en dos columnas excluyentes, y no cabían dos personas que no
    // fueran matrimonio: Betesaida y Dante en Raíces. Ver
    // docs/migraciones/2026-11-16-varios-coordinadores.sql

    /**
     * Agrega a cada fila de pastoral quién la coordina, ya listo para pintar:
     *
     *   - `coordinadores`: [['id' => …, 'nombre' => …, 'activo' => …], …], solo
     *     las fichas, en su orden;
     *   - `coordina`: "Betesaida y Dante", con el nombre libre al final si lo
     *     hay; null si nadie coordina;
     *   - `coordina_varios`: más de una persona, contando el nombre libre;
     *   - `coordina_pareja`: coordinan exactamente dos fichas y son pareja entre
     *     sí (`parejas`). Es lo que pinta el corazón en el panel: ya no se elige
     *     "una pareja", se elige a dos personas y que sean matrimonio se sabe
     *     por sus fichas.
     *
     * El nombre sale de la ficha cada vez que se lee, no de una copia guardada
     * en la pastoral: así un cambio de nombre en Equipo pastoral se ve en todas
     * partes sin sincronizar nada, y borrar una ficha no deja su nombre
     * colgado. Una sola consulta para todas las filas, no una por pastoral.
     *
     * @param  array $filas Filas de `pastorales` (al menos id y responsable_nombre)
     * @return array Las mismas filas, en el mismo orden, con las cuatro llaves
     */
    public function conCoordinacion(array $filas): array
    {
        if (!$filas) {
            return [];
        }

        $marcadores = [];
        $params     = [];
        foreach (array_values($filas) as $i => $fila) {
            $marcadores[]      = ":p{$i}";
            $params[":p{$i}"] = (int) $fila['id'];
        }

        // La pareja de cada coordinador, si tiene: dos personas que coordinan
        // juntas y comparten pareja_id son un matrimonio.
        $porPastoral = [];
        foreach ($this->fetchAll(
            'SELECT pc.pastoral_id, pe.id, pe.nombre, pe.activo, j.id AS pareja_id
               FROM pastoral_coordinadores pc
               JOIN personas pe ON pe.id = pc.persona_id
               LEFT JOIN parejas j ON j.persona_a_id = pe.id OR j.persona_b_id = pe.id
              WHERE pc.pastoral_id IN (' . implode(',', $marcadores) . ')
              ORDER BY pc.pastoral_id, pc.orden, pe.nombre',
            $params
        ) as $fila) {
            $porPastoral[(int) $fila['pastoral_id']][] = $fila;
        }

        foreach ($filas as &$pastoral) {
            $suyos   = $porPastoral[(int) $pastoral['id']] ?? [];
            $nombres = array_column($suyos, 'nombre');
            $libre   = trim((string) ($pastoral['responsable_nombre'] ?? ''));
            if ($libre !== '') {
                $nombres[] = $libre;
            }

            $pastoral['coordinadores'] = array_map(
                static fn (array $c): array => ['id' => (int) $c['id'], 'nombre' => $c['nombre'], 'activo' => (bool) $c['activo']],
                $suyos
            );
            $pastoral['coordina']        = $nombres ? unir_nombres($nombres) : null;
            $pastoral['coordina_varios'] = count($nombres) > 1;
            $pastoral['coordina_pareja'] = count($nombres) === 2 && count($suyos) === 2
                && $suyos[0]['pareja_id'] !== null && $suyos[0]['pareja_id'] === $suyos[1]['pareja_id'];
        }
        unset($pastoral);

        return $filas;
    }

    /** conCoordinacion() para una sola fila, que puede no existir. */
    private function unaConCoordinacion(?array $fila): ?array
    {
        return $fila ? $this->conCoordinacion([$fila])[0] : null;
    }

    /**
     * Deja a estas personas como coordinadoras de la pastoral, en este orden.
     * Reemplaza la lista entera: quitar a alguien es no mandarlo. Los ids
     * repetidos se quedan en su primera posición.
     *
     * @param int[] $personaIds
     */
    private function guardarCoordinadores(int $pastoralId, array $personaIds): void
    {
        $this->execute('DELETE FROM pastoral_coordinadores WHERE pastoral_id = :id', [':id' => $pastoralId]);
        foreach (array_values(array_unique(array_map('intval', $personaIds))) as $orden => $personaId) {
            $this->execute(
                'INSERT INTO pastoral_coordinadores (pastoral_id, persona_id, orden) VALUES (:pastoral, :persona, :orden)',
                [':pastoral' => $pastoralId, ':persona' => $personaId, ':orden' => $orden]
            );
        }
    }

    /** id => nombre, para selectores (formularios de avisos, eventos, galería). */
    public function paraSelector(): array
    {
        return $this->fetchAll('SELECT id, nombre FROM pastorales WHERE activa = 1 ORDER BY nombre');
    }

    /**
     * Igual que paraSelector(), pero sin las Comisiones (las de primer nivel
     * que agrupan hijas): una Comisión no organiza nada operativo por sí
     * misma, y el alcance de un coordinador no se hereda de Comisión a hija
     * (Auth::pastoralesPermitidas() no mira pastoral_padre_id), así que
     * marcársela ahí no le daría acceso a nada real. Para el checklist de
     * "qué pastorales administra" al dar de alta un usuario.
     *
     * Una dimensión que a su vez agrupa grupos —Piedad Popular dentro de
     * Litúrgica— sí se ofrece: tiene coordinadora, panel y vida propia, no es
     * solo un encabezado. Administrarla tampoco da acceso a sus grupos.
     */
    public function sinComisiones(): array
    {
        return $this->fetchAll(
            'SELECT id, nombre FROM pastorales p
              WHERE activa = 1
                AND NOT (p.pastoral_padre_id IS NULL
                         AND EXISTS (SELECT 1 FROM pastorales h WHERE h.pastoral_padre_id = p.id))
              ORDER BY nombre'
        );
    }

    /** $datos['coordinadores']: ids de persona, en orden (ver guardarCoordinadores()). */
    public function crear(array $datos): int
    {
        $this->beginTransaction();
        try {
            $this->execute(
                'INSERT INTO pastorales
                    (centro_id, pastoral_padre_id, slug, nombre, descripcion_corta, descripcion, imagen, icono,
                     responsable_nombre,
                     contacto_email, contacto_telefono, dia_reunion, hora_reunion, lugar_reunion,
                     acepta_voluntarios, orden, activa)
                 VALUES
                    (:centro, :padre, :slug, :nombre, :descCorta, :desc, :imagen, :icono,
                     :responsable,
                     :email, :telefono, :diaReunion, :horaReunion, :lugarReunion,
                     :voluntarios, :orden, :activa)',
                $this->parametros($datos)
            );
            $id = $this->lastInsertId();
            $this->guardarCoordinadores($id, $datos['coordinadores']);
            $this->commit();
            return $id;
        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    /** $datos['coordinadores']: ids de persona, en orden (ver guardarCoordinadores()). */
    public function actualizar(int $id, array $datos): int
    {
        $this->beginTransaction();
        try {
            $filas = $this->execute(
                'UPDATE pastorales
                    SET centro_id = :centro, pastoral_padre_id = :padre, slug = :slug, nombre = :nombre,
                        descripcion_corta = :descCorta,
                        descripcion = :desc, imagen = :imagen, icono = :icono,
                        responsable_nombre = :responsable,
                        contacto_email = :email,
                        contacto_telefono = :telefono, dia_reunion = :diaReunion,
                        hora_reunion = :horaReunion, lugar_reunion = :lugarReunion,
                        acepta_voluntarios = :voluntarios, orden = :orden, activa = :activa
                  WHERE id = :id',
                $this->parametros($datos) + [':id' => $id]
            );
            $this->guardarCoordinadores($id, $datos['coordinadores']);
            $this->commit();
            return $filas;
        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    // ── Jerarquía: Comisión → dimensión → grupo ─────────────────────────
    // Hasta noviembre de 2026 eran dos niveles exactos, y así lo daba por
    // hecho buena parte del código. El tercero llegó con Piedad Popular, que
    // pasó a ser una dimensión de Litúrgica con sus propios grupos —Llama del
    // Amor, Rosario Diario, Sábado Comunitario, Viernes de Reparación—. La
    // base no cambió: pastoral_padre_id siempre pudo apuntar a cualquier
    // pastoral; el límite vive aquí. La tabla tiene unas decenas de filas, así
    // que las preguntas de profundidad se responden con el mapa entero en
    // memoria y no con consultas recursivas.

    /**
     * id => ['nombre', 'slug', 'padre' => ?int], de todas las pastorales,
     * ordenado por nombre.
     */
    private function mapaJerarquia(): array
    {
        $mapa = [];
        foreach ($this->fetchAll('SELECT id, nombre, slug, pastoral_padre_id FROM pastorales ORDER BY nombre') as $fila) {
            $mapa[(int) $fila['id']] = [
                'nombre' => $fila['nombre'],
                'slug'   => $fila['slug'],
                'padre'  => $fila['pastoral_padre_id'] !== null ? (int) $fila['pastoral_padre_id'] : null,
            ];
        }
        return $mapa;
    }

    /**
     * De la raíz hasta $id, inclusive: [id, …]. El tope de pasos es defensa
     * contra un ciclo en los datos, que guardar() ya no deja crear.
     *
     * @return int[]
     */
    private function rutaDe(int $id, array $mapa): array
    {
        $ruta = [];
        for ($actual = $id; $actual !== null && isset($mapa[$actual]) && count($ruta) <= count($mapa); $actual = $mapa[$actual]['padre']) {
            array_unshift($ruta, $actual);
        }
        return $ruta;
    }

    /** Cuántos niveles cuelgan debajo de $id: 0 sin hijas, 1 con hijas, 2 si alguna hija tiene las suyas. */
    private function alturaBajo(int $id, array $mapa, int $tope = 0): int
    {
        if ($tope > count($mapa)) {
            return 0;
        }
        $altura = 0;
        foreach ($mapa as $hijaId => $nodo) {
            if ($nodo['padre'] === $id) {
                $altura = max($altura, 1 + $this->alturaBajo($hijaId, $mapa, $tope + 1));
            }
        }
        return $altura;
    }

    /**
     * Por qué $padreId no puede ser la comisión padre de $id (null si sí
     * puede). $id es null para una pastoral que todavía no existe. Las tres
     * reglas: el padre existe, no es ella misma ni algo que ella agrupa
     * —sería un ciclo—, y con él nadie queda más abajo del tercer nivel:
     * ni ella ni los grupos que ya cuelgan de ella.
     */
    public function motivoPadreInvalido(?int $id, int $padreId, ?array $mapa = null): ?string
    {
        $mapa ??= $this->mapaJerarquia();
        if (!isset($mapa[$padreId])) {
            return 'Esa comisión padre ya no existe.';
        }
        $nombrePadre = '«' . $mapa[$padreId]['nombre'] . '»';
        if ($id !== null && $padreId === $id) {
            return 'Una pastoral no puede ir dentro de sí misma.';
        }
        if ($id !== null && in_array($id, $this->rutaDe($padreId, $mapa), true)) {
            return "{$nombrePadre} está dentro de esta pastoral: no puede ser también su comisión padre.";
        }

        $nivelPadre = count($this->rutaDe($padreId, $mapa));
        $altura     = $id !== null && isset($mapa[$id]) ? $this->alturaBajo($id, $mapa) : 0;
        if ($nivelPadre + 1 + $altura > self::NIVEL_MAXIMO) {
            return $altura
                ? "Esta pastoral ya agrupa a otras: dentro de {$nombrePadre}, sus grupos quedarían en un cuarto nivel. "
                  . 'Solo puede ir dentro de una Comisión.'
                : "{$nombrePadre} ya es un grupo dentro de otra dimensión: no se puede bajar a un cuarto nivel.";
        }
        return null;
    }

    /**
     * Candidatas a "Comisión padre" en el selector: las que motivoPadreInvalido()
     * acepta, con `etiqueta` de ruta ("Litúrgica › Piedad Popular") y `nivel`.
     * Cada Comisión seguida de sus dimensiones, por nombre.
     */
    public function candidatosPadre(?int $excluirId = null): array
    {
        $mapa       = $this->mapaJerarquia();
        $candidatos = [];
        $agregar    = function (int $id) use (&$candidatos, $mapa, $excluirId): void {
            if ($this->motivoPadreInvalido($excluirId, $id, $mapa) !== null) {
                return;
            }
            $ruta         = $this->rutaDe($id, $mapa);
            $candidatos[] = [
                'id'       => $id,
                'nombre'   => $mapa[$id]['nombre'],
                'etiqueta' => implode(' › ', array_map(static fn (int $r): string => $mapa[$r]['nombre'], $ruta)),
                'nivel'    => count($ruta),
            ];
        };
        foreach ($mapa as $id => $nodo) {
            if ($nodo['padre'] !== null) {
                continue;
            }
            $agregar($id);
            foreach ($mapa as $hijaId => $hija) {
                if ($hija['padre'] === $id) {
                    $agregar($hijaId);
                }
            }
        }
        return $candidatos;
    }

    /**
     * Las pastorales que contienen a $id, de la Comisión hacia abajo, sin
     * incluirla: [['id', 'nombre', 'slug'], …]. Para las migas de pan.
     */
    public function ancestros(int $id): array
    {
        $mapa = $this->mapaJerarquia();
        $ruta = $this->rutaDe($id, $mapa);
        array_pop($ruta);
        return array_map(
            static fn (int $r): array => ['id' => $r, 'nombre' => $mapa[$r]['nombre'], 'slug' => $mapa[$r]['slug']],
            $ruta
        );
    }

    /**
     * Esos ids más todas las pastorales que los contienen, sin repetidos. Es la
     * audiencia de lectura de Auth::pastoralesAudiencia(): quien está en
     * Proclamadores tiene que poder leer lo que se publique en Litúrgica, y
     * quien está en Rosario Diario, lo de Piedad Popular y lo de Litúrgica.
     *
     * @param  array $ids Pastorales asignadas a la cuenta
     * @return array Esas mismas más sus padres y abuelos
     */
    public function conAncestros(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $mapa  = $this->mapaJerarquia();
        $todos = [];
        foreach ($ids as $id) {
            $todos[(int) $id] = true;
            foreach ($this->rutaDe((int) $id, $mapa) as $arriba) {
                $todos[$arriba] = true;
            }
        }
        return array_keys($todos);
    }

    /**
     * Agrupa estos ids de pastoral (asignados a una cuenta o a una ficha) por
     * su Comisión, para listados compactos (Usuarios, equipo pastoral): mismo
     * criterio de agrupado que agrupadoVisible(), pero sobre un conjunto
     * arbitrario de ids en vez de "todas recortadas a un alcance".
     *
     * Trae también las pastorales que contienen a cada id, solo para armar el
     * árbol y tener el nombre de la Comisión como encabezado; luego se quitan
     * las que no estaban entre los ids: a quien tiene Rosario Diario no se le
     * lista además Piedad Popular, que solo la contiene.
     */
    public function agruparIds(array $ids): array
    {
        if (!$ids) {
            return ['comisiones' => [], 'sueltas' => []];
        }
        $ids = array_map('intval', array_values($ids));

        $marcadores = [];
        $params     = [];
        foreach ($this->conAncestros($ids) as $i => $id) {
            $marcadores[]      = ":id{$i}";
            $params[":id{$i}"] = $id;
        }
        $filas = $this->fetchAll(
            'SELECT id, nombre, pastoral_padre_id FROM pastorales
              WHERE id IN (' . implode(',', $marcadores) . ')
              ORDER BY orden, nombre',
            $params
        );

        return $this->recortarGrupos($this->agrupar($filas), static fn (array $p): bool => in_array((int) $p['id'], $ids, true));
    }

    public function tieneHijos(int $id): bool
    {
        return (bool) $this->fetchColumn(
            'SELECT 1 FROM pastorales WHERE pastoral_padre_id = :id LIMIT 1',
            [':id' => $id]
        );
    }

    /**
     * Una Comisión: primer nivel y con hijas. Piedad Popular agrupa grupos pero
     * no es Comisión: está dentro de Litúrgica y tiene vida propia.
     */
    public function esComision(int $id): bool
    {
        return (bool) $this->fetchColumn(
            'SELECT 1 FROM pastorales p
              WHERE p.id = :id AND p.pastoral_padre_id IS NULL
                AND EXISTS (SELECT 1 FROM pastorales h WHERE h.pastoral_padre_id = p.id)',
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
        return $this->conCoordinacion($this->fetchAll(
            'SELECT * FROM pastorales WHERE pastoral_padre_id = :id AND activa = 1 ORDER BY orden, nombre',
            [':id' => $padreId]
        ));
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
     * `hijas` es una lista PLANA con todo lo que cuelga de la Comisión: cada
     * dimensión seguida, justo debajo, de sus grupos. Cada una lleva `profundidad`
     * (2 dimensión, 3 grupo), `padre_nombre` y `tiene_grupos`. Plana a
     * propósito: una pantalla que todavía no sabe del tercer nivel sigue
     * enseñando a los grupos —sin sangría, entre las dimensiones—, en vez de
     * perderlos. Las que sí lo saben los sangran con `profundidad`, o los meten
     * dentro de su dimensión con anidarHijas().
     *
     * @return array{comisiones: array<array{padre: array, hijas: array}>, sueltas: array}
     */
    private function agrupar(array $pastorales): array
    {
        $hijasDe = [];
        foreach ($pastorales as $fila) {
            if ($fila['pastoral_padre_id'] !== null) {
                $hijasDe[(int) $fila['pastoral_padre_id']][] = $fila;
            }
        }

        $comisiones = [];
        $sueltas    = [];
        foreach ($pastorales as $fila) {
            if ($fila['pastoral_padre_id'] !== null) {
                continue;
            }
            $hijas = $this->descendenciaPlana((int) $fila['id'], $fila['nombre'], $hijasDe, 2);
            if ($hijas) {
                $comisiones[] = ['padre' => $fila, 'hijas' => $hijas];
            } else {
                $sueltas[] = $fila;
            }
        }

        return ['comisiones' => $comisiones, 'sueltas' => $sueltas];
    }

    /** Las hijas de $padreId y, justo debajo de cada una, las suyas: ver agrupar(). */
    private function descendenciaPlana(int $padreId, string $padreNombre, array $hijasDe, int $nivel): array
    {
        if ($nivel > self::NIVEL_MAXIMO + 1) {
            return []; // defensa ante un ciclo en los datos
        }
        $lista = [];
        foreach ($hijasDe[$padreId] ?? [] as $hija) {
            $debajo               = $this->descendenciaPlana((int) $hija['id'], $hija['nombre'], $hijasDe, $nivel + 1);
            $hija['profundidad']  = $nivel;
            $hija['padre_nombre'] = $padreNombre;
            $hija['tiene_grupos'] = (bool) $debajo;
            $lista[]              = $hija;
            array_push($lista, ...$debajo);
        }
        return $lista;
    }

    /**
     * Las hijas planas de un grupo de agrupar(), con cada grupo metido dentro
     * de su dimensión: [['pastoral' => dimensión, 'grupos' => [grupo, …]], …].
     * Para las vistas de árbol (mapa imprimible, directorio). Un grupo cuya
     * dimensión no viene en la lista —recortada al alcance de quien mira—
     * queda como entrada propia, y su `padre_nombre` dice de dónde es.
     */
    public static function anidarHijas(array $hijas): array
    {
        $anidadas = [];
        $posicion = [];
        foreach ($hijas as $hija) {
            $padreId = (int) ($hija['pastoral_padre_id'] ?? 0);
            if (($hija['profundidad'] ?? 2) > 2 && isset($posicion[$padreId])) {
                $anidadas[$posicion[$padreId]]['grupos'][] = $hija;
                continue;
            }
            $posicion[(int) $hija['id']] = count($anidadas);
            $anidadas[] = ['pastoral' => $hija, 'grupos' => []];
        }
        return $anidadas;
    }

    /**
     * Borrado físico de la fila (no un desactivado): `pastoral_actividades`,
     * `pastoral_documentos` y `pastoral_coordinadores` se van con ella en
     * cascada —las fichas de quienes la coordinaban no—, pero avisos, eventos
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
            ':responsable' => $datos['responsable_nombre'],
            ':email'       => $datos['contacto_email'],
            ':telefono'    => $datos['contacto_telefono'],
            ':diaReunion'  => $datos['dia_reunion'],
            ':horaReunion' => $datos['hora_reunion'],
            ':lugarReunion'=> $datos['lugar_reunion'],
            ':voluntarios' => $datos['acepta_voluntarios'],
            ':orden'       => $datos['orden'],
            ':activa'      => $datos['activa'],
        ];
    }
}
