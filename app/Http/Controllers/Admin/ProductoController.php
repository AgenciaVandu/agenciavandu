<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\ProductoCategoria;
use App\Models\ProductoMovimiento;
use App\Models\Proveedor;
use App\Support\Cuentas;
use App\Support\FotoProducto;
use App\Support\Inventario;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Catálogo e inventario. Quien administra productos ve costos, márgenes y mueve existencias;
 * quien solo cotiza consulta el catálogo (precio y existencia) y comparte el enlace de cada producto.
 */
class ProductoController extends Controller
{
    public function __construct()
    {
        // Solo los negocios con inventario (giro de comercio o módulo activado)
        $this->middleware(function ($request, $next) {
            abort_unless(Inventario::activo(), 404);
            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $categoria = (int) $request->query('categoria') ?: null;
        $estado = in_array($request->query('estado'), ['bajos', 'agotados', 'inactivos'], true) ? $request->query('estado') : null;
        $admin = Inventario::veCostos();

        $productos = Producto::query()->with('categoria')
            ->buscar($q)
            ->when($categoria, fn ($w) => $w->where('categoria_id', $categoria))
            ->when($estado === 'bajos', fn ($w) => $w->activos()->bajos())
            ->when($estado === 'agotados', fn ($w) => $w->activos()->where('controla_existencia', true)->where('existencia', '<=', 0))
            ->when($estado === 'inactivos', fn ($w) => $w->where('activo', false), fn ($w) => $estado ? $w : $w->activos())
            ->orderBy('nombre')
            ->paginate(30)->withQueryString();

        $categorias = ProductoCategoria::withCount(['productos' => fn ($w) => $w->activos()])->orderBy('orden')->orderBy('nombre')->get();
        $resumen = [
            'activos'   => Producto::activos()->count(),
            'bajos'     => Producto::activos()->bajos()->count(),
            'agotados'  => Producto::activos()->where('controla_existencia', true)->where('existencia', '<=', 0)->count(),
            'inactivos' => $admin ? Producto::where('activo', false)->count() : 0,
            'valor'     => $admin ? (float) Producto::activos()->where('controla_existencia', true)->where('existencia', '>', 0)->selectRaw('SUM(existencia * COALESCE(costo, 0)) as v')->value('v') : 0,
        ];
        $mostrarPrecio = (bool) Cuentas::actual()?->ajuste('catalogo.mostrar_precio', true);
        $ejemplos = $admin ? Producto::where('ejemplo', true)->count() : 0;

        return view('admin.productos.index', compact('productos', 'q', 'categoria', 'estado', 'categorias', 'resumen', 'admin', 'mostrarPrecio', 'ejemplos'));
    }

    public function create(Request $request)
    {
        return view('admin.productos.form', $this->datosForm(new Producto(['unidad' => 'pieza', 'controla_existencia' => true, 'publico' => true, 'activo' => true])));
    }

    public function store(Request $request)
    {
        $d = $this->validar($request);
        $p = Producto::create($d['datos']);
        $this->foto($request, $p);
        if ($p->controla_existencia && ($d['inicial'] ?? 0) > 0) Inventario::mover($p, 'entrada', $d['inicial'], 'Existencia inicial');

        return redirect()->route('admin.productos.show', $p)->with('ok', 'Producto guardado. Ya tiene su página: ' . $p->url);
    }

    public function show(Producto $producto)
    {
        $admin = Inventario::veCostos();
        $producto->load(['categoria', 'proveedor']);
        $movimientos = $producto->movimientos()->with(['usuario', 'presupuesto'])->limit(60)->get();
        $enCotizaciones = \App\Models\PresupuestoConcepto::where('producto_id', $producto->id)
            ->whereHas('presupuesto', fn ($w) => $w->whereIn('estado', ['enviada', 'negociacion']))->sum('cantidad');

        return view('admin.productos.show', [
            'producto' => $producto, 'movimientos' => $movimientos, 'admin' => $admin, 'enCotizaciones' => (float) $enCotizaciones,
            'enlace' => $producto->url . '?v=' . auth()->user()->codigo_vendedor,
        ]);
    }

    public function edit(Producto $producto)
    {
        return view('admin.productos.form', $this->datosForm($producto));
    }

    public function update(Request $request, Producto $producto)
    {
        $d = $this->validar($request, $producto);
        $producto->update($d['datos']);
        $this->foto($request, $producto);
        return redirect()->route('admin.productos.show', $producto)->with('ok', 'Cambios guardados.');
    }

    public function destroy(Producto $producto)
    {
        $vendido = $producto->movimientos()->where('tipo', 'venta')->exists();
        if ($vendido && ! request()->boolean('confirmar')) {
            return back()->with('error', 'Este producto ya aparece en ventas. Mejor desactívalo para conservar su historial.');
        }
        FotoProducto::borrar($producto);
        $producto->delete();
        return redirect()->route('admin.productos.index')->with('ok', 'Producto eliminado.');
    }

    /** Entrada, salida o ajuste por conteo físico */
    public function movimiento(Request $request, Producto $producto)
    {
        $d = $request->validate([
            'tipo'     => 'required|in:entrada,salida,ajuste',
            'cantidad' => 'required|numeric|min:0|max:99999999',
            'nota'     => 'nullable|string|max:255',
        ], ['cantidad.required' => 'Escribe la cantidad.', 'cantidad.min' => 'La cantidad no puede ser negativa.']);
        abort_unless($producto->controla_existencia, 422, 'Este producto no lleva inventario.');

        $cantidad = (float) $d['cantidad'];
        if ($d['tipo'] !== 'ajuste' && $cantidad <= 0) return back()->withErrors(['cantidad' => 'Escribe cuántas ' . $producto->unidadPara(2) . ' entran o salen.']);
        $m = match ($d['tipo']) {
            'entrada' => Inventario::mover($producto, 'entrada', $cantidad, $d['nota'] ?? null),
            'salida'  => Inventario::mover($producto, 'salida', -$cantidad, $d['nota'] ?? null),
            'ajuste'  => Inventario::mover($producto, 'ajuste', 0, $d['nota'] ?? 'Conteo físico', null, $cantidad),
        };
        $producto->refresh();
        return back()->with('ok', $m ? 'Listo: quedan ' . $producto->existencia_texto . '.' : 'La existencia ya era esa, no hubo cambios.');
    }

    /** Para elegir productos al cotizar */
    public function buscar(Request $request)
    {
        $costos = Inventario::veCostos();
        $productos = Producto::activos()->with('categoria')->buscar($request->query('q'))
            ->orderByRaw('CASE WHEN controla_existencia = 1 AND existencia <= 0 THEN 1 ELSE 0 END')->orderBy('nombre')
            ->limit(20)->get();

        return response()->json($productos->map(fn (Producto $p) => array_filter([
            'id' => $p->id, 'clave' => $p->clave, 'nombre' => $p->nombre, 'descripcion' => $p->descripcion,
            'unidad' => $p->unidad, 'precio' => $p->precio, 'categoria' => $p->categoria?->nombre,
            'controla' => $p->controla_existencia, 'existencia' => $p->existencia, 'existencia_texto' => $p->existencia_texto,
            'agotado' => $p->agotado, 'bajo' => $p->bajo_minimo, 'miniatura' => $p->miniatura_url,
            'costo' => $costos ? $p->costo : null,
        ], fn ($v) => $v !== null)));
    }

    /** Diez productos de ejemplo para conocer el módulo */
    public function cargarEjemplos()
    {
        $n = \App\Support\ProductosEjemplo::cargar();
        return redirect()->route('admin.productos.index')->with('ok', $n ? "Listo: $n productos de ejemplo. Cuando cargues los tuyos, quítalos con un clic." : 'Los productos de ejemplo ya estaban cargados.');
    }

    public function quitarEjemplos()
    {
        $n = \App\Support\ProductosEjemplo::quitar();
        return redirect()->route('admin.productos.index')->with('ok', "Se quitaron $n productos de ejemplo.");
    }

    /** Lo que el catálogo público muestra por defecto */
    public function ajustes(Request $request)
    {
        $d = $request->validate(['mostrar_precio' => 'required|boolean']);
        $c = Cuentas::actual();
        $a = $c->ajustes ?? [];
        data_set($a, 'catalogo.mostrar_precio', (bool) $d['mostrar_precio']);
        $c->update(['ajustes' => $a]);
        return back()->with('ok', $d['mostrar_precio'] ? 'Las páginas de producto muestran el precio.' : 'Las páginas de producto ya no muestran el precio; invitan a pedirlo por WhatsApp.');
    }

    private function datosForm(Producto $p): array
    {
        return [
            'producto'    => $p,
            'categorias'  => ProductoCategoria::orderBy('orden')->orderBy('nombre')->pluck('nombre'),
            'proveedores' => Proveedor::orderBy('empresa')->get(['id', 'empresa']),
            'unidades'    => ['pieza', 'caja', 'paquete', 'juego', 'metro', 'm²', 'kg', 'litro', 'servicio', 'hora'],
        ];
    }

    private function validar(Request $request, ?Producto $p = null): array
    {
        $d = $request->validate([
            'nombre'              => 'required|string|max:200',
            'clave'               => ['nullable', 'string', 'max:60', Rule::unique('productos', 'clave')->where('cuenta_id', Cuentas::id())->ignore($p?->id)],
            'codigo_barras'       => ['nullable', 'string', 'max:80', Rule::unique('productos', 'codigo_barras')->where('cuenta_id', Cuentas::id())->ignore($p?->id)],
            'categoria'           => 'nullable|string|max:80',
            'unidad'              => 'nullable|string|max:30',
            'descripcion'         => 'nullable|string|max:5000',
            'precio'              => 'required|numeric|min:0|max:9999999999',
            'costo'               => 'nullable|numeric|min:0|max:9999999999',
            'proveedor_id'        => 'nullable|integer',
            'controla_existencia' => 'boolean',
            'existencia_inicial'  => 'nullable|numeric|min:0|max:99999999',
            'minimo'              => 'nullable|numeric|min:0|max:99999999',
            'foto'                => 'nullable|image|mimes:jpg,jpeg,png,webp|max:12288',
            'quitar_foto'         => 'boolean',
            'publico'             => 'boolean',
            'mostrar_precio'      => 'nullable|in:0,1',
            'activo'              => 'boolean',
        ], [
            'nombre.required' => 'Escribe el nombre del producto.',
            'precio.required' => 'Escribe el precio de venta.',
            'clave.unique'    => 'Ya tienes otro producto con esa clave.',
            'foto.image'      => 'La foto debe ser JPG, PNG o WebP.',
            'foto.max'        => 'La foto pesa más de 12 MB.',
        ]);

        $datos = [
            'nombre'              => trim($d['nombre']),
            'clave'               => filled($d['clave'] ?? null) ? trim($d['clave']) : null,
            'codigo_barras'       => filled($d['codigo_barras'] ?? null) ? trim($d['codigo_barras']) : null,
            'categoria_id'        => ProductoCategoria::porNombre($d['categoria'] ?? null)?->id,
            'unidad'              => trim($d['unidad'] ?? '') ?: 'pieza',
            'descripcion'         => $d['descripcion'] ?? null,
            'precio'              => round((float) $d['precio'], 2),
            'proveedor_id'        => ! empty($d['proveedor_id']) && Proveedor::whereKey($d['proveedor_id'])->exists() ? (int) $d['proveedor_id'] : null,
            'controla_existencia' => $request->boolean('controla_existencia'),
            'minimo'              => isset($d['minimo']) && $d['minimo'] !== '' ? (float) $d['minimo'] : null,
            'publico'             => $request->boolean('publico'),
            'mostrar_precio'      => isset($d['mostrar_precio']) && $d['mostrar_precio'] !== '' ? (bool) $d['mostrar_precio'] : null,
            'activo'              => $p ? $request->boolean('activo') : true,
        ];
        // El costo solo lo cambia quien lo ve
        if (Inventario::veCostos()) $datos['costo'] = isset($d['costo']) && $d['costo'] !== '' ? round((float) $d['costo'], 2) : null;

        return ['datos' => $datos, 'inicial' => (float) ($d['existencia_inicial'] ?? 0)];
    }

    private function foto(Request $request, Producto $p): void
    {
        if ($request->hasFile('foto')) {
            try {
                $p->foto = FotoProducto::guardar($p, $request->file('foto'));
                $p->saveQuietly();
            } catch (\RuntimeException $e) {
                session()->flash('error', $e->getMessage());
            }
        } elseif ($request->boolean('quitar_foto') && $p->foto) {
            FotoProducto::borrar($p);
            $p->foto = null;
            $p->saveQuietly();
        }
    }
}
