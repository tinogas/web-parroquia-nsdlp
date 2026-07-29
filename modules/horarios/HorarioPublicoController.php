<?php
require_once BASE_PATH . '/core/ControllerPublico.php';
require_once BASE_PATH . '/modules/horarios/HorarioModel.php';
require_once BASE_PATH . '/modules/bloques/BloqueModel.php';
require_once BASE_PATH . '/modules/centros/CentroModel.php';

class HorarioPublicoController extends ControllerPublico
{
    public function index(): void
    {
        $centros = (new CentroModel())->activos();

        // "Todos" (sin filtro) si no viene ?centro=, o si no es uno de los centros reales.
        $idsValidos = array_map(static fn (array $c): int => (int) $c['id'], $centros);
        $centroId   = isset($_GET['centro']) && is_numeric($_GET['centro']) ? (int) $_GET['centro'] : null;
        if ($centroId !== null && !in_array($centroId, $idsValidos, true)) {
            $centroId = null;
        }

        $this->render('horarios/publico/index', [
            'metaTitulo'      => 'Horarios',
            'metaDescripcion' => 'Horarios de misas, confesiones, adoración eucarística y oficina parroquial.',
            'urlCanonica'     => url_publica('horarios', $centroId !== null ? ['centro' => $centroId] : []),
            'porTipo'         => (new HorarioModel())->vigentesPorTipo($centroId),
            'centros'         => $centros,
            'centroId'        => $centroId,
            'bloques'         => (new BloqueModel())->porZona('horarios'),
        ]);
    }
}
