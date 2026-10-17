<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Credito;
use App\Models\CreditoPago;
use App\Models\Sucursal;
use App\Models\Unidad;
use App\Models\User;
use App\Support\Credito as Calculo;
use App\Support\Cuentas;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Venta de unidades y cobranza de los créditos del lote */
class CreditoController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            abort_unless(Cuentas::actual()?->tiene('autos'), 404);
            return $next($request);
        });
    }

    /** Cobranza: lo que falta por cobrar, lo vencido y el termómetro de cada cliente */
    public function index(Request $request)
    {
        $vista = in_array($request->query('ver'), ['liquidados', 'ventas'], true) ? $request->query('ver') : 'activos';
        $nivel = (string) $request->query('nivel');
        $sucursal = (int) $request->query('sucursal') ?: null;
        $q = trim((string) $request->query('q'));
        $tz = config('vandu.zona_horaria');

        $base = Credito::with(['cliente', 'unidad.fotos', 'letras', 'sucursal'])
            ->when($sucursal, fn ($w) => $w->where('sucursal_id', $sucursal))
            ->when($q, fn ($w) => $w->whereHas('cliente', fn ($c) => $c->where('nombre', 'like', "%$q%")->orWhere('telefono', 'like', "%$q%")));
        $creditos = match ($vista) {
            'liquidados' => (clone $base)->where('tipo', 'credito')->where('estado', 'liquidado')->latest('fecha')->get(),
            'ventas'     => (clone $base)->where('estado', '!=', 'cancelado')->latest('fecha')->limit(200)->get(),
            default      => (clone $base)->conCobranza()->get(),
        };
        if ($vista === 'activos') {
            $creditos = $creditos->sortByDesc(fn ($c) => [$c->atrasadas()->count(), -($c->proxima?->vence_el?->timestamp ?? 0)])->values();
            if ($nivel !== '') $creditos = $creditos->filter(fn ($c) => $c->termometro['nombre'] === $nivel)->values();
        }

        $activos = Credito::conCobranza()->with('letras')->get();
        $inicioMes = now($tz)->startOfMonth()->toDateString();
        $resumen = [
            'por_cobrar' => $activos->sum(fn ($c) => $c->por_cobrar),
            'vencido' => $activos->sum(fn ($c) => $c->vencido),
            'cobrado_mes' => (float) CreditoPago::whereHas('credito')->where('fecha', '>=', $inicioMes)->sum('monto'),
            'con_adeudo' => $activos->filter(fn ($c) => $c->atrasadas()->isNotEmpty())->count(),
            'activos' => $activos->count(),
        ];
        $rangos = Calculo::ajustes()['termometro'];
        $porNivel = collect($rangos)->mapWithKeys(fn ($r) => [$r['nombre'] => $activos->filter(fn ($c) => $c->termometro['nombre'] === $r['nombre'])->count()]);

        return view('admin.creditos.index', compact('creditos', 'vista', 'nivel', 'sucursal', 'q', 'resumen', 'rangos', 'porNivel')
            + ['sucursales' => Sucursal::orderBy('nombre')->get()]);
    }

    public function create(Request $request, Unidad $unidad)
    {
        if ($unidad->venta) return redirect()->route('admin.creditos.show', $unidad->venta);
        $a = Calculo::ajustes();
        return view('admin.creditos.vender', [
            'u' => $unidad->load('fotos'), 'a' => $a,
            'clientes' => Cliente::orderBy('nombre')->get(['id', 'nombre', 'empresa', 'telefono']),
            'vendedores' => User::where('activo', true)->orderBy('name')->get(['id', 'name']),
            'sucursales' => Sucursal::orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request, Unidad $unidad)
    {
        abort_if((bool) $unidad->venta, 422, 'Esta unidad ya está vendida.');
        $d = $request->validate([
            'cliente_id'         => 'nullable|integer',
            'cliente_nombre'     => 'required_without:cliente_id|nullable|string|max:160',
            'cliente_telefono'   => 'nullable|string|max:30',
            'cliente_email'      => 'nullable|email|max:160',
            'tipo'               => 'required|in:contado,credito,financiera',
            'fecha'              => 'required|date',
            'precio'             => 'required|numeric|min:1|max:99999999',
            'enganche'           => 'nullable|numeric|min:0|max:99999999',
            'tasa'               => 'nullable|numeric|min:0|max:20',
            'plazo'              => 'nullable|integer|min:1|max:120',
            'primer_vencimiento' => 'nullable|date|after_or_equal:fecha',
            'moratorio_modo'     => 'nullable|in:dia,porcentaje',
            'moratorio_valor'    => 'nullable|numeric|min:0',
            'dias_gracia'        => 'nullable|integer|min:0|max:60',
            'descuento_adelanto' => 'nullable|numeric|min:0',
            'financiera'         => 'nullable|string|max:120',
            'vendedor_id'        => 'nullable|integer',
            'sucursal_id'        => 'nullable|integer',
            'notas'              => 'nullable|string|max:2000',
        ], [
            'cliente_nombre.required_without' => 'Elige al cliente o escribe su nombre.',
            'primer_vencimiento.after_or_equal' => 'La primera mensualidad no puede vencer antes de la venta.',
        ]);
        $credito = $d['tipo'] === 'credito';
        $enganche = (float) ($d['enganche'] ?? 0);
        if ($credito) {
            if ($enganche >= (float) $d['precio']) return back()->withInput()->withErrors(['enganche' => 'El enganche debe ser menor al precio para que haya algo que financiar.']);
            if (empty($d['plazo'])) return back()->withInput()->withErrors(['plazo' => 'Escribe el plazo en meses.']);
        }

        $venta = DB::transaction(function () use ($d, $unidad, $credito, $enganche, $request) {
            $cliente = ! empty($d['cliente_id']) ? Cliente::findOrFail($d['cliente_id'])
                : Cliente::create(['nombre' => trim($d['cliente_nombre']), 'telefono' => $d['cliente_telefono'] ?? null, 'email' => $d['cliente_email'] ?? null, 'origen' => 'Venta de auto']);
            $a = Calculo::ajustes();
            $financiado = $credito ? round((float) $d['precio'] - $enganche, 2) : 0;
            $tasa = $credito ? (float) ($d['tasa'] ?? $a['tasa']) : 0;
            $plazo = $credito ? (int) $d['plazo'] : 0;
            $v = Credito::create([
                'unidad_id' => $unidad->id, 'cliente_id' => $cliente->id, 'tipo' => $d['tipo'], 'fecha' => $d['fecha'],
                'vendedor_id' => ! empty($d['vendedor_id']) && User::whereKey($d['vendedor_id'])->exists() ? $d['vendedor_id'] : ($unidad->vendedor_id ?? $request->user()->id),
                'sucursal_id' => ! empty($d['sucursal_id']) && Sucursal::whereKey($d['sucursal_id'])->exists() ? $d['sucursal_id'] : $unidad->sucursal_id,
                'precio' => $d['precio'], 'enganche' => $credito ? $enganche : 0, 'financiado' => $financiado, 'tasa' => $tasa, 'plazo' => $plazo,
                'mensualidad' => $credito ? Calculo::mensualidad($financiado, $tasa, $plazo) : 0,
                'moratorio_modo' => $d['moratorio_modo'] ?? $a['moratorio_modo'], 'moratorio_valor' => $d['moratorio_valor'] ?? $a['moratorio_valor'],
                'dias_gracia' => $d['dias_gracia'] ?? $a['dias_gracia'], 'descuento_adelanto' => $d['descuento_adelanto'] ?? $a['descuento_adelanto'],
                'financiera' => $d['tipo'] === 'financiera' ? ($d['financiera'] ?? null) : null,
                'estado' => $credito ? 'activo' : 'liquidado', 'notas' => $d['notas'] ?? null,
            ]);
            if ($credito) {
                $primer = $d['primer_vencimiento'] ?? Carbon::parse($d['fecha'])->addMonthNoOverflow()->toDateString();
                foreach (Calculo::tabla($financiado, $tasa, $plazo, $primer) as $f) {
                    $v->letras()->create(collect($f)->except('saldo')->all());
                }
            }
            $unidad->update(['estado' => 'vendido']);
            return $v;
        });

        return redirect()->route('admin.creditos.show', $venta)->with('ok', $credito
            ? 'Venta registrada con ' . $venta->plazo . ' mensualidades de $' . number_format($venta->mensualidad, 2) . '.'
            : 'Venta registrada.');
    }

    public function show(Credito $credito)
    {
        $credito->load(['cliente', 'unidad.fotos', 'letras.pago', 'pagos.letras', 'pagos.usuario', 'vendedor', 'sucursal']);
        return view('admin.creditos.show', ['c' => $credito, 'hoy' => now(config('vandu.zona_horaria'))->toDateString()]);
    }

    /** Vista previa del pago (lo que se cobraría en esa fecha) */
    public function cotizar(Request $request, Credito $credito)
    {
        $fecha = Carbon::parse($request->query('fecha', now(config('vandu.zona_horaria'))->toDateString()));
        return response()->json(Calculo::cotizarPago($credito, $fecha, max(1, (int) $request->query('cuantas', 1)), $request->boolean('adelantar')));
    }

    public function pagar(Request $request, Credito $credito)
    {
        abort_unless($credito->tipo === 'credito' && $credito->estado === 'activo', 422);
        $d = $request->validate([
            'fecha' => 'required|date|before_or_equal:' . now(config('vandu.zona_horaria'))->addDay()->toDateString(),
            'cuantas' => 'required|integer|min:1|max:120',
            'adelantar' => 'boolean',
            'metodo' => 'nullable|string|max:30',
            'nota' => 'nullable|string|max:255',
        ], ['fecha.before_or_equal' => 'El pago no puede tener fecha futura.']);
        try {
            $pago = Calculo::registrarPago($credito, Carbon::parse($d['fecha']), (int) $d['cuantas'], $request->boolean('adelantar'), $d['metodo'] ?? null, $d['nota'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        $credito->refresh();
        return back()->with('ok', 'Pago de $' . number_format($pago->monto, 2) . ' registrado.' . ($credito->estado === 'liquidado' ? ' ¡El crédito quedó liquidado!' : ''));
    }

    public function cancelarPago(Credito $credito, CreditoPago $pago)
    {
        abort_unless($pago->credito_id === $credito->id, 404);
        abort_unless($credito->pagos()->first()?->id === $pago->id, 422, 'Solo se puede deshacer el último pago.');
        Calculo::cancelarPago($pago);
        return back()->with('ok', 'Pago deshecho. Las letras vuelven a quedar pendientes.');
    }

    /** Cancelar la venta: la unidad vuelve a estar disponible */
    public function cancelar(Credito $credito)
    {
        if ($credito->pagos()->exists()) return back()->with('error', 'Esta venta tiene pagos registrados. Deshazlos primero o habla con administración.');
        DB::transaction(function () use ($credito) {
            $credito->update(['estado' => 'cancelado']);
            $credito->unidad?->update(['estado' => 'disponible']);
        });
        return redirect()->route('admin.unidades.show', $credito->unidad_id)->with('ok', 'Venta cancelada. La unidad está disponible otra vez.');
    }
}
