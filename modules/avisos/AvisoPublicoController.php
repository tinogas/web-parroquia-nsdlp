<?php
require_once BASE_PATH . '/core/ControllerPublico.php';
require_once BASE_PATH . '/modules/avisos/AvisoModel.php';

class AvisoPublicoController extends ControllerPublico
{
    public function index(): void
    {
        $pagina  = max(1, $this->getInt('pagina', 1));
        $listado = (new AvisoModel())->publicados($pagina);

        $this->render('avisos/publico/index', [
            'metaTitulo'      => 'Avisos',
            'metaDescripcion' => 'Boletín semanal y noticias de la Parroquia Nuestra Señora de la Paz.',
            'urlCanonica'     => url_publica('avisos'),
            'listado'         => $listado,
        ]);
    }

    public function ver(): void
    {
        $slug  = $this->slug();
        $aviso = $slug !== '' ? (new AvisoModel())->porSlugPublicado($slug) : null;

        if (!$aviso) {
            $this->noEncontrado();
            return;
        }

        (new AvisoModel())->incrementarVistas((int) $aviso['id']);

        $this->render('avisos/publico/detalle', [
            'metaTitulo'      => $aviso['titulo'],
            'metaDescripcion' => $aviso['resumen'] ?: resumen($aviso['contenido']),
            'ogImagen'        => $aviso['imagen'] ?: null,
            'urlCanonica'     => url_publica('avisos', ['slug' => $aviso['slug']]),
            'aviso'           => $aviso,
        ]);
    }

    private function slug(): string
    {
        $slug = strtolower(trim((string) ($_GET['slug'] ?? '')));
        return preg_match('/^[a-z0-9\-]{1,160}$/', $slug) ? $slug : '';
    }
}
