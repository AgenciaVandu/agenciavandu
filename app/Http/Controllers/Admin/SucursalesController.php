<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sucursal;
use Illuminate\Http\Request;

/** Sucursales del negocio. Cuántas se pueden tener lo define el plan contratado (Plataforma) */
class SucursalesController extends Controller
{
    public function index()
    {
        return view('admin.sucursales', [
            'sucursales' => Sucursal::withCount(['unidades as disponibles_count' => fn ($q) => $q->where('estado', '!=', 'vendido')])->orderBy('nombre')->get(),
            'limite' => Sucursal::limite(),
        ]);
    }

    public function store(Request $request)
    {
        if (Sucursal::count() >= Sucursal::limite()) {
            return back()->with('error', 'Tu plan incluye ' . Sucursal::limite() . ' ' . (Sucursal::limite() === 1 ? 'sucursal' : 'sucursales') . '. Para agregar otra, pide que te la activen.');
        }
        Sucursal::create($this->validar($request));
        return back()->with('ok', 'Sucursal agregada.');
    }

    public function update(Request $request, Sucursal $sucursal)
    {
        $sucursal->update($this->validar($request));
        return back()->with('ok', 'Sucursal guardada.');
    }

    public function destroy(Sucursal $sucursal)
    {
        if ($sucursal->unidades()->where('estado', '!=', 'vendido')->exists()) {
            return back()->with('error', 'Esa sucursal todavía tiene unidades. Muévelas a otra antes de eliminarla.');
        }
        $sucursal->delete();
        return back()->with('ok', 'Sucursal eliminada.');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre'    => 'required|string|max:100',
            'direccion' => 'nullable|string|max:255',
            'telefono'  => 'nullable|string|max:30',
        ], ['nombre.required' => 'Ponle nombre a la sucursal.']);
    }
}
