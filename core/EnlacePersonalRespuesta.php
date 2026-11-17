<?php
require_once BASE_PATH . '/core/EnlacePersonal.php';

/**
 * Lo que responde el botón «Enlace personal», desde donde se pida: las listas
 * de Ministros, Proclamadores, Coros y Catequistas (EnlacePersonalAcciones) y la lista «Quiénes
 * están en esta pastoral» del panel de cualquier pastoral
 * (PastoralController::enlacePersonal()). Quien lo usa ya comprobó el permiso,
 * el alcance y que la persona esté activa; aquí se crea o se regenera el
 * enlace, se deja en la auditoría y se arma el mensaje de WhatsApp.
 *
 * Responde JSON y termina: lo lee shared/views/parciales/enlace_personal_modal.php.
 */
trait EnlacePersonalRespuesta
{
    /**
     * El mensaje le habla como su página: por su nombre corto si tiene, o por
     * su nombre de pila (EnlacePersonal::comoLlamarle()), venga de la lista
     * que venga.
     *
     * @param string  $nombre  su nombre completo, para el título del cuadro y la bitácora
     * @param string  $queVe   lo que verá con el enlace, para el mensaje: «Ahí ves …»
     * @param ?string $aviso   algo que la coordinación debe saber antes de mandarlo
     */
    private function responderEnlacePersonal(int $personaId, string $nombre, ?string $telefono,
                                             string $queVe, ?string $aviso = null): void
    {
        $enlaces   = new EnlacePersonal();
        $usuarioId = (int) Auth::usuario()['id'];

        if ($this->postBool('regenerar')) {
            $fila  = $enlaces->regenerar($personaId, $usuarioId);
            $nuevo = true;
            $this->auditoria('regenerar_enlace', 'persona_enlaces', $personaId,
                "Enlace personal de {$nombre}: el anterior deja de servir");
        } else {
            [$fila, $nuevo] = $enlaces->obtenerOCrear($personaId, $usuarioId);
            if ($nuevo) {
                $this->auditoria('crear_enlace', 'persona_enlaces', $personaId, "Enlace personal de {$nombre}");
            }
        }

        $url     = EnlacePersonal::url($fila['token']);
        $pila    = $enlaces->comoLlamarle($personaId);
        $mensaje = "Hola {$pila}, este es tu enlace personal de la parroquia:\n{$url}\n\n"
                 . "Ahí ves {$queVe}. Es solo tuyo: no lo compartas.";

        // El número solo viaja en el enlace de WhatsApp a quien puede escribirle
        // a la gente de la pastoral (personas.contactar), como el botón verde
        // de cada renglón. Sin número, WhatsApp deja elegir el contacto.
        $numero = Auth::tienePermiso('personas.contactar') ? telefono_internacional($telefono) : '';

        $this->json([
            'nombre'       => $nombre,
            'url'          => $url,
            'whatsapp'     => 'https://wa.me/' . $numero . '?text=' . rawurlencode($mensaje),
            'con_telefono' => $numero !== '',
            'estado'       => EnlacePersonal::textoEstado($fila),
            'abierto'      => $fila['ultimo_uso_at'] !== null,
            'nuevo'        => $nuevo,
            'aviso'        => $aviso,
        ]);
    }
}
