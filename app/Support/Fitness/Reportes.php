<?php

namespace App\Support\Fitness;

use App\Models\Clase;
use App\Models\Membresia;
use App\Models\Reservacion;
use Illuminate\Support\Carbon;

/** Números del gimnasio por periodo (fechas en la zona del negocio; null = desde el principio / hasta hoy) */
class Reportes
{
    public static function datos(?string $desde, ?string $hasta): array
    {
        $tz = config('vandu.zona_horaria');
        $ini = $desde ? Carbon::parse($desde, $tz)->startOfDay() : null;
        $fin = Carbon::parse($hasta ?? now($tz)->toDateString(), $tz)->endOfDay()->min(now($tz));

        $clases = Clase::with('tipo')->programadas()
            ->when($ini, fn ($q) => $q->where('inicia_at', '>=', $ini->copy()->utc()))
            ->where('inicia_at', '<=', $fin->copy()->utc())
            ->withCount(['ocupadas', 'reservaciones as asistencias_count' => fn ($q) => $q->where('estado', 'asistio'),
                         'reservaciones as faltas_count' => fn ($q) => $q->where('estado', 'falta'),
                         'reservaciones as canceladas_count' => fn ($q) => $q->where('estado', 'cancelada')])
            ->get();

        $cupo = $clases->sum('cupo');
        $porTipo = $clases->groupBy('clase_tipo_id')->map(fn ($g) => [
            'tipo' => $g->first()->tipo, 'clases' => $g->count(), 'reservas' => $g->sum('ocupadas_count'), 'asistencias' => $g->sum('asistencias_count'),
            'ocupacion' => $g->sum('cupo') ? round($g->sum('ocupadas_count') / $g->sum('cupo') * 100) : 0,
        ])->sortByDesc('reservas')->values();

        $porHora = $clases->groupBy(fn ($c) => $c->inicio->format('H:00'))->map(fn ($g, $h) => [
            'hora' => $h, 'clases' => $g->count(), 'ocupacion' => $g->sum('cupo') ? round($g->sum('ocupadas_count') / $g->sum('cupo') * 100) : 0,
        ])->sortKeys()->values();

        $diario = $clases->groupBy(fn ($c) => $c->inicio->toDateString())->map(fn ($g, $d) => ['dia' => $d, 'asistencias' => $g->sum('asistencias_count')])
            ->sortKeys()->values()->take(-31);

        $ids = $clases->pluck('id');
        $top = Reservacion::with('cliente')->whereIn('clase_id', $ids)->where('estado', 'asistio')
            ->selectRaw('cliente_id, count(*) as n')->groupBy('cliente_id')->orderByDesc('n')->limit(10)->get();

        $hoy = now($tz)->toDateString();
        $pronto = now($tz)->addDays(7)->toDateString();
        $vigentes = Membresia::with('cliente')->vigentes()->get();

        return [
            'kpi' => [
                'clases'      => $clases->count(),
                'reservas'    => $clases->sum('ocupadas_count'),
                'asistencias' => $clases->sum('asistencias_count'),
                'faltas'      => $clases->sum('faltas_count'),
                'canceladas'  => $clases->sum('canceladas_count'),
                'ocupacion'   => $cupo ? round($clases->sum('ocupadas_count') / $cupo * 100) : 0,
                'activos'     => $vigentes->pluck('cliente_id')->unique()->count(),
                'asistieron'  => Reservacion::whereIn('clase_id', $ids)->where('estado', 'asistio')->distinct('cliente_id')->count('cliente_id'),
            ],
            'porTipo' => $porTipo, 'porHora' => $porHora, 'diario' => $diario, 'top' => $top,
            'porVencer' => $vigentes->filter(fn ($m) => $m->vence_el->toDateString() <= $pronto || (! $m->ilimitada && $m->restantes <= 1))
                ->sortBy(fn ($m) => $m->vence_el->toDateString())->values(),
            'clasesCanceladas' => Clase::where('estado', 'cancelada')->when($ini, fn ($q) => $q->where('inicia_at', '>=', $ini->copy()->utc()))->where('inicia_at', '<=', $fin->copy()->utc())->count(),
            'desde' => $ini, 'hasta' => $fin,
        ];
    }
}
