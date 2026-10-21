<?php

namespace App\Http\Controllers\Admin\Fitness;

use App\Models\ClaseTipo;
use Illuminate\Http\Request;

/** Tipos de clase: HYROX Engine, Mobility… con su duración, cupo propuesto y color */
class ClaseTiposController extends FitnessController
{
    public function index()
    {
        return view('admin.fitness.tipos', [
            'tipos' => ClaseTipo::ordenados()->withCount(['clases as futuras_count' => fn ($q) => $q->where('inicia_at', '>', now())])->get(),
        ]);
    }

    public function store(Request $request)
    {
        ClaseTipo::create($this->validar($request) + ['orden' => (int) ClaseTipo::max('orden') + 1]);
        return back()->with('ok', 'Tipo de clase agregado.');
    }

    public function update(Request $request, ClaseTipo $tipo)
    {
        $tipo->update($this->validar($request));
        return back()->with('ok', "Se guardó {$tipo->nombre}.");
    }

    public function destroy(ClaseTipo $tipo)
    {
        if ($tipo->clases()->exists()) {
            $tipo->update(['activo' => false]);
            return back()->with('ok', "{$tipo->nombre} ya tiene clases, así que solo se desactivó (ya no se podrá programar).");
        }
        $tipo->delete();
        return back()->with('ok', 'Tipo de clase eliminado.');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre'      => 'required|string|max:100',
            'descripcion' => 'nullable|string|max:1000',
            'duracion'    => 'required|integer|min:10|max:480',
            'cupo'        => 'required|integer|min:1|max:500',
            'color'       => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'activo'      => 'nullable|boolean',
        ], ['nombre.required' => 'Ponle nombre a la clase.']) + ['activo' => $request->boolean('activo', true)];
    }
}
