<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Presupuesto;
use App\Models\PresupuestoFactura;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** La factura de cada cotización: subir el PDF y el XML, verlos y quitarlos */
class FacturaController extends Controller
{
    public const EXTENSIONES = ['pdf', 'xml', 'zip'];

    public function subir(Request $request, Presupuesto $presupuesto)
    {
        $request->validate([
            'facturas'   => 'required|array|min:1|max:6',
            'facturas.*' => ['file', 'max:10240', function ($attr, $f, $fail) {
                if (! in_array(Str::lower($f->getClientOriginalExtension()), self::EXTENSIONES, true)) $fail('La factura debe ser PDF, XML o ZIP.');
            }],
        ], [
            'facturas.required' => 'Elige el PDF y el XML de la factura.',
            'facturas.*.max'    => 'Cada archivo puede pesar hasta 10 MB.',
        ]);

        foreach ($request->file('facturas') as $f) {
            $nombre = $f->getClientOriginalName() ?: 'factura.' . Str::lower($f->getClientOriginalExtension());
            // Si vuelves a subir un archivo con el mismo nombre, reemplaza al anterior
            $presupuesto->facturas()->where('nombre', $nombre)->get()->each->delete();
            $ruta = $f->storeAs("presupuestos/{$presupuesto->id}/facturas", Str::uuid() . '.' . Str::lower($f->getClientOriginalExtension()), 'local');
            $presupuesto->facturas()->create([
                'nombre' => $nombre, 'ruta' => $ruta,
                'mime'   => Str::lower($f->getClientOriginalExtension()) === 'xml' ? 'application/xml' : ($f->getMimeType() ?: 'application/octet-stream'),
                'peso'   => $f->getSize(),
            ]);
        }

        $n = count($request->file('facturas'));
        return back()->with('ok', ($n === 1 ? 'Archivo de factura guardado' : "$n archivos de factura guardados") . " en {$presupuesto->folio}. El cliente ya puede descargarla.");
    }

    public function ver(Request $request, Presupuesto $presupuesto, PresupuestoFactura $factura)
    {
        abort_unless($factura->presupuesto_id === $presupuesto->id, 404);
        return $factura->respuesta($request->boolean('descargar') || $factura->ext !== 'pdf');
    }

    public function borrar(Presupuesto $presupuesto, PresupuestoFactura $factura)
    {
        abort_unless($factura->presupuesto_id === $presupuesto->id, 404);
        $factura->delete();
        return back()->with('ok', "Se quitó {$factura->nombre}.");
    }
}
