<?php
/**
 * importar_proclamadores.php — Mete la lista de la pastoral de Proclamadores
 * en el equipo pastoral (`personas`) y en el catálogo del módulo
 * (`proclamadores`).
 *
 * Se ejecuta desde la línea de órdenes, con el JSON que sale de
 * herramientas/extraer_proclamadores.py:
 *
 *     python herramientas/extraer_proclamadores.py
 *     …se revisa el resumen que imprime, sobre todo lo que marca…
 *     C:\xampp\php\php.exe herramientas/importar_proclamadores.php --dry-run
 *     C:\xampp\php\php.exe herramientas/importar_proclamadores.php
 *
 * (El paso por JSON es porque el PHP de este XAMPP no trae la extensión zip y
 * no puede abrir un xlsx. Mismo reparto que importar_agenda.php.)
 *
 * Qué hace con cada persona de la hoja:
 *
 *   - Si YA está en el equipo pastoral, no la vuelve a dar de alta. Solo le
 *     completa la fecha de nacimiento si su ficha la tiene vacía —nunca pisa
 *     una fecha ya capturada, que es un dato revisado por alguien—.
 *   - Si no está, la crea con cargo «Proclamador».
 *   - En los dos casos la deja ligada a la pastoral y en el catálogo del
 *     módulo, con sus preferencias, que es lo que hace falta para poder
 *     asignarle un turno.
 *
 * Guarda con PersonaModel, no con SQL a pelo, para que las fichas queden igual
 * que si se hubieran creado desde el panel (y para que la sincronización de
 * nombre y contacto hacia el catálogo y las cuentas siga funcionando).
 *
 * Es idempotente: volver a ejecutarlo no duplica nada.
 *
 * Ojo: al no pasar por los controladores, la carga NO deja rastro en la tabla
 * `auditoria`.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo se ejecuta desde la línea de órdenes.');
}

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/app.php';

if (!is_file(BASE_PATH . '/config/database.php')) {
    exit("Falta config/database.php. Copia config/database.example.php y ajusta las credenciales.\n");
}
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/core/helpers.php';
require_once BASE_PATH . '/modules/personas/PersonaModel.php';
require_once BASE_PATH . '/modules/proclamadores/ProclamadoresModel.php';

/**
 * Año con el que se guardan los cumpleaños cuyo año real no se sabe (la hoja
 * traía el año en curso autocompletado). Tiene que ser el mismo que usa
 * extraer_proclamadores.py. Solo se usa para avisar en pantalla: la fecha
 * llega ya construida en el JSON.
 */
const ANIO_DESCONOCIDO = 1900;

/**
 * El caso que ninguna comparación automática podía resolver, y que se decidió
 * a mano: en la hoja firma con su nombre completo y en el equipo pastoral está
 * dada de alta con el corto. Son la misma persona —es la coordinadora de la
 * pastoral—, así que la ficha existente se completa en vez de crear una
 * segunda. Es la cuarta vez que aparece este problema en el proyecto (ver el
 * comentario de PersonaModel::sincronizarPersonal()); la diferencia es que
 * aquí las dos grafías no comparten ni el número de palabras, y adivinarlo
 * habría sido más peligroso que dejarlo escrito.
 *
 * clave normalizada de la hoja => nombre exacto de la ficha que ya existe
 */
const MISMA_PERSONA = [
    'maria fernanda riojas duarte' => 'Fernanda Riojas',
];

/** Cargo con el que entran al equipo pastoral quienes no estaban. */
const CARGO = 'Proclamador';

/** `personas.tipo`: mismo criterio que los ministros de MESC y los catequistas. */
const TIPO = 'staff';

// ── Opciones ────────────────────────────────────────────────────────────────
$opciones = getopt('', ['dry-run', 'json:', 'ayuda']);
if (isset($opciones['ayuda'])) {
    exit(<<<TXT
    Uso: php herramientas/importar_proclamadores.php [opciones]

      --json=ARCHIVO   JSON de entrada (por omisión proclamadores.json)
      --dry-run        no escribe nada, solo dice qué haría
      --ayuda          esto

    TXT);
}
$ensayo   = isset($opciones['dry-run']);
$rutaJson = $opciones['json'] ?? BASE_PATH . '/proclamadores.json';

if (!is_file($rutaJson)) {
    exit("No encuentro «{$rutaJson}».\nGenéralo con: python herramientas/extraer_proclamadores.py\n");
}
$filas = json_decode((string) file_get_contents($rutaJson), true);
if (!is_array($filas) || !$filas || !isset($filas[0]['nombre'])) {
    exit("«{$rutaJson}» no tiene el formato esperado.\n");
}

try {
    $db = Database::getInstance();
} catch (PDOException $e) {
    exit('No se pudo conectar con la base de datos: ' . $e->getMessage() . "\n");
}

$personaModel       = new PersonaModel();
$proclamadoresModel = new ProclamadoresModel();

$pastoralId = $proclamadoresModel->pastoralId();
if ($pastoralId === null) {
    exit("No existe la pastoral de Proclamadores (slug '" . PASTORAL_PROCLAMADORES . "').\n");
}

// ── Ayudas ──────────────────────────────────────────────────────────────────

/** Normaliza un nombre para comparar: sin acentos, minúsculas, un solo espacio. */
function comparable(string $texto): string
{
    $texto = mb_strtolower(trim($texto), 'UTF-8');
    $texto = strtr($texto, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        'ü' => 'u', 'ñ' => 'n', 'Á' => 'a', 'É' => 'e', 'Í' => 'i',
        'Ó' => 'o', 'Ú' => 'u', 'Ñ' => 'n',
    ]);
    return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9 ]+/', ' ', $texto) ?? '') ?? '');
}

/** El equipo pastoral que ya existe, indexado por nombre normalizado. */
function personasExistentes(PDO $db): array
{
    $porClave = [];
    foreach ($db->query('SELECT id, nombre, fecha_nacimiento FROM personas')->fetchAll() as $fila) {
        $porClave[comparable($fila['nombre'])] = [
            'id'               => (int) $fila['id'],
            'nombre'           => $fila['nombre'],
            'fecha_nacimiento' => $fila['fecha_nacimiento'],
        ];
    }
    return $porClave;
}

/** Los del catálogo del módulo, por persona_id, para no darlos de alta dos veces. */
function catalogoPorPersona(PDO $db, int $pastoralId): array
{
    $consulta = $db->prepare('SELECT id, persona_id, preferencias FROM proclamadores WHERE pastoral_id = ?');
    $consulta->execute([$pastoralId]);

    $porPersona = [];
    foreach ($consulta->fetchAll() as $fila) {
        if ($fila['persona_id'] !== null) {
            $porPersona[(int) $fila['persona_id']] = [
                'id'           => (int) $fila['id'],
                'preferencias' => (string) ($fila['preferencias'] ?? ''),
            ];
        }
    }
    return $porPersona;
}

/** Datos de una ficha nueva, con todo lo que PersonaModel::crear() espera. */
function fichaNueva(array $fila, int $pastoralId): array
{
    return [
        'nombre'           => $fila['nombre'],
        'cargo'            => CARGO,
        'tipo'             => TIPO,
        'semblanza'        => null,
        'foto'             => null,
        'email'            => null,
        'telefono'         => null,
        'fecha_nacimiento' => $fila['fecha_nacimiento'] ?: null,
        'orden'            => 0,
        'activo'           => 1,
        'pastorales'       => [$pastoralId],
        'centros'          => [],
    ];
}

// ── Carga ───────────────────────────────────────────────────────────────────

echo 'Origen:   ' . basename($rutaJson) . ' (' . count($filas) . " personas)\n";
echo 'Pastoral: ' . PASTORAL_PROCLAMADORES . " (id {$pastoralId})\n";
if ($ensayo) {
    echo "MODO ENSAYO: no se escribe nada.\n";
}
echo str_repeat('─', 78) . PHP_EOL;

$existentes = personasExistentes($db);
$catalogo   = catalogoPorPersona($db, $pastoralId);

$creadas = $fechasCompletadas = $altasCatalogo = $yaEstaban = 0;
$avisos  = [];

foreach ($filas as $fila) {
    $nombre    = (string) $fila['nombre'];
    $claveHoja = comparable($nombre);

    // La ficha se busca primero por el nombre de la hoja y solo después por el
    // alias, no al revés: la primera pasada renombra la ficha al nombre
    // completo, así que en la segunda ya coincide por sí sola y el alias
    // apunta a un nombre que ya no existe. Buscando en ese orden, volver a
    // ejecutar el script no crea una ficha duplicada. Ver MISMA_PERSONA.
    $claveAlias = isset(MISMA_PERSONA[$claveHoja])
        ? comparable(MISMA_PERSONA[$claveHoja])
        : null;

    if (isset($existentes[$claveHoja])) {
        $clave   = $claveHoja;
        $esAlias = false;
    } elseif ($claveAlias !== null && isset($existentes[$claveAlias])) {
        $clave   = $claveAlias;
        $esAlias = true;
    } else {
        $clave   = $claveHoja;
        $esAlias = false;
    }

    $persona   = $existentes[$clave] ?? null;
    $personaId = $persona['id'] ?? null;

    if ($persona === null) {
        echo "+ alta   {$nombre}";
        if (!$ensayo) {
            $personaId = $personaModel->crear(fichaNueva($fila, $pastoralId));
            $existentes[$clave] = [
                'id'               => $personaId,
                'nombre'           => $nombre,
                'fecha_nacimiento' => $fila['fecha_nacimiento'],
            ];
        }
        echo $fila['fecha_nacimiento'] ? '  · cumpleaños ' . $fila['fecha_nacimiento'] : '  · sin cumpleaños';
        echo PHP_EOL;
        $creadas++;
    } else {
        $yaEstaban++;
        echo "= ya está {$persona['nombre']}";

        // La fecha solo se rellena si falta: una ya capturada la revisó
        // alguien, y la de la hoja viene de un formulario y no le gana. El
        // nombre solo se toca en el caso de MISMA_PERSONA, donde la hoja trae
        // el nombre completo y la ficha el corto. Y la pertenencia a la
        // pastoral se asegura siempre: estar en la hoja es justamente eso, y
        // varios de los que ya están en el equipo entraron por MESC.
        $completarFecha  = $fila['fecha_nacimiento'] && $persona['fecha_nacimiento'] === null;
        $completarNombre = $esAlias && $persona['nombre'] !== $nombre;
        $faltaPastoral   = !in_array($pastoralId, $personaModel->pastoralesDe($personaId), true);

        if ($completarFecha || $completarNombre || $faltaPastoral) {
            if (!$ensayo) {
                // De la ficha que ya existe se conserva TODO lo demás: su
                // cargo, su tipo y su contacto son datos suyos, no de esta hoja.
                $ficha = $personaModel->porId($personaId);

                $personaModel->actualizar($personaId, [
                    'nombre'           => $completarNombre ? $nombre : $ficha['nombre'],
                    'cargo'            => $ficha['cargo'],
                    'tipo'             => $ficha['tipo'],
                    'semblanza'        => $ficha['semblanza'],
                    'foto'             => $ficha['foto'],
                    'email'            => $ficha['email'],
                    'telefono'         => $ficha['telefono'],
                    'fecha_nacimiento' => $completarFecha ? $fila['fecha_nacimiento'] : $ficha['fecha_nacimiento'],
                    'orden'            => (int) $ficha['orden'],
                    'activo'           => (int) $ficha['activo'],
                    'pastorales'       => array_values(array_unique(array_merge(
                        $personaModel->pastoralesDe($personaId),
                        [$pastoralId]
                    ))),
                    'centros'          => $personaModel->centrosDe($personaId),
                ]);
            }
            if ($completarNombre) {
                echo "  · nombre completo: {$nombre}";
            }
            if ($completarFecha) {
                echo '  · cumpleaños ' . $fila['fecha_nacimiento'] . ' (su ficha no lo tenía)';
                $fechasCompletadas++;
            }
            if ($faltaPastoral) {
                echo '  · ligada a la pastoral';
            }
        } elseif ($fila['fecha_nacimiento'] && $persona['fecha_nacimiento'] !== $fila['fecha_nacimiento']) {
            $avisos[] = sprintf(
                '%s: su ficha dice %s y la hoja %s. Se deja la de la ficha.',
                $persona['nombre'], $persona['fecha_nacimiento'], $fila['fecha_nacimiento']
            );
            echo '  · cumpleaños distinto al de su ficha, se respeta el de la ficha';
        }
        echo PHP_EOL;
    }

    // ── El catálogo del módulo ──────────────────────────────────────────
    if ($personaId !== null && isset($catalogo[$personaId])) {
        $preferenciasHoja = implode(',', $fila['preferencias']);
        if ($preferenciasHoja !== '' && $catalogo[$personaId]['preferencias'] === '') {
            if (!$ensayo) {
                $proclamador = $proclamadoresModel->proclamadorPorId($catalogo[$personaId]['id']);
                $proclamador['preferencias'] = $fila['preferencias'];
                $proclamadoresModel->actualizarProclamador((int) $proclamador['id'], $proclamador);
            }
            echo "           · preferencias: {$preferenciasHoja}" . PHP_EOL;
        }
        continue;
    }

    if (!$ensayo && $personaId !== null) {
        $persona = $personaModel->porId($personaId);
        $proclamadoresModel->crearProclamador([
            'pastoral_id'  => $pastoralId,
            'persona_id'   => $personaId,
            'nombre'       => $persona['nombre'],
            'telefono'     => $persona['telefono'],
            'email'        => $persona['email'],
            'preferencias' => $fila['preferencias'],
            'orden'        => 0,
            'activo'       => 1,
        ]);
    }
    $altasCatalogo++;
}

echo str_repeat('─', 78) . PHP_EOL;
printf("Equipo pastoral: %d altas, %d ya estaban (%d con el cumpleaños completado)\n",
    $creadas, $yaEstaban, $fechasCompletadas);
printf("Catálogo del módulo: %d altas\n", $altasCatalogo);

$sinFecha = array_filter($filas, static fn (array $f): bool => empty($f['fecha_nacimiento']));
$sinAnio  = array_filter($filas, static fn (array $f): bool => !empty($f['fecha_nacimiento']) && !$f['anio_conocido']);

if ($sinAnio) {
    printf("\n%d fichas quedan con el año %d: de esas solo se sabe el día y el mes.\n",
        count($sinAnio), ANIO_DESCONOCIDO);
}
if ($sinFecha) {
    echo "\nSin fecha de nacimiento, hay que preguntarles:\n";
    foreach ($sinFecha as $fila) {
        echo '  - ' . $fila['nombre'];
        if (!empty($fila['notas'])) {
            echo ' (' . implode('; ', $fila['notas']) . ')';
        }
        echo PHP_EOL;
    }
}
if ($avisos) {
    echo "\nRevisar:\n";
    foreach ($avisos as $aviso) {
        echo '  - ' . $aviso . PHP_EOL;
    }
}
if ($ensayo) {
    echo "\n(Ensayo: no se escribió nada. Quita --dry-run para aplicarlo.)\n";
}
