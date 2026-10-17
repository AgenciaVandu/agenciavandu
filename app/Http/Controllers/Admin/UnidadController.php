<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sucursal;
use App\Models\Unidad;
use App\Models\UnidadFoto;
use App\Models\User;
use App\Support\Cuentas;
use App\Support\FotoUnidad;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Unidades del lote: ficha, fotos, precio y su página pública */
class UnidadController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            abort_unless(Cuentas::actual()?->tiene('autos'), 404);
            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $estado = array_key_exists($request->query('estado'), Unidad::ESTADOS) ? $request->query('estado') : null;
        $sucursal = (int) $request->query('sucursal') ?: null;

        $unidades = Unidad::with(['fotos', 'sucursal', 'vendedor'])->buscar($q)
            ->when($estado, fn ($w) => $w->where('estado', $estado), fn ($w) => $w->where('estado', '!=', 'vendido'))
            ->when($sucursal, fn ($w) => $w->where('sucursal_id', $sucursal))
            ->latest()->paginate(24)->withQueryString();

        $conteo = Unidad::selectRaw('estado, count(*) as n')->groupBy('estado')->pluck('n', 'estado');
        $valor = (float) Unidad::where('estado', '!=', 'vendido')->sum('precio');
        return view('admin.unidades.index', ['unidades' => $unidades, 'q' => $q, 'estado' => $estado, 'sucursal' => $sucursal, 'conteo' => $conteo, 'valor' => $valor,
            'sucursales' => Sucursal::orderBy('nombre')->get(), 'admin' => $request->user()->puede('unidades')]);
    }

    public function create(Request $request)
    {
        return view('admin.unidades.form', $this->datos(new Unidad(['vendedor_id' => $request->user()->id, 'sucursal_id' => $request->user()->sucursal_id])));
    }

    public function store(Request $request)
    {
        $u = Unidad::create($this->validar($request));
        $error = $this->fotos($request, $u);
        return redirect()->route('admin.unidades.show', $u)->with($error ? 'error' : 'ok', $error ?: 'Unidad guardada. Ya tiene su página para compartir.');
    }

    public function show(Request $request, Unidad $unidad)
    {
        $unidad->load(['fotos', 'sucursal', 'vendedor', 'venta.cliente']);
        return view('admin.unidades.show', [
            'u' => $unidad, 'enlace' => $unidad->url . '?v=' . $request->user()->codigo_vendedor,
            'veCosto' => $request->user()->puede('finanzas'), 'puedeVender' => $request->user()->puede('cobranza'),
        ]);
    }

    public function edit(Unidad $unidad)
    {
        return view('admin.unidades.form', $this->datos($unidad->load('fotos')));
    }

    public function update(Request $request, Unidad $unidad)
    {
        $d = $this->validar($request, $unidad);
        $unidad->update($d);
        $error = $this->fotos($request, $unidad);
        return redirect()->route('admin.unidades.show', $unidad)->with($error ? 'error' : 'ok', $error ?: 'Cambios guardados.');
    }

    public function destroy(Unidad $unidad)
    {
        if ($unidad->venta) return back()->with('error', 'Esta unidad ya tiene una venta registrada; no se puede eliminar.');
        foreach ($unidad->fotos as $f) FotoUnidad::quitar($f);
        $unidad->delete();
        return redirect()->route('admin.unidades.index')->with('ok', 'Unidad eliminada.');
    }

    public function quitarFoto(Unidad $unidad, UnidadFoto $foto)
    {
        abort_unless($foto->unidad_id === $unidad->id, 404);
        FotoUnidad::quitar($foto);
        return back()->with('ok', 'Foto eliminada.');
    }

    /** La primera es la portada */
    public function ordenFotos(Request $request, Unidad $unidad)
    {
        $ids = array_map('intval', (array) $request->input('orden', []));
        foreach ($ids as $i => $id) UnidadFoto::where('unidad_id', $unidad->id)->whereKey($id)->update(['orden' => $i]);
        return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('ok', 'Portada actualizada.');
    }

    private function datos(Unidad $u): array
    {
        return [
            'u' => $u, 'sucursales' => Sucursal::where('activa', true)->orderBy('nombre')->get(),
            'vendedores' => User::where('activo', true)->orderBy('name')->get(['id', 'name']),
            'marcas' => Unidad::distinct()->orderBy('marca')->pluck('marca'),
            'veCosto' => auth()->user()->puede('finanzas'),
        ];
    }

    private function validar(Request $request, ?Unidad $u = null): array
    {
        $d = $request->validate([
            'marca'       => 'required|string|max:60',
            'modelo'      => 'required|string|max:80',
            'version'     => 'nullable|string|max:80',
            'anio'        => 'nullable|integer|min:1950|max:' . (now()->year + 1),
            'tipo'        => 'nullable|string|max:30',
            'color'       => 'nullable|string|max:40',
            'kilometraje' => 'nullable|integer|min:0|max:2000000',
            'transmision' => 'nullable|string|max:20',
            'combustible' => 'nullable|string|max:20',
            'placas'      => 'nullable|string|max:20',
            'serie'       => 'nullable|string|max:40',
            'precio'      => 'required|numeric|min:0|max:99999999',
            'costo'       => 'nullable|numeric|min:0|max:99999999',
            'descripcion' => 'nullable|string|max:5000',
            'ficha'       => 'nullable|string|max:5000',
            'estado'      => ['nullable', Rule::in(['disponible', 'apartado'])],
            'publico'     => 'boolean',
            'sucursal_id' => 'nullable|integer',
            'vendedor_id' => 'nullable|integer',
            'fotos'       => 'array|max:20',
            'fotos.*'     => 'image|mimes:jpg,jpeg,png,webp|max:12288',
        ], [
            'marca.required' => 'Escribe la marca.', 'modelo.required' => 'Escribe el modelo.', 'precio.required' => 'Escribe el precio.',
            'fotos.*.image' => 'Las fotos deben ser JPG, PNG o WebP.', 'fotos.*.max' => 'Cada foto puede pesar hasta 12 MB.', 'fotos.max' => 'Sube hasta 20 fotos a la vez.',
        ]);
        unset($d['fotos']);
        $d['placas'] = isset($d['placas']) ? mb_strtoupper(trim($d['placas'])) : null;
        $d['serie'] = isset($d['serie']) ? mb_strtoupper(trim($d['serie'])) : null;
        $d['publico'] = $request->boolean('publico');
        $d['sucursal_id'] = ! empty($d['sucursal_id']) && Sucursal::whereKey($d['sucursal_id'])->exists() ? (int) $d['sucursal_id'] : null;
        $d['vendedor_id'] = ! empty($d['vendedor_id']) && User::whereKey($d['vendedor_id'])->exists() ? (int) $d['vendedor_id'] : null;
        // Vendida no se cambia aquí: se cancela la venta en Cobranza
        if ($u?->estado === 'vendido') unset($d['estado']);
        else $d['estado'] = $d['estado'] ?? 'disponible';
        if (! $request->user()->puede('finanzas')) unset($d['costo']);
        return $d;
    }

    private function fotos(Request $request, Unidad $u): ?string
    {
        foreach ((array) $request->file('fotos', []) as $f) {
            try {
                FotoUnidad::agregar($u, $f);
            } catch (\RuntimeException $e) {
                return $e->getMessage();
            }
        }
        return null;
    }
}
