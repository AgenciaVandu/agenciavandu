<?php

namespace App\Support;

/**
 * Lee la primera hoja de un Excel (.xlsx) o un CSV y regresa sus filas como arreglos de texto.
 * Sin dependencias: el .xlsx es un zip con XML adentro.
 */
class LectorHoja
{
    public const MAX_FILAS = 10000;

    /** @return array<int, array<int, string>> filas (sin las vacías) */
    public static function leer(string $ruta, string $extension): array
    {
        $extension = strtolower($extension);
        $filas = match (true) {
            $extension === 'xlsx' => self::xlsx($ruta),
            in_array($extension, ['csv', 'txt'], true) => self::csv($ruta),
            $extension === 'xls' => throw new \RuntimeException('Ese archivo es de Excel antiguo (.xls). Ábrelo en Excel y guárdalo como .xlsx o .csv.'),
            default => throw new \RuntimeException('Sube un archivo .xlsx o .csv.'),
        };

        // Fuera filas vacías y espacios de más
        $limpias = [];
        foreach ($filas as $f) {
            $f = array_map(fn ($v) => trim(preg_replace('/\s+/u', ' ', (string) $v)), $f);
            if (implode('', $f) === '') continue;
            $limpias[] = $f;
            if (count($limpias) > self::MAX_FILAS) throw new \RuntimeException('El archivo tiene más de ' . number_format(self::MAX_FILAS) . ' filas. Divídelo en partes.');
        }
        // Todas del mismo ancho
        $ancho = $limpias ? max(array_map('count', $limpias)) : 0;
        return array_map(fn ($f) => array_pad($f, $ancho, ''), $limpias);
    }

    private static function xlsx(string $ruta): array
    {
        if (! class_exists(\ZipArchive::class)) throw new \RuntimeException('El servidor no puede abrir archivos de Excel. Guárdalo como .csv y vuelve a intentarlo.');
        $zip = new \ZipArchive();
        if ($zip->open($ruta) !== true) throw new \RuntimeException('No pudimos abrir el Excel. ¿Está dañado o protegido con contraseña?');

        try {
            $compartidos = [];
            if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
                $sst = self::xml($xml);
                foreach ($sst->si as $si) {
                    // Texto simple o con formato (varios <r><t>)
                    $t = isset($si->t) ? (string) $si->t : '';
                    foreach ($si->r ?? [] as $r) $t .= (string) $r->t;
                    $compartidos[] = $t;
                }
            }

            $hoja = self::primeraHoja($zip);
            $xml = $zip->getFromName($hoja);
            if ($xml === false) throw new \RuntimeException('El Excel no tiene hojas con datos.');
            $ws = self::xml($xml);

            $filas = [];
            foreach ($ws->sheetData->row ?? [] as $row) {
                $fila = [];
                foreach ($row->c as $c) {
                    $col = self::columna((string) $c['r']) ?? count($fila);
                    $tipo = (string) $c['t'];
                    $v = match ($tipo) {
                        's'         => $compartidos[(int) $c->v] ?? '',
                        'inlineStr' => (string) ($c->is->t ?? ''),
                        'b'         => ((string) $c->v) === '1' ? 'Sí' : 'No',
                        default     => self::numeroLimpio((string) $c->v),
                    };
                    $fila[$col] = $v;
                }
                if ($fila) {
                    $max = max(array_keys($fila));
                    $filas[] = array_map(fn ($i) => $fila[$i] ?? '', range(0, $max));
                }
                if (count($filas) > self::MAX_FILAS + 50) break;
            }
            return $filas;
        } finally {
            $zip->close();
        }
    }

    /** Ruta de la primera hoja según el libro (no siempre es sheet1.xml) */
    private static function primeraHoja(\ZipArchive $zip): string
    {
        $libro = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($libro !== false && $rels !== false) {
            $wb = self::xml($libro);
            $sheet = $wb->sheets->sheet[0] ?? null;
            $rid = $sheet ? (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] : null;
            foreach (self::xml($rels)->Relationship as $r) {
                if ((string) $r['Id'] === $rid) {
                    $t = ltrim((string) $r['Target'], '/');
                    return str_starts_with($t, 'xl/') ? $t : 'xl/' . $t;
                }
            }
        }
        return 'xl/worksheets/sheet1.xml';
    }

    private static function xml(string $texto): \SimpleXMLElement
    {
        $x = @simplexml_load_string($texto, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
        if (! $x) throw new \RuntimeException('No pudimos leer el Excel. Guárdalo de nuevo o súbelo como .csv.');
        return $x;
    }

    /** "C12" → 2 */
    private static function columna(string $ref): ?int
    {
        if (! preg_match('/^([A-Z]+)/', $ref, $m)) return null;
        $n = 0;
        foreach (str_split($m[1]) as $l) $n = $n * 26 + (ord($l) - 64);
        return $n - 1;
    }

    /** Excel guarda 12.5 como "12.4999999999" a veces; y enteros como "3.0" */
    private static function numeroLimpio(string $v): string
    {
        if ($v === '' || ! is_numeric($v)) return $v;
        $f = (float) $v;
        if (abs($f) >= 1e15) return $v;   // códigos largos: tal cual
        return rtrim(rtrim(number_format(round($f, 6), 6, '.', ''), '0'), '.');
    }

    private static function csv(string $ruta): array
    {
        $texto = (string) file_get_contents($ruta);
        $texto = preg_replace('/^\xEF\xBB\xBF/', '', $texto);
        if (! mb_check_encoding($texto, 'UTF-8')) $texto = mb_convert_encoding($texto, 'UTF-8', 'Windows-1252');

        // Separador: el que más aparece en una misma línea de las primeras (la primera puede ser un título)
        $lineas = array_slice(explode("\n", $texto), 0, 10);
        $sep = collect([',', ';', "\t", '|'])->sortByDesc(fn ($s) => max(array_map(fn ($l) => substr_count($l, $s), $lineas ?: [''])))->first();

        $h = fopen('php://temp', 'r+');
        fwrite($h, $texto);
        rewind($h);
        $filas = [];
        while (($f = fgetcsv($h, 0, $sep, '"', '')) !== false) {
            if ($f === [null]) continue;
            $filas[] = array_map(fn ($v) => (string) $v, $f);
            if (count($filas) > self::MAX_FILAS + 50) break;
        }
        fclose($h);
        return $filas;
    }
}
