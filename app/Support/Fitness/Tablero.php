<?php

namespace App\Support\Fitness;

use App\Models\Clase;
use App\Models\Cliente;
use App\Models\Membresia;
use App\Models\Reservacion;
use App\Models\User;

/** El Resumen de un gimnasio: lo de hoy, la ocupación, quién está por renovar y el dinero del mes */
class Tablero
{
    public static function datos(User $u): array
    {
        $tz = config('vandu.zona_horaria');
        $hoy = now($tz);
        Agenda::completarTodas();

        $clasesHoy = Clase::with(['tipo', 'coach'])->withCount(['ocupadas', 'reservaciones as asistencias_count' => fn ($q) => $q->where('estado', 'asistio')])
            ->delDia($hoy)->when(! $u->puede('clases'), fn ($q) => $q->where('coach_id', $u->id))->orderBy('inicia_at')->get();
        $programadas = $clasesHoy->where('estado', 'programada');

        $vigentes = Membresia::with('cliente')->vigentes()->get();
        $pronto = $hoy->copy()->addDays(7)->toDateString();
        $porRenovar = $vigentes->filter(fn ($m) => $m->vence_el->toDateString() <= $pronto || (! $m->ilimitada && $m->restantes <= 1))
            ->sortBy(fn ($m) => $m->vence_el->toDateString())->values();

        $mes = Finanzas::datos($hoy->copy()->startOfMonth()->toDateString(), $hoy->toDateString());

        $actividad = Reservacion::with(['cliente', 'clase.tipo'])->latest('updated_at')->take(8)->get()->map(fn ($r) => [
            'atleta' => $r->cliente, 'clase' => $r->clase,
            'texto' => match ($r->estado) {
                'asistio'   => 'entró a',
                'cancelada' => 'canceló',
                'falta'     => 'faltó a',
                default     => $r->origen === 'atleta' ? 'reservó' : 'quedó inscrito en',
            },
            'cuando' => $r->updated_at,
        ]);

        // La semana: ocupación de los próximos 7 días
        $semana = Clase::programadas()->withCount('ocupadas')->entre($hoy->copy()->startOfDay(), $hoy->copy()->addDays(6)->endOfDay())->get()
            ->groupBy(fn ($c) => $c->inicio->toDateString());
        $dias = collect(range(0, 6))->map(function ($i) use ($hoy, $semana) {
            $d = $hoy->copy()->addDays($i);
            $g = $semana->get($d->toDateString(), collect());
            return ['etiqueta' => $i === 0 ? 'Hoy' : rtrim(ucfirst($d->locale('es')->isoFormat('ddd')), '.'), 'clases' => $g->count(),
                    'ocupados' => $g->sum('ocupadas_count'), 'cupo' => $g->sum('cupo')];
        });

        return [
            'kpi' => [
                'activos'     => $vigentes->pluck('cliente_id')->unique()->count(),
                'atletas'     => Cliente::where('activo', '!=', false)->count(),
                'clasesHoy'   => $programadas->count(),
                'reservasHoy' => $programadas->sum('ocupadas_count'),
                'cupoHoy'     => $programadas->sum('cupo'),
                'asistenciasHoy' => $programadas->sum('asistencias_count'),
                'membresias'  => $vigentes->count(),
                'porRenovar'  => $porRenovar->count(),
                'vendidoMes'  => $mes['kpi']['vendido'],
                'cobradoMes'  => $mes['kpi']['cobrado'],
                'porCobrar'   => $mes['kpi']['porCobrar'],
            ],
            'clasesHoy' => $clasesHoy, 'porRenovar' => $porRenovar->take(8), 'actividad' => $actividad, 'dias' => $dias,
            'sinTipos' => ! \App\Models\ClaseTipo::exists(), 'sinPaquetes' => ! \App\Models\Paquete::exists(),
        ];
    }
}
