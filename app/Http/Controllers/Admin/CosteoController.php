<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Costeo;
use App\Support\Cuentas;
use Illuminate\Http\Request;

/** Configuración → Costeo: los costos que este negocio captura al cotizar (cada giro costea distinto) */
class CosteoController extends Controller
{
    public function show()
    {
        $c = Cuentas::actual();
        return view('admin.costeo', [
            'componentes' => Costeo::componentes(),
            'utilidad'    => Costeo::utilidad(),
            'propio'      => ! empty(($c->ajustes ?? [])['costeo']['componentes'] ?? null),
            'fabrica'     => Cuentas::costeoDeFabrica($c),
        ]);
    }

    public function update(Request $request)
    {
        $d = $request->validate([
            'utilidad'                 => 'required|numeric|min:0|max:1000',
            'componentes'              => 'required|array|min:1|max:30',
            'componentes.*.clave'      => 'nullable|string|max:40',
            'componentes.*.nombre'     => 'required|string|max:60',
            'componentes.*.modo'       => 'required|in:unidad,fijo',
            'componentes.*.clase'      => 'required|in:tercero,operativo',
        ], [
            'componentes.required'          => 'Deja al menos un costo.',
            'componentes.*.nombre.required' => 'Cada costo necesita un nombre.',
        ]);
        $this->guardar(['utilidad' => (float) $d['utilidad'], 'componentes' => Costeo::limpiar($d['componentes'])]);
        return redirect()->route('admin.costeo')->with('ok', 'Costeo guardado. Las cotizaciones nuevas ya usan estos costos; las anteriores conservan los suyos.');
    }

    /** Vuelve a los costos de fábrica de su giro */
    public function restaurar()
    {
        $this->guardar(null);
        return redirect()->route('admin.costeo')->with('ok', 'Costeo restaurado a los valores de fábrica.');
    }

    private function guardar(?array $costeo): void
    {
        $c = Cuentas::actual();
        $a = $c->ajustes ?? [];
        if ($costeo) $a['costeo'] = $costeo; else unset($a['costeo']);
        $c->update(['ajustes' => $a]);
        Cuentas::activar($c->fresh());
    }
}
