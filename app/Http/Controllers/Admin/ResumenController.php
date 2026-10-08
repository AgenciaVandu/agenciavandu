<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Presupuesto;
use Illuminate\Support\Carbon;

class ResumenController extends Controller
{
    public function index()
    {
        $tz = config('vandu.zona_horaria');
        $ahora = now();
        $inicioMes = now($tz)->startOfMonth()->setTimezone(config('app.timezone'));

        // Volumen pequeño: se calcula en memoria con los conceptos ya cargados
        $todas = Presupuesto::with(['conceptos', 'cliente'])->latest()->get();
        $abiertas = fn ($p) => ! in_array($p->estado, ['aceptada', 'rechazada']);

        $vigentes = $todas->filter(fn ($p) => $p->vigente_hasta->gt($ahora) && $abiertas($p));
        $porVencer = $vigentes->filter(fn ($p) => $p->vigente_hasta->lte($ahora->copy()->addHours(72)))
            ->sortBy('vigente_hasta')->values();
        $aceptadasMes = $todas->filter(fn ($p) => $p->estado === 'aceptada' && $p->updated_at->gte($inicioMes));

        // Tasa de aceptación de los últimos 90 días (solo cotizaciones ya resueltas o vencidas)
        $recientes = $todas->filter(fn ($p) => $p->fecha->gte($ahora->copy()->subDays(90)));
        $resueltas = $recientes->filter(fn ($p) => in_array($p->estado, ['aceptada', 'rechazada']) || ! $p->vigente);
        $tasa = $resueltas->count() ? round($resueltas->where('estado', 'aceptada')->count() / $resueltas->count() * 100) : null;

        // Monto cotizado por mes (últimos 6 meses, por fecha de la cotización)
        $meses = collect(range(5, 0))->map(function ($i) use ($tz, $todas) {
            $mes = now($tz)->startOfMonth()->subMonths($i);
            $delMes = $todas->filter(fn ($p) => $p->fecha->format('Y-m') === $mes->format('Y-m'));
            return [
                'etiqueta'  => rtrim(ucfirst($mes->locale('es')->isoFormat('MMM')), '.'),
                'completa'  => ucfirst($mes->locale('es')->isoFormat('MMMM YYYY')),
                'monto'     => round($delMes->sum(fn ($p) => $p->subtotal), 2),
                'aceptado'  => round($delMes->where('estado', 'aceptada')->sum(fn ($p) => $p->subtotal), 2),
                'cuantas'   => $delMes->count(),
                'actual'    => $i === 0,
            ];
        });

        // Pendientes: enviadas que el cliente aún no abre
        $sinAbrir = $vigentes->filter(fn ($p) => $p->estado === 'enviada' && ! $p->vistas)->take(5)->values();

        $actividad = $todas->whereNotNull('ultima_vista_at')->sortByDesc('ultima_vista_at')->take(6)->values();

        return view('admin.resumen', [
            'kpi' => [
                'vigentes'       => $vigentes->count(),
                'vigentesMonto'  => $vigentes->sum(fn ($p) => $p->subtotal),
                'porVencer'      => $porVencer->count(),
                'aceptadasMes'   => $aceptadasMes->count(),
                'aceptadasMonto' => $aceptadasMes->sum(fn ($p) => $p->subtotal),
                'tasa'           => $tasa,
                'resueltas'      => $resueltas->count(),
                'clientes'       => Cliente::count(),
            ],
            'meses'     => $meses,
            'porVencer' => $porVencer->take(6),
            'sinAbrir'  => $sinAbrir,
            'actividad' => $actividad,
            'recientes' => $todas->take(5),
            'saludo'    => $this->saludo(now($tz)),
        ]);
    }

    private function saludo(Carbon $hora): string
    {
        return match (true) {
            $hora->hour < 12 => 'Buenos días',
            $hora->hour < 19 => 'Buenas tardes',
            default          => 'Buenas noches',
        };
    }
}
