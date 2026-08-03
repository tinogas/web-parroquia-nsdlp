<?php
require_once BASE_PATH . '/core/Model.php';
require_once BASE_PATH . '/modules/usuarios/UsuarioModel.php';

/**
 * PersonaModel — Sacerdote, diáconos, religiosos, laicos y personal.
 *
 * Borrado lógico por defecto (`activo`): quien deja el cargo se desactiva, no
 * se borra, porque `organigrama_nodos.persona_id` puede seguir apuntando a su
 * historial. Un borrado real solo tiene sentido para corregir un alta hecha
 * por error, y la FK de organigrama ya lo deja en NULL sin romper nada
 * (`ON DELETE SET NULL`).
 */
class PersonaModel extends Model
{
    /** tipo => nombre visible, en el orden en que se muestran en "Quiénes somos". */
    public const TIPOS = [
        'parroco'   => 'Párroco',
        'vicario'   => 'Vicario',
        'diacono'   => 'Diácono',
        'religioso' => 'Religioso o religiosa',
        'laico'     => 'Laico',
        'staff'     => 'Personal de oficina',
    ];

    /**
     * @param ?array $pastorales Filtro opcional por pertenencia (persona_pastorales): null = todas, [] = ninguna.
     * @param ?array $centros    Igual, por pertenencia a centro/sede (persona_centros).
     */
    public function todas(?array $pastorales = null, ?array $centros = null): array
    {
        [$condPastoral, $paramsPastoral] = $this->condicionPertenece($pastorales, 'persona_pastorales', 'pastoral_id');
        [$condCentro,   $paramsCentro]   = $this->condicionPertenece($centros, 'persona_centros', 'centro_id');

        $condiciones = array_filter([$condPastoral, $condCentro], static fn (string $c): bool => $c !== '');
        $where = $condiciones ? ('WHERE ' . implode(' AND ', $condiciones)) : '';

        return $this->fetchAll(
            "SELECT p.*,
                    (SELECT GROUP_CONCAT(pa.nombre ORDER BY pa.nombre SEPARATOR ', ')
                       FROM persona_pastorales pp JOIN pastorales pa ON pa.id = pp.pastoral_id
                      WHERE pp.persona_id = p.id) AS pastorales_nombres,
                    (SELECT GROUP_CONCAT(c.nombre ORDER BY c.nombre SEPARATOR ', ')
                       FROM persona_centros pc JOIN centros c ON c.id = pc.centro_id
                      WHERE pc.persona_id = p.id) AS centros_nombres,
                    (SELECT GROUP_CONCAT(pa2.nombre ORDER BY pa2.nombre SEPARATOR ', ')
                       FROM pastorales pa2 WHERE pa2.responsable_persona_id = p.id) AS pastorales_coordina
               FROM personas p
               {$where}
              ORDER BY FIELD(p.tipo, " . $this->ordenTipos() . "), p.orden, p.nombre",
            $paramsPastoral + $paramsCentro
        );
    }

    /**
     * Condición EXISTS para acotar `personas` a quienes pertenecen a alguno de estos ids,
     * vía una tabla pivote propia de la ficha (no el alcance de una cuenta). Mismo contrato
     * que Model::condicionAlcance(), pero con EXISTS en vez de columna directa: aquí la
     * pertenencia vive en persona_pastorales/persona_centros, no en personas.pastoral_id.
     *
     * @return array{0: string, 1: array} [condición SQL o cadena vacía, parámetros]
     */
    private function condicionPertenece(?array $ids, string $tabla, string $columna): array
    {
        if ($ids === null) {
            return ['', []];
        }
        if (!$ids) {
            return ['1 = 0', []];
        }

        $marcadores = [];
        $params     = [];
        foreach (array_values($ids) as $i => $id) {
            $clave          = ":{$columna}{$i}";
            $marcadores[]   = $clave;
            $params[$clave] = (int) $id;
        }

        return [
            "EXISTS (SELECT 1 FROM {$tabla} x WHERE x.persona_id = p.id AND x.{$columna} IN (" . implode(',', $marcadores) . '))',
            $params,
        ];
    }

    public function porId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM personas WHERE id = :id', [':id' => $id]);
    }

    /** Para el sitio público: solo activos, agrupados por tipo. */
    public function activasPorTipo(): array
    {
        $filas = $this->fetchAll(
            "SELECT * FROM personas WHERE activo = 1
             ORDER BY FIELD(tipo, " . $this->ordenTipos() . "), orden, nombre"
        );
        $porTipo = [];
        foreach ($filas as $fila) {
            $porTipo[$fila['tipo']][] = $fila;
        }
        return $porTipo;
    }

    /** id => nombre de las personas activas, para el selector del organigrama. */
    public function paraSelector(): array
    {
        return $this->fetchAll('SELECT id, nombre, cargo FROM personas WHERE activo = 1 ORDER BY nombre');
    }

    /** IDs de pastoral asignadas, para marcar las casillas del formulario. */
    public function pastoralesDe(int $id): array
    {
        $filas = $this->fetchAll(
            'SELECT pastoral_id FROM persona_pastorales WHERE persona_id = :id',
            [':id' => $id]
        );
        return array_map(static fn (array $f): int => (int) $f['pastoral_id'], $filas);
    }

    /** IDs de centro/sede asignados, para marcar las casillas del formulario. */
    public function centrosDe(int $id): array
    {
        $filas = $this->fetchAll(
            'SELECT centro_id FROM persona_centros WHERE persona_id = :id',
            [':id' => $id]
        );
        return array_map(static fn (array $f): int => (int) $f['centro_id'], $filas);
    }

    public function crear(array $datos): int
    {
        $this->beginTransaction();
        try {
            $this->execute(
                'INSERT INTO personas
                    (nombre, cargo, tipo, semblanza, foto, email, telefono, fecha_nacimiento, orden, activo)
                 VALUES
                    (:nombre, :cargo, :tipo, :semblanza, :foto, :email, :telefono, :fechaNacimiento, :orden, :activo)',
                $this->parametros($datos)
            );
            $id = $this->lastInsertId();
            $this->sincronizarPastorales($id, $datos['pastorales']);
            $this->sincronizarCentros($id, $datos['centros']);
            $this->commit();
            return $id;
        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function actualizar(int $id, array $datos): int
    {
        $this->beginTransaction();
        try {
            $filas = $this->execute(
                'UPDATE personas
                    SET nombre = :nombre, cargo = :cargo, tipo = :tipo, semblanza = :semblanza,
                        foto = :foto, email = :email, telefono = :telefono,
                        fecha_nacimiento = :fechaNacimiento, orden = :orden, activo = :activo
                  WHERE id = :id',
                $this->parametros($datos) + [':id' => $id]
            );
            $this->sincronizarPastorales($id, $datos['pastorales']);
            $this->sincronizarCentros($id, $datos['centros']);
            $this->sincronizarCuenta($id, $datos);
            $this->sincronizarCuentaAlcance($id, $datos['pastorales'], $datos['centros']);
            $this->sincronizarResponsable($id, $datos['nombre']);
            $this->sincronizarPersonal($id, $datos);
            $this->commit();
            return $filas;
        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function eliminar(int $id): int
    {
        return $this->execute('DELETE FROM personas WHERE id = :id', [':id' => $id]);
    }

    /**
     * Si esta persona tiene cuenta en el panel, su nombre, teléfono y foto van
     * detrás: la ficha es el registro principal y la cuenta no guarda una
     * versión propia que pueda quedarse vieja. Lo que NO se toca son sus
     * permisos —rol, pastorales y sedes de la cuenta—, que se deciden aparte:
     * figurar en una pastoral no es administrarla. Ver docs/ARQUITECTURA.md
     */
    private function sincronizarCuenta(int $personaId, array $datos): void
    {
        $this->execute(
            'UPDATE usuarios SET nombre = :nombre, telefono = :telefono, foto = :foto
              WHERE persona_id = :persona',
            [
                ':nombre'   => $datos['nombre'],
                ':telefono' => $datos['telefono'],
                ':foto'     => $datos['foto'],
                ':persona'  => $personaId,
            ]
        );
    }

    /**
     * Si esta persona ya tiene cuenta vinculada y esa cuenta usa alcance por
     * pastoral (Coordinador, Coordinador general o Consulta), su alcance se
     * sincroniza aquí mismo con lo recién guardado en la ficha —para que
     * agregar o quitar una pastoral en el equipo pastoral no exija además
     * volver a abrir "Editar" en Usuarios—. Con un rol sin alcance
     * (Administrador, Editor, Secretaría) no se toca nada: esa cuenta no usa
     * usuarios_pastorales/usuarios_centros para nada.
     *
     * Límite conocido y aceptado: si esa persona tiene sesión abierta en este
     * momento, no lo verá hasta volver a iniciar sesión —
     * Auth::pastoralesPermitidas() se cachea en sesión al iniciar sesión.
     */
    private function sincronizarCuentaAlcance(int $personaId, array $pastoralIds, array $centroIds): void
    {
        $cuenta = (new UsuarioModel())->porPersona($personaId);
        if (!$cuenta || !in_array($cuenta['rol'], ROLES_CON_ALCANCE_PASTORAL, true)) {
            return;
        }
        (new UsuarioModel())->sincronizarAlcance((int) $cuenta['id'], $pastoralIds, $centroIds);
    }

    /**
     * Si esta persona es la responsable de alguna pastoral (`pastorales.
     * responsable_persona_id`), su nombre ahí también viene de aquí: la
     * pastoral no guarda su propia copia editable una vez que se elige a
     * alguien del equipo. Ver PastoralController::guardar(), que hace el
     * mismo cálculo al elegir responsable.
     */
    private function sincronizarResponsable(int $personaId, string $nombre): void
    {
        $this->execute(
            'UPDATE pastorales SET responsable_nombre = :nombre WHERE responsable_persona_id = :persona',
            [':nombre' => $nombre, ':persona' => $personaId]
        );
    }

    /**
     * Si esta persona está registrada como ministro de MESC, catequista o
     * lector —`mesc_ministros`/`catequesis_catequistas`/`lector_lectores`,
     * cualquiera de las tres, incluso más de una a la vez—, sus datos de
     * contacto van detrás, igual que en `sincronizarCuenta()`. Corrige de raíz
     * el mismo problema que ya se vio con los responsables de pastoral: antes
     * de este vínculo, Zulema estaba escrita como "Zulema" en
     * `mesc_ministros`, "Zulema Alvarez" en `catequesis_catequistas` y con su
     * nombre completo aquí en `personas` — tres grafías de la misma persona,
     * sin nada que las mantuviera iguales.
     *
     * El `nombre` sí se sincroniza en catequistas y lectores, pero NO en
     * ministros de MESC: ahí es el nombre corto del calendario de turnos, un
     * dato propio (ver abajo).
     */
    private function sincronizarPersonal(int $personaId, array $datos): void
    {
        // MESC es la excepción: su `nombre` es el nombre CORTO del ministro
        // —el que cabe en una casilla del calendario de turnos y con el que se
        // le reconoce al capturar uno de fuera—, un dato propio del módulo que
        // la ficha no debe pisar. Ver MescController::ministroGuardar().
        $this->execute(
            'UPDATE mesc_ministros SET telefono = :telefono WHERE persona_id = :persona',
            [':telefono' => $datos['telefono'], ':persona' => $personaId]
        );
        $this->execute(
            'UPDATE catequesis_catequistas SET nombre = :nombre, telefono = :telefono, email = :email WHERE persona_id = :persona',
            [':nombre' => $datos['nombre'], ':telefono' => $datos['telefono'], ':email' => $datos['email'], ':persona' => $personaId]
        );
        $this->execute(
            'UPDATE lector_lectores SET nombre = :nombre, telefono = :telefono, email = :email WHERE persona_id = :persona',
            [':nombre' => $datos['nombre'], ':telefono' => $datos['telefono'], ':email' => $datos['email'], ':persona' => $personaId]
        );
    }

    private function sincronizarPastorales(int $personaId, array $pastoralIds): void
    {
        $this->execute('DELETE FROM persona_pastorales WHERE persona_id = :id', [':id' => $personaId]);
        foreach (array_unique($pastoralIds) as $pid) {
            $this->execute(
                'INSERT INTO persona_pastorales (persona_id, pastoral_id) VALUES (:pid, :pastoral)',
                [':pid' => $personaId, ':pastoral' => $pid]
            );
        }
    }

    private function sincronizarCentros(int $personaId, array $centroIds): void
    {
        $this->execute('DELETE FROM persona_centros WHERE persona_id = :id', [':id' => $personaId]);
        foreach (array_unique($centroIds) as $cid) {
            $this->execute(
                'INSERT INTO persona_centros (persona_id, centro_id) VALUES (:pid, :centro)',
                [':pid' => $personaId, ':centro' => $cid]
            );
        }
    }

    private function parametros(array $datos): array
    {
        return [
            ':nombre'          => $datos['nombre'],
            ':cargo'           => $datos['cargo'],
            ':tipo'            => $datos['tipo'],
            ':semblanza'       => $datos['semblanza'],
            ':foto'            => $datos['foto'],
            ':email'           => $datos['email'],
            ':telefono'        => $datos['telefono'],
            ':fechaNacimiento' => $datos['fecha_nacimiento'],
            ':orden'           => $datos['orden'],
            ':activo'          => $datos['activo'],
        ];
    }

    /**
     * Personas activas que cumplen años en el mes dado (el actual si no se
     * especifica), ordenadas por día — para "Cumpleaños del mes" en el panel
     * de inicio. Solo mes y día importan: no se calcula ni se expone edad.
     */
    public function cumpleanerosDelMes(?int $mes = null): array
    {
        $mes ??= (int) date('n');
        return $this->fetchAll(
            'SELECT id, nombre, foto, DAY(fecha_nacimiento) AS dia
               FROM personas
              WHERE activo = 1 AND fecha_nacimiento IS NOT NULL AND MONTH(fecha_nacimiento) = :mes
              ORDER BY DAY(fecha_nacimiento), nombre',
            [':mes' => $mes]
        );
    }

    private function ordenTipos(): string
    {
        return implode(',', array_map(
            static fn (string $t): string => "'" . $t . "'",
            array_keys(self::TIPOS)
        ));
    }
}
