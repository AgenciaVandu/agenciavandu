<?php

namespace App\Http\Controllers\Admin\Lealtad;

use App\Models\Comensal;
use App\Models\LealtadPremio;
use App\Support\Cuentas;
use App\Support\Lealtad\Codigo;
use App\Support\Lealtad\Programa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

/**
 * Caja / mesero: escanea el QR del comensal (cambia cada minuto) y suma su visita, lo une al programa o le entrega premios.
 * Después de leer el QR se usa un pase firmado de 10 minutos: no se puede actuar sobre alguien sin haber visto su código.
 */
class EscanearController extends LealtadController
{
    public function show()
    {
        return view('admin.lealtad.escanear', ['cuenta' => Cuentas::actual()]);
    }

    public function leer(Request $request)
    {
        $d = $request->validate(['codigo' => 'required|string|max:120']);
        $p = Codigo::leer($d['codigo']);
        if (! $p) {
            return response()->json(['ok' => false, 'mensaje' => Codigo::pareceDeLealtad($d['codigo'])
                ? 'Ese código ya venció. Pídele que abra su QR otra vez.' : 'Ese no es un QR de lealtad.']);
        }
        return response()->json($this->ficha($p) + ['ok' => true, 'pase' => $this->pase($p)]);
    }

    public function unir(Request $request)
    {
        $p = $this->delPase($request);
        Programa::unir(Cuentas::actual(), $p, 'caja', $request->user());
        return response()->json($this->ficha($p) + ['ok' => true, 'pase' => $this->pase($p), 'mensaje' => "{$p->primer_nombre} ya está en el programa."]);
    }

    public function visita(Request $request)
    {
        $p = $this->delPase($request);
        $d = $request->validate(['monto' => 'nullable|numeric|min:0|max:999999']);
        try {
            $r = Programa::visita(Cuentas::actual(), $p, isset($d['monto']) ? (float) $d['monto'] : null, $request->user(), $request->user()->sucursal_id);
        } catch (ValidationException $e) {
            return response()->json($this->ficha($p) + ['ok' => false, 'pase' => $this->pase($p), 'mensaje' => collect($e->errors())->flatten()->first()]);
        }
        $nuevos = $r['nuevos']->map(fn ($x) => $x['premio']->nombre)->all();
        return response()->json($this->ficha($p) + ['ok' => true, 'pase' => $this->pase($p), 'nuevos' => $nuevos,
            'mensaje' => 'Visita registrada' . ($nuevos ? '. ¡Desbloqueó ' . implode(' y ', $nuevos) . '!' : '.')]);
    }

    public function canjear(Request $request)
    {
        $p = $this->delPase($request);
        $d = $request->validate(['premio_id' => 'required|integer']);
        $premio = LealtadPremio::findOrFail($d['premio_id']);
        try {
            Programa::canjear(Cuentas::actual(), $p, $premio, $request->user());
        } catch (ValidationException $e) {
            return response()->json($this->ficha($p) + ['ok' => false, 'pase' => $this->pase($p), 'mensaje' => collect($e->errors())->flatten()->first()]);
        }
        return response()->json($this->ficha($p) + ['ok' => true, 'pase' => $this->pase($p), 'mensaje' => "Entregado: {$premio->nombre}."]);
    }

    /* ---------- ---------- */

    private function pase(Comensal $p): string
    {
        return Crypt::encryptString(json_encode(['c' => $p->id, 'n' => Cuentas::id(), 'e' => now()->addMinutes(10)->timestamp]));
    }

    private function delPase(Request $request): Comensal
    {
        $request->validate(['pase' => 'required|string']);
        try {
            $x = json_decode(Crypt::decryptString($request->input('pase')), true);
        } catch (\Throwable) {
            $x = null;
        }
        abort_unless($x && ($x['n'] ?? null) === Cuentas::id() && ($x['e'] ?? 0) >= now()->timestamp, 422, 'Vuelve a escanear el QR.');
        return Comensal::findOrFail($x['c']);
    }

    /** Lo que ve la caja: si está inscrito, su avance y los premios que puede canjear hoy */
    private function ficha(Comensal $p): array
    {
        $c = Cuentas::actual();
        $inscrito = Programa::inscrito($c, $p);
        $avance = $inscrito ? Programa::avance($c, $p) : collect();
        $hoy = $inscrito && \App\Models\LealtadVisita::where('comensal_id', $p->id)->whereDate('fecha', Programa::hoy())->exists();
        return [
            'comensal' => ['nombre' => $p->nombre, 'iniciales' => $p->iniciales, 'telefono' => $inscrito ? $p->telefono : null],
            'inscrito' => $inscrito,
            'visitaHoy' => $hoy,
            'campanas' => $avance->map(fn ($a) => [
                'nombre' => $a['campana']->nombre, 'visitas' => $a['visitas'], 'meta' => $a['meta'], 'vigente' => $a['campana']->vigente,
                'siguiente' => $a['siguiente'] ? ['nombre' => $a['siguiente']['premio']->nombre, 'faltan' => $a['siguiente']['faltan']] : null,
                'disponibles' => $a['disponibles']->map(fn ($x) => ['id' => $x['premio']->id, 'nombre' => $x['premio']->nombre, 'visitas' => $x['premio']->visitas])->values(),
            ])->values(),
        ];
    }
}
