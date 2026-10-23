<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Presupuesto;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use App\Support\Cuentas;
use App\Support\Ventas\Envios;
use App\Support\Ventas\Registrar;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Ventas directas: Nueva venta (mostrador), pedidos con envío, cobros y entregas */
class PedidosController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            abort_unless(Cuentas::actual()?->tiene('stock'), 404);
            return $next($request);
        });
    }

    public const VISTAS = ['hoy' => 'Hoy', 'entregar' => 'Por entregar', 'cobrar' => 'Por cobrar', 'todas' => 'Todas'];

    public function index(Request $request)
    {
        $tz = config('vandu.zona_horaria');
        $hoy = now($tz)->toDateString();
        $ver = array_key_exists($request->query('ver'), self::VISTAS) ? $request->query('ver') : 'hoy';
        $q = trim((string) $request->query('q'));
        $ventas = Venta::with(['cliente:id,nombre,empresa', 'vendedor:id,name', 'repartidor:id,name'])
            ->when($ver === 'hoy', fn ($w) => $w->whereDate('fecha', $hoy))
            ->when($ver === 'entregar', fn ($w) => $w->porEntregar())
            ->when($ver === 'cobrar', fn ($w) => $w->porCobrar())
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('folio', 'like', "%$q%")->orWhere('cliente_nombre', 'like', "%$q%")->orWhere('envio_nombre', 'like', "%$q%")
                ->orWhereHas('cliente', fn ($c) => $c->where('nombre', 'like', "%$q%")->orWhere('empresa', 'like', "%$q%"))))
            ->orderByDesc('id')->paginate(40)->withQueryString();

        $delDia = Venta::vigentes()->whereDate('fecha', $hoy)->get();
        $mes = Venta::vigentes()->whereNull('presupuesto_id')->whereDate('fecha', '>=', now($tz)->startOfMonth()->toDateString())->get();
        return view('admin.pedidos.index', [
            'ventas' => $ventas, 'ver' => $ver, 'q' => $q,
            'kpi' => [
                'hoy' => round($delDia->sum('total'), 2), 'nHoy' => $delDia->count(),
                'mes' => round($mes->sum('total'), 2), 'nMes' => $mes->count(),
                'entregar' => Venta::porEntregar()->count(),
                'cobrar' => round(Venta::porCobrar()->get()->sum('saldo'), 2), 'nCobrar' => Venta::porCobrar()->count(),
                'vencidas' => Venta::porCobrar()->whereNotNull('vence_el')->whereDate('vence_el', '<', $hoy)->count(),
            ],
        ]);
    }

    public function create(Request $request)
    {
        $env = Envios::ajustes();
        return view('admin.pedidos.nueva', [
            'cliente' => $request->query('cliente') ? Cliente::find($request->query('cliente')) : null,
            'equipo' => User::where('activo', true)->orderBy('name')->get(['id', 'name']),
            'envios' => $env,
            'conIva' => (bool) Cuentas::actual()->ajuste('ventas.iva', true),
        ]);
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'productos' => 'required|array|min:1',
            'productos.*.producto_id' => 'required|integer',
            'productos.*.cantidad' => 'required|numeric|min:0.01|max:999999',
            'productos.*.precio' => 'required|numeric|min:0|max:99999999',
            'cliente_id' => 'nullable|integer',
            'cliente_nombre' => 'nullable|string|max:160',
            'cliente_telefono' => 'nullable|string|max:30',
            'guardar_cliente' => 'nullable|boolean',
            'descuento' => 'nullable|numeric|min:0',
            'con_iva' => 'nullable|boolean',
            'pago' => ['required', Rule::in(['contado', 'parcial', 'credito'])],
            'forma' => ['nullable', Rule::in(array_keys(Venta::FORMAS))],
            'monto' => 'nullable|numeric|min:0',
            'dias' => 'nullable|integer|min:1|max:365',
            'entrega' => ['required', Rule::in(['ahora', 'recoge', 'envio'])],
            'datos' => ['nullable', Rule::in(['cliente', 'vendedor'])],
            'envio_nombre' => 'nullable|string|max:160',
            'envio_telefono' => 'nullable|string|max:30',
            'envio_direccion' => 'nullable|string|max:300',
            'envio_referencias' => 'nullable|string|max:300',
            'envio_lat' => 'nullable|numeric|between:-90,90',
            'envio_lng' => 'nullable|numeric|between:-180,180',
            'envio_costo' => 'nullable|numeric|min:0|max:999999',
            'repartidor_id' => 'nullable|integer',
            'notas' => 'nullable|string|max:2000',
        ], [
            'productos.required' => 'Agrega al menos un producto.',
        ]);
        foreach ($d['productos'] as $f) abort_unless(Producto::whereKey($f['producto_id'])->exists(), 422, 'Ese producto ya no existe.');
        if ($d['pago'] !== 'contado' && empty($d['cliente_id']) && (blank($d['cliente_nombre'] ?? null) || blank($d['cliente_telefono'] ?? null))) {
            return back()->withErrors(['cliente_nombre' => 'Para vender a crédito o con abonos, captura el nombre y el teléfono del cliente.'])->withInput();
        }
        if (($d['entrega'] === 'envio') && ($d['datos'] ?? 'cliente') === 'cliente' && empty($d['cliente_id']) && blank($d['cliente_telefono'] ?? null)) {
            return back()->withErrors(['cliente_telefono' => 'Para mandarle el enlace de sus datos de envío, captura su WhatsApp. O llena tú los datos.'])->withInput();
        }
        if (! empty($d['repartidor_id']) && ! User::whereKey($d['repartidor_id'])->exists()) $d['repartidor_id'] = null;
        $d['guardar_cliente'] = $request->boolean('guardar_cliente');
        $d['con_iva'] = $request->boolean('con_iva');
        if (! empty($d['cliente_id']) && ! Cliente::whereKey($d['cliente_id'])->exists()) $d['cliente_id'] = null;

        $v = Registrar::crear($d, $request->user());
        $msg = 'Venta ' . $v->folio . ' registrada por ' . Venta::dinero($v->total) . '.';
        return redirect()->route('admin.pedidos.show', [$v, 'nueva' => 1])->with('ok', $msg);
    }

    public function show(Request $request, Venta $venta)
    {
        $venta->load(['conceptos', 'pagos.usuario:id,name', 'cliente', 'vendedor:id,name', 'repartidor:id,name', 'presupuesto:id,folio']);
        return view('admin.pedidos.show', [
            'v' => $venta,
            'equipo' => User::where('activo', true)->orderBy('name')->get(['id', 'name']),
            'envios' => Envios::ajustes(),
            'nueva' => $request->boolean('nueva'),
        ]);
    }

    public function abonar(Request $request, Venta $venta)
    {
        abort_if($venta->estado === 'cancelada', 422);
        $d = $request->validate(['monto' => 'required|numeric|min:0.01', 'forma' => ['required', Rule::in(array_keys(Venta::FORMAS))], 'fecha' => 'nullable|date', 'nota' => 'nullable|string|max:200']);
        Registrar::abonar($venta, (float) $d['monto'], $d['forma'], $request->user(), $d['fecha'] ?? null, $d['nota'] ?? null);
        return back()->with('ok', $venta->fresh()->pagada ? 'Pago registrado: la venta quedó pagada.' : 'Abono registrado. Resta ' . Venta::dinero($venta->fresh()->saldo) . '.');
    }

    public function estado(Request $request, Venta $venta)
    {
        abort_if($venta->estado === 'cancelada', 422);
        $d = $request->validate(['estado' => ['required', Rule::in(['por_surtir', 'listo', 'en_camino', 'entregada'])]]);
        $venta->update(['estado' => $d['estado'], 'entregada_at' => $d['estado'] === 'entregada' ? ($venta->entregada_at ?? now()) : null]);
        return back()->with('ok', $venta->folio . ': ' . mb_strtolower(Venta::ESTADOS[$d['estado']]['texto']) . '.');
    }

    /** El vendedor llena o corrige los datos de envío, el costo o el repartidor */
    public function envio(Request $request, Venta $venta)
    {
        abort_if($venta->estado === 'cancelada', 422);
        $d = $request->validate([
            'envio_nombre' => 'nullable|string|max:160', 'envio_telefono' => 'nullable|string|max:30',
            'envio_direccion' => 'required|string|max:300', 'envio_referencias' => 'nullable|string|max:300',
            'envio_lat' => 'nullable|numeric|between:-90,90', 'envio_lng' => 'nullable|numeric|between:-180,180',
            'envio_costo' => 'nullable|numeric|min:0|max:999999', 'repartidor_id' => 'nullable|integer',
        ], ['envio_direccion.required' => 'Escribe la dirección de entrega.']);
        if (isset($d['envio_costo']) && $d['envio_costo'] !== null && (float) $d['envio_costo'] !== $venta->envio_costo) {
            $venta->envio_costo = round((float) $d['envio_costo'], 2);
            $venta->envio_costo_manual = true;
        }
        $venta->repartidor_id = ! empty($d['repartidor_id']) && User::whereKey($d['repartidor_id'])->exists() ? (int) $d['repartidor_id'] : null;
        Envios::guardarDatos($venta, ['nombre' => $d['envio_nombre'] ?? null, 'telefono' => $d['envio_telefono'] ?? null, 'direccion' => $d['envio_direccion'],
            'referencias' => $d['envio_referencias'] ?? null, 'lat' => $d['envio_lat'] ?? null, 'lng' => $d['envio_lng'] ?? null], 'vendedor');
        return back()->with('ok', 'Datos de envío guardados' . ($venta->envio_km ? ' · ' . $venta->envio_km . ' km' : '') . ' · envío ' . Venta::dinero($venta->envio_costo) . '.');
    }

    public function cancelar(Venta $venta)
    {
        abort_if($venta->estado === 'cancelada', 422);
        Registrar::cancelar($venta);
        return back()->with('ok', 'Venta ' . $venta->folio . ' cancelada' . ($venta->presupuesto_id ? '.' : '; la mercancía regresó al inventario.'));
    }

    public function nota(Venta $venta)
    {
        return self::pdf($venta)->stream('nota-' . $venta->folio . '.pdf');
    }

    public static function pdf(Venta $v): \Barryvdh\DomPDF\PDF
    {
        $v->loadMissing(['conceptos', 'pagos', 'cliente']);
        return Pdf::loadView('pedidos.nota', ['v' => $v])->setPaper('a4');
    }

    /** Cotización aceptada solo de productos → venta (sin proyecto) */
    public function desdeCotizacion(Request $request, Presupuesto $presupuesto)
    {
        abort_unless($presupuesto->estado === 'aceptada', 422, 'La cotización tiene que estar aceptada.');
        $v = Registrar::desdeCotizacion($presupuesto, $request->user());
        return redirect()->route('admin.pedidos.show', $v)->with('ok', 'Listo: la cotización ' . $presupuesto->folio . ' ahora es la venta ' . $v->folio . '. Registra el pago y la entrega aquí.');
    }

    public function ajustes()
    {
        return view('admin.pedidos.ajustes', ['e' => Envios::ajustes(), 'conIva' => (bool) Cuentas::actual()->ajuste('ventas.iva', true)]);
    }

    public function guardarAjustes(Request $request)
    {
        $d = $request->validate([
            'modo' => ['required', Rule::in(array_keys(Envios::MODOS))],
            'fijo' => 'nullable|numeric|min:0|max:99999', 'base' => 'nullable|numeric|min:0|max:99999',
            'por_km' => 'nullable|numeric|min:0|max:9999', 'minimo' => 'nullable|numeric|min:0|max:99999',
            'lat' => 'nullable|numeric|between:-90,90', 'lng' => 'nullable|numeric|between:-180,180', 'direccion' => 'nullable|string|max:300',
        ]);
        if ($d['modo'] === 'km' && (empty($d['lat']) || empty($d['lng']))) {
            return back()->withErrors(['lat' => 'Para cobrar por kilómetro, marca en el mapa dónde está tu negocio.'])->withInput();
        }
        $c = Cuentas::actual();
        $a = $c->ajustes ?? [];
        $a['envios'] = ['modo' => $d['modo'], 'fijo' => (float) ($d['fijo'] ?? 0), 'base' => (float) ($d['base'] ?? 0), 'por_km' => (float) ($d['por_km'] ?? 0),
            'minimo' => (float) ($d['minimo'] ?? 0), 'factor' => 1.3, 'lat' => isset($d['lat']) ? (float) $d['lat'] : null, 'lng' => isset($d['lng']) ? (float) $d['lng'] : null,
            'direccion' => $d['direccion'] ?? null];
        $a['ventas'] = ['iva' => $request->boolean('iva')];
        $c->update(['ajustes' => $a]);
        return back()->with('ok', 'Ajustes de ventas y envíos guardados.');
    }
}
