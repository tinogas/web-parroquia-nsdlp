<?php
require_once BASE_PATH . '/core/CambiosTurno.php';

/**
 * Cómo se lee y se valida el formulario de cambios de turno. Lo comparten la
 * pantalla del panel (core/CambiosTurnoAcciones.php) y la página del enlace
 * personal (modules/mi/MiController.php), para que un cambio pedido desde
 * cualquiera de las dos pase exactamente por las mismas reglas.
 *
 * Todo se valida contra el rol REAL del turno (el original más los cambios ya
 * registrados), nunca contra lo que el formulario dice que hay: el filtrado de
 * las listas en el navegador es solo comodidad. Y nada en el pasado: lo que ya
 * pasó no se mueve.
 *
 * Quien lo use necesita postInt() y postStr(), que da Controller.
 */
trait CambiosTurnoValidacion
{
    /**
     * Arma los movimientos del cambio que trae el POST y los valida.
     *
     * @param array $personas todas las personas del módulo (id, nombre, activo)
     * @return array{0: ?string, 1: array, 2: ?array} el error (o null), los
     *         movimientos y el primer turno, con su rol real
     */
    private function cambioDelFormulario(CambiosTurno $modelo, int $pastoralId, array $cfg, array $personas): array
    {
        $tipo   = $this->postStr('tipo');
        $turnoA = $this->postInt('turno_a') > 0 ? $modelo->turnoConRol($this->postInt('turno_a'), $pastoralId) : null;

        if (!isset(CambiosTurno::TIPOS[$tipo])) {
            return ['Elige qué tipo de cambio es.', [], $turnoA];
        }
        if (!$turnoA) {
            return ['Elige el turno.', [], null];
        }
        if ($turnoA['fecha'] < date('Y-m-d')) {
            return ['El turno del ' . CambiosTurno::turnoCorto($turnoA) . ' ya pasó: solo se registran cambios de hoy en adelante.', [], $turnoA];
        }

        [$error, $movimientos] = $tipo === 'permuta'
            ? $this->validarPermuta($modelo, $pastoralId, $turnoA, $cfg)
            : $this->validarEntrada($personas, $turnoA, $tipo, $cfg);

        return [$error, $movimientos, $turnoA];
    }

    /**
     * El laico solo pide cambios que lo incluyen: él deja un turno, cubre a
     * alguien o se suma. Pedir por otros es cosa de la coordinación.
     *
     * @param array $yo su ficha en el módulo (id, nombre)
     */
    private static function errorSiNoLoIncluye(array $movimientos, array $yo): ?string
    {
        foreach ($movimientos as $mov) {
            if ((int) $mov['sale_id'] === (int) $yo['id'] || (int) $mov['entra_id'] === (int) $yo['id']) {
                return null;
            }
        }
        return 'La solicitud debe incluirte a ti, ' . $yo['nombre'] . ': que dejes un turno, cubras a alguien o te sumes a uno.';
    }

    /**
     * Permuta de grupo a grupo: los X marcados dejan A y pasan a B; los Y
     * marcados dejan B y pasan a A. Uno por uno, dos por dos, o todos por
     * todos (intercambio de turnos completos); también dos por uno, si así se
     * acordó.
     */
    private function validarPermuta(CambiosTurno $modelo, int $pastoralId, array $turnoA, array $cfg): array
    {
        $turnoB = $this->postInt('turno_b') > 0 ? $modelo->turnoConRol($this->postInt('turno_b'), $pastoralId) : null;
        $xs = $this->idsPedidos('salen_a');
        $ys = $this->idsPedidos('salen_b');

        if (!$turnoB) {
            return ['Elige el segundo turno de la permuta.', []];
        }
        if ($turnoB['fecha'] < date('Y-m-d')) {
            return ['El turno del ' . CambiosTurno::turnoCorto($turnoB) . ' ya pasó: solo se registran cambios de hoy en adelante.', []];
        }
        if ((int) $turnoB['id'] === (int) $turnoA['id']) {
            return ['Una permuta es entre dos turnos distintos. Para cambiar a alguien dentro del mismo turno usa «Cubre a otro».', []];
        }
        if (!$xs) {
            return ["Marca al menos un {$cfg['singular']} del primer turno.", []];
        }
        if (!$ys) {
            return ["Marca al menos un {$cfg['singular']} del segundo turno.", []];
        }
        foreach ($xs as $x) {
            if (!isset($turnoA['actuales'][$x])) {
                return ["Alguno de los marcados ya no está en el primer turno. Vuelve a elegir.", []];
            }
            if (isset($turnoB['actuales'][$x])) {
                return [$turnoA['actuales'][$x] . ' ya está en el segundo turno; no puede pasar a él.', []];
            }
        }
        foreach ($ys as $y) {
            if (!isset($turnoB['actuales'][$y])) {
                return ["Alguno de los marcados ya no está en el segundo turno. Vuelve a elegir.", []];
            }
            if (isset($turnoA['actuales'][$y])) {
                return [$turnoB['actuales'][$y] . ' ya está en el primer turno; no puede pasar a él.', []];
            }
        }

        // Primero todo lo del turno A y luego todo lo del B. Si los grupos no
        // son del mismo tamaño, al que le sobra le toca una fila de solo
        // «sale» de un lado y de solo «entra» del otro.
        $movimientos = [];
        $n = max(count($xs), count($ys));
        for ($i = 0; $i < $n; $i++) {
            $movimientos[] = ['turno_id' => (int) $turnoA['id'], 'sale_id' => $xs[$i] ?? null, 'entra_id' => $ys[$i] ?? null];
        }
        for ($i = 0; $i < $n; $i++) {
            $movimientos[] = ['turno_id' => (int) $turnoB['id'], 'sale_id' => $ys[$i] ?? null, 'entra_id' => $xs[$i] ?? null];
        }
        return [null, $movimientos];
    }

    /** Ids marcados en una lista de casillas del formulario, sin repetir. */
    private function idsPedidos(string $campo): array
    {
        return array_values(array_unique(array_map('intval',
            array_filter((array) ($_POST[$campo] ?? []), 'is_numeric')
        )));
    }

    /** Sustitución (sale X, entra Y) o agregado (solo entra Y), en un solo turno. */
    private function validarEntrada(array $personas, array $turnoA, string $tipo, array $cfg): array
    {
        $entra = $this->postInt('entra');
        $activas = [];
        foreach ($personas as $persona) {
            if ($persona['activo']) {
                $activas[(int) $persona['id']] = $persona['nombre'];
            }
        }

        if (!isset($activas[$entra])) {
            return ["Elige qué {$cfg['singular']} entra al turno.", []];
        }
        if (isset($turnoA['actuales'][$entra])) {
            return [$activas[$entra] . ' ya está en ese turno.', []];
        }

        $sale = null;
        if ($tipo === 'sustitucion') {
            $sale = $this->postInt('sale_a');
            if (!isset($turnoA['actuales'][$sale])) {
                return ["Elige a qué {$cfg['singular']} del turno cubre.", []];
            }
        }

        return [null, [['turno_id' => (int) $turnoA['id'], 'sale_id' => $sale, 'entra_id' => $entra]]];
    }
}
