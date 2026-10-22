<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrdenCompra;
use App\Models\Proveedor;
use Illuminate\Http\Request;

class ProveedorController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $rubro = trim((string) $request->query('rubro'));

        $proveedores = Proveedor::query()
            ->withCount(['ordenes', 'ordenes as abiertas_count' => fn ($w) => $w->visibles()->abiertas()])
            ->withMax('ordenes', 'fecha')
            ->when($q, fn ($query) => $query->where(fn ($w) => $w
                ->where('empresa', 'like', "%$q%")
                ->orWhere('nombre', 'like', "%$q%")
                ->orWhere('email', 'like', "%$q%")
                ->orWhere('telefono', 'like', "%$q%")
                ->orWhere('rubro', 'like', "%$q%")))
            ->when($rubro, fn ($query) => $query->where('rubro', $rubro))
            ->orderBy('empresa')
            ->paginate(25)
            ->withQueryString();

        $rubros = Proveedor::whereNotNull('rubro')->selectRaw('rubro, count(*) as n')->groupBy('rubro')->orderBy('rubro')->pluck('n', 'rubro');

        return view('admin.proveedores.index', compact('proveedores', 'q', 'rubro', 'rubros'));
    }

    public function create()
    {
        return view('admin.proveedores.form', ['proveedor' => new Proveedor(), 'rubros' => Proveedor::rubros()]);
    }

    public function store(Request $request)
    {
        $p = Proveedor::create($this->validar($request));
        return redirect()->route('admin.proveedores.show', $p)->with('ok', 'Proveedor guardado.');
    }

    public function show(Proveedor $proveedor)
    {
        $ordenes = $proveedor->ordenes()->with(['conceptos', 'proyecto.cliente', 'archivos'])->get();
        $proyectos = $proveedor->proyectos()->get();
        $porPagar = $ordenes->whereIn('estado', ['entregada', 'facturada'])->sum(fn ($o) => $o->total_final);
        $pagado = $ordenes->where('estado', 'pagada')->sum(fn ($o) => $o->total_final);

        return view('admin.proveedores.show', compact('proveedor', 'ordenes', 'proyectos', 'porPagar', 'pagado'));
    }

    public function edit(Proveedor $proveedor)
    {
        return view('admin.proveedores.form', ['proveedor' => $proveedor, 'rubros' => Proveedor::rubros()]);
    }

    public function update(Request $request, Proveedor $proveedor)
    {
        $proveedor->update($this->validar($request));
        return redirect()->route('admin.proveedores.show', $proveedor)->with('ok', 'Cambios guardados.');
    }

    public function destroy(Proveedor $proveedor)
    {
        $n = $proveedor->ordenes()->count();
        abort_if($n && ! request()->boolean('confirmar'), 422);
        $proveedor->delete();
        return redirect()->route('admin.proveedores.index')->with('ok', 'Proveedor eliminado' . ($n ? " junto con sus $n " . ($n === 1 ? 'orden' : 'órdenes') . ' de compra.' : '.'));
    }

    private function validar(Request $request): array
    {
        $d = $request->validate([
            'empresa'      => 'required|string|max:160',
            'nombre'       => 'nullable|string|max:160',
            'rubro'        => 'nullable|string|max:80',
            'telefono'     => 'nullable|string|max:40',
            'email'        => 'nullable|email|max:160',
            'rfc'          => 'nullable|string|max:20',
            'razon_social' => 'nullable|string|max:200',
            'direccion'    => 'nullable|string|max:300',
            'notas'        => 'nullable|string|max:3000',
        ], ['empresa.required' => 'Escribe el nombre de la empresa o del proveedor.', 'email.email' => 'Revisa el correo.']);
        $d['rfc'] = isset($d['rfc']) ? mb_strtoupper(trim($d['rfc'])) : null;
        // Mismo rubro escrito distinto ("impresion", "Impresión ") queda como el que ya existe
        if (filled($d['rubro'] ?? null)) {
            $d['rubro'] = trim($d['rubro']);
            $igual = collect(Proveedor::rubros())->first(fn ($r) => \Illuminate\Support\Str::ascii(mb_strtolower($r)) === \Illuminate\Support\Str::ascii(mb_strtolower($d['rubro'])));
            $d['rubro'] = $igual ?: $d['rubro'];
        }
        return $d;
    }
}
