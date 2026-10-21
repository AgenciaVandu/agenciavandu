<?php

namespace App\Http\Controllers\Admin\Fitness;

use App\Models\ClaseTipo;
use App\Models\Paquete;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Paquetes de clases con su precio (son productos del catálogo de tipo "paquete") */
class PaquetesController extends FitnessController
{
    public function index()
    {
        $paquetes = Paquete::withCount(['membresias as vigentes_count' => fn ($q) => $q->vigentes()])
            ->orderByDesc('activo')->orderBy('precio')->get();
        return view('admin.fitness.paquetes', ['paquetes' => $paquetes, 'tipos' => ClaseTipo::activos()->ordenados()->get()]);
    }

    public function store(Request $request)
    {
        Paquete::create($this->validar($request));
        return back()->with('ok', 'Paquete creado. Ya lo puedes asignar desde la ficha de cada atleta.');
    }

    public function update(Request $request, Paquete $paquete)
    {
        $paquete->update($this->validar($request));
        return back()->with('ok', "Se guardó {$paquete->nombre}. Las membresías ya asignadas conservan sus condiciones.");
    }

    public function destroy(Paquete $paquete)
    {
        if ($paquete->membresias()->exists()) {
            $paquete->update(['activo' => false]);
            return back()->with('ok', "{$paquete->nombre} ya se vendió, así que solo se desactivó.");
        }
        $paquete->delete();
        return back()->with('ok', 'Paquete eliminado.');
    }

    private function validar(Request $request): array
    {
        $d = $request->validate([
            'nombre'        => 'required|string|max:200',
            'descripcion'   => 'nullable|string|max:1000',
            'precio'        => 'required|numeric|min:0|max:9999999',
            'ilimitado'     => 'nullable|boolean',
            'creditos'      => 'nullable|required_unless:ilimitado,1|integer|min:1|max:1000',
            'vigencia_dias' => 'required|integer|min:1|max:3650',
            'es_prueba'     => 'nullable|boolean',
            'max_por_dia'   => 'nullable|integer|min:1|max:10',
            'tipos_clase'   => 'nullable|array',
            'tipos_clase.*' => ['integer', Rule::exists('clase_tipos', 'id')->where('cuenta_id', \App\Support\Cuentas::id())],
        ], [
            'nombre.required'           => 'Ponle nombre al paquete.',
            'creditos.required_unless'  => 'Indica cuántas clases incluye o márcalo como ilimitado.',
        ]);
        return [
            'nombre' => $d['nombre'], 'descripcion' => $d['descripcion'] ?? null, 'precio' => $d['precio'],
            'creditos' => $request->boolean('ilimitado') ? null : (int) $d['creditos'],
            'vigencia_dias' => (int) $d['vigencia_dias'], 'es_prueba' => $request->boolean('es_prueba'),
            'max_por_dia' => $d['max_por_dia'] ?? null, 'tipos_clase' => array_values(array_map('intval', $d['tipos_clase'] ?? [])) ?: null,
            'activo' => $request->boolean('activo', true),
        ];
    }
}
