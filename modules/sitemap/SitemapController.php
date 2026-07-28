<?php
require_once BASE_PATH . '/core/ControllerPublico.php';
require_once BASE_PATH . '/modules/avisos/AvisoModel.php';
require_once BASE_PATH . '/modules/eventos/EventoModel.php';
require_once BASE_PATH . '/modules/cursos/CursoModel.php';
require_once BASE_PATH . '/modules/pastorales/PastoralModel.php';
require_once BASE_PATH . '/modules/sacramentos/SacramentoModel.php';
require_once BASE_PATH . '/modules/paginas/PaginaModel.php';

/**
 * SitemapController — genera sitemap.xml desde la base de datos en cada
 * visita, sin escribir un archivo estático. Para el volumen de contenido de
 * una parroquia (decenas o pocos cientos de avisos y eventos en años) esto es
 * más simple que regenerar un archivo cada vez que se publica algo, y
 * noCache() ya deja una caché HTTP de 5 minutos para no repetir el trabajo en
 * cada rastreo.
 */
class SitemapController extends ControllerPublico
{
    public function index(): void
    {
        $this->noCache();

        $urls = array_merge(
            $this->fijas(),
            $this->sacramentos(),
            $this->pastorales(),
            $this->cursos(),
            $this->avisos(),
            $this->eventos(),
            $this->paginas(),
        );

        header('Content-Type: application/xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $url) {
            echo "  <url>\n";
            echo '    <loc>' . e($url['loc']) . "</loc>\n";
            if (!empty($url['lastmod'])) {
                echo '    <lastmod>' . e($url['lastmod']) . "</lastmod>\n";
            }
            echo '    <changefreq>' . e($url['changefreq']) . "</changefreq>\n";
            echo '    <priority>' . e($url['priority']) . "</priority>\n";
            echo "  </url>\n";
        }
        echo '</urlset>';
        exit;
    }

    // ── Secciones fijas del sitio ───────────────────────────────────────

    private function fijas(): array
    {
        return [
            $this->url(url_publica('inicio'), 'weekly', '1.0'),
            $this->url(url_publica('nosotros'), 'monthly', '0.6'),
            $this->url(url_publica('horarios'), 'weekly', '0.8'),
            $this->url(url_publica('sacramentos'), 'monthly', '0.6'),
            $this->url(url_publica('pastorales'), 'monthly', '0.6'),
            $this->url(url_publica('cursos'), 'weekly', '0.7'),
            $this->url(url_publica('avisos'), 'daily', '0.7'),
            $this->url(url_publica('eventos'), 'weekly', '0.7'),
            $this->url(url_publica('galeria'), 'monthly', '0.4'),
            $this->url(url_publica('contacto'), 'yearly', '0.5'),
        ];
    }

    // ── Contenido dinámico ──────────────────────────────────────────────

    private function sacramentos(): array
    {
        $urls = [];
        foreach ((new SacramentoModel())->todos() as $s) {
            if ($s['activo']) {
                $urls[] = $this->url(url_publica('sacramentos', ['slug' => $s['slug']]), 'yearly', '0.5');
            }
        }
        return $urls;
    }

    private function pastorales(): array
    {
        $urls = [];
        foreach ((new PastoralModel())->activas() as $p) {
            $urls[] = $this->url(
                url_publica('pastorales', ['slug' => $p['slug']]),
                'monthly',
                '0.5',
                $p['created_at']
            );
        }
        return $urls;
    }

    private function cursos(): array
    {
        $urls = [];
        foreach ((new CursoModel())->publicados() as $c) {
            $urls[] = $this->url(
                url_publica('cursos', ['slug' => $c['slug']]),
                'weekly',
                '0.5',
                $c['created_at']
            );
        }
        return $urls;
    }

    private function avisos(): array
    {
        $urls = [];
        foreach ((new AvisoModel())->paraSitemap() as $a) {
            $urls[] = $this->url(url_publica('avisos', ['slug' => $a['slug']]), 'monthly', '0.5', $a['modificado']);
        }
        return $urls;
    }

    private function eventos(): array
    {
        $urls = [];
        foreach ((new EventoModel())->paraSitemap() as $ev) {
            $urls[] = $this->url(url_publica('eventos', ['slug' => $ev['slug']]), 'monthly', '0.5', $ev['modificado']);
        }
        return $urls;
    }

    /** Páginas libres publicadas: el aviso de privacidad y las que se creen desde el panel. */
    private function paginas(): array
    {
        $urls = [];
        foreach ((new PaginaModel())->todas() as $p) {
            if ($p['publicada']) {
                $urls[] = $this->url(
                    url_publica('pagina', ['slug' => $p['slug']]),
                    'yearly',
                    '0.4',
                    $p['updated_at'] ?? $p['created_at']
                );
            }
        }
        return $urls;
    }

    // ── Interno ─────────────────────────────────────────────────────────

    private function url(string $rutaRelativa, string $changefreq, string $priority, ?string $fecha = null): array
    {
        return [
            'loc'         => url_absoluta($rutaRelativa),
            'lastmod'     => $fecha ? date('Y-m-d', strtotime($fecha)) : null,
            'changefreq'  => $changefreq,
            'priority'    => $priority,
        ];
    }
}
