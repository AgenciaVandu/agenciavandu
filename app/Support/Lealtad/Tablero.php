<?php

namespace App\Support\Lealtad;

use App\Models\LealtadCampana;
use App\Models\LealtadCanje;
use App\Models\LealtadInscripcion;
use App\Models\LealtadVisita;

/** Resumen del programa de lealtad de un restaurante (cuenta activa) */
class Tablero
{
    public static function datos(): array
    {
        $tz = config('vandu.zona_horaria');
        $hoy = now($tz);
        $mes = $hoy->copy()->startOfMonth()->toDateString();
        $mesAnt = $hoy->copy()->subMonthNoOverflow()->startOfMonth();
        $hace60 = $hoy->copy()->subDays(60)->toDateString();

        $visitasMes = LealtadVisita::where('fecha', '>=', $mes)->count();
        $visitasAnt = LealtadVisita::whereBetween('fecha', [$mesAnt->toDateString(), $mesAnt->copy()->endOfMonth()->toDateString()])->count();
        $porComensal = LealtadVisita::where('fecha', '>=', $hace60)->selectRaw('comensal_id, count(*) as n')->groupBy('comensal_id')->pluck('n');
        $regresan = $porComensal->filter(fn ($n) => $n >= 2)->count();

        $campanas = LealtadCampana::with('premios')->where('activa', true)->whereDate('termina_el', '>=', $hoy->copy()->subDays(30)->toDateString())
            ->orderBy('inicia_el')->get()->map(function (LealtadCampana $k) {
                $visitas = LealtadVisita::whereBetween('fecha', [$k->inicia_el->toDateString(), $k->termina_el->toDateString()])
                    ->selectRaw('comensal_id, count(*) as n')->groupBy('comensal_id')->pluck('n');
                return [
                    'campana' => $k,
                    'participan' => $visitas->count(),
                    'meta' => $visitas->filter(fn ($n) => $n >= $k->meta_visitas)->count(),
                    'visitas' => $visitas->sum(),
                    'canjes' => LealtadCanje::whereIn('premio_id', $k->premios->pluck('id'))->count(),
                    'premios' => $k->premios->map(fn ($p) => ['premio' => $p, 'alcanzan' => $visitas->filter(fn ($n) => $n >= $p->visitas)->count()]),
                ];
            });

        $dias = collect(range(29, 0))->mapWithKeys(fn ($i) => [$hoy->copy()->subDays($i)->toDateString() => 0])->all();
        foreach (LealtadVisita::where('fecha', '>=', $hoy->copy()->subDays(29)->toDateString())->selectRaw('fecha, count(*) as n')->groupBy('fecha')->get() as $r) {
            $dias[$r->fecha->toDateString()] = (int) $r->n;
        }

        return [
            'kpi' => [
                'inscritos' => LealtadInscripcion::activas()->count(),
                'nuevos' => LealtadInscripcion::activas()->where('consentido_at', '>=', $mes)->count(),
                'visitasMes' => $visitasMes,
                'visitasAnt' => $visitasAnt,
                'regresan' => $regresan,
                'retorno' => $porComensal->count() ? (int) round($regresan / $porComensal->count() * 100) : null,
                'canjesMes' => LealtadCanje::where('created_at', '>=', $hoy->copy()->startOfMonth()->utc())->count(),
            ],
            'campanas' => $campanas,
            'dias' => $dias,
            'ultimas' => LealtadVisita::with('comensal')->latest('id')->take(8)->get(),
        ];
    }
}
