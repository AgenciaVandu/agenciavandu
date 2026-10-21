<?php

namespace App\Http\Controllers\Admin\Fitness;

use App\Models\ClaseSerie;
use App\Models\Sucursal;
use App\Models\User;
use App\Support\Cuentas;
use App\Support\Fitness\Agenda;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Horario fijo: las clases que se repiten cada semana */
class SeriesController extends FitnessController
{
    public function index()
    {
        return view('admin.fitness.series', [
            'series' => ClaseSerie::with(['tipo', 'coach', 'sucursal'])->withCount(['clases as futuras_count' => fn ($q) => $q->where('inicia_at', '>', now())])
                ->orderByDesc('activa')->orderBy('hora')->get(),
            'coaches' => User::with('rol')->where('activo', true)->orderBy('name')->get()->filter(fn ($u) => $u->puede('asistencia') || $u->puede('clases'))->values(),
            'sucursales' => Sucursal::orderBy('nombre')->get(),
        ]);
    }

    /** Cambiar entrenador, sucursal o cupo: aplica a sus clases que vienen */
    public function update(Request $request, ClaseSerie $serie)
    {
        $cuenta = Cuentas::id();
        $d = $request->validate([
            'coach_id'    => ['nullable', Rule::exists('users', 'id')->where('cuenta_id', $cuenta)],
            'sucursal_id' => ['nullable', Rule::exists('sucursales', 'id')->where('cuenta_id', $cuenta)],
            'cupo'        => 'required|integer|min:1|max:500',
            'hasta'       => 'nullable|date',
        ]);
        $serie->update($d);
        Agenda::actualizarFuturas($serie);
        if ($serie->hasta) $serie->clases()->where('inicia_at', '>', $serie->hasta->copy()->endOfDay())->doesntHave('ocupadas')->delete();
        Agenda::generar($serie);
        return back()->with('ok', 'Horario guardado; se aplicó a sus próximas clases.');
    }

    public function destroy(ClaseSerie $serie)
    {
        $quedan = Agenda::terminar($serie);
        return back()->with('ok', 'Ya no se repite.' . ($quedan ? " Se quedaron $quedan " . ($quedan === 1 ? 'clase que ya tiene' : 'clases que ya tienen') . ' reservaciones; cancélalas si no se van a dar.' : ''));
    }
}
