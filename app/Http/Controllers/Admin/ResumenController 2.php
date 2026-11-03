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
        // Lote de autos: su propio resumen (por vender, por cobrar, adeudos y termómetro)
        if (\App\Support\Cuentas::actual()?->tiene('autos')) return $this->autos();
        $tz = config('vandu.zona_horaria');
        $ahora = now();
        $inicioMes = now($tz)->startOfMonth()->setTimezone(config('app.timezone'));

        // Volumen pequeño: se calcula en memoria con los conceptos ya cargados
        $todas = Presupuesto::with(['conceptos', 'cliente'])->latest()->get();
        $abiertas = fn ($p) => ! in_array($p->estado, ['aceptada', 'rechazada', 'cancelada']);

        $vigentes = $todas->filter(fn ($p) => $p->abierta);
        $porVencer = $vigentes->filter(fn ($p) => $p->estado !== 'negociacion' && $p->vigente_hasta->gt($ahora) && $p->vigente_hasta->lte($ahora->copy()->addHours(72)))
            ->sortBy('vigente_hasta')->values();
        $aceptadasMes = $todas->filter(fn ($p) => $p->estado === 'aceptada' && ($p->aceptada_el?->format('Y-m') ?? $p->updated_at->copy()->setTimezone($tz)->format('Y-m')) === now($tz)->format('Y-m'));

        // Tasa de aceptación de los últimos 90 días (solo cotizaciones ya resueltas o vencidas)
        $recientes = $todas->filter(fn ($p) => $p->fecha->gte($ahora->copy()->subDays(90)));
        $resueltas = $recientes->filter(fn ($p) => $p->estado === 'aceptada' || $p->perdida);
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

        // Pagos pendientes de proyectos que no están en pausa
        $pendientes = \App\Models\ProyectoPago::with('proyecto.presupuesto.conceptos')->whereNull('pagado_el')->whereHas('proyecto', fn ($q) => $q->whereNotIn('estado', \App\Models\Proyecto::SIN_COBRO))->get();
        // Con o sin IVA según lo último que elegiste en Finanzas, para que ambos digan lo mismo
        $conIva = \App\Support\EsteMes::conIva();
        $montoPago = function ($pg) use ($conIva) {
            $p = $pg->proyecto?->presupuesto;
            return (! $conIva && $p && $p->total > 0) ? round($pg->monto * $p->subtotal / $p->total, 2) : (float) $pg->monto;
        };

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
                'porCobrar'      => round($pendientes->sum($montoPago), 2),
                'porCobrarIva'   => $conIva,
                'pendientes'     => $pendientes->count(),
                'vencidos'       => $pendientes->filter->vencido->count(),
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

    private function autos()
    {
        $tz = config('vandu.zona_horaria');
        $hoy = now($tz)->startOfDay();
        $mes = now($tz)->format('Y-m');
        $unidades = \App\Models\Unidad::where('estado', '!=', 'vendido')->get(['id', 'estado', 'precio', 'costo']);
        $ventas = \App\Models\Credito::where('estado', '!=', 'cancelado')->get(['id', 'tipo', 'precio', 'fecha']);
        $ventasMes = $ventas->filter(fn ($v) => $v->fecha->format('Y-m') === $mes);
        $activos = \App\Models\Credito::conCobranza()->with(['letras', 'cliente', 'unidad'])->get();
        $pagos = \App\Models\CreditoPago::whereHas('credito')->where('fecha', '>=', now($tz)->startOfMonth()->subMonths(5)->toDateString())->get(['monto', 'fecha']);
        $rangos = \App\Support\Credito::ajustes()['termometro'];

        $meses = collect(range(5, 0))->map(function ($i) use ($tz, $pagos) {
            $m = now($tz)->startOfMonth()->subMonths($i);
            return ['etiqueta' => rtrim(ucfirst($m->locale('es')->isoFormat('MMM')), '.'), 'monto' => round($pagos->filter(fn ($p) => $p->fecha->format('Y-m') === $m->format('Y-m'))->sum('monto'), 2)];
        });

        return view('admin.resumen-autos', [
            'porVender' => ['n' => $unidades->where('estado', 'disponible')->count(), 'apartadas' => $unidades->where('estado', 'apartado')->count(), 'valor' => $unidades->sum('precio')],
            'ventasMes' => ['n' => $ventasMes->count(), 'monto' => $ventasMes->sum('precio'), 'tipos' => $ventasMes->groupBy('tipo')->map->count()],
            'cobranza' => ['por_cobrar' => $activos->sum(fn ($c) => $c->por_cobrar), 'vencido' => $activos->sum(fn ($c) => $c->vencido),
                           'cobrado_mes' => $pagos->filter(fn ($p) => $p->fecha->format('Y-m') === $mes)->sum('monto')],
            'adeudos' => $activos->filter(fn ($c) => $c->atrasadas()->isNotEmpty())->sortByDesc(fn ($c) => [$c->atrasadas()->count(), $c->vencido])->take(10)->values(),
            'proximos' => $activos->map(fn ($c) => [$c, $c->proxima])->filter(fn ($x) => $x[1] && $x[1]->vence_el->between($hoy, $hoy->copy()->addDays(7)))->sortBy(fn ($x) => $x[1]->vence_el)->take(8)->values(),
            'rangos' => $rangos,
            'porNivel' => collect($rangos)->mapWithKeys(fn ($r) => [$r['nombre'] => $activos->filter(fn ($c) => $c->termometro['nombre'] === $r['nombre'])->count()]),
            'totalActivos' => $activos->count(),
            'meses' => $meses,
        ]);
    }
}
