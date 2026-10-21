<?php

namespace App\Http\Controllers\Admin\Fitness;

use App\Models\Ejercicio;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Biblioteca de ejercicios para armar los entrenamientos */
class EjerciciosController extends FitnessController
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $cat = $request->query('categoria');
        return view('admin.fitness.ejercicios', [
            'ejercicios' => Ejercicio::when($q, fn ($w) => $w->where('nombre', 'like', "%$q%"))
                ->when($cat, fn ($w) => $w->where('categoria', $cat))->orderBy('categoria')->orderBy('nombre')->get(),
            'categorias' => Ejercicio::categorias(), 'q' => $q, 'categoria' => $cat,
        ]);
    }

    public function store(Request $request)
    {
        Ejercicio::create($this->validar($request));
        return back()->with('ok', 'Ejercicio agregado.');
    }

    public function update(Request $request, Ejercicio $ejercicio)
    {
        $ejercicio->update($this->validar($request));
        return back()->with('ok', "Se guardó {$ejercicio->nombre}.");
    }

    public function destroy(Ejercicio $ejercicio)
    {
        $ejercicio->delete();   // los entrenamientos guardan el nombre, no se pierden
        return back()->with('ok', 'Ejercicio eliminado.');
    }

    /** Para el armador de entrenamientos */
    public function buscar(Request $request)
    {
        return Ejercicio::activos()->orderBy('nombre')->get(['id', 'nombre', 'categoria', 'unidad']);
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre'        => 'required|string|max:100',
            'categoria'     => 'nullable|string|max:60',
            'unidad'        => ['required', Rule::in(array_keys(Ejercicio::UNIDADES))],
            'instrucciones' => 'nullable|string|max:2000',
        ], ['nombre.required' => 'Ponle nombre al ejercicio.']) + ['activo' => $request->boolean('activo', true)];
    }
}
