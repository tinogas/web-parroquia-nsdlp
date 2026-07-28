<?php
require_once BASE_PATH . '/core/ControllerPublico.php';
require_once BASE_PATH . '/modules/pastorales/PastoralModel.php';
require_once BASE_PATH . '/modules/bloques/BloqueModel.php';

class PastoralPublicoController extends ControllerPublico
{
    public function index(): void
    {
        $this->render('pastorales/publico/index', [
            'metaTitulo'      => 'Pastorales',
            'metaDescripcion' => 'Pastorales y grupos de la Parroquia Nuestra Señora de la Paz: coro, catequesis, caridad, jóvenes y más.',
            'urlCanonica'     => url_publica('pastorales'),
            'pastorales'      => (new PastoralModel())->activas(),
            'bloques'         => (new BloqueModel())->porZona('pastorales'),
        ]);
    }

    public function ver(): void
    {
        $slug     = $this->slug();
        $modelo   = new PastoralModel();
        $pastoral = $slug !== '' ? $modelo->porSlugActiva($slug) : null;

        if (!$pastoral) {
            $this->noEncontrado();
            return;
        }

        $this->render('pastorales/publico/detalle', [
            'metaTitulo'      => $pastoral['nombre'],
            'metaDescripcion' => $pastoral['descripcion_corta'] ?: resumen($pastoral['descripcion']),
            'ogImagen'        => $pastoral['imagen'] ?: null,
            'urlCanonica'     => url_publica('pastorales', ['slug' => $pastoral['slug']]),
            'pastoral'        => $pastoral,
            'actividades'     => $modelo->actividadesActivas((int) $pastoral['id']),
        ]);
    }

    private function slug(): string
    {
        $slug = strtolower(trim((string) ($_GET['slug'] ?? '')));
        return preg_match('/^[a-z0-9\-]{1,80}$/', $slug) ? $slug : '';
    }
}
