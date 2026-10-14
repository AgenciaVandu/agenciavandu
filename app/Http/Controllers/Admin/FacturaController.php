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

    /** Complemento de pago (REP) de una factura PPD: su PDF y XML, ligado al pago que ampara */
    public function subirComplemento(Request $request, Presupuesto $presupuesto)
    {
        $pagos = $presupuesto->proyecto?->pagos()->pluck('id')->all() ?? [];
        $d = $request->validate([
            'complemento'   => 'required|array|min:1|max:4',
            'complemento.*' => ['file', 'max:10240', function ($attr, $f, $fail) {
                if (! in_array(Str::lower($f->getClientOriginalExtension()), self::EXTENSIONES, true)) $fail('El complemento debe ser PDF, XML o ZIP.');
            }],
            'pago_id'    => ['nullable', 'integer', \Illuminate\Validation\Rule::in($pagos)],
            'fecha_pago' => 'nullable|date',
        ], [
            'complemento.required' => 'Elige el PDF y el XML del complemento.',
            'complemento.*.max'    => 'Cada archivo puede pesar hasta 10 MB.',
        ]);

        $lote = (string) Str::uuid();
        foreach ($request->file('complemento') as $f) {
            $ext = Str::lower($f->getClientOriginalExtension());
            $presupuesto->archivosFiscales()->create([
                'tipo' => 'complemento', 'lote' => $lote, 'pago_id' => $d['pago_id'] ?? null, 'fecha_pago' => $d['fecha_pago'] ?? null,
                'nombre' => $f->getClientOriginalName() ?: "complemento.$ext",
                'ruta'   => $f->storeAs("presupuestos/{$presupuesto->id}/facturas", Str::uuid() . '.' . $ext, 'local'),
                'mime'   => $ext === 'xml' ? 'application/xml' : ($f->getMimeType() ?: 'application/octet-stream'),
                'peso'   => $f->getSize(),
            ]);
        }

        return redirect()->route('admin.presupuestos.edit', [$presupuesto, 'ver' => 'factura'])
            ->with('ok', 'Complemento de pago guardado. Ya lo puedes enviar.');
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
        if ($factura->es_complemento) {
            return redirect()->route('admin.presupuestos.edit', [$presupuesto, 'ver' => 'factura'])->with('ok', "Se quitó {$factura->nombre}.");
        }
        return back()->with('ok', "Se quitó {$factura->nombre}.");
    }
}
