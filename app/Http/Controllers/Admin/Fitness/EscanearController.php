<?php

namespace App\Http\Controllers\Admin\Fitness;

use App\Models\Clase;
use App\Models\Cliente;
use App\Support\Fitness\Checkin;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** La entrada del gimnasio: escanear la credencial QR del atleta con la cámara del celular */
class EscanearController extends FitnessController
{
    public function show(Request $request)
    {
        $u = $request->user();
        return view('admin.fitness.escanear', [
            'clases' => Checkin::clasesEnVentana($u)->load('coach'),
            'sucursal' => $u->sucursal,
        ]);
    }

    public function checkin(Request $request)
    {
        $d = $request->validate(['codigo' => 'required|string|max:80']);
        return response()->json(Checkin::escanear($d['codigo'], $request->user()));
    }

    /** No había reservado: se inscribe en la clase que elijan y entra */
    public function inscribir(Request $request)
    {
        $d = $request->validate(['cliente_id' => 'required|integer', 'clase_id' => 'required|integer']);
        $c = Cliente::findOrFail($d['cliente_id']);
        $clase = Clase::findOrFail($d['clase_id']);
        try {
            $r = Checkin::inscribirYEntrar($clase, $c, $request->user());
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'tipo' => 'error', 'mensaje' => collect($e->errors())->flatten()->first()], 422);
        }
        return response()->json(['ok' => true, 'tipo' => 'adelante', 'mensaje' => '¡Adelante! Quedó inscrito en ' . $clase->nombre . '.',
            'clase' => ['id' => $clase->id, 'nombre' => $clase->nombre, 'horario' => $clase->horario],
            'atleta' => ['id' => $c->id, 'nombre' => $c->nombre, 'foto' => $c->foto_url, 'iniciales' => $c->iniciales,
                'paquete' => $r->membresia ? $r->membresia->nombre . ' · ' . $r->membresia->fresh()->restantes_texto : null, 'url' => route('admin.clientes.show', [$c, 'ver' => 'atleta'])]]);
    }
}
