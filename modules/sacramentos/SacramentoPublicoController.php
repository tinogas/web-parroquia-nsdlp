<?php
require_once BASE_PATH . '/core/ControllerPublico.php';
require_once BASE_PATH . '/modules/sacramentos/SacramentoModel.php';
require_once BASE_PATH . '/modules/bloques/BloqueModel.php';

/**
 * SacramentoPublicoController — Catálogo puramente informativo: requisitos,
 * documentos y aportación de cada sacramento. Sin formulario de solicitud
 * (issue #3); para llevarlo a cabo, la persona se acerca a la oficina
 * parroquial.
 */
class SacramentoPublicoController extends ControllerPublico
{
    public function index(): void
    {
        $this->render('sacramentos/publico/index', [
            'metaTitulo'      => 'Sacramentos',
            'metaDescripcion' => 'Requisitos y documentos para el bautizo, la primera comunión, la confirmación, el matrimonio y más.',
            'urlCanonica'     => url_publica('sacramentos'),
            'sacramentos'     => (new SacramentoModel())->activos(),
            'bloques'         => (new BloqueModel())->porZona('sacramentos'),
        ]);
    }

    public function ver(): void
    {
        $sacramento = $this->sacramentoDelSlug();
        if (!$sacramento) {
            $this->noEncontrado();
            return;
        }

        $this->render('sacramentos/publico/detalle', [
            'metaTitulo'      => $sacramento['nombre'],
            'metaDescripcion' => resumen($sacramento['descripcion'] ?: $sacramento['requisitos']),
            'ogImagen'        => $sacramento['imagen'] ?: null,
            'urlCanonica'     => url_publica('sacramentos', ['slug' => $sacramento['slug']]),
            'sacramento'      => $sacramento,
        ]);
    }

    private function sacramentoDelSlug(): ?array
    {
        $slug = strtolower(trim((string) ($_GET['slug'] ?? '')));
        if (!preg_match('/^[a-z0-9\-]{1,60}$/', $slug)) {
            return null;
        }
        return (new SacramentoModel())->porSlugActivo($slug);
    }
}
