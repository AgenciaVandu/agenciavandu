<?php

namespace App\Support\Documentos;

use App\Models\Cuenta;
use App\Models\User;
use App\Support\IA\Anthropic;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Lee la Constancia de Situación Fiscal del SAT y regresa RFC, razón social, régimen, código postal, domicilio fiscal y fecha de emisión.
 *  1. El PDF que se descarga del SAT trae texto: se lee directo, exacto y sin costo.
 *  2. Si es una foto o un PDF escaneado, la lee la IA (si el negocio la tiene activa).
 * No guarda nada: llena el formulario y la persona confirma.
 */
class Constancia
{
    /** Palabras del régimen → clave del SAT (c_RegimenFiscal) */
    private const REGIMENES = [
        'simplificado de confianza' => '626', 'actividades empresariales y profesionales' => '612', 'general de ley' => '601',
        'fines no lucrativos' => '603', 'sueldos y salarios' => '605', 'arrendamiento' => '606', 'plataformas tecnologicas' => '625',
        'incorporacion fiscal' => '621', 'sin obligaciones fiscales' => '616', 'agricolas' => '622', 'cooperativas de produccion' => '620',
        'grupos de sociedades' => '623', 'coordinados' => '624', 'demas ingresos' => '608', 'intereses' => '614', 'dividendos' => '611',
        'enajenacion' => '607', 'premios' => '615', 'actividad empresarial' => '612',
    ];

    /** Campos del domicilio fiscal en la constancia → su etiqueta */
    private const DOMICILIO = [
        'tipo_vialidad' => 'Tipo\s+de\s+Vialidad', 'vialidad' => 'Nombre\s+de\s+(?:la\s+)?Vialidad', 'exterior' => 'N[uú]mero\s+Exterior',
        'interior' => 'N[uú]mero\s+Interior', 'colonia' => 'Nombre\s+de\s+la\s+Colonia', 'localidad' => 'Nombre\s+de\s+la\s+Localidad',
        'municipio' => 'Nombre\s+del\s+Municipio\s+o\s+Demarcaci[oó]n\s+Territorial', 'estado' => 'Nombre\s+de\s+la\s+Entidad\s+Federativa',
        'entre' => 'Entre\s+Calle', 'y_calle' => '(?<!Entre\s)(?<![A-Za-zÁÉÍÓÚáéíóú])Y\s+Calle',
    ];

    /**
     * Arma la dirección en una línea, como se escribe en México (con cruzamientos, como en Yucatán):
     * "Calle 21 #104 Int. 3 x 18 y 20, Col. Itzimná, Mérida, Yucatán, C.P. 97100"
     */
    public static function direccion(array $d): ?string
    {
        $t = fn ($k) => isset($d[$k]) && is_string($d[$k]) && trim($d[$k]) !== '' && ! in_array(Str::upper(Str::ascii(trim($d[$k]))), ['SIN NUMERO', 'S/N', 'SN', 'NINGUNO', 'NA', 'N/A', '-'], true)
            ? mb_convert_case(trim(preg_replace('/\s+/u', ' ', $d[$k])), MB_CASE_TITLE, 'UTF-8') : null;
        $calle = trim(implode(' ', array_filter([$t('tipo_vialidad'), $t('vialidad')])));
        if ($calle === '' && ! $t('colonia')) return null;
        $linea = $calle;
        if ($ext = $t('exterior')) $linea .= ' #' . $ext;
        elseif ($calle !== '' && isset($d['exterior'])) $linea .= ' S/N';
        if ($int = $t('interior')) $linea .= ' Int. ' . $int;
        if (($e = $t('entre')) && ($y = $t('y_calle'))) $linea .= ' x ' . $e . ' y ' . $y;
        elseif ($e) $linea .= ' entre ' . $e;
        $partes = array_filter([
            trim($linea) ?: null,
            ($c = $t('colonia')) ? (preg_match('/^(col|fracc|barrio|unidad|residencial|privada)\b/iu', $c) ? $c : 'Col. ' . $c) : null,
            ($l = $t('localidad')) && $l !== $t('municipio') ? $l : null,
            $t('municipio'), $t('estado'),
            isset($d['cp']) && preg_match('/^\d{5}$/', (string) $d['cp']) ? 'C.P. ' . $d['cp'] : null,
        ]);
        return $partes ? mb_substr(implode(', ', $partes), 0, 300) : null;
    }

    private const MESES = ['ENERO' => 1, 'FEBRERO' => 2, 'MARZO' => 3, 'ABRIL' => 4, 'MAYO' => 5, 'JUNIO' => 6, 'JULIO' => 7, 'AGOSTO' => 8,
                           'SEPTIEMBRE' => 9, 'OCTUBRE' => 10, 'NOVIEMBRE' => 11, 'DICIEMBRE' => 12];

    /** @return array{ok: bool, datos?: array, origen?: string, motivo?: string, mensaje?: string} */
    public static function leer(string $bytes, string $mime, Cuenta $c, ?User $u): array
    {
        $texto = str_contains($mime, 'pdf') ? self::texto($bytes) : null;
        if ($texto && ($d = self::deTexto($texto))) {
            return ['ok' => true, 'datos' => $d, 'origen' => 'texto'];
        }
        if (str_contains($mime, 'pdf')) {
            // Para revisar en el log por qué no se leyó (sin datos personales: solo si hubo texto y cuánto)
            \Illuminate\Support\Facades\Log::info('Constancia PDF sin leer', ['texto' => $texto !== null, 'largo' => strlen((string) $texto),
                'pdftotext' => self::hayPdftotext(), 'es_constancia' => $texto ? str_contains(Str::upper(Str::ascii($texto)), 'SITUACION FISCAL') || str_contains(Str::upper(Str::ascii($texto)), 'IDENTIFICACION FISCAL') : null]);
        }
        if (! $c->tiene('ia')) {
            return ['ok' => false, 'motivo' => 'sin_ia', 'mensaje' => str_contains($mime, 'pdf')
                ? (! self::hayPdftotext()
                    ? 'El servidor todavía no puede leer PDFs (falta instalar pdftotext). Mientras, captura los datos.'
                    : ($texto === null ? 'Este PDF parece escaneado (es una imagen). Usa el que se descarga del SAT o captura los datos.'
                                       : 'No reconocimos los datos de este PDF. Usa la constancia que se descarga del SAT o captura los datos.'))
                : 'Para leer fotos de la constancia hace falta la lectura con IA. Sube el PDF del SAT o captura los datos.'];
        }
        if (! str_contains($mime, 'pdf')) {
            $bytes = \App\Support\Gastos\LectorTicket::preparar($bytes) ?? $bytes;
            $mime = 'image/jpeg';
        }
        $r = Anthropic::herramienta($c, $u, 'constancia', [Anthropic::archivo($bytes, $mime), ['type' => 'text', 'text' => self::instrucciones()]], self::herramienta(), 600);
        if (! $r['ok']) return $r + ['mensaje' => 'No pudimos leer la constancia. Captura los datos.'];
        $e = $r['entrada'];
        if (($e['es_constancia'] ?? true) === false) return ['ok' => false, 'motivo' => 'no_es', 'mensaje' => 'Ese documento no parece una Constancia de Situación Fiscal.'];
        $d = self::limpiar($e);
        return $d['rfc'] ? ['ok' => true, 'datos' => $d, 'origen' => 'ia'] : ['ok' => false, 'motivo' => 'ilegible', 'mensaje' => 'No se alcanza a leer el RFC. Toma la foto otra vez, completa y con buena luz.'];
    }

    public static function hayPdftotext(): bool
    {
        static $hay = null;
        if ($hay === null) {
            try {
                $p = new Process(['pdftotext', '-v']);
                $p->run();
                $hay = $p->getExitCode() === 0 || str_contains($p->getErrorOutput() . $p->getOutput(), 'pdftotext');
            } catch (Throwable) {
                $hay = false;
            }
        }
        return $hay;
    }

    /** Texto del PDF con pdftotext (si el servidor lo tiene). null si no se puede */
    public static function texto(string $pdf): ?string
    {
        try {
            $p = new Process(['pdftotext', '-layout', '-l', '3', '-', '-']);
            $p->setInput($pdf);
            $p->setTimeout(20);
            $p->run();
            $t = $p->isSuccessful() ? trim($p->getOutput()) : '';
            return $t !== '' ? $t : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** Lee los datos del texto de la constancia. null si no parece una */
    public static function deTexto(string $t): ?array
    {
        $plano = Str::upper(Str::ascii($t));
        if (! str_contains($plano, 'CONSTANCIA DE SITUACION FISCAL') && ! str_contains($plano, 'CEDULA DE IDENTIFICACION FISCAL')) return null;
        $campo = function (string $etiqueta) use ($t): ?string {
            if (preg_match('/' . $etiqueta . '\s*:?\s*(.+?)(?:\s{2,}|\n|$)/iu', $t, $m)) {
                $v = trim($m[1]);
                return $v === '' ? null : $v;
            }
            return null;
        };
        $valido = fn (?string $r) => $r && preg_match('/^[A-Z&Ñ]{3,4}\d{6}[A-Z0-9]{3}$/u', $r);
        $rfc = $campo('RFC');
        $rfc = $rfc ? Str::upper(preg_replace('/[^A-Za-z0-9&Ññ]/u', '', $rfc)) : null;
        // La cédula (CIF) trae el RFC arriba, sin etiqueta "RFC:"; se busca el primero con forma de RFC
        if (! $valido($rfc) && preg_match('/\b([A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3})\b/u', Str::upper($t), $m)) $rfc = $m[1];
        if (! $valido($rfc)) return null;

        $razon = $campo('Denominaci[oó]n\s*\/?\s*Raz[oó]n\s+Social');
        if (! $razon) {
            $partes = array_filter([$campo('Nombre\s*\(s\)'), $campo('Primer\s+Apellido'), $campo('Segundo\s+Apellido')]);
            $razon = $partes ? implode(' ', $partes) : null;
        }
        // En la cédula el nombre va en la línea de arriba de "Nombre, denominación o razón social"
        if (! $razon && preg_match('/^\s*(\S[^\n]*?)\s*\n\s*Nombre,\s*denominaci[oó]n\s+o\s+raz[oó]n\s+social/imu', $t, $m)) $razon = trim($m[1]);
        $cp = preg_match('/C[oó]digo\s+Postal\s*:?\s*(\d{5})/iu', $t, $m) ? $m[1] : null;

        // Régimen: el primero vigente de la tabla "Regímenes" (sin fecha de fin)
        $regimen = null; $regimenes = [];
        $tabla = preg_match('/Reg[ií]menes\s*:?(.*?)(Obligaciones|Sus datos personales|$)/isu', $t, $m) ? $m[1] : $t;
        foreach (preg_split('/\n/', $tabla) as $linea) {
            $l = Str::lower(Str::ascii($linea));
            foreach (self::REGIMENES as $palabras => $clave) {
                if (str_contains($l, $palabras)) {
                    $vigente = preg_match_all('/\d{2}\/\d{2}\/\d{4}/', $linea) < 2;
                    $regimenes[] = $clave;
                    if ($vigente && ! $regimen) $regimen = $clave;
                    break;
                }
            }
        }
        $regimen ??= $regimenes[0] ?? null;

        $emitida = null;
        if (preg_match('/A\s+(\d{1,2})\s+DE\s+([A-Z]+)\s+DE\s+(\d{4})/u', $plano, $m) && isset(self::MESES[$m[2]])) {
            $emitida = sprintf('%04d-%02d-%02d', $m[3], self::MESES[$m[2]], $m[1]);
        }
        $dom = [];
        $otra = '/\s*(?:' . implode('|', [...array_values(self::DOMICILIO), 'C[oó]digo\s+Postal']) . ')\s*:.*$/iu';
        foreach (self::DOMICILIO as $k => $etiqueta) {
            $v = $campo($etiqueta);
            // El texto puede pegar la columna de al lado ("YUCATAN Entre Calle: 18") o, si el campo viene vacío, traer la etiqueta siguiente
            $v = $v !== null ? trim(preg_replace($otra, '', $v)) : null;
            if ($v !== null && $v !== '' && ! str_contains($v, ':')) $dom[$k] = $v;
        }
        return [
            'rfc'            => $rfc,
            'direccion'      => self::direccion($dom + ['cp' => $cp]),
            'domicilio'      => $dom ?: null,
            'razon_social'   => $razon ? mb_substr(Str::upper(trim(preg_replace('/\s+/', ' ', $razon))), 0, 255) : null,
            'nombre_comercial' => ($n = $campo('Nombre\s+Comercial')) ? mb_substr(trim($n), 0, 160) : null,
            'regimen_fiscal' => $regimen,
            'regimenes'      => array_values(array_unique($regimenes)),
            'cp_fiscal'      => $cp,
            'emitida_el'     => $emitida,
            'persona'        => strlen($rfc) === 12 ? 'moral' : 'fisica',
        ];
    }

    /** Valida lo que regresó la IA */
    public static function limpiar(array $e): array
    {
        $rfc = is_string($e['rfc'] ?? null) ? Str::upper(preg_replace('/[^A-Za-z0-9&Ññ]/u', '', $e['rfc'])) : null;
        $rfc = $rfc && preg_match('/^[A-Z&Ñ]{3,4}\d{6}[A-Z0-9]{3}$/u', $rfc) ? $rfc : null;
        $reg = is_string($e['regimen_fiscal'] ?? null) && array_key_exists($e['regimen_fiscal'], config('vandu.sat.regimenes')) ? $e['regimen_fiscal'] : null;
        $fecha = is_string($e['emitida_el'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $e['emitida_el']) && strtotime($e['emitida_el']) <= time() + 86400 ? $e['emitida_el'] : null;
        $cp = is_string($e['cp_fiscal'] ?? null) && preg_match('/^\d{5}$/', $e['cp_fiscal']) ? $e['cp_fiscal'] : null;
        $dom = array_filter(array_intersect_key((array) ($e['domicilio'] ?? []), self::DOMICILIO), fn ($v) => is_string($v) && trim($v) !== '');
        return [
            'rfc'            => $rfc,
            'direccion'      => self::direccion($dom + ['cp' => $cp]),
            'domicilio'      => $dom ?: null,
            'razon_social'   => is_string($e['razon_social'] ?? null) && trim($e['razon_social']) !== '' ? mb_substr(Str::upper(trim($e['razon_social'])), 0, 255) : null,
            'nombre_comercial' => is_string($e['nombre_comercial'] ?? null) && trim($e['nombre_comercial']) !== '' ? mb_substr(trim($e['nombre_comercial']), 0, 160) : null,
            'regimen_fiscal' => $reg,
            'regimenes'      => array_values(array_filter((array) ($e['regimenes'] ?? []), fn ($r) => is_string($r) && array_key_exists($r, config('vandu.sat.regimenes')))),
            'cp_fiscal'      => is_string($e['cp_fiscal'] ?? null) && preg_match('/^\d{5}$/', $e['cp_fiscal']) ? $e['cp_fiscal'] : null,
            'emitida_el'     => $fecha,
            'persona'        => $rfc && strlen($rfc) === 12 ? 'moral' : 'fisica',
        ];
    }

    private static function instrucciones(): string
    {
        return "Lee esta Constancia de Situación Fiscal del SAT (México) y regístrala con la herramienta.\n"
            . "- razon_social: para personas morales, la Denominación/Razón Social tal cual (sin el régimen de capital); para personas físicas, nombre(s) y apellidos.\n"
            . "- regimen_fiscal: la clave del régimen vigente principal. Claves: " . collect(config('vandu.sat.regimenes'))->map(fn ($v, $k) => "$k $v")->join('; ') . ".\n"
            . "- cp_fiscal: el código postal del domicilio fiscal. emitida_el: la fecha de emisión en AAAA-MM-DD.\n"
            . "- domicilio: los datos del domicilio registrado tal como aparecen (tipo y nombre de vialidad, número exterior e interior, colonia, localidad, municipio, entidad federativa, entre calle y y calle). Los que vengan vacíos, null.\n"
            . "- No inventes: si un dato no se lee con seguridad, déjalo en null. Si el documento no es una constancia fiscal, es_constancia = false.";
    }

    private static function herramienta(): array
    {
        $claves = array_map('strval', array_keys(config('vandu.sat.regimenes')));
        return [
            'name' => 'registrar_constancia',
            'description' => 'Registra los datos de una Constancia de Situación Fiscal.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'es_constancia'    => ['type' => 'boolean'],
                    'rfc'              => ['type' => ['string', 'null']],
                    'razon_social'     => ['type' => ['string', 'null']],
                    'nombre_comercial' => ['type' => ['string', 'null']],
                    'regimen_fiscal'   => ['type' => ['string', 'null'], 'enum' => [...$claves, null]],
                    'regimenes'        => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => $claves]],
                    'cp_fiscal'        => ['type' => ['string', 'null']],
                    'emitida_el'       => ['type' => ['string', 'null']],
                    'domicilio'        => ['type' => ['object', 'null'], 'properties' => collect(array_keys(self::DOMICILIO))->mapWithKeys(fn ($k) => [$k => ['type' => ['string', 'null']]])->all()],
                ],
                'required' => ['es_constancia', 'rfc', 'razon_social', 'regimen_fiscal', 'cp_fiscal'],
            ],
        ];
    }
}
