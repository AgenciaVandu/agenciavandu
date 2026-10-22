<?php

namespace App\Support\Gastos;

/**
 * Lee el XML de una factura del SAT (CFDI 3.3 o 4.0): datos exactos, sin IA.
 * Devuelve null si no es un CFDI de ingreso timbrado.
 */
class Cfdi
{
    /** Formas de pago del SAT → las del gasto */
    private const FORMAS = ['01' => 'efectivo', '02' => 'otro', '03' => 'transferencia', '04' => 'tarjeta', '28' => 'tarjeta', '29' => 'tarjeta'];

    /** Prefijos de la clave de producto o servicio del SAT → categoría del gasto (lo demás queda en "otros") */
    private const CLAVES = [
        '1510' => 'combustible', '7811' => 'combustible', '7810' => 'combustible',
        '9010' => 'comida', '5019' => 'comida', '5020' => 'comida', '5018' => 'comida', '9011' => 'comida',
        '4412' => 'oficina', '1411' => 'oficina', '4410' => 'oficina', '4321' => 'herramienta', '2711' => 'herramienta', '2710' => 'herramienta',
        '8310' => 'servicios', '8311' => 'servicios', '8312' => 'servicios', '8013' => 'renta', '7210' => 'mantenimiento', '7211' => 'mantenimiento', '7610' => 'mantenimiento',
        '8210' => 'publicidad', '8014' => 'publicidad', '8111' => 'software', '4323' => 'software', '8412' => 'impuestos', '8410' => 'impuestos',
        '3010' => 'materiales', '3011' => 'materiales', '3012' => 'materiales', '3016' => 'materiales', '3110' => 'materiales', '3916' => 'materiales', '4011' => 'materiales',
    ];

    public static function leer(string $xml): ?array
    {
        $xml = trim($xml);
        if ($xml === '' || stripos($xml, '<!DOCTYPE') !== false) return null;   // sin entidades externas
        $prev = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (! $doc || $doc->getName() !== 'Comprobante') return null;

        $ns = $doc->getNamespaces(true);
        $cfdi = $ns['cfdi'] ?? null;
        if (! $cfdi) return null;
        $a = $doc->attributes();
        if (strtoupper((string) $a['TipoDeComprobante']) !== 'I') return null;   // solo facturas de ingreso (no notas de crédito ni pagos)

        $hijos = $doc->children($cfdi);
        $emisor = $hijos->Emisor?->attributes();
        $receptor = $hijos->Receptor?->attributes();

        $iva = 0.0; $claves = []; $descripciones = [];
        foreach ($hijos->Conceptos?->Concepto ?? [] as $con) {
            $ca = $con->attributes();
            $claves[] = (string) $ca['ClaveProdServ'];
            $descripciones[] = trim((string) $ca['Descripcion']);
        }
        $imp = $hijos->Impuestos;
        if ($imp) {
            foreach ($imp->children($cfdi)->Traslados?->Traslado ?? [] as $t) {
                $ta = $t->attributes();
                if ((string) $ta['Impuesto'] === '002') $iva += (float) $ta['Importe'];
            }
        }

        $uuid = null;
        $comp = $hijos->Complemento;
        if ($comp && isset($ns['tfd'])) {
            $tfd = $comp->children($ns['tfd'])->TimbreFiscalDigital;
            if ($tfd) $uuid = strtoupper((string) $tfd->attributes()['UUID']);
        }
        if (! $uuid || ! preg_match('/^[0-9A-F]{8}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{12}$/', $uuid)) return null;

        $subtotal = round((float) $a['SubTotal'] - (float) $a['Descuento'], 2);
        $total = round((float) $a['Total'], 2);
        $concepto = $descripciones ? $descripciones[0] . (count($descripciones) > 1 ? ' y ' . (count($descripciones) - 1) . ' más' : '') : 'Factura';
        $categoria = 'otros';
        foreach ($claves as $cl) {
            if ($cat = self::CLAVES[substr($cl, 0, 4)] ?? null) { $categoria = $cat; break; }
        }

        return [
            'uuid'       => $uuid,
            'fecha'      => substr((string) $a['Fecha'], 0, 10),
            'proveedor'  => mb_substr(trim((string) ($emisor['Nombre'] ?? '')) ?: (string) ($emisor['Rfc'] ?? ''), 0, 160),
            'rfc'        => mb_strtoupper((string) ($emisor['Rfc'] ?? '')) ?: null,
            'concepto'   => mb_substr($concepto, 0, 160),
            'categoria'  => $categoria,
            'subtotal'   => $subtotal,
            'iva'        => round($iva, 2),
            'total'      => $total,
            'forma_pago' => self::FORMAS[(string) $a['FormaPago']] ?? null,
            'receptor'   => trim((string) ($receptor['Nombre'] ?? '')) ?: null,
            'receptor_rfc' => mb_strtoupper((string) ($receptor['Rfc'] ?? '')) ?: null,
        ];
    }
}
