<?php
/**
 * La semana de horarios, como en la propuesta de rediseño: encabezado con
 * las fichas de sede a la derecha, pestañas por día (abre en el de hoy) y,
 * en cada día, tres tarjetas: misas, confesiones y oración y grupos.
 *
 * La usan la portada (franja #horarios) y la página de Horarios. Espera:
 *   $horarios       vigentes de toda la semana (HorarioModel::vigentesDeLaSemana)
 *   $centros        sedes y centros activos con todos sus datos
 *   $nombresCortos  id del centro => "Villa Bonita", "San Pío"… (CentroModel::nombresCortos)
 *   $semana         ['nivel' => 'h1'|'h2', 'intro' => HTML de confianza, 'sede' => ?int]
 *
 * Las fichas filtran sin recargar (publico.js); 'sede' deja una ya elegida
 * (la página de Horarios la toma de ?centro=). Sin JavaScript las fichas no
 * se dibujan y se ven los siete días seguidos con todas las sedes.
 */
$hoySemana    = (int) date('w');
$nivelTitulo  = $semana['nivel'] ?? 'h2';
$sedeInicial  = $semana['sede'] ?? null;
$centrosPorId = array_column($centros, null, 'id');

$columnas = [
    ['Misas',            ['misa'],                         'bi-house-heart'],
    ['Confesiones',      ['confesion'],                    'bi-chat-heart'],
    ['Oración y grupos', ['adoracion', 'otro', 'oficina'], 'bi-stars'],
];

$porDia = array_fill(0, 7, []);
foreach ($horarios as $h) {
    $porDia[(int) $h['dia_semana']][] = $h;
}

/** Qué es: la nota si la hay ("Misa para niños"), si no el nombre del tipo. */
$queEs = static function (array $h): string {
    if (!empty($h['nota'])) {
        return $h['nota'];
    }
    return match ($h['tipo']) {
        'misa'      => 'Santa Misa',
        'confesion' => 'Confesiones',
        default     => HorarioModel::TIPOS[$h['tipo']][0] ?? ucfirst($h['tipo']),
    };
};
?>
<div class="d-flex flex-column flex-lg-row justify-content-lg-between align-items-lg-end gap-4">
    <div class="encabezado-seccion mb-0">
        <span class="antetitulo">Horarios</span>
        <<?= $nivelTitulo ?> class="<?= $nivelTitulo === 'h1' ? 'titulo-pagina mb-3' : 'titulo-seccion' ?>">Misas, confesiones y oración</<?= $nivelTitulo ?>>
        <?= $semana['intro'] ?? '' ?>
    </div>
    <?php if (count($centros) > 1): ?>
    <div class="fichas-sede solo-con-js" role="group" aria-label="Filtrar por sede" data-filtro-sedes>
        <button type="button" class="ficha-sede <?= $sedeInicial === null ? 'activa' : '' ?>" data-sede="todas"
                aria-pressed="<?= $sedeInicial === null ? 'true' : 'false' ?>">Todas las sedes</button>
        <?php foreach ($centros as $centro): ?>
        <?php $elegida = $sedeInicial === (int) $centro['id']; ?>
        <button type="button" class="ficha-sede <?= $elegida ? 'activa' : '' ?>" data-sede="<?= (int) $centro['id'] ?>"
                aria-pressed="<?= $elegida ? 'true' : 'false' ?>"><?= e($nombresCortos[(int) $centro['id']] ?? $centro['nombre']) ?></button>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php if (!$horarios): ?>
<p class="text-muted fst-italic mt-5">Los horarios se están actualizando. Vuelve pronto.</p>
<?php else: ?>

<div class="pestanas-dias-marco mt-5">
    <ul class="nav pestanas-dias" role="tablist">
        <?php for ($d = 0; $d < 7; $d++): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $d === $hoySemana ? 'active' : '' ?>" id="pestana-dia-<?= $d ?>"
                    data-bs-toggle="pill" data-bs-target="#dia-<?= $d ?>" type="button" role="tab"
                    aria-controls="dia-<?= $d ?>" aria-selected="<?= $d === $hoySemana ? 'true' : 'false' ?>">
                <span class="d-sm-none"><?= e(ucfirst(mb_substr(nombre_dia($d), 0, 3))) ?></span>
                <span class="d-none d-sm-inline"><?= e(ucfirst(nombre_dia($d))) ?></span>
                <?php if ($d === $hoySemana): ?>
                <span class="punto-hoy" aria-hidden="true"></span><span class="visually-hidden"> (hoy)</span>
                <?php endif; ?>
            </button>
        </li>
        <?php endfor; ?>
    </ul>
</div>

<div class="tab-content mt-4">
    <?php for ($d = 0; $d < 7; $d++): ?>
    <div class="tab-pane fade <?= $d === $hoySemana ? 'show active' : '' ?>" id="dia-<?= $d ?>" role="tabpanel"
         aria-labelledby="pestana-dia-<?= $d ?>" tabindex="0">
        <h3 class="solo-sin-js titulo-seccion text-capitalize"><?= e(nombre_dia($d)) ?></h3>
        <div class="row g-4">
            <?php foreach ($columnas as [$titulo, $tipos, $icono]): ?>
            <?php $lista = array_values(array_filter($porDia[$d], static fn (array $h): bool => in_array($h['tipo'], $tipos, true))); ?>
            <div class="col-md-4">
                <div class="grupo-horario" data-nombre="<?= e(mb_strtolower($titulo)) ?>">
                    <h4 class="grupo-horario-titulo">
                        <i class="bi <?= e($icono) ?>" aria-hidden="true"></i><?= e($titulo) ?>
                        <span class="cuenta"><?= $lista ? count($lista) : '' ?></span>
                    </h4>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($lista as $h): ?>
                        <?php
                        $centroId = $h['centro_id'] !== null ? (int) $h['centro_id'] : null;
                        $donde    = $centroId !== null && isset($centrosPorId[$centroId])
                            ? $centrosPorId[$centroId]['nombre']
                            : ($h['lugar'] ?: 'En las comunidades');
                        ?>
                        <li class="renglon-horario" data-sede="<?= $centroId ?? '' ?>">
                            <span class="hora"><?= e(hora_corta($h['hora'])) ?></span>
                            <span class="min-w-0">
                                <span class="que"><?= e($queEs($h)) ?></span>
                                <span class="donde"><?= e($donde) ?><?php if ($h['hora_fin']): ?> · hasta las <?= e(hora_corta($h['hora_fin'])) ?><?php endif; ?></span>
                            </span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="grupo-horario-vacio" <?= $lista ? 'hidden' : '' ?>>Sin <?= e(mb_strtolower($titulo)) ?> este día.</p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endfor; ?>
</div>

<?php endif; ?>
