<?php

namespace App\Http\Controllers\Admin\Lealtad;

use App\Models\LealtadCampana;
use App\Models\LealtadPremio;
use App\Support\Lealtad\Tablero;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Campañas del restaurante: vigencia, meta de visitas y premios que se desbloquean */
class CampanasController extends LealtadController
{
    public function index()
    {
        $campanas = LealtadCampana::with('premios')->orderByDesc('activa')->orderByDesc('termina_el')->get();
        $stats = collect(Tablero::datos()['campanas'])->keyBy(fn ($x) => $x['campana']->id);
        return view('admin.lealtad.campanas', [
            'grupos' => [
                'vigente' => $campanas->filter(fn ($k) => $k->estado === 'vigente'),
                'proxima' => $campanas->filter(fn ($k) => $k->estado === 'proxima'),
                'canje' => $campanas->filter(fn ($k) => $k->estado === 'canje'),
                'otras' => $campanas->filter(fn ($k) => in_array($k->estado, ['terminada', 'pausada'], true)),
            ],
            'stats' => $stats,
        ]);
    }

    public function create()
    {
        $tz = config('vandu.zona_horaria');
        return view('admin.lealtad.campana-form', ['k' => new LealtadCampana([
            'inicia_el' => now($tz)->toDateString(), 'termina_el' => now($tz)->addMonths(2)->toDateString(), 'meta_visitas' => 10, 'dias_canje' => 15, 'activa' => true,
        ]), 'premios' => [['visitas' => 3, 'nombre' => '', 'descripcion' => ''], ['visitas' => 6, 'nombre' => '', 'descripcion' => ''], ['visitas' => 10, 'nombre' => '', 'descripcion' => '']]]);
    }

    public function store(Request $request)
    {
        $k = DB::transaction(function () use ($request) {
            [$d, $premios] = $this->validar($request);
            $k = LealtadCampana::create($d);
            foreach ($premios as $p) $k->premios()->create($p + ['cuenta_id' => $k->cuenta_id]);
            return $k;
        });
        return redirect()->route('admin.lealtad.campanas')->with('ok', "Campaña «{$k->nombre}» creada. Las visitas cuentan del " . $k->vigencia_texto . '.');
    }

    public function edit(LealtadCampana $campana)
    {
        return view('admin.lealtad.campana-form', ['k' => $campana, 'premios' => $campana->premios->map(fn ($p) => $p->only(['id', 'visitas', 'nombre', 'descripcion']))->all()]);
    }

    public function update(Request $request, LealtadCampana $campana)
    {
        DB::transaction(function () use ($request, $campana) {
            [$d, $premios] = $this->validar($request);
            $campana->update($d);
            // Los premios que ya alguien canjeó no se borran (se conservan para el historial)
            $quedan = collect($premios)->pluck('id')->filter()->all();
            $campana->premios()->whereNotIn('id', $quedan)->get()->each(function (LealtadPremio $p) {
                if (\App\Models\LealtadCanje::where('premio_id', $p->id)->exists()) {
                    throw ValidationException::withMessages(['premios' => "«{$p->nombre}» ya se canjeó; no se puede quitar."]);
                }
                $p->delete();
            });
            foreach ($premios as $p) {
                $id = $p['id'] ?? null; unset($p['id']);
                $id ? $campana->premios()->whereKey($id)->update($p) : $campana->premios()->create($p + ['cuenta_id' => $campana->cuenta_id]);
            }
        });
        return redirect()->route('admin.lealtad.campanas')->with('ok', 'Campaña guardada.');
    }

    public function estado(LealtadCampana $campana)
    {
        $campana->update(['activa' => ! $campana->activa]);
        return back()->with('ok', $campana->activa ? "«{$campana->nombre}» está activa otra vez." : "«{$campana->nombre}» quedó en pausa: no suma visitas ni se canjean sus premios.");
    }

    public function destroy(LealtadCampana $campana)
    {
        if (\App\Models\LealtadCanje::whereIn('premio_id', $campana->premios->pluck('id'))->exists()) {
            return back()->with('error', 'Esta campaña ya tiene premios canjeados. Mejor ponla en pausa para conservar el historial.');
        }
        $campana->delete();
        return redirect()->route('admin.lealtad.campanas')->with('ok', 'Campaña eliminada.');
    }

    private function validar(Request $request): array
    {
        $d = $request->validate([
            'nombre'               => 'required|string|max:120',
            'descripcion'          => 'nullable|string|max:600',
            'inicia_el'            => 'required|date',
            'termina_el'           => 'required|date|after_or_equal:inicia_el',
            'meta_visitas'         => 'required|integer|min:1|max:200',
            'dias_canje'           => 'required|integer|min:0|max:180',
            'premios'              => 'required|array|min:1|max:12',
            'premios.*.id'         => 'nullable|integer',
            'premios.*.visitas'    => 'required|integer|min:1|max:200',
            'premios.*.nombre'     => 'required|string|max:120',
            'premios.*.descripcion' => 'nullable|string|max:300',
        ], [
            'nombre.required' => 'Ponle nombre a la campaña.',
            'termina_el.after_or_equal' => 'La campaña no puede terminar antes de empezar.',
            'premios.required' => 'Agrega al menos un premio.',
            'premios.*.nombre.required' => 'Cada premio necesita un nombre.',
            'premios.*.visitas.required' => 'Indica en qué visita se desbloquea cada premio.',
        ]);
        $premios = collect($d['premios'])->map(fn ($p) => ['id' => $p['id'] ?? null, 'visitas' => (int) $p['visitas'], 'nombre' => trim($p['nombre']), 'descripcion' => $p['descripcion'] ?? null])
            ->sortBy('visitas')->values();
        if ($premios->max('visitas') > $d['meta_visitas']) {
            throw ValidationException::withMessages(['premios' => 'Ningún premio puede pedir más visitas que la meta (' . $d['meta_visitas'] . ').']);
        }
        unset($d['premios']);
        $d['activa'] = $request->boolean('activa', true);
        return [$d, $premios->all()];
    }
}
