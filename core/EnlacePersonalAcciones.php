<?php
require_once BASE_PATH . '/core/EnlacePersonal.php';
require_once BASE_PATH . '/core/EnlacePersonalRespuesta.php';

/**
 * El botón «Enlace personal» de las listas propias de un módulo: Ministros
 * (MESC), Proclamadores, Integrantes (Coros) y Catequistas. La coordinación
 * copia el enlace de alguien, se lo manda por WhatsApp o genera uno nuevo. El
 * enlace nace la primera vez que se pide; no hay que dar de alta a nadie. Ver
 * core/EnlacePersonal.php. El mismo botón está, para cualquier pastoral, en
 * su panel (PastoralController::enlacePersonal()).
 *
 * El controlador que lo usa pone su pastoralIdOFallar() y su enlaceConfig():
 *
 *   permiso   el que hace falta, el de editar la lista ('coros.editar')
 *   tabla     su lista, una de EnlacePersonal::LISTAS
 *   singular  cómo se le dice a alguien de la lista, para los errores
 *   titulo    el nombre de la pastoral en el mensaje: «tus turnos de MESC»
 *   turnos    si su página le trae turnos (MESC, Proclamadores)
 *
 * Ruta: <modulo>/enlace_personal (POST, responde JSON).
 */
trait EnlacePersonalAcciones
{
    use EnlacePersonalRespuesta;

    abstract protected function enlaceConfig(): array;

    public function enlacePersonal(): void
    {
        $cfg = $this->enlaceConfig();
        $this->requirePermiso($cfg['permiso']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Este enlace se pide desde la lista.'], 405);
        }
        $this->validarCsrf();

        $pastoralId = $this->pastoralIdOFallar();
        if ($pastoralId === null) {
            return;
        }

        $enlaces = new EnlacePersonal();
        $ficha   = $enlaces->fichaDeLista($cfg['tabla'], $this->postInt('id'), $pastoralId);
        if (!$ficha) {
            $this->json(['error' => "No encontramos a ese {$cfg['singular']} en la lista."], 404);
        }
        if (!$ficha['persona_id']) {
            $this->json(['error' => "{$ficha['nombre']} todavía no está ligado a su ficha del equipo pastoral, y el enlace sale de ahí. "
                . 'Ábrelo con el lápiz y elige quién es en «¿Quién es?».'], 422);
        }
        if (!$ficha['activo'] || !$ficha['persona_activa']) {
            $this->json(['error' => "{$ficha['nombre']} está inactivo: su enlace no le mostraría nada."], 422);
        }

        $personaId = (int) $ficha['persona_id'];
        $this->responderEnlacePersonal(
            $personaId,
            $ficha['nombre_completo'] ?: $ficha['nombre'],
            $ficha['nombre'],
            $ficha['telefono'],
            EnlacePersonal::queVe($cfg['titulo'], $cfg['turnos']),
            // Si en su ficha no está marcado en esta pastoral, su página no le
            // mostrará nada de ella: mejor saberlo antes de mandarlo.
            $enlaces->perteneceA($personaId, $pastoralId) ? null
                : "En su ficha del equipo pastoral no está marcado en {$cfg['titulo']}: así, su página no le mostrará "
                  . ($cfg['turnos'] ? 'estos turnos' : "nada de {$cfg['titulo']}") . '. Márcalo en su ficha.'
        );
    }
}
