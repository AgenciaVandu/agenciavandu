<?php

namespace App\Http\Controllers\Admin\Fitness;

use App\Models\Clase;
use App\Models\Cliente;
use App\Models\Reservacion;
use App\Support\Fitness\Reservas;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Inscribir atletas desde el panel, quitarlos y pasar lista */
class ReservacionesController extends FitnessController
{
    public function store(Request $request, Clase $clase)
    {
        $d = $request->validate(['cliente_id' => ['required', Rule::exists('clientes', 'id')->where('cuenta_id', \App\Support\Cuentas::id())]]);
        $c = Cliente::findOrFail($d['cliente_id']);
        Reservas::reservar($clase, $c, 'panel', $request->user());
        return back()->with('ok', "{$c->nombre} quedó inscrito.");
    }

    public function cancelar(Request $request, Reservacion $reservacion)
    {
        // Desde el panel el gimnasio decide si regresa la clase (por defecto sí)
        $devuelto = Reservas::cancelar($reservacion, $request->has('devolver') ? $request->boolean('devolver') : null);
        return back()->with('ok', 'Se quitó de la clase' . ($devuelto ? ' y se le regresó su clase.' : '.'));
    }

    public function asistencia(Request $request, Reservacion $reservacion)
    {
        $u = $request->user();
        $reservacion->loadMissing('clase');
        abort_if(! $u->puede('clases') && ! $u->puede('membresias') && $reservacion->clase->coach_id !== $u->id && $u->sucursal_id && $reservacion->clase->sucursal_id !== $u->sucursal_id, 403);
        $d = $request->validate(['estado' => 'required|in:asistio,falta,reservada']);
        Reservas::marcar($reservacion, $d['estado'], $u);
        if ($request->expectsJson()) return response()->json(['ok' => true, 'estado' => $reservacion->fresh()->estado]);
        return back();
    }
}
