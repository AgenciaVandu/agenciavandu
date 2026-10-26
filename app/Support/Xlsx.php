<?php

namespace App\Support;

/**
 * Excel (.xlsx) sencillo sin librerías: varias hojas, encabezado en negritas y fijo, números con dos decimales.
 * $hojas = ['Gastos' => ['columnas' => ['Fecha', 'Total', …], 'filas' => [[…], …], 'anchos' => [12, 14, …]], …]
 * Los números (int|float) se guardan como número para que el contador pueda sumar; lo demás como texto.
 */
class Xlsx
{
    public static function crear(array $hojas): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);

        $nombres = [];
        foreach (array_keys($hojas) as $n) {
            $limpio = mb_substr(preg_replace('/[\[\]\*\?\/\\\\:]/u', ' ', (string) $n), 0, 31);
            $nombres[] = $limpio !== '' ? $limpio : 'Hoja';
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . implode('', array_map(fn ($i) => '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>', range(1, count($nombres))))
            . '</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'
            . implode('', array_map(fn ($n, $i) => '<sheet name="' . self::e($n) . '" sheetId="' . ($i + 1) . '" r:id="rId' . ($i + 1) . '"/>', $nombres, array_keys($nombres)))
            . '</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . implode('', array_map(fn ($i) => '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>', range(1, count($nombres))))
            . '<Relationship Id="rId' . (count($nombres) + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>');
        // Estilos: 0 normal, 1 encabezado en negritas con fondo, 2 número con dos decimales, 3 negritas con dos decimales
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00"/></numFmts>'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFEEF0F3"/></patternFill></fill></fills>'
            . '<borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs>'
            . '<cellXfs count="4"><xf/><xf fontId="1" fillId="2" applyFont="1" applyFill="1"/><xf numFmtId="164" applyNumberFormat="1"/><xf numFmtId="164" fontId="1" applyNumberFormat="1" applyFont="1"/></cellXfs>'
            . '</styleSheet>');

        $i = 1;
        foreach ($hojas as $h) {
            $zip->addFromString('xl/worksheets/sheet' . $i++ . '.xml', self::hoja($h['columnas'] ?? [], $h['filas'] ?? [], $h['anchos'] ?? [], $h['negritas'] ?? []));
        }
        $zip->close();
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);
        return $bytes;
    }

    private static function hoja(array $columnas, array $filas, array $anchos, array $negritas): string
    {
        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        if ($columnas) $x .= '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
        if ($anchos) {
            $x .= '<cols>';
            foreach (array_values($anchos) as $k => $w) $x .= '<col min="' . ($k + 1) . '" max="' . ($k + 1) . '" width="' . (float) $w . '" customWidth="1"/>';
            $x .= '</cols>';
        }
        $x .= '<sheetData>';
        $r = 1;
        if ($columnas) $x .= self::fila($r++, $columnas, true);
        foreach ($filas as $k => $f) $x .= self::fila($r++, array_values($f), false, in_array($k, $negritas, true));
        return $x . '</sheetData></worksheet>';
    }

    private static function fila(int $r, array $celdas, bool $encabezado, bool $negrita = false): string
    {
        $x = '<row r="' . $r . '">';
        foreach ($celdas as $c => $v) {
            $ref = self::columna($c) . $r;
            if ($v === null || $v === '') { continue; }
            if (! $encabezado && (is_int($v) || is_float($v))) {
                $x .= '<c r="' . $ref . '" s="' . ($negrita ? 3 : 2) . '"><v>' . $v . '</v></c>';
            } else {
                $x .= '<c r="' . $ref . '" t="inlineStr"' . ($encabezado || $negrita ? ' s="1"' : '') . '><is><t xml:space="preserve">' . self::e((string) $v) . '</t></is></c>';
            }
        }
        return $x . '</row>';
    }

    private static function columna(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) $s = chr(65 + ($i - 1) % 26) . $s;
        return $s;
    }

    private static function e(string $s): string
    {
        // Quita caracteres de control que Excel no acepta
        return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
