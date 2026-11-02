<?php
require_once BASE_PATH . '/core/Model.php';

/**
 * MenuModel — El menú del sitio (tabla menu_sitio).
 *
 * Un renglón por entrada del menú entre Inicio y Contacto: una sección fija
 * (clave = el módulo público) o una página libre (pagina_id). Cada uno guarda
 * su orden, si se muestra y, si se quiere, un nombre distinto del de siempre.
 *
 * El sitio público NO lee este modelo: lee menu_del_sitio() en
 * core/helpers.php, porque el menú se dibuja en todas las páginas y no puede
 * depender de que este módulo esté cargado.
 *
 * En las páginas, «se muestra» vive en dos lugares que se mantienen iguales:
 * menu_sitio.visible (lo que lee el menú) y paginas.en_menu (la palomita del
 * formulario de la página). Las dos pantallas escriben las dos.
 */
class MenuModel extends Model
{
    /** Separación entre un orden y el siguiente: deja hueco para insertar a mano. */
    private const PASO = 10;

    /**
     * Todas las entradas posibles, en el orden del menú, para la pantalla del
     * panel: las ocho secciones fijas y TODAS las páginas, estén o no en el
     * menú. Las que aún no tienen renglón van al final, apagadas.
     *
     * Cada entrada: llave ('s:horarios' / 'p:3'), tipo, nombre (el de siempre),
     * etiqueta (el personalizado o ''), visible, y para páginas su slug y si
     * está publicada.
     */
    public function entradas(): array
    {
        $filas = $this->fetchAll('SELECT * FROM menu_sitio ORDER BY orden, id');
        $porClave  = [];
        $porPagina = [];
        foreach ($filas as $fila) {
            if ($fila['clave'] !== null) {
                $porClave[$fila['clave']] = $fila;
            } else {
                $porPagina[(int) $fila['pagina_id']] = $fila;
            }
        }

        $entradas = [];
        $sinRenglon = 100000;   // detrás de todo lo que ya tiene orden

        foreach (secciones_del_menu() as $clave => $nombre) {
            $fila = $porClave[$clave] ?? null;
            $entradas[] = [
                'llave'     => 's:' . $clave,
                'tipo'      => 'seccion',
                'nombre'    => $nombre,
                'etiqueta'  => (string) ($fila['etiqueta'] ?? ''),
                'visible'   => $fila ? (int) $fila['visible'] : 1,
                'orden'     => $fila ? (int) $fila['orden'] : $sinRenglon++,
                'publicada' => 1,
                'slug'      => null,
                'modulo'    => $clave,
            ];
        }

        foreach ($this->fetchAll('SELECT id, slug, titulo, publicada, en_menu FROM paginas ORDER BY orden, titulo') as $pagina) {
            $fila = $porPagina[(int) $pagina['id']] ?? null;
            $entradas[] = [
                'llave'     => 'p:' . $pagina['id'],
                'tipo'      => 'pagina',
                'nombre'    => $pagina['titulo'],
                'etiqueta'  => (string) ($fila['etiqueta'] ?? ''),
                'visible'   => $fila ? (int) $fila['visible'] : (int) $pagina['en_menu'],
                'orden'     => $fila ? (int) $fila['orden'] : $sinRenglon++,
                'publicada' => (int) $pagina['publicada'],
                'slug'      => $pagina['slug'],
                'modulo'    => null,
            ];
        }

        usort($entradas, static fn (array $a, array $b): int => $a['orden'] <=> $b['orden']);
        return $entradas;
    }

    /**
     * Guarda el menú completo tal como quedó en la pantalla.
     *
     * @param string[]              $orden     llaves ('s:…' / 'p:…') en el orden nuevo
     * @param array<string, bool>   $visibles  llave => se muestra
     * @param array<string, string> $etiquetas llave => nombre personalizado ('' = el de siempre)
     */
    public function guardar(array $orden, array $visibles, array $etiquetas): void
    {
        $secciones = secciones_del_menu();
        $paginas   = array_map('intval', array_column(
            $this->fetchAll('SELECT id FROM paginas'), 'id'
        ));

        $this->beginTransaction();
        try {
            $posicion = 0;
            foreach ($orden as $llave) {
                [$tipo, $valor] = array_pad(explode(':', (string) $llave, 2), 2, '');

                if ($tipo === 's' && isset($secciones[$valor])) {
                    $clave = $valor;
                    $paginaId = null;
                    $nombre = $secciones[$valor];
                } elseif ($tipo === 'p' && in_array((int) $valor, $paginas, true)) {
                    $clave = null;
                    $paginaId = (int) $valor;
                    $nombre = null;
                } else {
                    continue;   // llave desconocida o página borrada mientras tanto
                }

                $posicion += self::PASO;
                $visible  = !empty($visibles[$llave]) ? 1 : 0;
                $etiqueta = trim(mb_substr((string) ($etiquetas[$llave] ?? ''), 0, 60));
                // Escribir el nombre de siempre es lo mismo que no cambiarlo.
                if ($etiqueta === '' || $etiqueta === $nombre) {
                    $etiqueta = null;
                }

                $this->execute(
                    'INSERT INTO menu_sitio (clave, pagina_id, etiqueta, visible, orden, updated_at)
                     VALUES (:clave, :pagina, :etiqueta, :visible, :orden, NOW())
                     ON DUPLICATE KEY UPDATE etiqueta = VALUES(etiqueta), visible = VALUES(visible),
                                             orden = VALUES(orden), updated_at = NOW()',
                    [':clave' => $clave, ':pagina' => $paginaId, ':etiqueta' => $etiqueta,
                     ':visible' => $visible, ':orden' => $posicion]
                );

                if ($paginaId !== null) {
                    $this->execute('UPDATE paginas SET en_menu = :v WHERE id = :id',
                                   [':v' => $visible, ':id' => $paginaId]);
                }
            }
            $this->commit();
        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * La palomita «Mostrar en el menú» del formulario de una página. Si la
     * página aún no tiene renglón y se marca, entra al final del menú; desde
     * Contenido → Menú del sitio se acomoda después.
     */
    public function sincronizarPagina(int $paginaId, bool $enMenu): void
    {
        $existe = $this->fetchColumn('SELECT id FROM menu_sitio WHERE pagina_id = :id', [':id' => $paginaId]);

        if ($existe) {
            $this->execute('UPDATE menu_sitio SET visible = :v, updated_at = NOW() WHERE pagina_id = :id',
                           [':v' => $enMenu ? 1 : 0, ':id' => $paginaId]);
        } elseif ($enMenu) {
            $ultimo = (int) $this->fetchColumn('SELECT COALESCE(MAX(orden), 0) FROM menu_sitio');
            $this->execute(
                'INSERT INTO menu_sitio (pagina_id, visible, orden, updated_at) VALUES (:id, 1, :orden, NOW())',
                [':id' => $paginaId, ':orden' => $ultimo + self::PASO]
            );
        }
    }
}
