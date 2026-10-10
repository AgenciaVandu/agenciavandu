<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\ProyectoArchivo;
use App\Support\Push\Notificar;
use Illuminate\Http\Request;

/** El espacio del cliente: un solo enlace con todas sus cotizaciones y proyectos */
class ClientePortalController extends Controller
{
    public function show(Request $request, string $token)
    {
        $c = Cliente::where('portal_token', $token)->firstOrFail();

        $cotizaciones = $c->presupuestos()->where('estado', '!=', 'borrador')
            ->with(['conceptos', 'proyecto:id,presupuesto_id,token,nombre'])->orderByDesc('fecha')->orderByDesc('id')->get();
        $proyectos = $c->proyectos()->with(['etapas', 'pagos'])->get()
            ->sortBy(fn ($p) => [$p->estado === 'terminado' ? 1 : 0, -$p->id])->values();

        // Hasta 4 miniaturas por proyecto (las ya optimizadas, ligeras)
        $fotos = ProyectoArchivo::whereIn('proyecto_id', $proyectos->pluck('id'))->where('grupo', 'galeria')->where('visible', true)
            ->whereNotNull('miniatura')->orderByDesc('id')->get(['id', 'proyecto_id', 'nombre', 'mime', 'miniatura'])
            ->groupBy('proyecto_id')->map(fn ($g) => $g->take(4));
        $enGaleria = ProyectoArchivo::whereIn('proyecto_id', $proyectos->pluck('id'))->where('grupo', 'galeria')->where('visible', true)
            ->selectRaw('proyecto_id, count(*) as n')->groupBy('proyecto_id')->pluck('n', 'proyecto_id');

        $redes = $c->redes_token && \App\Support\Cuentas::actual()?->tiene('redes') ? \App\Support\Redes::urlCliente($c) : null;

        if (! auth()->check() && ! $request->boolean('vista_previa')) {
            if (Notificar::visitaNueva($c->portal_visto_at)) {
                Notificar::evento('proyecto_visto', ($c->empresa ?: $c->nombre) . ' abrió su espacio', 'Está viendo sus cotizaciones y proyectos', route('admin.clientes.show', $c), 'portal-' . $c->id);
            }
            $c->timestamps = false;
            $c->forceFill(['portal_visto_at' => now()])->save();
        }

        return response()->view('clientes.portal', compact('c', 'cotizaciones', 'proyectos', 'fotos', 'enGaleria', 'redes'))
            ->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer');
    }
}
