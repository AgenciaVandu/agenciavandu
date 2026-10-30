<?php

namespace App\Support\Facturas;

use App\Support\Gastos\Cfdi;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Guarda el PDF y el XML de una factura (de cotización o de pedido) y lee del XML el folio fiscal, el RFC y el total */
class Archivos
{
    public const EXTENSIONES = ['pdf', 'xml', 'zip'];

    /** @param UploadedFile[] $archivos */
    public static function subir(Documento $doc, array $archivos): int
    {
        foreach ($archivos as $f) {
            $ext = Str::lower($f->getClientOriginalExtension());
            self::guardar($doc, $f->getClientOriginalName() ?: 'factura.' . $ext, file_get_contents($f->getRealPath()), $ext === 'xml' ? 'application/xml' : ($f->getMimeType() ?: 'application/octet-stream'), 'subida');
        }
        return count($archivos);
    }

    /** Guarda un archivo de factura; si ya había uno con el mismo nombre, lo reemplaza */
    public static function guardar(Documento $doc, string $nombre, string $contenido, string $mime, string $origen, ?string $facturapiId = null)
    {
        $ext = Str::lower(pathinfo($nombre, PATHINFO_EXTENSION));
        $doc->relacionArchivos()->where('nombre', $nombre)->get()->each->delete();
        $ruta = $doc->carpeta() . '/' . Str::uuid() . '.' . $ext;
        Storage::disk('local')->put($ruta, $contenido);
        $datos = ['tipo' => 'factura', 'nombre' => $nombre, 'ruta' => $ruta, 'mime' => $mime, 'peso' => strlen($contenido), 'origen' => $origen, 'facturapi_id' => $facturapiId];
        if ($ext === 'xml' && ($x = self::leerXml($contenido))) $datos += $x;
        return $doc->relacionArchivos()->create($datos);
    }

    /** Folio fiscal, RFC del cliente y total, si el XML es un CFDI timbrado */
    public static function leerXml(string $xml): ?array
    {
        $c = Cfdi::leer($xml);
        return $c ? ['uuid' => $c['uuid'], 'rfc_receptor' => $c['receptor_rfc'], 'total_cfdi' => $c['total']] : null;
    }

    /** Valida los archivos subidos (para los formularios) */
    public static function reglas(): array
    {
        return [
            'facturas'   => 'required|array|min:1|max:6',
            'facturas.*' => ['file', 'max:10240', function ($attr, $f, $fail) {
                if (! in_array(Str::lower($f->getClientOriginalExtension()), self::EXTENSIONES, true)) $fail('La factura debe ser PDF, XML o ZIP.');
            }],
        ];
    }

    public const MENSAJES = ['facturas.required' => 'Elige el PDF y el XML de la factura.', 'facturas.*.max' => 'Cada archivo puede pesar hasta 10 MB.'];
}
