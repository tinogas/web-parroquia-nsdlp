<?php
/**
 * Hoja de Excel (.xlsx) de una sola pestaña, sin librerías.
 *
 * Un .xlsx es un zip con unos cuantos XML. El proyecto no usa Composer y el
 * PHP de XAMPP trae ZipArchive desactivado (;extension=zip en php.ini), así
 * que el zip se arma aquí a mano con gzdeflate(), que sí viene siempre. Es lo
 * mínimo que Excel, LibreOffice y Google Sheets abren sin quejarse: encabezado
 * en negrita y fijo, autofiltro, anchos de columna, y fechas y horas como
 * valores de verdad —se ordenan y se filtran por fecha, no como texto—.
 *
 * Por qué no CSV: el CSV no lleva formato, Excel en español espera «;» y no
 * «,», y las fechas llegan como texto. Las demás exportaciones del panel
 * (inscripciones, auditoría, rutas MESC) siguen en CSV; esta es la primera que
 * se pidió explícitamente «a Excel».
 *
 * Uso:
 *     $hoja = new Xlsx('Eventos', [
 *         ['Fecha', 12, 'fecha'],     // [título, ancho en caracteres, tipo]
 *         ['Evento', 40, 'texto'],
 *     ]);
 *     $hoja->fila(['2026-11-04', 'Misa']);
 *     $hoja->descargar('eventos.xlsx');
 *
 * Tipos: 'texto', 'numero', 'fecha' ('Y-m-d…') y 'hora' ('H:i…'). Un valor
 * null o '' deja la celda vacía; una fecha u hora que no se entiende se
 * escribe como texto, tal cual.
 */
class Xlsx
{
    // Índices de cellXfs en estilos(): el orden importa.
    private const ESTILO_NORMAL     = 0;
    private const ESTILO_ENCABEZADO = 1;
    private const ESTILO_FECHA      = 2;
    private const ESTILO_HORA       = 3;

    private string $nombreHoja;
    private array $columnas;
    private array $filas = [];

    public function __construct(string $nombreHoja, array $columnas)
    {
        // Excel no admite en el nombre de una pestaña : \ / ? * [ ] y lo corta en 31.
        $this->nombreHoja = mb_substr(preg_replace('~[:\\\\/?*\[\]]~u', ' ', $nombreHoja), 0, 31) ?: 'Hoja1';
        $this->columnas   = array_values($columnas);
    }

    public function fila(array $valores): void
    {
        $this->filas[] = array_values($valores);
    }

    /** Envía el archivo al navegador como descarga y termina. */
    public function descargar(string $archivo): void
    {
        $contenido = $this->contenido();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $archivo) . '"');
        header('Content-Length: ' . strlen($contenido));
        header('Cache-Control: no-store, max-age=0');
        echo $contenido;
        exit;
    }

    /** El .xlsx como cadena binaria. */
    public function contenido(): string
    {
        return $this->zip([
            '[Content_Types].xml'        => $this->tiposDeContenido(),
            '_rels/.rels'                => $this->relacionesRaiz(),
            'xl/workbook.xml'            => $this->libro(),
            'xl/_rels/workbook.xml.rels' => $this->relacionesLibro(),
            'xl/styles.xml'              => $this->estilos(),
            'xl/worksheets/sheet1.xml'   => $this->hoja(),
        ]);
    }

    // ── Las piezas XML ──────────────────────────────────────────────────────

    private const XML = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    private const NS  = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const NSR = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private function tiposDeContenido(): string
    {
        return self::XML
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private function relacionesRaiz(): string
    {
        return self::XML
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="' . self::NSR . '/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function libro(): string
    {
        $nombre = $this->x($this->nombreHoja);
        // El autofiltro necesita su nombre definido para que Excel lo respete al abrir.
        $rango  = "'" . str_replace("'", "''", $nombre) . "'!\$A\$1:\$"
                . $this->letra(count($this->columnas) - 1) . '$' . (count($this->filas) + 1);
        return self::XML
            . '<workbook xmlns="' . self::NS . '" xmlns:r="' . self::NSR . '">'
            . '<sheets><sheet name="' . $nombre . '" sheetId="1" r:id="rId1"/></sheets>'
            . '<definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">'
            . $rango . '</definedName></definedNames>'
            . '</workbook>';
    }

    private function relacionesLibro(): string
    {
        return self::XML
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="' . self::NSR . '/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="' . self::NSR . '/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function estilos(): string
    {
        return self::XML
            . '<styleSheet xmlns="' . self::NS . '">'
            . '<numFmts count="2">'
            . '<numFmt numFmtId="164" formatCode="dd/mm/yyyy"/>'
            . '<numFmt numFmtId="165" formatCode="hh:mm"/>'
            . '</numFmts>'
            . '<fonts count="2">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="3">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF1E4D8B"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="4">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"><alignment vertical="top"/></xf>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"><alignment horizontal="left" vertical="top"/></xf>'
            . '<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"><alignment horizontal="left" vertical="top"/></xf>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private function hoja(): string
    {
        $ultima = $this->letra(count($this->columnas) - 1);
        $total  = count($this->filas) + 1;

        $xml = self::XML . '<worksheet xmlns="' . self::NS . '" xmlns:r="' . self::NSR . '">'
             . '<sheetViews><sheetView workbookViewId="0">'
             . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
             . '</sheetView></sheetViews>'
             . '<cols>';
        foreach ($this->columnas as $i => [, $ancho]) {
            $xml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . (float) $ancho . '" customWidth="1"/>';
        }
        $xml .= '</cols><sheetData>';

        $xml .= '<row r="1" ht="20" customHeight="1">';
        foreach ($this->columnas as $i => [$titulo]) {
            $xml .= $this->celdaTexto($this->letra($i) . '1', (string) $titulo, self::ESTILO_ENCABEZADO);
        }
        $xml .= '</row>';

        foreach ($this->filas as $n => $valores) {
            $r = $n + 2;
            $xml .= '<row r="' . $r . '">';
            foreach ($this->columnas as $i => $columna) {
                $xml .= $this->celda($this->letra($i) . $r, $valores[$i] ?? null, $columna[2] ?? 'texto');
            }
            $xml .= '</row>';
        }

        return $xml . '</sheetData>'
             . '<autoFilter ref="A1:' . $ultima . $total . '"/>'
             . '<pageMargins left="0.5" right="0.5" top="0.6" bottom="0.6" header="0.3" footer="0.3"/>'
             . '<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
             . '</worksheet>';
    }

    private function celda(string $ref, $valor, string $tipo): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }
        if ($tipo === 'numero' && is_numeric($valor)) {
            return '<c r="' . $ref . '"><v>' . (0 + $valor) . '</v></c>';
        }
        if ($tipo === 'fecha' && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', (string) $valor, $m)
            && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            // Días desde el 30/12/1899, que es como Excel cuenta las fechas.
            $serial = (int) (gmmktime(0, 0, 0, (int) $m[2], (int) $m[3], (int) $m[1]) / 86400) + 25569;
            return '<c r="' . $ref . '" s="' . self::ESTILO_FECHA . '"><v>' . $serial . '</v></c>';
        }
        if ($tipo === 'hora' && preg_match('/^(\d{1,2}):(\d{2})/', (string) $valor, $m)
            && (int) $m[1] < 24 && (int) $m[2] < 60) {
            // La hora es la fracción del día.
            $fraccion = round(((int) $m[1] * 60 + (int) $m[2]) / 1440, 10);
            return '<c r="' . $ref . '" s="' . self::ESTILO_HORA . '"><v>' . $fraccion . '</v></c>';
        }
        return $this->celdaTexto($ref, (string) $valor, self::ESTILO_NORMAL);
    }

    private function celdaTexto(string $ref, string $texto, int $estilo): string
    {
        return '<c r="' . $ref . '" t="inlineStr"' . ($estilo ? ' s="' . $estilo . '"' : '') . '>'
             . '<is><t xml:space="preserve">' . $this->x($texto) . '</t></is></c>';
    }

    /** Escapa para XML y quita los caracteres de control que el XML no admite. */
    private function x(string $texto): string
    {
        $texto = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $texto) ?? '';
        // Excel no acepta más de 32 767 caracteres en una celda.
        if (mb_strlen($texto) > 32767) {
            $texto = mb_substr($texto, 0, 32767);
        }
        return htmlspecialchars($texto, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** 0 → A, 25 → Z, 26 → AA. */
    private function letra(int $indice): string
    {
        $letra = '';
        for ($n = $indice + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $letra = chr(65 + ($n - 1) % 26) . $letra;
        }
        return $letra;
    }

    // ── El zip ──────────────────────────────────────────────────────────────

    /** Zip con deflate, sin ZipArchive: [ruta => contenido] → binario. */
    private function zip(array $archivos): string
    {
        $cuerpo  = '';
        $central = '';
        $ahora   = getdate();
        $hora    = ($ahora['hours'] << 11) | ($ahora['minutes'] << 5) | intdiv($ahora['seconds'], 2);
        $fecha   = (($ahora['year'] - 1980) << 9) | ($ahora['mon'] << 5) | $ahora['mday'];

        foreach ($archivos as $ruta => $datos) {
            $comprimido = gzdeflate($datos, 6);
            $crc        = crc32($datos);
            $desplaz    = strlen($cuerpo);
            // versión 20, bandera 0x0800 (nombres en UTF-8), método 8 (deflate)
            $comun = pack('vvvvvVVVv', 20, 0x0800, 8, $hora, $fecha, $crc,
                          strlen($comprimido), strlen($datos), strlen($ruta));

            $cuerpo  .= "PK\x03\x04" . $comun . pack('v', 0) . $ruta . $comprimido;
            $central .= "PK\x01\x02" . pack('v', 20) . $comun
                      . pack('vvvvVV', 0, 0, 0, 0, 0, $desplaz) . $ruta;
        }

        return $cuerpo . $central
             . "PK\x05\x06" . pack('vvvvVVv', 0, 0, count($archivos), count($archivos),
                                   strlen($central), strlen($cuerpo), 0);
    }
}
