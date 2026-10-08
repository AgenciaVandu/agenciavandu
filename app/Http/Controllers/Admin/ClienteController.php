<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));

        $clientes = Cliente::query()
            ->withCount(['presupuestos', 'presupuestos as vigentes_count' => fn ($w) => $w->where('vigente_hasta', '>', now())])
            ->withMax('presupuestos', 'fecha')
            ->when($q, fn ($query) => $query->where(fn ($w) => $w
                ->where('nombre', 'like', "%$q%")
                ->orWhere('empresa', 'like', "%$q%")
                ->orWhere('email', 'like', "%$q%")
                ->orWhere('telefono', 'like', "%$q%")))
            ->orderBy('nombre')
            ->paginate(25)
            ->withQueryString();

        return view('admin.clientes.index', compact('clientes', 'q'));
    }

    public function create()
    {
        return view('admin.clientes.form', ['cliente' => new Cliente()]);
    }

    public function store(Request $request)
    {
        $cliente = Cliente::create($this->validar($request));

        if ($request->boolean('y_cotizar')) {
            return redirect()->route('admin.presupuestos.create', ['cliente' => $cliente->id]);
        }

        return redirect()->route('admin.clientes.show', $cliente)->with('ok', 'Cliente creado.');
    }

    public function show(Cliente $cliente)
    {
        $cliente->load('presupuestos.conceptos');

        return view('admin.clientes.show', compact('cliente'));
    }

    public function edit(Cliente $cliente)
    {
        return view('admin.clientes.form', compact('cliente'));
    }

    public function update(Request $request, Cliente $cliente)
    {
        $cliente->update($this->validar($request));

        return redirect()->route('admin.clientes.show', $cliente)->with('ok', 'Cliente actualizado.');
    }

    public function destroy(Cliente $cliente)
    {
        $cliente->delete();

        return redirect()->route('admin.clientes.index')->with('ok', 'Cliente eliminado junto con sus cotizaciones.');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre'       => 'required|string|max:255',
            'empresa'      => 'nullable|string|max:255',
            'email'        => 'nullable|email|max:255',
            'telefono'     => 'nullable|string|max:30',
            'rfc'          => 'nullable|string|max:13',
            'razon_social' => 'nullable|string|max:255',
            'uso_cfdi'     => 'nullable|string|max:10',
            'notas'        => 'nullable|string|max:5000',
        ]);
    }
}
