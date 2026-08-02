<?php
/**
 * Calendario — Motor de periodos y cuadrículas, sin saber nada de eventos.
 *
 * Nació dentro de EventoPublicoController, que era el único calendario del
 * sitio. Al aparecer la agenda interna del panel (issue de filtrado por
 * pastoral) hubo que elegir entre duplicar las cuatro vistas o compartirlas.
 * MESC sí duplicó su cuadrícula de turnos, y con razón: eran veinte líneas.
 * Aquí son doscientas —cuatro periodos, sus saltos, sus títulos y el reparto
 * de un rango de varios días entre las casillas que le tocan—, y dos copias de
 * eso se separan a la primera corrección que se aplique solo en una.
 *
 * La clase no construye URLs ni consulta la base: recibe «ítems» ya leídos y
 * devuelve estructuras listas para las plantillas. Cada ítem es un arreglo con
 * al menos:
 *
 *   fecha_inicio  'Y-m-d H:i:s'   obligatorio
 *   fecha_fin     'Y-m-d H:i:s'   o null si dura un solo día
 *   todo_el_dia   0 | 1           para ordenar dentro del día
 *   titulo        string          para desempatar ese orden
 *   color         '#rrggbb'       lo usa la vista de año para marcar el día
 *
 * Un evento cumple eso tal cual. Un curso se normaliza a esa forma antes de
 * entrar (AgendaController::comoItem()).
 */
class Calendario
{
    /** Modos: unidad que se muestra y se navega de una vez. */
    public const VISTAS = [
        'dia'    => 'Día',
        'semana' => 'Semana',
        'mes'    => 'Mes',
        'anio'   => 'Año',
    ];
    public const VISTA_POR_OMISION = 'mes';

    private const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
                           'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    // ── Qué periodo se pidió ────────────────────────────────────────────────

    /** ?vista=…, con el modo por omisión si llega cualquier otra cosa. */
    public static function vistaSolicitada(array $get): string
    {
        $vista = strtolower(trim((string) ($get['vista'] ?? '')));
        return isset(self::VISTAS[$vista]) ? $vista : self::VISTA_POR_OMISION;
    }

    /**
     * La fecha de referencia. Se admite ?fecha=Y-m-d y también el ?anio=&mes=
     * de siempre, para que los enlaces que ya circulan sigan funcionando.
     */
    public static function fechaSolicitada(array $get): DateTimeImmutable
    {
        $fecha = trim((string) ($get['fecha'] ?? ''));
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha, $partes)) {
            [, $a, $m, $d] = array_map('intval', $partes);
            if (checkdate($m, $d, $a) && $a >= 2000 && $a <= 2100) {
                return new DateTimeImmutable($fecha);
            }
        }

        $anio = (int) ($get['anio'] ?? date('Y'));
        $mes  = (int) ($get['mes']  ?? date('n'));
        $dia  = (int) ($get['dia']  ?? 0);
        if ($mes < 1 || $mes > 12) {
            $mes = (int) date('n');
        }
        if ($anio < 2000 || $anio > 2100) {
            $anio = (int) date('Y');
        }
        if ($dia < 1 || !checkdate($mes, $dia, $anio)) {
            // Sin día explícito: hoy si es el mes en curso, y si no el día 1,
            // que es lo que espera quien llega desde un enlace ?anio=&mes=.
            $dia = ($anio === (int) date('Y') && $mes === (int) date('n')) ? (int) date('j') : 1;
        }
        return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $anio, $mes, $dia));
    }

    /** Primer y último día del periodo, como 'Y-m-d' y ambos inclusive. */
    public static function limites(string $vista, DateTimeImmutable $ancla): array
    {
        switch ($vista) {
            case 'dia':
                return [$ancla->format('Y-m-d'), $ancla->format('Y-m-d')];
            case 'semana':
                $domingo = self::domingoDeLaSemana($ancla);
                return [$domingo->format('Y-m-d'), $domingo->modify('+6 days')->format('Y-m-d')];
            case 'anio':
                return [$ancla->format('Y') . '-01-01', $ancla->format('Y') . '-12-31'];
            case 'mes':
            default:
                return [$ancla->format('Y-m-01'), $ancla->format('Y-m-t')];
        }
    }

    /** Mueve la fecha ancla un periodo completo hacia atrás o hacia delante. */
    public static function desplazar(string $vista, DateTimeImmutable $ancla, int $pasos): DateTimeImmutable
    {
        $salto = ($pasos < 0 ? '-' : '+') . abs($pasos);
        switch ($vista) {
            case 'dia':
                return $ancla->modify("{$salto} days");
            case 'semana':
                return $ancla->modify("{$salto} weeks");
            case 'anio':
                // Al 1 de enero antes de saltar: «29 de febrero -1 año» no existe
                // y PHP lo corre al 1 de marzo.
                return $ancla->setDate((int) $ancla->format('Y'), 1, 1)->modify("{$salto} years");
            case 'mes':
            default:
                // 'first day of' antes de sumar: sobre un 31, «+1 month» se va al
                // día 1 o 3 del mes siguiente en vez de al mes siguiente.
                return $ancla->modify('first day of this month')->modify("{$salto} months");
        }
    }

    public static function titulo(string $vista, DateTimeImmutable $ancla): string
    {
        [$desde, $hasta] = self::limites($vista, $ancla);
        switch ($vista) {
            case 'dia':
                return ucfirst(fecha_con_dia($desde)) . ' de ' . $ancla->format('Y');
            case 'semana':
                $ini = new DateTimeImmutable($desde);
                $fin = new DateTimeImmutable($hasta);
                // El mes y el año solo se repiten si la semana los cruza.
                $izquierda = (int) $ini->format('j')
                    . ($ini->format('n') !== $fin->format('n')
                        ? ' de ' . self::nombreMes((int) $ini->format('n')) : '')
                    . ($ini->format('Y') !== $fin->format('Y') ? ' de ' . $ini->format('Y') : '');
                return $izquierda . ' al ' . (int) $fin->format('j') . ' de '
                     . self::nombreMes((int) $fin->format('n')) . ' de ' . $fin->format('Y');
            case 'anio':
                return $ancla->format('Y');
            case 'mes':
            default:
                return ucfirst(self::nombreMes((int) $ancla->format('n'))) . ' ' . $ancla->format('Y');
        }
    }

    public static function nombreMes(int $mes): string
    {
        return self::MESES[$mes - 1] ?? '';
    }

    /**
     * La semana empieza en domingo, igual que la cuadrícula del mes y que
     * `horarios.dia_semana`. format('w') ya da 0 para domingo.
     */
    private static function domingoDeLaSemana(DateTimeImmutable $fecha): DateTimeImmutable
    {
        return $fecha->modify('-' . (int) $fecha->format('w') . ' days');
    }

    // ── Reparto de los ítems por día ────────────────────────────────────────

    /**
     * 'Y-m-d' => ítems de ese día. Uno de varios días aparece en todos los que
     * le tocan dentro del periodo, no solo en el de inicio.
     */
    public static function repartirPorDia(array $items, string $desde, string $hasta): array
    {
        $porDia = [];
        foreach ($items as $item) {
            foreach (self::diasDelItem($item, $desde, $hasta) as $fecha) {
                $porDia[$fecha][] = $item;
            }
        }
        return $porDia;
    }

    /**
     * Días ('Y-m-d') que un ítem ocupa dentro del periodo mostrado, recortando
     * su rango [fecha_inicio, fecha_fin] a los límites recibidos. Uno de un
     * solo día devuelve un único elemento.
     */
    public static function diasDelItem(array $item, string $desde, string $hasta): array
    {
        $inicio = substr((string) $item['fecha_inicio'], 0, 10);
        $fin    = !empty($item['fecha_fin']) ? substr((string) $item['fecha_fin'], 0, 10) : $inicio;

        $primero = max($inicio, $desde);
        $ultimo  = min($fin, $hasta);
        if ($primero > $ultimo) {
            return [];
        }

        $dias   = [];
        $cursor = new DateTimeImmutable($primero);
        $limite = new DateTimeImmutable($ultimo);
        while ($cursor <= $limite) {
            $dias[] = $cursor->format('Y-m-d');
            $cursor = $cursor->modify('+1 day');
        }
        return $dias;
    }

    /**
     * Dentro de un día, primero lo que tiene hora y al final lo de todo el día.
     * La consulta ordena por fecha_inicio, que en un ítem de varios días es la
     * de su primer día y no la del día que se está mostrando.
     */
    public static function ordenarPorHora(array $items): array
    {
        usort($items, static function (array $a, array $b): int {
            $ha = !empty($a['todo_el_dia']) ? '99:99' : substr((string) $a['fecha_inicio'], 11, 5);
            $hb = !empty($b['todo_el_dia']) ? '99:99' : substr((string) $b['fecha_inicio'], 11, 5);
            return [$ha, (string) $a['titulo']] <=> [$hb, (string) $b['titulo']];
        });
        return $items;
    }

    // ── Estructuras para las plantillas ─────────────────────────────────────

    /**
     * Los días del periodo, uno por uno y en orden. Lo usan las vistas de día y
     * de semana, que listan cada jornada en vez de dibujar una cuadrícula.
     */
    public static function diasDelPeriodo(string $vista, string $desde, string $hasta, array $porDia): array
    {
        if ($vista !== 'dia' && $vista !== 'semana') {
            return [];
        }
        $hoy    = date('Y-m-d');
        $dias   = [];
        $cursor = new DateTimeImmutable($desde);
        $limite = new DateTimeImmutable($hasta);
        while ($cursor <= $limite) {
            $fecha  = $cursor->format('Y-m-d');
            $dias[] = [
                'fecha'     => $fecha,
                'dia'       => (int) $cursor->format('j'),
                'nombreDia' => nombre_dia((int) $cursor->format('w')),
                'nombreMes' => self::nombreMes((int) $cursor->format('n')),
                'eventos'   => self::ordenarPorHora($porDia[$fecha] ?? []),
                'hoy'       => $fecha === $hoy,
            ];
            $cursor = $cursor->modify('+1 day');
        }
        return $dias;
    }

    /**
     * Cuadrícula del mes en semanas de 7 casillas (domingo primero, igual que
     * horarios.dia_semana). Una casilla es null si cae fuera del mes.
     */
    public static function cuadriculaDelMes(DateTimeImmutable $ancla, array $porDia): array
    {
        $primerDia = $ancla->modify('first day of this month');
        $diasEnMes = (int) $primerDia->format('t');
        $hoy       = date('Y-m-d');

        $semanas = [];
        $semana  = array_fill(0, (int) $primerDia->format('w'), null);

        for ($dia = 1; $dia <= $diasEnMes; $dia++) {
            $fecha    = $primerDia->modify('+' . ($dia - 1) . ' days')->format('Y-m-d');
            $semana[] = [
                'dia'     => $dia,
                'fecha'   => $fecha,
                'eventos' => self::ordenarPorHora($porDia[$fecha] ?? []),
                'hoy'     => $fecha === $hoy,
            ];
            if (count($semana) === 7) {
                $semanas[] = $semana;
                $semana    = [];
            }
        }
        if ($semana) {
            while (count($semana) < 7) {
                $semana[] = null;
            }
            $semanas[] = $semana;
        }
        return $semanas;
    }

    /**
     * Los doce meses del año como mini-cuadrículas. Cada día lleva cuántos
     * ítems tiene, no la lista: en una casilla de esa medida no cabe ningún
     * título, así que el día se marca y se enlaza a la vista de día.
     */
    public static function cuadriculaDelAnio(DateTimeImmutable $ancla, array $porDia): array
    {
        $anio  = (int) $ancla->format('Y');
        $hoy   = date('Y-m-d');
        $meses = [];

        for ($mes = 1; $mes <= 12; $mes++) {
            $primerDia = new DateTimeImmutable(sprintf('%04d-%02d-01', $anio, $mes));
            $celdas    = array_fill(0, (int) $primerDia->format('w'), null);
            $total     = 0;

            for ($dia = 1; $dia <= (int) $primerDia->format('t'); $dia++) {
                $fecha    = sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
                $cuantos  = count($porDia[$fecha] ?? []);
                $total   += $cuantos;
                $celdas[] = [
                    'dia'     => $dia,
                    'fecha'   => $fecha,
                    'cuantos' => $cuantos,
                    'hoy'     => $fecha === $hoy,
                    'color'   => $cuantos ? ($porDia[$fecha][0]['color'] ?: '#1e4d8b') : null,
                ];
            }
            while (count($celdas) % 7 !== 0) {
                $celdas[] = null;
            }

            $meses[] = [
                'mes'     => $mes,
                'nombre'  => ucfirst(self::nombreMes($mes)),
                'semanas' => array_chunk($celdas, 7),
                'total'   => $total,
            ];
        }
        return $meses;
    }
}
