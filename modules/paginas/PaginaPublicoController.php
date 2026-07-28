<?php
require_once BASE_PATH . '/core/ControllerPublico.php';
require_once BASE_PATH . '/modules/paginas/PaginaModel.php';

/**
 * Muestra una página libre por su dirección: /aviso-de-privacidad
 *
 * El Router llega aquí de dos maneras: por la ruta explícita
 * /pagina/<slug>, y como último recurso cuando el primer segmento de la
 * dirección no corresponde a ninguna sección conocida.
 */
class PaginaPublicoController extends ControllerPublico
{
    public function ver(): void
    {
        $slug = $this->slug();
        if ($slug === '') {
            $this->noEncontrado();
            return;
        }

        $pagina = (new PaginaModel())->porSlugPublicada($slug);
        if (!$pagina) {
            $this->noEncontrado();
            return;
        }

        $this->render('paginas/publico/detalle', [
            'pagina'          => $pagina,
            'metaTitulo'      => $pagina['titulo'],
            'metaDescripcion' => $pagina['meta_descripcion'] ?? '',
            'urlCanonica'     => url_publica('pagina', ['slug' => $pagina['slug']]),
        ]);
    }

    /** La acción por omisión sin slug no tiene sentido aquí. */
    public function index(): void
    {
        $this->ver();
    }

    private function slug(): string
    {
        $slug = strtolower(trim((string) ($_GET['slug'] ?? '')));
        return preg_match('/^[a-z0-9\-]{1,120}$/', $slug) ? $slug : '';
    }
}
