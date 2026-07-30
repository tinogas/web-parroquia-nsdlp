<?php
/**
 * importar_agenda.php — Mete la agenda parroquial de 2026 en `eventos` y en
 * `horarios`.
 *
 * Se ejecuta desde la línea de órdenes, con el JSON que sale de
 * herramientas/extraer_agenda.py una vez revisada la hoja:
 *
 *     python herramientas/extraer_agenda.py                       # saca el xlsx revisable
 *     …se revisa y corrige agenda-2026-extraida.xlsx en Excel…
 *     python herramientas/extraer_agenda.py --a-json agenda-2026.json
 *     C:\xampp\php\php.exe herramientas/importar_agenda.php --dry-run
 *     C:\xampp\php\php.exe herramientas/importar_agenda.php
 *
 * (El paso por JSON es porque el PHP de este XAMPP no trae la extensión zip y
 * no puede abrir un xlsx.)
 *
 * Guarda con EventoModel y HorarioModel, no con SQL a pelo, para que las filas
 * queden igual que si se hubieran creado desde el panel. Es idempotente: un
 * evento que ya esté en la misma fecha y con el mismo título se omite, así que
 * se puede volver a ejecutar sin duplicar nada —y en particular no toca el
 * Curso de Visitadores que se cargó a mano—.
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
require_once BASE_PATH . '/core/Slug.php';
require_once BASE_PATH . '/modules/eventos/EventoModel.php';
require_once BASE_PATH . '/modules/horarios/HorarioModel.php';

// ── Opciones ────────────────────────────────────────────────────────────────
$opciones  = getopt('', ['dry-run', 'json:', 'usuario:', 'borrador', 'ayuda']);
if (isset($opciones['ayuda'])) {
    exit(<<<TXT
    Uso: php herramientas/importar_agenda.php [opciones]

      --json=ARCHIVO   JSON de entrada (por omisión agenda-2026.json)
      --usuario=ID     id de usuarios que queda como autor (por omisión 1)
      --borrador       entra sin publicar (publicado = 0)
      --dry-run        no escribe nada, solo dice qué haría
      --ayuda          esto

    TXT);
}
$ensayo    = isset($opciones['dry-run']);
$borrador  = isset($opciones['borrador']);
$rutaJson  = $opciones['json']    ?? BASE_PATH . '/agenda-2026.json';
$usuarioId = (int) ($opciones['usuario'] ?? 1);

if (!is_file($rutaJson)) {
    exit("No encuentro «{$rutaJson}».\nGenéralo con: python herramientas/extraer_agenda.py --a-json agenda-2026.json\n");
}
$datos = json_decode((string) file_get_contents($rutaJson), true);
if (!is_array($datos) || !isset($datos['eventos'])) {
    exit("«{$rutaJson}» no tiene el formato esperado.\n");
}

try {
    $db = Database::getInstance();
} catch (PDOException $e) {
    exit('No se pudo conectar con la base de datos: ' . $e->getMessage() . "\n");
}

if (!$db->query('SELECT COUNT(*) FROM usuarios WHERE id = ' . $usuarioId)->fetchColumn()) {
    exit("No existe el usuario {$usuarioId}; pásale otro con --usuario=ID.\n");
}

$eventoModel  = new EventoModel();
$horarioModel = new HorarioModel();

echo 'Origen: ' . ($datos['origen'] ?? basename($rutaJson)) . PHP_EOL;
echo 'Autor:  usuario ' . $usuarioId . PHP_EOL;
if ($ensayo) {
    echo "MODO ENSAYO: no se escribe nada." . PHP_EOL;
}
echo str_repeat('─', 78) . PHP_EOL;

// ── Ayudas ──────────────────────────────────────────────────────────────────

/** Normaliza un título para comparar: sin acentos, minúsculas, un solo espacio. */
function comparable(string $texto): string
{
    $texto = mb_strtolower(trim($texto), 'UTF-8');
    $texto = strtr($texto, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
    ]);
    return trim(preg_replace('/[^a-z0-9]+/', ' ', $texto) ?? '');
}

/**
 * Los eventos que ya hay en la base, agrupados por día. Sirve para no duplicar:
 * la comparación es por fecha + título, no por slug, porque el mismo evento
 * cargado a mano puede tener otro slug.
 */
function eventosPorDia(PDO $db): array
{
    $porDia = [];
    $filas = $db->query('SELECT id, titulo, DATE(fecha_inicio) AS dia, fecha_inicio FROM eventos')
                ->fetchAll();
    foreach ($filas as $f) {
        $porDia[$f['dia']][] = [
            'id'     => (int) $f['id'],
            'clave'  => comparable($f['titulo']),
            'hora'   => substr((string) $f['fecha_inicio'], 11, 5),
        ];
    }
    return $porDia;
}

/** Los horarios activos, indexados por día|hora|nota normalizada. */
function horariosExistentes(PDO $db): array
{
    $claves = [];
    $filas = $db->query('SELECT dia_semana, TIME_FORMAT(hora, "%H:%i") AS hora, nota, centro_id
                           FROM horarios WHERE activo = 1')->fetchAll();
    foreach ($filas as $f) {
        $claves[$f['dia_semana'] . '|' . $f['hora']][] = comparable((string) $f['nota']);
    }
    return $claves;
}

/** «2026-11-21» + «19:00» → «2026-11-21 19:00:00». Sin hora, medianoche. */
function aDatetime(?string $fecha, ?string $hora, string $porOmision = '00:00'): ?string
{
    if (!$fecha) {
        return null;
    }
    return $fecha . ' ' . ($hora ?: $porOmision) . ':00';
}

// ── Eventos ─────────────────────────────────────────────────────────────────
$yaHay   = eventosPorDia($db);
$creados = $omitidos = $sinTitulo = 0;
$porMes  = [];
$porClase = [];

foreach ($datos['eventos'] as $e) {
    $titulo = trim((string) ($e['titulo'] ?? ''));
    $inicio = (string) ($e['fecha_inicio'] ?? '');
    if ($titulo === '' || $inicio === '') {
        $sinTitulo++;
        continue;
    }

    // ¿Está ya? Mismo día y mismo título (y misma hora, si ambos la tienen).
    $clave = comparable($titulo);
    $hora  = $e['hora'] ?? null;
    foreach ($yaHay[$inicio] ?? [] as $existente) {
        if ($existente['clave'] !== $clave) {
            continue;
        }
        if ($hora && $existente['hora'] !== '00:00' && $existente['hora'] !== $hora) {
            continue;       // la misma celebración a otra hora sí es otro evento
        }
        $omitidos++;
        continue 2;
    }

    $todoElDia = $hora ? 0 : 1;
    $fechaInicio = aDatetime($inicio, $hora);
    // fecha_fin cubre las dos cosas: el periodo de varios días y la hora de
    // término dentro del mismo día.
    if (!empty($e['fecha_fin'])) {
        $fechaFin = aDatetime((string) $e['fecha_fin'], $e['hora_fin'] ?? null, '23:59');
    } elseif (!empty($e['hora_fin'])) {
        $fechaFin = aDatetime($inicio, (string) $e['hora_fin']);
    } else {
        $fechaFin = null;
    }

    $descripcion = trim((string) ($e['descripcion'] ?? ''));
    if (comparable($descripcion) === $clave) {
        $descripcion = '';      // no repetir el título como descripción
    }

    $registro = [
        'slug'         => $ensayo
            ? Slug::generar($titulo . ' ' . $inicio)
            : Slug::unico($titulo . ' ' . $inicio, 'eventos'),
        'titulo'       => $titulo,
        'descripcion'  => $descripcion !== ''
            ? '<p>' . htmlspecialchars($descripcion, ENT_QUOTES, 'UTF-8') . '</p>'
            : null,
        'imagen'       => null,
        'lugar'        => $e['lugar'] ?? null,
        'fecha_inicio' => $fechaInicio,
        'fecha_fin'    => $fechaFin,
        'todo_el_dia'  => $todoElDia,
        'pastoral_id'  => $e['pastoral_id'] ?? null,
        'color'        => $e['color'] ?? '#1e4d8b',
        'publicado'    => $borrador ? 0 : 1,
    ];

    if (!$ensayo) {
        $eventoModel->crear($registro, $usuarioId);
        // Que la comprobación de duplicados vea lo insertado en esta misma pasada.
        $yaHay[$inicio][] = ['id' => 0, 'clave' => $clave, 'hora' => $hora ?: '00:00'];
    }
    $creados++;
    $porMes[substr($inicio, 0, 7)] = ($porMes[substr($inicio, 0, 7)] ?? 0) + 1;
    $clase = (string) ($e['clase'] ?? 'evento');
    $porClase[$clase] = ($porClase[$clase] ?? 0) + 1;
}

printf("Eventos: %d %s, %d ya estaban%s%s", $creados,
    $ensayo ? 'por crear' : 'creados', $omitidos,
    $sinTitulo ? ", {$sinTitulo} sin título o sin fecha (omitidos)" : '', PHP_EOL);
foreach ($porClase as $clase => $n) {
    printf("  %-10s %d%s", $clase, $n, PHP_EOL);
}
ksort($porMes);
foreach ($porMes as $mes => $n) {
    printf("  %s  %d%s", $mes, $n, PHP_EOL);
}

// ── Horarios semanales ──────────────────────────────────────────────────────
$existentes = horariosExistentes($db);
$hCreados = $hOmitidos = 0;

foreach ($datos['horarios'] ?? [] as $h) {
    $titulo = trim((string) ($h['titulo'] ?? ''));
    $hora   = (string) ($h['hora'] ?? '');
    $dia    = $h['dia_semana'] ?? null;
    if ($titulo === '' || $hora === '' || $dia === null) {
        continue;
    }
    $indice = $dia . '|' . $hora;
    if (in_array(comparable($titulo), $existentes[$indice] ?? [], true)) {
        $hOmitidos++;
        continue;
    }

    $registro = [
        'centro_id'     => $h['centro_id'] ?? null,
        'tipo'          => 'otro',
        'dia_semana'    => (int) $dia,
        'hora'          => $hora . ':00',
        'hora_fin'      => !empty($h['hora_fin']) ? $h['hora_fin'] . ':00' : null,
        'lugar'         => $h['lugar'] ?? null,
        // `horarios` no tiene columna de título: el nombre de la actividad va en
        // la nota, que es lo que muestra la página pública.
        'nota'          => mb_substr($titulo, 0, 160, 'UTF-8'),
        'vigente_desde' => null,
        'vigente_hasta' => null,
        'orden'         => 0,
        'activo'        => 1,
    ];
    if (!$ensayo) {
        $horarioModel->crear($registro);
        $existentes[$indice][] = comparable($titulo);
    }
    $hCreados++;
}

printf("Horarios semanales: %d %s, %d ya estaban%s", $hCreados,
    $ensayo ? 'por crear' : 'creados', $hOmitidos, PHP_EOL);

echo str_repeat('─', 78) . PHP_EOL;
echo $ensayo
    ? "Nada escrito. Quita --dry-run para hacerlo de verdad." . PHP_EOL
    : "Listo. Recuerda que esta carga no deja rastro en `auditoria`." . PHP_EOL;
