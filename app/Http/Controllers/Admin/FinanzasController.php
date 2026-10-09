<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Presupuesto;
use App\Models\Proyecto;
use App\Models\ProyectoPago;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Finanzas: números de ventas y cobranza.
 *  - Cotizado y perdido se cuentan por la fecha de la cotización.
 *  - Ganado se cuenta por la fecha en que se aceptó.
 *  - Cobrado se cuenta por la fecha de cada pago.
 *  - Con ?iva=sin (por defecto) todo se muestra antes de IVA, también los pagos.
 */
class FinanzasController extends Controller
{
    /** "Todo" va primero y es el periodo por defecto: suma todo lo registrado */
    public const PERIODOS = [
        'todo'      => 'Todo',
        'anio'      => 'Este año',
        'trimestre' => 'Últimos 3 meses',
        'mes_ant'   => 'Mes pasado',
        'mes'       => 'Este mes',
    ];

    public function index(Request $request)
    {
        $tz = config('vandu.zona_horaria');
        $periodo = array_key_exists($request->query('periodo'), self::PERIODOS) ? $request->query('periodo') : 'todo';
        $conIva = $request->query('iva') === 'con';
        [$desde, $hasta] = $this->rango($periodo);
        $hoy = now($tz)->toDateString();

        $cotizaciones = Presupuesto::with(['conceptos', 'cliente', 'proyecto'])->get();
        $proyectos = Proyecto::with(['pagos', 'cliente', 'presupuesto.conceptos', 'etapas'])->get();
        $monto = fn (Presupuesto $p) => $conIva ? $p->total : $p->subtotal;

        // Factor para expresar pagos sin IVA: subtotal / total de la cotización de origen
        $factor = fn (Proyecto $pr) => (! $conIva && $pr->presupuesto && $pr->presupuesto->total > 0)
            ? $pr->presupuesto->subtotal / $pr->presupuesto->total : 1;
        $pagos = $proyectos->flatMap(fn ($pr) => $pr->pagos->map(fn ($pg) => (object) [
            'pago' => $pg, 'proyecto' => $pr, 'monto' => round($pg->monto * $factor($pr), 2),
        ]));

        $enRango = fn (?string $fecha) => $fecha && (! $desde || $fecha >= $desde) && (! $hasta || $fecha <= $hasta);
        $perdida = fn (Presupuesto $p) => $p->perdida;

        $delPeriodo = $cotizaciones->filter(fn ($p) => $enRango($p->fecha->toDateString()));
        $ganadas = $cotizaciones->filter(fn ($p) => $p->estado === 'aceptada' && $enRango(($p->aceptada_el ?? $p->updated_at->copy()->setTimezone($tz))->toDateString()));
        $perdidas = $delPeriodo->filter($perdida);
        $resueltasPeriodo = $delPeriodo->filter(fn ($p) => $p->estado === 'aceptada' || $perdida($p));
        $abiertas = $cotizaciones->filter(fn ($p) => $p->abierta);
        $cobrados = $pagos->filter(fn ($x) => $x->pago->pagado_el && $enRango($x->pago->pagado_el->toDateString()));
        $porCobrar = $pagos->filter(fn ($x) => ! $x->pago->pagado_el && $x->proyecto->estado !== 'pausado')
            ->sortBy(fn ($x) => $x->proyecto->fecha_inicio?->toDateString() ?? '9999');

        $suma = fn (Collection $c) => round($c->sum($monto), 2);
        $ganadoMonto = $suma($ganadas);
        $aceptadasResueltas = $resueltasPeriodo->where('estado', 'aceptada');

        $kpi = [
            'cotizado'       => $suma($delPeriodo),
            'cotizadas'      => $delPeriodo->count(),
            'ganado'         => $ganadoMonto,
            'ganadas'        => $ganadas->count(),
            'perdido'        => $suma($perdidas),
            'perdidas'       => $perdidas->count(),
            'enJuego'        => $suma($abiertas),
            'abiertas'       => $abiertas->count(),
            'negociacion'    => $abiertas->where('estado', 'negociacion')->count(),
            'negociacionMonto' => $suma($abiertas->where('estado', 'negociacion')),
            'cobrado'        => round($cobrados->sum('monto'), 2),
            'cobros'         => $cobrados->count(),
            'porCobrar'      => round($porCobrar->sum('monto'), 2),
            'pendientes'     => $porCobrar->count(),
            'conversion'     => $resueltasPeriodo->count() ? round($aceptadasResueltas->count() / $resueltasPeriodo->count() * 100) : null,
            'conversionMonto'=> $suma($resueltasPeriodo) > 0 ? round($suma($aceptadasResueltas) / $suma($resueltasPeriodo) * 100) : null,
            'ticket'         => $ganadas->count() ? round($ganadoMonto / $ganadas->count(), 2) : null,
            'diasCierre'     => $this->diasPromedioCierre($ganadas),
        ];

        // Comparación contra el periodo anterior de la misma duración
        $kpi['ganadoAnterior'] = null;
        if ($desde && $hasta) {
            $dias = Carbon::parse($desde)->diffInDays(Carbon::parse($hasta)) + 1;
            $antDesde = Carbon::parse($desde)->subDays($dias)->toDateString();
            $antHasta = Carbon::parse($desde)->subDay()->toDateString();
            $kpi['ganadoAnterior'] = round($cotizaciones->filter(fn ($p) => $p->estado === 'aceptada'
                && ($f = ($p->aceptada_el ?? $p->updated_at->copy()->setTimezone($tz))->toDateString()) >= $antDesde && $f <= $antHasta)->sum($monto), 2);
        }

        // Tendencia: últimos 12 meses (siempre, sin importar el periodo elegido)
        $meses = collect(range(11, 0))->map(function ($i) use ($tz, $cotizaciones, $pagos, $monto) {
            $m = now($tz)->startOfMonth()->subMonths($i);
            $ym = $m->format('Y-m');
            return [
                'etiqueta' => rtrim(ucfirst($m->locale('es')->isoFormat('MMM')), '.'),
                'completa' => ucfirst($m->locale('es')->isoFormat('MMMM YYYY')),
                'actual'   => $i === 0,
                'cotizado' => round($cotizaciones->filter(fn ($p) => $p->fecha->format('Y-m') === $ym)->sum($monto), 2),
                'ganado'   => round($cotizaciones->filter(fn ($p) => $p->estado === 'aceptada' && ($p->aceptada_el ?? $p->updated_at)->format('Y-m') === $ym)->sum($monto), 2),
                'cobrado'  => round($pagos->filter(fn ($x) => $x->pago->pagado_el?->format('Y-m') === $ym)->sum('monto'), 2),
            ];
        });

        // Embudo del periodo
        $embudo = [
            ['etiqueta' => 'Cotizadas',             'n' => $delPeriodo->count(),                          'monto' => $suma($delPeriodo)],
            ['etiqueta' => 'Abiertas por el cliente', 'n' => $delPeriodo->where('vistas', '>', 0)->count(), 'monto' => $suma($delPeriodo->where('vistas', '>', 0))],
            ['etiqueta' => 'Aceptadas',             'n' => $delPeriodo->where('estado', 'aceptada')->count(), 'monto' => $suma($delPeriodo->where('estado', 'aceptada'))],
            ['etiqueta' => 'Cobradas completas',    'n' => $delPeriodo->filter(fn ($p) => $p->proyecto && $p->proyecto->pagos->every(fn ($pg) => $pg->pagado_el))->count(),
                'monto' => $suma($delPeriodo->filter(fn ($p) => $p->proyecto && $p->proyecto->pagos->every(fn ($pg) => $pg->pagado_el)))],
        ];

        // Ganado por tipo de servicio
        $porTipo = collect(config('vandu.proyectos'))->map(fn ($m, $k) => [
            'nombre' => $m['nombre'], 'icono' => $m['icono'],
            'monto'  => round($ganadas->filter(fn ($p) => $p->proyecto?->tipo === $k)->sum($monto), 2),
            'n'      => $ganadas->filter(fn ($p) => $p->proyecto?->tipo === $k)->count(),
        ])->put('sin', [
            'nombre' => 'Sin proyecto', 'icono' => 'bi-file-earmark-check',
            'monto'  => round($ganadas->filter(fn ($p) => ! $p->proyecto)->sum($monto), 2),
            'n'      => $ganadas->filter(fn ($p) => ! $p->proyecto)->count(),
        ])->filter(fn ($t) => $t['n'] > 0)->sortByDesc('monto');

        // Mejores clientes por monto ganado en el periodo
        $clientes = $ganadas->groupBy('cliente_id')->map(fn ($g) => [
            'cliente' => $g->first()->cliente,
            'monto'   => round($g->sum($monto), 2),
            'n'       => $g->count(),
        ])->sortByDesc('monto')->take(6)->values();

        return view('admin.finanzas', [
            'periodo'   => $periodo,
            'conIva'    => $conIva,
            'desde'     => $desde,
            'hasta'     => $hasta,
            'kpi'       => $kpi,
            'meses'     => $meses,
            'embudo'    => $embudo,
            'porTipo'   => $porTipo,
            'clientes'  => $clientes,
            'porCobrar' => $porCobrar->take(12)->values(),
            'perdidas'  => $perdidas->sortByDesc(fn ($p) => $monto($p))->take(8)->values(),
            'monto'     => $monto,
            'hoy'       => $hoy,
            'rangos'    => collect(self::PERIODOS)->map(fn ($l, $k) => $this->rango($k))->all(),
        ]);
    }

    /** Exporta las cotizaciones del periodo en CSV (se abre en Excel o Sheets) */
    public function exportar(Request $request)
    {
        $periodo = array_key_exists($request->query('periodo'), self::PERIODOS) ? $request->query('periodo') : 'todo';
        [$desde, $hasta] = $this->rango($periodo);
        $tz = config('vandu.zona_horaria');

        $filas = Presupuesto::with(['conceptos', 'proyecto.pagos'])->orderBy('fecha')->get()
            ->filter(fn ($p) => (! $desde || $p->fecha->toDateString() >= $desde) && (! $hasta || $p->fecha->toDateString() <= $hasta));

        $nombre = 'vandu-cotizaciones-' . ($desde ?: 'inicio') . '-a-' . ($hasta ?: now($tz)->toDateString()) . '.csv';

        return response()->streamDownload(function () use ($filas) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM para que Excel lea los acentos
            fputcsv($out, ['Folio', 'Fecha', 'Cliente', 'Empresa', 'Concepto principal', 'Estado', 'Resultado', 'Aceptada el', 'Subtotal', 'IVA', 'Total', 'Cobrado', 'Por cobrar', 'Tipo de servicio', 'Costo (proveedor + gasolina)', 'Utilidad']);
            foreach ($filas as $p) {
                $perdida = $p->perdida;
                $cobrado = $p->proyecto ? $p->proyecto->pagos->whereNotNull('pagado_el')->sum('monto') : 0;
                $pend = $p->proyecto ? $p->proyecto->pagos->whereNull('pagado_el')->sum('monto') : 0;
                fputcsv($out, [
                    $p->folio, $p->fecha->toDateString(), $p->cliente_nombre, $p->cliente_empresa,
                    $p->conceptos->first()?->resumen, Presupuesto::ESTADOS[$p->estado] ?? $p->estado,
                    $p->estado === 'aceptada' ? 'Ganada' : ($perdida ? 'Perdida' : 'Abierta'),
                    $p->aceptada_el?->toDateString(),
                    number_format($p->subtotal, 2, '.', ''), number_format($p->iva, 2, '.', ''), number_format($p->total, 2, '.', ''),
                    number_format($cobrado, 2, '.', ''), number_format($pend, 2, '.', ''),
                    $p->proyecto?->tipo_nombre ?? config("vandu.proyectos.{$p->tipo}.nombre"),
                    $p->costo > 0 ? number_format($p->costo, 2, '.', '') : '',
                    $p->costo > 0 ? number_format($p->utilidad, 2, '.', '') : '',
                ]);
            }
            fclose($out);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0: ?string, 1: ?string} fechas Y-m-d (null = sin límite) */
    private function rango(string $periodo): array
    {
        $hoy = now(config('vandu.zona_horaria'));
        return match ($periodo) {
            'mes'       => [$hoy->copy()->startOfMonth()->toDateString(), $hoy->toDateString()],
            'mes_ant'   => [$hoy->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(), $hoy->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()],
            'trimestre' => [$hoy->copy()->subMonthsNoOverflow(2)->startOfMonth()->toDateString(), $hoy->toDateString()],
            'anio'      => [$hoy->copy()->startOfYear()->toDateString(), $hoy->toDateString()],
            default     => [null, null],
        };
    }

    /** Días promedio entre la fecha de la cotización y su aceptación */
    private function diasPromedioCierre(Collection $ganadas): ?int
    {
        $dias = $ganadas->filter(fn ($p) => $p->aceptada_el)->map(fn ($p) => max(0, $p->fecha->diffInDays($p->aceptada_el, false)));
        return $dias->isEmpty() ? null : (int) round($dias->avg());
    }
}
