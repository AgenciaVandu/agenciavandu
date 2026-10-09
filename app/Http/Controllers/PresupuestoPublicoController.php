<?php

namespace App\Http\Controllers;

use App\Models\Presupuesto;
use App\Support\PresupuestoPdf;
use Illuminate\Http\Request;

/** Vista que recibe el cliente: /cotizacion/{token} */
class PresupuestoPublicoController extends Controller
{
    public function show(Request $request, string $token)
    {
        $p = Presupuesto::where('token', $token)->with(['conceptos', 'proyecto.etapas', 'proyecto.pagos'])->firstOrFail();

        // ?vista_previa=1 lo usa el panel para no contar tus propias visitas
        if (! $request->boolean('vista_previa')) {
            $p->timestamps = false;
            $p->increment('vistas', 1, ['ultima_vista_at' => now()]);
        }

        return response()
            ->view('presupuestos.publico', ['p' => $p])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function descargar(string $token)
    {
        $p = Presupuesto::where('token', $token)->firstOrFail();

        if (! $p->vigente && $p->estado !== 'aceptada') {
            return redirect()->route('presupuesto.publico', $token);
        }

        return PresupuestoPdf::descargar($p);
    }
}
