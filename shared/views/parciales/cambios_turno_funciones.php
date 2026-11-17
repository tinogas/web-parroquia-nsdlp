<?php
/**
 * Cómo se pinta un cambio de turno: qué cambió, su estado y su bitácora. Lo
 * comparten la pantalla «Cambios de turno» del panel (cambios_turno.php) y la
 * página del enlace personal (modules/mi/views/pagina.php).
 */
if (!function_exists('cambios_turno_detalle')) {
    /** Qué cambia en cada turno: «dom 6 dic, Misa 09:00: ~~Eugenia, Sergio~~ → Hiram, Sandra». */
    function cambios_turno_detalle(array $cambio): void
    {
        foreach (CambiosTurno::porTurno($cambio['movimientos']) as $g) {
            echo '<div><span class="fw-semibold">' . e(CambiosTurno::turnoCorto($g['turno'])) . ':</span> ';
            if ($g['salen']) {
                echo '<span class="text-decoration-line-through text-muted">' . e(implode(', ', $g['salen'])) . '</span> ';
            }
            if ($g['salen'] && $g['entran']) {
                echo '<i class="bi bi-arrow-right mx-1"></i>';
            } elseif ($g['entran']) {
                echo '<i class="bi bi-plus-lg me-1"></i>';
            } else {
                echo '<span class="text-muted">(sale)</span>';
            }
            echo e(implode(', ', $g['entran'])) . '</div>';
        }
        if (!empty($cambio['notas'])) {
            echo '<div class="text-muted fst-italic mt-1">' . e($cambio['notas']) . '</div>';
        }
    }
}

if (!function_exists('cambios_turno_estado')) {
    /**
     * Etiqueta del estado de un cambio: [clase del badge, texto]. Una
     * solicitud se reconoce por resuelto_at (ver CambiosTurno::HISTORIAL); un
     * cambio deshecho dice si había sido registro directo o solicitud.
     */
    function cambios_turno_estado(array $c): array
    {
        $solicitud = $c['resuelto_at'] !== null;
        return match ($c['estado']) {
            'pendiente' => ['text-bg-warning', 'Pendiente'],
            'rechazado' => ['text-bg-danger', 'Solicitud rechazada'],
            'retirado'  => ['text-bg-light border', 'Solicitud retirada'],
            'deshecho'  => ['text-bg-dark', $solicitud ? 'Solicitud deshecha' : 'Registro deshecho'],
            default     => $solicitud ? ['text-bg-success', 'Solicitud aprobada'] : ['text-bg-secondary', 'Registrado directo'],
        };
    }

    /**
     * La bitácora de un cambio, renglón por renglón: quién lo pidió o registró,
     * quién lo resolvió, quién lo deshizo o retiró, cuándo y por qué. $propia:
     * se la muestra a quien la pidió («La pediste», «La retiraste»).
     */
    function cambios_turno_bitacora(array $c, bool $propia = false): void
    {
        $cuando = static fn (?string $f): string => $f ? ' · ' . e(fecha_larga(substr($f, 0, 10))) : '';
        $quien  = static fn (?string $n): string => e($n ?? 'cuenta eliminada');
        $linea  = static function (string $icono, string $texto): void {
            echo '<div><i class="bi ' . $icono . ' me-1"></i>' . $texto . '</div>';
        };
        $motivo = static function (?string $m, string $clase = 'text-danger'): void {
            if ($m) {
                echo '<div class="' . $clase . '"><i class="bi bi-chat-left-text me-1"></i>' . e($m) . '</div>';
            }
        };

        $solicitud = $c['resuelto_at'] !== null || in_array($c['estado'], ['pendiente', 'retirado'], true);
        if ($solicitud) {
            $linea('bi-send', ($propia ? 'La pediste' : 'Pidió ' . $quien($c['autor'] ?? null)
                . (!empty($c['por_enlace']) ? ' desde su enlace personal' : '')) . $cuando($c['created_at']));
        } else {
            $linea('bi-pencil-square', 'Registró ' . $quien($c['autor'] ?? null) . $cuando($c['created_at']));
        }
        if ($c['resuelto_at'] !== null) {
            $rechazada = $c['estado'] === 'rechazado';
            $linea($rechazada ? 'bi-x-circle text-danger' : 'bi-check-circle text-success',
                ($rechazada ? 'Rechazó ' : 'Aprobó ') . $quien($c['resolvio'] ?? null) . $cuando($c['resuelto_at']));
            if ($rechazada) {
                $motivo($c['motivo_rechazo']);
            }
        }
        if ($c['estado'] === 'deshecho') {
            $linea('bi-arrow-counterclockwise text-dark', 'Deshizo ' . $quien($c['cancelo'] ?? null) . $cuando($c['cancelado_at']));
            $motivo($c['motivo_cancelacion'], 'text-dark');
        } elseif ($c['estado'] === 'retirado') {
            $linea('bi-box-arrow-left', ($propia ? 'La retiraste' : 'Retiró ' . $quien($c['cancelo'] ?? null)) . $cuando($c['cancelado_at']));
        }
    }
}
