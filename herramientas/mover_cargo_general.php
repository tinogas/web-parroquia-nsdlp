<?php
/**
 * mover_cargo_general.php — Pasa el cargo general de cada persona a la
 * pastoral donde lo ejerce, y deja el general vacío.
 *
 *     C:\xampp\php\php.exe herramientas/mover_cargo_general.php           # solo dice qué haría
 *     C:\xampp\php\php.exe herramientas/mover_cargo_general.php --aplicar # lo hace
 *
 *     --base=parroquia_nsdlp_pruebas     sobre otra base (por ejemplo, una copia)
 *     --asignar=11:18                     decisión a mano: persona 11, a la pastoral 18,
 *                                         con su cargo general
 *     --asignar=9:30=Coordinadora General con otro texto; se puede repetir, también
 *                                         varias veces para la misma persona
 *
 * POR QUÉ: desde que cada quien tiene un cargo en cada pastoral (migración del
 * 16 de noviembre de 2026) el cargo general sobraba casi siempre: «Proclamador»
 * arriba y Proclamadores marcada abajo. Y desde la migración del 20
 * (cargo-general-aparte), quien no tiene general se muestra con el más alto de
 * sus cargos por pastoral, así que vaciarlo ya no deja a nadie sin cargo.
 *
 * A DÓNDE VA, en este orden —la primera regla que decide, gana—:
 *   1. Lo que se diga con --asignar.
 *   2. El clero —párroco, vicario, diácono, religiosos— se queda con su cargo
 *      general: «Vicario» es de la parroquia, aunque esté marcado en Raíces.
 *   3. Si no está en ninguna pastoral activa, también se queda: es el caso de
 *      la secretaria, que no es de una pastoral.
 *   4. Si ya lo tiene escrito en una de sus pastorales, solo se vacía el general.
 *   5. Si está en una sola pastoral, a esa, aunque diga dos cosas
 *      («Coordinadora / Suplente Coro», en Coros).
 *   6. Si dice más de un cargo y está en varias pastorales («Coordinadora
 *      General de Litúrgica - MESC - Proclamadora»), no se adivina: se pide
 *      decidir.
 *   7. Si el cargo nombra una de sus pastorales —«Proclamador» y Proclamadores,
 *      «Catequista» y Catequesis, «Coordinadora de Monaguillos»—, a esa.
 *   8. Si está en una Comisión y en una sola de sus dimensiones o grupos: a la
 *      dimensión, que es donde se ejerce el cargo; salvo si el cargo es de
 *      coordinación general y coordina la Comisión, que entonces va a ella.
 *   9. Si el cargo es de coordinación y coordina una sola de sus pastorales, a esa.
 *  10. Si no, se pide decidir.
 * Nunca pisa un cargo que la persona ya tenga escrito en una pastoral: si ahí
 * dice otra cosa, se pide decidir.
 *
 * En MESC, «Ministro Extraordinario de la Sagrada Comunión» se escribe
 * «Ministro»: la pastoral ya se llama así, y es como lo escribieron a mano
 * quienes ya tenían su cargo ahí.
 *
 * Con --aplicar todo va en una transacción, y cada persona deja un renglón en
 * la bitácora (sin usuario: la hizo esta herramienta).
 */

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/app.php';

$opciones = getopt('', ['aplicar', 'base:', 'asignar:']);
$aplicar  = isset($opciones['aplicar']);
if (!empty($opciones['base'])) {
    // config/database.php define DB_NAME; definida antes, gana esta.
    define('DB_NAME', (string) $opciones['base']);
    set_error_handler(static fn (int $n, string $m): bool => str_contains($m, 'DB_NAME'), E_WARNING);
}
if (!is_file(BASE_PATH . '/config/database.php')) {
    fwrite(STDERR, "Falta config/database.php.\n");
    exit(1);
}
require_once BASE_PATH . '/config/database.php';
restore_error_handler();
require_once BASE_PATH . '/core/helpers.php';
require_once BASE_PATH . '/modules/personas/PersonaModel.php';

$pdo = Database::getInstance();
$tieneColumna = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personas' AND COLUMN_NAME = 'cargo_general'")->fetchColumn();
if (!$tieneColumna) {
    fwrite(STDERR, "Esta base todavía no tiene personas.cargo_general: aplica antes\n"
        . "docs/migraciones/2026-11-20-cargo-general-aparte.sql\n");
    exit(1);
}

// ── Decisiones a mano ─────────────────────────────────────────────────
$asignadas = [];
foreach ((array) ($opciones['asignar'] ?? []) as $asignacion) {
    if (!preg_match('/^(\d+):(\d+)(?:=(.+))?$/u', (string) $asignacion, $m)) {
        fwrite(STDERR, "No entiendo --asignar={$asignacion}. Es persona:pastoral o persona:pastoral=cargo.\n");
        exit(1);
    }
    $asignadas[(int) $m[1]][] = ['pastoral' => (int) $m[2], 'cargo' => isset($m[3]) ? trim($m[3]) : null];
}

// ── Los datos ─────────────────────────────────────────────────────────
$sinAcentos = static fn (string $t): string => strtr(
    mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $t)), 'UTF-8'),
    ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']
);

$pastorales = [];
foreach ($pdo->query('SELECT id, nombre, pastoral_padre_id, activa FROM pastorales') as $p) {
    $pastorales[(int) $p['id']] = $p;
}
$tieneHijas = [];
foreach ($pastorales as $p) {
    if ($p['pastoral_padre_id'] !== null) {
        $tieneHijas[(int) $p['pastoral_padre_id']] = true;
    }
}
$nivel = static function (int $id) use ($pastorales, $tieneHijas): string {
    $padre = $pastorales[$id]['pastoral_padre_id'];
    if ($padre === null) {
        return isset($tieneHijas[$id]) ? 'Comisión' : 'suelta';
    }
    return $pastorales[(int) $padre]['pastoral_padre_id'] === null ? 'dimensión' : 'grupo';
};

$miembro = []; // persona => pastoral => su cargo ahí ('' si no tiene)
foreach ($pdo->query('SELECT persona_id, pastoral_id, cargo FROM persona_pastorales') as $f) {
    $miembro[(int) $f['persona_id']][(int) $f['pastoral_id']] = trim((string) $f['cargo']);
}
$coordina = [];
foreach ($pdo->query('SELECT persona_id, pastoral_id FROM pastoral_coordinadores') as $f) {
    $coordina[(int) $f['persona_id']][(int) $f['pastoral_id']] = true;
}

/** Palabras que dicen de qué es el cargo, sin las que dicen qué tan alto es. */
$palabras = static function (string $texto) use ($sinAcentos): array {
    $vacias = ['de', 'del', 'la', 'las', 'el', 'los', 'y', 'en', 'a', 'coordinador', 'coordinadora',
               'coordinadores', 'coordinacion', 'general', 'pastoral', 'equipo', 'equipos', 'grupo', 'sub'];
    $sinonimos = ['corista' => 'coros', 'coro' => 'coros', 'mesc' => 'ministro'];
    $salida = [];
    foreach (preg_split('/[^a-z]+/', $sinAcentos($texto), -1, PREG_SPLIT_NO_EMPTY) as $p) {
        if (!in_array($p, $vacias, true)) {
            $salida[] = $sinonimos[$p] ?? $p;
        }
    }
    return $salida;
};
/** ¿El cargo nombra esta pastoral? «Proclamador»/Proclamadores, «Catequista»/Catequesis. */
$nombra = static function (string $cargo, string $pastoral) use ($palabras): bool {
    foreach ($palabras($cargo) as $a) {
        foreach ($palabras($pastoral) as $b) {
            $largo = 0;
            while ($largo < min(strlen($a), strlen($b)) && $a[$largo] === $b[$largo]) {
                $largo++;
            }
            if ($a === $b || $largo >= 6) {
                return true;
            }
        }
    }
    return false;
};
$esMesc = static fn (string $nombre): bool
    => $sinAcentos($nombre) === 'ministro extraordinario de la sagrada comunion';

// ── Decidir ───────────────────────────────────────────────────────────
$plan = []; // motivo => [ [persona, general, destinos[], nota] ]
$personas = $pdo->query("SELECT id, nombre, tipo, cargo_general FROM personas
                          WHERE activo = 1 AND COALESCE(TRIM(cargo_general), '') <> '' ORDER BY nombre")->fetchAll();

foreach ($personas as $persona) {
    $id      = (int) $persona['id'];
    $general = PersonaModel::normalizarCargo($persona['cargo_general']);
    $suyas   = array_filter(
        $miembro[$id] ?? [],
        static fn ($cargo, $pid) => isset($pastorales[$pid]) && (int) $pastorales[$pid]['activa'] === 1,
        ARRAY_FILTER_USE_BOTH
    );
    $anotar = static function (string $motivo, array $destinos = [], string $nota = '') use (&$plan, $persona, $general): void {
        $plan[$motivo][] = ['persona' => $persona, 'general' => $general, 'destinos' => $destinos, 'nota' => $nota];
    };
    // El texto que va a la pastoral (en MESC, «Ministro»).
    $textoPara = static fn (int $pid, string $cargo): string
        => ($esMesc($pastorales[$pid]['nombre']) && $sinAcentos($cargo) === $sinAcentos($pastorales[$pid]['nombre']))
            ? 'Ministro' : $cargo;

    if (isset($asignadas[$id])) {
        $destinos = [];
        foreach ($asignadas[$id] as $a) {
            if (!isset($pastorales[$a['pastoral']])) {
                fwrite(STDERR, "--asignar: no existe la pastoral {$a['pastoral']}.\n");
                exit(1);
            }
            $destinos[$a['pastoral']] = $a['cargo'] ?? $textoPara($a['pastoral'], $general);
        }
        $anotar('Decidido a mano', $destinos);
        continue;
    }
    if (in_array($persona['tipo'], ['parroco', 'vicario', 'diacono', 'religioso'], true)) {
        $anotar('Se queda como cargo general: es del clero');
        continue;
    }
    if (!$suyas) {
        $anotar('Se queda como cargo general: no está en ninguna pastoral activa');
        continue;
    }
    // Regla 3: ya lo tiene escrito en una de sus pastorales.
    $repetido = false;
    foreach ($suyas as $pid => $cargoAhi) {
        if ($cargoAhi === '') {
            continue;
        }
        $nombre = $pastorales[$pid]['nombre'];
        if (in_array($sinAcentos($general), [$sinAcentos($cargoAhi), $sinAcentos("$cargoAhi de $nombre"), $sinAcentos("$cargoAhi en $nombre")], true)
            || ($esMesc($nombre) && $sinAcentos($general) === $sinAcentos($nombre))) {
            $repetido = true;
        }
    }
    if ($repetido) {
        $anotar('Ya lo tiene en su pastoral: solo se vacía el general');
        continue;
    }
    if (count($suyas) > 1 && preg_match('/\s[-–\/]\s|,|\s\/|\/\s/u', $general)) {
        $anotar('Hay que decidir: dice más de un cargo', [], implode(', ', array_map(fn ($pid) => $pastorales[$pid]['nombre'], array_keys($suyas))));
        continue;
    }

    $destino = null;
    $motivo  = '';
    $ids     = array_keys($suyas);
    if (count($ids) === 1) {
        [$destino, $motivo] = [$ids[0], 'A su única pastoral'];
    }
    if ($destino === null) {
        $nombradas = array_values(array_filter($ids, fn ($pid) => $nombra($general, $pastorales[$pid]['nombre'])));
        if (count($nombradas) === 1) {
            [$destino, $motivo] = [$nombradas[0], 'A la pastoral que nombra su cargo'];
        }
    }
    if ($destino === null) {
        $comisiones = array_values(array_filter($ids, fn ($pid) => $nivel($pid) === 'Comisión'));
        $debajo     = array_values(array_filter($ids, fn ($pid) => in_array($nivel($pid), ['dimensión', 'grupo'], true)));
        if (count($comisiones) === 1 && count($debajo) === 1 && count($ids) === 2) {
            $alComision = PersonaModel::rangoCargo($general) === 0 && isset($coordina[$id][$comisiones[0]]);
            [$destino, $motivo] = $alComision
                ? [$comisiones[0], 'A la Comisión que coordina']
                : [$debajo[0], 'A su dimensión, no a la Comisión'];
        }
    }
    if ($destino === null && PersonaModel::rangoCargo($general) <= 1) {
        $coordinadas = array_values(array_filter($ids, fn ($pid) => isset($coordina[$id][$pid])));
        if (count($coordinadas) === 1) {
            [$destino, $motivo] = [$coordinadas[0], 'A la pastoral que coordina'];
        }
    }
    if ($destino === null) {
        $anotar('Hay que decidir: no se sabe a cuál de sus pastorales', [],
            implode(', ', array_map(fn ($pid) => $pastorales[$pid]['nombre'], $ids)));
        continue;
    }
    $texto = $textoPara($destino, $general);
    if ($suyas[$destino] !== '' && $sinAcentos($suyas[$destino]) !== $sinAcentos($texto)) {
        $anotar('Hay que decidir: en esa pastoral ya tiene otro cargo', [],
            "{$pastorales[$destino]['nombre']} ya dice «{$suyas[$destino]}»");
        continue;
    }
    $anotar($motivo, [$destino => $texto]);
}

// ── Contar ────────────────────────────────────────────────────────────
$orden = ['A su única pastoral', 'A su dimensión, no a la Comisión', 'A la Comisión que coordina',
          'A la pastoral que nombra su cargo', 'A la pastoral que coordina', 'Decidido a mano',
          'Ya lo tiene en su pastoral: solo se vacía el general',
          'Se queda como cargo general: es del clero',
          'Se queda como cargo general: no está en ninguna pastoral activa'];
uksort($plan, static function (string $a, string $b) use ($orden): int {
    $ia = array_search($a, $orden, true);
    $ib = array_search($b, $orden, true);
    return ($ia === false ? 99 : $ia) <=> ($ib === false ? 99 : $ib) ?: strcmp($a, $b);
});
echo ($aplicar ? 'APLICANDO' : 'SIMULACIÓN (nada se guarda; con --aplicar se hace)') . ' — base ' . DB_NAME . "\n";
echo count($personas) . " personas activas con cargo general.\n";
foreach ($plan as $motivo => $casos) {
    echo "\n" . $motivo . ' (' . count($casos) . ")\n";
    foreach ($casos as $c) {
        $linea = '  ' . str_pad((string) $c['persona']['id'], 4, ' ', STR_PAD_LEFT) . '  '
               . $c['persona']['nombre'] . ' — «' . $c['general'] . '»';
        foreach ($c['destinos'] as $pid => $texto) {
            $linea .= "\n          → «{$texto}» en {$pastorales[$pid]['nombre']}";
        }
        if ($c['nota'] !== '') {
            $linea .= "\n          ({$c['nota']})";
        }
        echo $linea . "\n";
    }
}

if (!$aplicar) {
    exit(0);
}

// ── Aplicar ───────────────────────────────────────────────────────────
$modelo = new PersonaModel();
$movidas = 0;
$pdo->beginTransaction();
try {
    $ponerCargo = $pdo->prepare('UPDATE persona_pastorales SET cargo = :cargo WHERE persona_id = :persona AND pastoral_id = :pastoral');
    $marcar     = $pdo->prepare('INSERT IGNORE INTO persona_pastorales (persona_id, pastoral_id) VALUES (:persona, :pastoral)');
    $vaciar     = $pdo->prepare('UPDATE personas SET cargo_general = NULL WHERE id = :id');
    $bitacora   = $pdo->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_ref, registro_id, ip, descripcion)
                                 VALUES (NULL, 'editar', 'personas', :id, 'herramienta', :descripcion)");
    foreach ($plan as $motivo => $casos) {
        $sePasa = !str_starts_with($motivo, 'Hay que decidir') && !str_starts_with($motivo, 'Se queda');
        if (!$sePasa) {
            continue;
        }
        foreach ($casos as $c) {
            $id = (int) $c['persona']['id'];
            $donde = [];
            foreach ($c['destinos'] as $pid => $texto) {
                $marcar->execute([':persona' => $id, ':pastoral' => $pid]); // por si se asignó a una no marcada
                $ponerCargo->execute([':cargo' => PersonaModel::normalizarCargo($texto), ':persona' => $id, ':pastoral' => $pid]);
                $donde[] = "«{$texto}» en {$pastorales[$pid]['nombre']}";
            }
            $vaciar->execute([':id' => $id]);
            $modelo->recalcularCargo($id);
            $bitacora->execute([':id' => $id, ':descripcion' => mb_substr(
                "Cargo general «{$c['general']}» " . ($donde ? 'pasó a ' . implode(', ', $donde) : 'vaciado: ya estaba en su pastoral')
                . ' (herramienta mover_cargo_general)', 0, 255)]);
            $movidas++;
        }
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'No se guardó nada: ' . $e->getMessage() . "\n");
    exit(1);
}
echo "\nListo: {$movidas} personas.\n";
