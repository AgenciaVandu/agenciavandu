<?php

namespace App\Http\Controllers;

use App\Models\Presupuesto;
use App\Support\PresupuestoPdf;
use App\Support\Push\Notificar;
use Illuminate\Http\Request;

/** Vista que recibe el cliente: /cotizacion/{token} */
class PresupuestoPublicoController extends Controller
{
    public function show(Request $request, string $token)
    {
        $p = Presupuesto::where('token', $token)->with(['conceptos', 'proyecto.etapas', 'proyecto.pagos'])->firstOrFail();

        // ?vista_previa=1 lo usa el panel para no contar tus propias visitas
        if (! $request->boolean('vista_previa')) {
            $avisar = ! auth()->check() && Notificar::visitaNueva($p->ultima_vista_at);
            $p->timestamps = false;
            $p->increment('vistas', 1, ['ultima_vista_at' => now()]);
            if ($avisar) {
                $quien = $p->cliente_empresa ?: $p->cliente_nombre;
                $veces = $p->vistas === 1 ? 'La abrió por primera vez' : "Ya la abrió {$p->vistas} veces";
                Notificar::evento('cotizacion_abierta', "$quien abrió su cotización",
                    "{$p->folio} · " . $p->monto($p->modo_iva === 'desglosado' ? $p->total : $p->subtotal) . " · $veces",
                    route('admin.presupuestos.edit', $p), 'cotizacion-' . $p->id);
            }
        }

        return response()
            ->view('presupuestos.publico', ['p' => $p])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function descargar(string $token)
    {
        $p = Presupuesto::where('token', $token)->firstOrFail();

        if (! $p->vigente && ! in_array($p->estado, ['aceptada', 'negociacion'], true)) {
            return redirect()->route('presupuesto.publico', $token);
        }

        if (! auth()->check()) {
            $quien = $p->cliente_empresa ?: $p->cliente_nombre;
            Notificar::evento('cotizacion_descargada', "$quien descargó su cotización",
                "{$p->folio} en PDF · buen momento para darle seguimiento", route('admin.presupuestos.edit', $p), 'descarga-' . $p->id);
        }

        return PresupuestoPdf::descargar($p);
    }
}
