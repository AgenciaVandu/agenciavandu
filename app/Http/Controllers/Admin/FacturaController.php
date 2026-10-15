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

        // Subir una nota de venta propia (en lugar de generarla) deja la cotización "sin factura"
        if ($request->input('tipo') === 'nota' && ! $presupuesto->es_nota) {
            $presupuesto->forceFill(['comprobante' => 'nota'])->saveQuietly();
            $presupuesto->facturas()->get()->each->delete();
        }
        foreach ($request->file('facturas') as $f) {
            $nombre = $f->getClientOriginalName() ?: 'factura.' . Str::lower($f->getClientOriginalExtension());
            // Si vuelves a subir un archivo con el mismo nombre, reemplaza al anterior
            $presupuesto->facturas()->where('nombre', $nombre)->get()->each->delete();
            $ruta = $f->storeAs("presupuestos/{$presupuesto->id}/facturas", Str::uuid() . '.' . Str::lower($f->getClientOriginalExtension()), 'local');
            $presupuesto->facturas()->create([
                'tipo'   => $presupuesto->es_nota ? 'nota' : 'factura',
                'nombre' => $nombre, 'ruta' => $ruta,
                'mime'   => Str::lower($f->getClientOriginalExtension()) === 'xml' ? 'application/xml' : ($f->getMimeType() ?: 'application/octet-stream'),
                'peso'   => $f->getSize(),
            ]);
        }

        $n = count($request->file('facturas'));
        $que = $presupuesto->es_nota ? 'Nota de venta guardada' : ($n === 1 ? 'Archivo de factura guardado' : "$n archivos de factura guardados");
        return back()->with('ok', "$que en {$presupuesto->folio}. El cliente ya puede descargarla.");
    }

    /**
     * Nota de venta para quien paga sin factura: se arma con los conceptos de la cotización,
     * se guarda como su comprobante y se envía y descarga igual que una factura.
     */
    public function generarNota(Request $request, Presupuesto $presupuesto)
    {
        $d = $request->validate([
            'con_iva'    => 'nullable|boolean',
            'forma_pago' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(config('vandu.metodos_pago')))],
            'nota_texto' => 'nullable|string|max:500',
        ]);
        $presupuesto->load('conceptos');
        $presupuesto->forceFill(['comprobante' => 'nota', 'nota_folio' => $presupuesto->nota_folio ?: Presupuesto::siguienteFolioNota()])->saveQuietly();

        $conIva = $request->boolean('con_iva');
        $pdf = \App\Support\PresupuestoPdf::nota($presupuesto, $conIva, $d['forma_pago'] ?? null, $d['nota_texto'] ?? null);
        $nombre = "Nota de venta {$presupuesto->nota_folio}.pdf";

        // Una nota nueva reemplaza a la anterior
        $presupuesto->facturas()->get()->each->delete();
        $ruta = "presupuestos/{$presupuesto->id}/facturas/" . Str::uuid() . '.pdf';
        \Illuminate\Support\Facades\Storage::disk('local')->put($ruta, $pdf->output());
        $presupuesto->facturas()->create([
            'tipo' => 'nota', 'nombre' => $nombre, 'ruta' => $ruta, 'mime' => 'application/pdf',
            'peso' => \Illuminate\Support\Facades\Storage::disk('local')->size($ruta),
        ]);

        return redirect()->route('admin.presupuestos.edit', [$presupuesto, 'ver' => 'factura'])
            ->with('ok', "Nota de venta {$presupuesto->nota_folio} lista. Ya la puedes enviar.");
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
