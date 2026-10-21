<?php

namespace App\Http\Controllers\Admin\Fitness;

use App\Models\Acceso;
use App\Models\AccesoDispositivo;
use App\Models\Sucursal;
use App\Support\Fitness\Ajustes;
use App\Support\Fitness\Torniquete;
use Illuminate\Http\Request;

/** Torniquetes con lector (Control iD iDFace): alta, dirección para configurarlo, prueba y registro de entradas */
class TorniquetesController extends FitnessController
{
    public function index(Request $request)
    {
        $tz = config('vandu.zona_horaria');
        $hoy = now($tz)->startOfDay()->utc();
        $negados = $request->query('ver') === 'negados';
        return view('admin.fitness.torniquetes', [
            'dispositivos' => AccesoDispositivo::with('sucursal')->orderBy('nombre')->get(),
            'sucursales'   => Sucursal::orderBy('nombre')->get(),
            'accesos'      => Acceso::with(['cliente', 'dispositivo', 'reservacion.clase'])->when($negados, fn ($q) => $q->where('permitido', false))
                                ->latest('id')->limit(60)->get(),
            'hoy'          => ['entradas' => Acceso::where('created_at', '>=', $hoy)->where('permitido', true)->whereIn('motivo', ['adelante', 'reingreso'])->count(),
                               'negados' => Acceso::where('created_at', '>=', $hoy)->where('permitido', false)->count()],
            'negados'      => $negados,
            'ventana'      => [(int) Ajustes::get('checkin_antes'), (int) Ajustes::get('checkin_despues')],
        ]);
    }

    public function store(Request $request)
    {
        $d = $this->validar($request);
        AccesoDispositivo::create($d);
        return back()->with('ok', 'Torniquete agregado. Copia su dirección en el iDFace.');
    }

    public function update(Request $request, AccesoDispositivo $dispositivo)
    {
        $dispositivo->update($this->validar($request) + ['reingreso' => $request->boolean('reingreso'), 'credencial_fija' => $request->boolean('credencial_fija'), 'activo' => $request->boolean('activo')]);
        return back()->with('ok', 'Torniquete guardado.');
    }

    /** Si la dirección se filtró: la anterior deja de funcionar y hay que poner la nueva en el equipo */
    public function token(AccesoDispositivo $dispositivo)
    {
        $dispositivo->update(['token' => AccesoDispositivo::nuevoToken(), 'ultimo_contacto_at' => null]);
        return back()->with('ok', 'Nueva dirección creada. Escríbela en el equipo: la anterior ya no funciona.');
    }

    public function destroy(AccesoDispositivo $dispositivo)
    {
        $dispositivo->delete();
        return back()->with('ok', 'Torniquete eliminado.');
    }

    /** Simula una lectura, sin el equipo: sirve para probar la regla con el QR del celular de un atleta */
    public function probar(Request $request, AccesoDispositivo $dispositivo)
    {
        $d = $request->validate(['codigo' => 'required|string|max:200']);
        $r = Torniquete::qr($dispositivo, $d['codigo'], 'prueba');
        return back()->with($r['permitido'] ? 'ok' : 'error', ($r['permitido'] ? 'Abriría: ' : 'No abriría: ') . $r['mensaje']);
    }

    private function validar(Request $request): array
    {
        $d = $request->validate([
            'nombre'      => 'required|string|max:80',
            'sucursal_id' => 'nullable|integer',
            'accion'      => 'nullable|string|max:30|regex:/^[a-z_]+$/',
            'parametros'  => 'nullable|string|max:120',
        ]);
        $d['sucursal_id'] = ($d['sucursal_id'] ?? null) && Sucursal::whereKey($d['sucursal_id'])->exists() ? (int) $d['sucursal_id'] : null;
        foreach (['accion' => 'sec_box', 'parametros' => 'id=65793,reason=3'] as $k => $def) $d[$k] = trim((string) ($d[$k] ?? '')) ?: $def;
        return $d;
    }
}
