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

    /** Cambiar días, hora, duración, entrenador, sucursal o cupo: aplica a sus clases que vienen */
    public function update(Request $request, ClaseSerie $serie)
    {
        $cuenta = Cuentas::id();
        $d = $request->validate([
            'coach_id'    => ['nullable', Rule::exists('users', 'id')->where('cuenta_id', $cuenta)],
            'sucursal_id' => ['nullable', Rule::exists('sucursales', 'id')->where('cuenta_id', $cuenta)],
            'cupo'        => 'required|integer|min:1|max:500',
            'hasta'       => 'nullable|date',
            'dias'        => 'sometimes|array|min:1',
            'dias.*'      => 'integer|between:1,7',
            'hora'        => ['sometimes', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'duracion'    => 'sometimes|integer|min:10|max:480',
        ], ['dias.min' => 'Deja al menos un día.', 'hora.regex' => 'Escribe la hora como 07:00.']);
        if (isset($d['dias'])) $d['dias'] = array_values(array_unique(array_map('intval', $d['dias'])));
        $serie->update($d);
        $cambio = $serie->wasChanged(['dias', 'hora', 'duracion']) ? Agenda::reprogramar($serie) : ['movidas' => 0, 'quedan' => 0];
        Agenda::actualizarFuturas($serie);
        if ($serie->hasta) $serie->clases()->where('inicia_at', '>', $serie->hasta->copy()->endOfDay())->doesntHave('ocupadas')->delete();
        Agenda::generar($serie);
        $msg = 'Horario fijo guardado; se aplicó a sus próximas clases.';
        if ($cambio['movidas']) $msg .= " {$cambio['movidas']} " . ($cambio['movidas'] === 1 ? 'clase con inscritos se movió' : 'clases con inscritos se movieron') . ' y se les avisó.';
        if ($cambio['quedan']) $msg .= " {$cambio['quedan']} " . ($cambio['quedan'] === 1 ? 'clase con inscritos se quedó' : 'clases con inscritos se quedaron') . ' en su día; cancélalas si no se van a dar.';
        return back()->with('ok', $msg);
    }

    public function destroy(ClaseSerie $serie)
    {
        $quedan = Agenda::terminar($serie);
        return back()->with('ok', 'Ya no se repite.' . ($quedan ? " Se quedaron $quedan " . ($quedan === 1 ? 'clase que ya tiene' : 'clases que ya tienen') . ' reservaciones; cancélalas si no se van a dar.' : ''));
    }
}
