<?php
/**
 * Config — Datos globales de la parroquia, guardados como pares clave/valor.
 *
 * Los lee una sola vez por petición y los deja en memoria: el layout público
 * necesita el teléfono, la dirección y las redes sociales en cada página, y no
 * tiene sentido consultarlos varias veces.
 *
 * La edición de estos valores vive en el módulo de administración
 * 'configuracion'. Aquí solo se leen.
 */
class Config
{
    private static ?array $valores = null;

    public static function todo(): array
    {
        if (self::$valores === null) {
            self::$valores = [];
            try {
                $filas = Database::getInstance()
                    ->query('SELECT clave, valor FROM configuracion')
                    ->fetchAll();
                foreach ($filas as $fila) {
                    self::$valores[$fila['clave']] = $fila['valor'];
                }
            } catch (PDOException $e) {
                // Sin base de datos configurada todavía: el sitio debe seguir
                // respondiendo con los valores por omisión en vez de romperse.
            }
        }
        return self::$valores;
    }

    public static function get(string $clave, string $default = ''): string
    {
        $valor = self::todo()[$clave] ?? '';
        return $valor !== '' ? $valor : $default;
    }

    public static function tiene(string $clave): bool
    {
        return !empty(self::todo()[$clave]);
    }

    /** Fuerza la relectura tras guardar cambios en el panel. */
    public static function limpiar(): void
    {
        self::$valores = null;
    }
}
