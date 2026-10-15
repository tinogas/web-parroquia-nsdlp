<?php
require_once BASE_PATH . '/core/ControllerPublico.php';
require_once BASE_PATH . '/modules/horarios/HorarioModel.php';
require_once BASE_PATH . '/modules/bloques/BloqueModel.php';
require_once BASE_PATH . '/modules/centros/CentroModel.php';

class HorarioPublicoController extends ControllerPublico
{
    /**
     * La semana de horarios, igual que la franja de la portada
     * (horarios/views/publico/semana.php). Las sedes se filtran sin recargar;
     * ?centro= sigue funcionando para los enlaces que ya circulan: deja esa
     * ficha elegida al abrir la página.
     */
    public function index(): void
    {
        $centros  = (new CentroModel())->activosCompletos();
        $horarios = (new HorarioModel())->vigentesDeLaSemana();

        // Sin filtro si no viene ?centro=, o si no es uno de los centros reales.
        $idsValidos = array_map(static fn (array $c): int => (int) $c['id'], $centros);
        $centroId   = isset($_GET['centro']) && is_numeric($_GET['centro']) ? (int) $_GET['centro'] : null;
        if ($centroId !== null && !in_array($centroId, $idsValidos, true)) {
            $centroId = null;
        }

        $this->render('horarios/publico/index', [
            'metaTitulo'      => 'Horarios',
            'metaDescripcion' => 'Horarios de misas, confesiones, adoración eucarística y oficina parroquial.',
            'urlCanonica'     => url_publica('horarios'),
            'horarios'        => $horarios,
            'centros'         => $centros,
            'nombresCortos'   => CentroModel::nombresCortos($centros, $horarios),
            'centroId'        => $centroId,
            'bloques'         => (new BloqueModel())->porZona('horarios'),
        ]);
    }
}
