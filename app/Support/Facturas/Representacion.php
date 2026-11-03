<?php

namespace App\Support\Facturas;

use App\Models\Cuenta;
use App\Support\Cuentas;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Representación impresa del CFDI 4.0 hecha por Nerby (en lugar del PDF genérico de Facturapi):
 * se arma desde el XML timbrado, con la marca del negocio y todos los datos que pide el SAT
 * (folio fiscal, sellos, certificados, cadena original del timbre y QR de verificación).
 */
class Representacion
{
    public const TIPOS = ['I' => 'Ingreso', 'E' => 'Egreso', 'P' => 'Pago', 'T' => 'Traslado', 'N' => 'Nómina'];
    public const TITULOS = ['I' => 'Factura', 'E' => 'Nota de crédito', 'P' => 'Complemento de pago', 'T' => 'Carta porte', 'N' => 'Recibo de nómina'];
    public const METODOS = ['PUE' => 'Pago en una sola exhibición', 'PPD' => 'Pago en parcialidades o diferido'];
    public const FORMAS = ['01' => 'Efectivo', '02' => 'Cheque nominativo', '03' => 'Transferencia electrónica', '04' => 'Tarjeta de crédito',
        '05' => 'Monedero electrónico', '06' => 'Dinero electrónico', '08' => 'Vales de despensa', '12' => 'Dación en pago', '13' => 'Pago por subrogación',
        '14' => 'Pago por consignación', '15' => 'Condonación', '17' => 'Compensación', '23' => 'Novación', '24' => 'Confusión', '25' => 'Remisión de deuda',
        '26' => 'Prescripción o caducidad', '27' => 'A satisfacción del acreedor', '28' => 'Tarjeta de débito', '29' => 'Tarjeta de servicios',
        '30' => 'Aplicación de anticipos', '31' => 'Intermediario pagos', '99' => 'Por definir'];
    public const OBJETOS = ['01' => 'No objeto de impuesto', '02' => 'Sí objeto de impuesto', '03' => 'Sí objeto, no obligado al desglose', '04' => 'Sí objeto, no causa impuesto', '05' => 'Sí objeto, IVA crédito PODEC'];
    public const EXPORTACION = ['01' => 'No aplica', '02' => 'Definitiva', '03' => 'Temporal', '04' => 'Definitiva con clave distinta a A1'];
    public const RELACIONES = ['01' => 'Nota de crédito de los documentos relacionados', '02' => 'Nota de débito', '03' => 'Devolución de mercancía',
        '04' => 'Sustitución de los CFDI previos', '05' => 'Traslados de mercancías facturados', '06' => 'Factura generada por traslados', '07' => 'CFDI por aplicación de anticipo'];
    public const PERIODICIDAD = ['01' => 'Diaria', '02' => 'Semanal', '03' => 'Quincenal', '04' => 'Mensual', '05' => 'Bimestral'];
    public const MESES = ['01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril', '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
        '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre', '13' => 'Enero-Febrero', '14' => 'Marzo-Abril', '15' => 'Mayo-Junio',
        '16' => 'Julio-Agosto', '17' => 'Septiembre-Octubre', '18' => 'Noviembre-Diciembre'];
    public const IMPUESTOS = ['001' => 'ISR', '002' => 'IVA', '003' => 'IEPS'];

    /** PDF de la factura a partir de su XML; null si algo falla (se usa el de Facturapi) */
    public static function pdf(string $xml, ?Cuenta $c = null): ?string
    {
        try {
            $d = self::leer($xml);
            if (! $d['uuid']) return null;
            $c ??= Cuentas::actual();
            return Pdf::loadView('facturas.cfdi', ['d' => $d, 'marca' => self::marca($c)])->setPaper('letter')->output();
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    /**
     * Vista previa antes de timbrar: el mismo diseño, armado con lo que se mandaría a Facturapi.
     * Sin folio fiscal, sellos ni QR (los pone el SAT al timbrar) y con la marca "Vista previa".
     */
    public static function borrador(array $payload, string $serie, int $folio, ?Cuenta $c = null): string
    {
        $c ??= Cuentas::actual();
        $aj = Timbrado::ajustes();
        $em = (array) ($aj['emisor'] ?? []);
        $conceptos = [];
        $subtotal = 0.0; $descuento = 0.0; $porTasa = [];
        foreach ($payload['items'] as $it) {
            $p = $it['product'];
            $cant = (float) $it['quantity'];
            $importe = round($cant * (float) $p['price'], 2);
            $desc = round((float) ($it['discount'] ?? 0), 2);
            $base = round($importe - $desc, 2);
            $imp = [];
            foreach ($p['taxes'] ?? [] as $t) {
                $rate = (float) $t['rate'];
                $monto = round($base * $rate, 2);
                $imp[] = ['tipo' => 'Traslado', 'impuesto' => $t['type'] ?? 'IVA', 'factor' => 'Tasa', 'tasa' => number_format($rate, 6, '.', ''), 'base' => $base, 'importe' => $monto];
                $k = number_format($rate, 6, '.', '');
                $porTasa[$k] = ($porTasa[$k] ?? 0) + $monto;
            }
            $conceptos[] = ['clave' => $p['product_key'], 'sku' => null, 'cantidad' => $cant, 'clave_unidad' => $p['unit_key'], 'unidad' => null,
                'descripcion' => $p['description'], 'unitario' => (float) $p['price'], 'importe' => $importe, 'descuento' => $desc,
                'objeto' => $p['taxability'] ?? '02', 'impuestos' => $imp];
            $subtotal += $importe; $descuento += $desc;
        }
        $traslados = [];
        foreach ($porTasa as $tasa => $monto) $traslados[] = ['impuesto' => 'IVA', 'tasa' => $tasa, 'factor' => 'Tasa', 'importe' => round($monto, 2)];
        $total = round($subtotal - $descuento + array_sum(array_column($traslados, 'importe')), 2);
        $cu = (array) $payload['customer'];
        $d = [
            'borrador' => true, 'tipo' => 'I', 'titulo' => 'Factura', 'serie' => $serie, 'folio' => (string) $folio,
            'fecha' => now(config('vandu.zona_horaria'))->toDateTimeString(), 'lugar' => $em['cp'] ?? '—', 'moneda' => 'MXN', 'tipo_cambio' => null,
            'forma' => $payload['payment_form'], 'metodo' => $payload['payment_method'], 'condiciones' => null, 'exportacion' => '01',
            'subtotal' => round($subtotal, 2), 'descuento' => round($descuento, 2), 'total' => $total, 'cert_emisor' => $aj['certificado']['serie'] ?? null,
            'sello' => '', 'version' => '4.0',
            'emisor' => ['rfc' => $aj['certificado']['rfc'] ?? '—', 'nombre' => $em['razon_social'] ?? ($c?->nombre ?? '—'), 'regimen' => $em['regimen'] ?? null],
            'receptor' => ['rfc' => $cu['tax_id'] ?? '—', 'nombre' => $cu['legal_name'] ?? '—', 'cp' => $cu['address']['zip'] ?? '—', 'regimen' => $cu['tax_system'] ?? null, 'uso' => $payload['use'] ?? null],
            'global' => null, 'conceptos' => $conceptos, 'traslados' => $traslados, 'retenciones' => [],
            'total_traslados' => array_sum(array_column($traslados, 'importe')), 'total_retenciones' => 0,
            'relacionados' => [], 'pagos' => [], 'uuid' => null, 'timbrado' => null, 'pac' => null, 'cert_sat' => null,
            'sello_cfd' => null, 'sello_sat' => null, 'cadena' => null, 'qr_url' => null, 'letra' => self::conLetra($total),
        ];
        return Pdf::loadView('facturas.cfdi', ['d' => $d, 'marca' => self::marca($c)])->setPaper('letter')->output();
    }

    /** Color, logo y contacto del negocio para el encabezado */
    public static function marca(?Cuenta $c): array
    {
        $color = $c ? ($c->ajuste('documento.color') ?: $c->ajuste('icono_fondo')) : null;
        if (! is_string($color) || ! preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) $color = '#1F2937';
        // Los colores muy claros no sirven como acento sobre papel blanco
        [$r, $g, $b] = sscanf($color, '#%02x%02x%02x');
        if ((0.2126 * $r + 0.7152 * $g + 0.0722 * $b) > 200) $color = '#1F2937';
        $logo = $c ? Organizacion::archivoLogo($c) : null;
        return [
            'color' => $color,
            'logo' => $logo ? 'data:' . (str_ends_with(strtolower($logo), '.png') ? 'image/png' : 'image/jpeg') . ';base64,' . base64_encode((string) file_get_contents($logo)) : null,
            'nombre' => $c?->nombre,
            'direccion' => (Timbrado::ajustes()['emisor']['direccion'] ?? null) ?: $c?->ajuste('emisor.direccion'),
            'telefono' => $c?->ajuste('emisor.telefono'),
            'email' => $c?->ajuste('emisor.email'),
            'sitio' => $c?->ajuste('marca.sitio'),
        ];
    }

    /** Todo lo necesario del XML (CFDI 4.0, con timbre, información global, relacionados y pagos 2.0) */
    public static function leer(string $xml): array
    {
        $doc = new \DOMDocument();
        $doc->loadXML($xml, LIBXML_NONET);
        $x = new \DOMXPath($doc);
        $x->registerNamespace('cfdi', 'http://www.sat.gob.mx/cfd/4');
        $x->registerNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');
        $x->registerNamespace('pago20', 'http://www.sat.gob.mx/Pagos20');

        $a = fn (?\DOMNode $n, string $k) => $n instanceof \DOMElement && $n->hasAttribute($k) ? $n->getAttribute($k) : null;
        $uno = fn (string $q, ?\DOMNode $ctx = null) => $x->query($q, $ctx)->item(0);

        $comp = $uno('/cfdi:Comprobante');
        $em = $uno('/cfdi:Comprobante/cfdi:Emisor');
        $re = $uno('/cfdi:Comprobante/cfdi:Receptor');
        $tfd = $uno('//tfd:TimbreFiscalDigital');
        $global = $uno('/cfdi:Comprobante/cfdi:InformacionGlobal');

        $conceptos = [];
        foreach ($x->query('/cfdi:Comprobante/cfdi:Conceptos/cfdi:Concepto') as $n) {
            $imp = [];
            foreach ($x->query('cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado', $n) as $t) {
                $imp[] = ['tipo' => 'Traslado', 'impuesto' => self::IMPUESTOS[$a($t, 'Impuesto')] ?? $a($t, 'Impuesto'), 'factor' => $a($t, 'TipoFactor'),
                    'tasa' => $a($t, 'TasaOCuota'), 'base' => (float) $a($t, 'Base'), 'importe' => (float) $a($t, 'Importe')];
            }
            foreach ($x->query('cfdi:Impuestos/cfdi:Retenciones/cfdi:Retencion', $n) as $t) {
                $imp[] = ['tipo' => 'Retención', 'impuesto' => self::IMPUESTOS[$a($t, 'Impuesto')] ?? $a($t, 'Impuesto'), 'factor' => $a($t, 'TipoFactor'),
                    'tasa' => $a($t, 'TasaOCuota'), 'base' => (float) $a($t, 'Base'), 'importe' => (float) $a($t, 'Importe')];
            }
            $conceptos[] = [
                'clave' => $a($n, 'ClaveProdServ'), 'sku' => $a($n, 'NoIdentificacion'), 'cantidad' => (float) $a($n, 'Cantidad'),
                'clave_unidad' => $a($n, 'ClaveUnidad'), 'unidad' => $a($n, 'Unidad'), 'descripcion' => $a($n, 'Descripcion'),
                'unitario' => (float) $a($n, 'ValorUnitario'), 'importe' => (float) $a($n, 'Importe'), 'descuento' => (float) $a($n, 'Descuento'),
                'objeto' => $a($n, 'ObjetoImp'), 'impuestos' => $imp,
            ];
        }

        // Totales de impuestos por tipo y tasa
        $traslados = [];
        foreach ($x->query('/cfdi:Comprobante/cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado') as $t) {
            $traslados[] = ['impuesto' => self::IMPUESTOS[$a($t, 'Impuesto')] ?? $a($t, 'Impuesto'), 'tasa' => $a($t, 'TasaOCuota'), 'factor' => $a($t, 'TipoFactor'), 'importe' => (float) $a($t, 'Importe')];
        }
        $retenciones = [];
        foreach ($x->query('/cfdi:Comprobante/cfdi:Impuestos/cfdi:Retenciones/cfdi:Retencion') as $t) {
            $retenciones[] = ['impuesto' => self::IMPUESTOS[$a($t, 'Impuesto')] ?? $a($t, 'Impuesto'), 'importe' => (float) $a($t, 'Importe')];
        }

        $relacionados = [];
        foreach ($x->query('/cfdi:Comprobante/cfdi:CfdiRelacionados') as $g) {
            foreach ($x->query('cfdi:CfdiRelacionado', $g) as $r) {
                $relacionados[] = ['tipo' => $a($g, 'TipoRelacion'), 'uuid' => $a($r, 'UUID')];
            }
        }

        $pagos = [];
        foreach ($x->query('//pago20:Pago') as $p) {
            $docs = [];
            foreach ($x->query('pago20:DoctoRelacionado', $p) as $dr) {
                $docs[] = ['uuid' => $a($dr, 'IdDocumento'), 'serie' => $a($dr, 'Serie'), 'folio' => $a($dr, 'Folio'), 'moneda' => $a($dr, 'MonedaDR'),
                    'parcialidad' => $a($dr, 'NumParcialidad'), 'anterior' => (float) $a($dr, 'ImpSaldoAnt'), 'pagado' => (float) $a($dr, 'ImpPagado'),
                    'insoluto' => (float) $a($dr, 'ImpSaldoInsoluto'), 'objeto' => $a($dr, 'ObjetoImpDR')];
            }
            $pagos[] = ['fecha' => $a($p, 'FechaPago'), 'forma' => $a($p, 'FormaDePagoP'), 'moneda' => $a($p, 'MonedaP'), 'tipo_cambio' => $a($p, 'TipoCambioP'),
                'monto' => (float) $a($p, 'Monto'), 'operacion' => $a($p, 'NumOperacion'), 'documentos' => $docs];
        }

        $tipo = $a($comp, 'TipoDeComprobante');
        $total = (float) $a($comp, 'Total');
        if ($tipo === 'P') $total = array_sum(array_column($pagos, 'monto'));
        $sello = (string) $a($comp, 'Sello');
        $uuid = $a($tfd, 'UUID');
        $rfcEm = (string) $a($em, 'Rfc');
        $rfcRe = (string) $a($re, 'Rfc');

        $d = [
            'tipo' => $tipo, 'titulo' => self::TITULOS[$tipo] ?? 'CFDI', 'serie' => $a($comp, 'Serie'), 'folio' => $a($comp, 'Folio'),
            'fecha' => $a($comp, 'Fecha'), 'lugar' => $a($comp, 'LugarExpedicion'), 'moneda' => $a($comp, 'Moneda'), 'tipo_cambio' => $a($comp, 'TipoCambio'),
            'forma' => $a($comp, 'FormaPago'), 'metodo' => $a($comp, 'MetodoPago'), 'condiciones' => $a($comp, 'CondicionesDePago'), 'exportacion' => $a($comp, 'Exportacion'),
            'subtotal' => (float) $a($comp, 'SubTotal'), 'descuento' => (float) $a($comp, 'Descuento'), 'total' => $total,
            'cert_emisor' => $a($comp, 'NoCertificado'), 'sello' => $sello, 'version' => $a($comp, 'Version'),
            'emisor' => ['rfc' => $rfcEm, 'nombre' => $a($em, 'Nombre'), 'regimen' => $a($em, 'RegimenFiscal')],
            'receptor' => ['rfc' => $rfcRe, 'nombre' => $a($re, 'Nombre'), 'cp' => $a($re, 'DomicilioFiscalReceptor'), 'regimen' => $a($re, 'RegimenFiscalReceptor'), 'uso' => $a($re, 'UsoCFDI')],
            'global' => $global ? ['periodicidad' => $a($global, 'Periodicidad'), 'meses' => $a($global, 'Meses'), 'anio' => $a($global, 'Año')] : null,
            'conceptos' => $conceptos, 'traslados' => $traslados, 'retenciones' => $retenciones,
            'total_traslados' => (float) $a($uno('/cfdi:Comprobante/cfdi:Impuestos'), 'TotalImpuestosTrasladados'),
            'total_retenciones' => (float) $a($uno('/cfdi:Comprobante/cfdi:Impuestos'), 'TotalImpuestosRetenidos'),
            'relacionados' => $relacionados, 'pagos' => $pagos,
            'uuid' => $uuid, 'timbrado' => $a($tfd, 'FechaTimbrado'), 'pac' => $a($tfd, 'RfcProvCertif'), 'cert_sat' => $a($tfd, 'NoCertificadoSAT'),
            'sello_cfd' => $a($tfd, 'SelloCFD') ?: $sello, 'sello_sat' => $a($tfd, 'SelloSAT'),
        ];
        // Cadena original del complemento de certificación digital del SAT
        $d['cadena'] = '||' . implode('|', array_filter([$a($tfd, 'Version') ?: '1.1', $uuid, $d['timbrado'], $d['pac'], $a($tfd, 'Leyenda'), $d['sello_cfd'], $d['cert_sat']], fn ($v) => $v !== null && $v !== '')) . '||';
        // QR de verificación del SAT (Anexo 20): id, re, rr, tt y los últimos 8 caracteres del sello
        $d['qr_url'] = 'https://verificacfdi.facturaelectronica.sat.gob.mx/default.aspx?id=' . $uuid . '&re=' . rawurlencode($rfcEm) . '&rr=' . rawurlencode($rfcRe)
            . '&tt=' . self::totalQr($tipo === 'P' ? 0 : (float) $a($comp, 'Total')) . '&fe=' . substr($sello, -8);
        $d['letra'] = $tipo === 'P' ? self::conLetra($total, $pagos[0]['moneda'] ?? 'MXN') : self::conLetra((float) $a($comp, 'Total'), $d['moneda'] ?: 'MXN');
        return $d;
    }

    /** Total para el QR como en el ejemplo del Anexo 20: 6 decimales y ceros a la izquierda (tt=0000014300.000000) */
    private static function totalQr(float $t): string
    {
        return str_pad(number_format($t, 6, '.', ''), 17, '0', STR_PAD_LEFT);
    }

    /** "OCHOCIENTOS CATORCE PESOS 32/100 M.N." */
    public static function conLetra(float $monto, string $moneda = 'MXN'): string
    {
        $entero = (int) floor(round($monto, 2));
        $centavos = (int) round(($monto - $entero) * 100);
        if ($centavos === 100) { $entero++; $centavos = 0; }
        $palabras = $entero === 0 ? 'CERO' : self::numero($entero);
        if ($moneda === 'MXN') {
            $unidad = $entero === 1 ? 'PESO' : 'PESOS';
            if ($entero >= 1000000 && $entero % 1000000 === 0) $palabras .= ' DE';
            return trim("$palabras $unidad " . str_pad((string) $centavos, 2, '0', STR_PAD_LEFT) . '/100 M.N.');
        }
        return trim("$palabras " . str_pad((string) $centavos, 2, '0', STR_PAD_LEFT) . "/100 $moneda");
    }

    private static function numero(int $n): string
    {
        $u = ['', 'UN', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE', 'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE',
            'DIECISÉIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE', 'VEINTE', 'VEINTIÚN', 'VEINTIDÓS', 'VEINTITRÉS', 'VEINTICUATRO', 'VEINTICINCO',
            'VEINTISÉIS', 'VEINTISIETE', 'VEINTIOCHO', 'VEINTINUEVE'];
        $d = [3 => 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
        $c = [1 => 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];
        $menorMil = function (int $n) use ($u, $d, $c): string {
            if ($n === 0) return '';
            if ($n === 100) return 'CIEN';
            $r = [];
            if ($n >= 100) { $r[] = $c[intdiv($n, 100)]; $n %= 100; }
            if ($n >= 30) { $r[] = $d[intdiv($n, 10)] . ($n % 10 ? ' Y ' . $u[$n % 10] : ''); }
            elseif ($n > 0) { $r[] = $u[$n]; }
            return implode(' ', $r);
        };
        $partes = [];
        $millones = intdiv($n, 1000000);
        $miles = intdiv($n % 1000000, 1000);
        $resto = $n % 1000;
        if ($millones) $partes[] = $millones === 1 ? 'UN MILLÓN' : self::numero($millones) . ' MILLONES';
        if ($miles) $partes[] = $miles === 1 ? 'MIL' : $menorMil($miles) . ' MIL';
        if ($resto) $partes[] = $menorMil($resto);
        return implode(' ', $partes);
    }
}
