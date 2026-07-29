<?php
/**
 * Slug — Convierte un título en un fragmento de URL legible.
 *
 * «Misa de Nochebuena 2026» → «misa-de-nochebuena-2026»
 *
 * Regla del proyecto: el slug NO se regenera al editar el título. Cambiarlo
 * rompería los enlaces que ya se compartieron por WhatsApp o en redes, que es
 * como circula la mayor parte de lo que publica una parroquia. Si de verdad hay
 * que cambiarlo, se edita a mano.
 */
class Slug
{
    /** Palabras que no aportan nada a una URL. */
    private const VACIAS = ['de', 'del', 'la', 'las', 'el', 'los', 'y', 'en', 'a', 'al', 'para'];

    public static function generar(string $texto, int $maximo = 90): string
    {
        $texto = self::sinAcentos(trim($texto));
        $texto = mb_strtolower($texto, 'UTF-8');

        // Todo lo que no sea letra o número se vuelve separador.
        $texto = preg_replace('/[^a-z0-9]+/', '-', $texto) ?? '';
        $texto = trim($texto, '-');

        if ($texto === '') {
            return 'sin-titulo';
        }

        // Si es largo, se recortan las palabras sin peso; si sigue siendo largo,
        // se corta por la última palabra completa.
        if (strlen($texto) > $maximo) {
            $partes = array_filter(
                explode('-', $texto),
                static fn (string $p): bool => !in_array($p, self::VACIAS, true)
            );
            $texto = implode('-', $partes);
        }

        if (strlen($texto) > $maximo) {
            $texto = substr($texto, 0, $maximo);
            $corte = strrpos($texto, '-');
            if ($corte !== false && $corte > 20) {
                $texto = substr($texto, 0, $corte);
            }
        }

        return trim($texto, '-');
    }

    /**
     * Igual que generar(), pero comprobando que no exista ya en la tabla.
     * Si choca, añade un sufijo numérico: «misa-de-navidad-2».
     *
     * @param string   $tabla      nombre de tabla; debe venir del código, nunca del usuario
     * @param int|null $excluirId  id del registro que se está editando
     */
    public static function unico(string $texto, string $tabla, ?int $excluirId = null, string $columna = 'slug'): string
    {
        // El nombre de tabla y columna no puede ir en un marcador de posición,
        // así que se restringe a identificadores válidos por si alguna vez llega
        // desde un sitio inesperado.
        $tabla   = preg_replace('/[^a-z0-9_]/', '', $tabla) ?? '';
        $columna = preg_replace('/[^a-z0-9_]/', '', $columna) ?? 'slug';
        if ($tabla === '') {
            throw new InvalidArgumentException('Tabla inválida para generar el slug.');
        }

        $base = self::generar($texto);
        $slug = $base;
        $db   = Database::getInstance();

        $sql = "SELECT COUNT(*) FROM {$tabla} WHERE {$columna} = :slug"
             . ($excluirId !== null ? ' AND id <> :id' : '');

        for ($intento = 2; $intento < 100; $intento++) {
            $params = [':slug' => $slug];
            if ($excluirId !== null) {
                $params[':id'] = $excluirId;
            }

            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            if ((int) $stmt->fetchColumn() === 0) {
                return $slug;
            }
            $slug = $base . '-' . $intento;
        }

        // Salida de emergencia: es preferible un slug feo a un bucle infinito.
        return $base . '-' . bin2hex(random_bytes(3));
    }

    /** Sustituye los caracteres acentuados por su equivalente sin acento. */
    private static function sinAcentos(string $texto): string
    {
        $mapa = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o',
            'â' => 'a', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u',
            'ñ' => 'n', 'ç' => 'c',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U',
            'Ñ' => 'N', 'Ç' => 'C',
            'º' => '', 'ª' => '', '°' => '',
        ];
        return strtr($texto, $mapa);
    }
}
