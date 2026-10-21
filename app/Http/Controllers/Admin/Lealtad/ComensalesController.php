<?php

namespace App\Http\Controllers\Admin\Lealtad;

use App\Models\LealtadCanje;
use App\Models\LealtadInscripcion;
use App\Models\LealtadVisita;
use Illuminate\Http\Request;

/** Comensales del programa de este restaurante. Sus datos de contacto solo se ven mientras sigan inscritos */
class ComensalesController extends LealtadController
{
    public const VISTAS = ['todos' => 'Todos', 'frecuentes' => 'Frecuentes', 'nuevos' => 'Nuevos este mes', 'sin_volver' => 'No han vuelto', 'baja' => 'Se salieron'];

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $ver = array_key_exists($request->query('ver'), self::VISTAS) ? $request->query('ver') : 'todos';
        $tz = config('vandu.zona_horaria');

        $visitas = LealtadVisita::selectRaw('comensal_id, count(*) as n, max(fecha) as ultima, sum(monto) as gastado')->groupBy('comensal_id')->get()->keyBy('comensal_id');
        $canjes = LealtadCanje::selectRaw('comensal_id, count(*) as n')->groupBy('comensal_id')->pluck('n', 'comensal_id');
        $lista = LealtadInscripcion::with('comensal')->get()
            ->filter(fn ($i) => $i->comensal)
            ->map(function ($i) use ($visitas, $canjes) {
                $v = $visitas->get($i->comensal_id);
                $i->setAttribute('visitas', (int) ($v->n ?? 0));
                $i->setAttribute('ultima', $v?->ultima ? \Illuminate\Support\Carbon::parse($v->ultima) : null);
                $i->setAttribute('gastado', (float) ($v->gastado ?? 0));
                $i->setAttribute('canjes', (int) ($canjes[$i->comensal_id] ?? 0));
                return $i;
            });

        $mes = now($tz)->startOfMonth();
        $hace30 = now($tz)->subDays(30)->toDateString();
        $filtro = fn ($i, $v) => match ($v) {
            'frecuentes' => ! $i->baja_at && $i->visitas >= 4,
            'nuevos' => ! $i->baja_at && $i->consentido_at->gte($mes),
            'sin_volver' => ! $i->baja_at && (! $i->ultima || $i->ultima->toDateString() < $hace30),
            'baja' => (bool) $i->baja_at,
            default => ! $i->baja_at,
        };
        $conteos = collect(self::VISTAS)->map(fn ($t, $k) => $lista->filter(fn ($i) => $filtro($i, $k))->count());
        $lista = $lista->filter(fn ($i) => $filtro($i, $ver))
            ->when($q, fn ($l) => $l->filter(fn ($i) => str_contains(mb_strtolower($i->comensal->nombre . ' ' . $i->comensal->email . ' ' . $i->comensal->telefono), mb_strtolower($q))))
            ->sortByDesc(fn ($i) => [$i->ultima?->timestamp ?? 0, $i->visitas])->values();

        return view('admin.lealtad.comensales', compact('lista', 'q', 'ver', 'conteos'));
    }
}
