<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cuenta;
use App\Models\LicenciaPago;
use App\Models\TimbreMovimiento;
use App\Models\TimbrePaquete;
use App\Support\Cuentas;
use App\Support\Facturas\Timbres;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Plataforma › Timbres: los paquetes que vendes y a qué negocio se los asignas */
class TimbresController extends Controller
{
    public function index()
    {
        $usados = Timbres::usadosMes();
        $conMov = TimbreMovimiento::distinct()->pluck('cuenta_id')->all();
        $negocios = Cuenta::where('id', '!=', Cuentas::principalId())->orderByDesc('activa')->orderBy('nombre')->get()
            ->filter(fn ($c) => $c->tiene('facturacion') || in_array($c->id, $conMov))
            ->map(fn ($c) => (object) (['cuenta' => $c, 'usados' => (int) ($usados[$c->id] ?? 0)] + Timbres::resumen($c)))
            ->values();

        $inicio = now(config('vandu.zona_horaria'))->startOfMonth()->utc();
        $mes = TimbreMovimiento::whereIn('tipo', ['compra', 'regalo'])->where('created_at', '>=', $inicio)
            ->selectRaw('tipo, sum(cantidad) as t, sum(coalesce(monto, 0)) as m')->groupBy('tipo')->get()->keyBy('tipo');

        return view('admin.plataforma.timbres', [
            'paquetes' => TimbrePaquete::orderByDesc('activo')->orderBy('orden')->orderBy('timbres')->get(),
            'negocios' => $negocios,
            'vendidos' => (int) ($mes['compra']->t ?? 0),
            'ingresos' => (float) ($mes['compra']->m ?? 0),
            'regalados' => (int) ($mes['regalo']->t ?? 0),
            'bajos' => $negocios->filter(fn ($x) => $x->disponibles <= Timbres::AVISO)->count(),
            'movimientos' => TimbreMovimiento::with('paquete')->whereNotIn('tipo', ['uso', 'uso_plan'])->latest('id')->limit(15)->get(),
            'nombres' => Cuenta::pluck('nombre', 'id'),
        ]);
    }

    private function reglas(): array
    {
        return [
            'nombre' => 'required|string|max:80',
            'timbres' => 'required|integer|min:1|max:1000000',
            'precio' => 'required|numeric|min:0|max:9999999',
        ];
    }

    public function store(Request $request)
    {
        $d = $request->validateWithBag('nuevo', $this->reglas(), ['timbres.required' => 'Pon cuántos timbres trae.', 'precio.required' => 'Pon el precio.']);
        $p = TimbrePaquete::create($d + ['activo' => true, 'orden' => (int) TimbrePaquete::max('orden') + 1]);
        return redirect(route('admin.plataforma.timbres') . '#paquetes')->with('ok', "Paquete “{$p->nombre}” creado. Ya lo puedes asignar.");
    }

    public function update(Request $request, TimbrePaquete $paquete)
    {
        $d = $request->validate($this->reglas() + ['activo' => 'nullable|boolean']);
        $paquete->update(['activo' => $request->boolean('activo')] + $d);
        return redirect(route('admin.plataforma.timbres') . '#paquetes')->with('ok', "Paquete “{$paquete->nombre}” guardado.");
    }

    public function destroy(TimbrePaquete $paquete)
    {
        // Si ya se asignó, se oculta para no perder el historial
        if (TimbreMovimiento::where('paquete_id', $paquete->id)->exists() || \App\Models\LicenciaComprobante::where('paquete_id', $paquete->id)->exists()) {
            $paquete->update(['activo' => false]);
            return redirect(route('admin.plataforma.timbres') . '#paquetes')->with('ok', "“{$paquete->nombre}” ya se había asignado; lo ocultamos para que no se ofrezca más.");
        }
        $paquete->delete();
        return redirect(route('admin.plataforma.timbres') . '#paquetes')->with('ok', "Paquete “{$paquete->nombre}” eliminado.");
    }

    public function asignar(Request $request)
    {
        $d = $request->validate([
            'cuenta_id' => ['required', 'integer', Rule::exists('cuentas', 'id'), Rule::notIn([Cuentas::principalId()])],
            'paquete_id' => 'required|exists:timbre_paquetes,id',
            'como' => 'required|in:cobrado,regalo',
            'monto' => 'nullable|numeric|min:0|max:9999999',
            'forma' => ['nullable', Rule::in(array_keys(LicenciaPago::FORMAS))],
            'nota' => 'nullable|string|max:200',
        ], ['paquete_id.required' => 'Elige un paquete.', 'cuenta_id.required' => 'Elige el negocio.']);
        $c = Cuenta::findOrFail($d['cuenta_id']);
        $p = TimbrePaquete::findOrFail($d['paquete_id']);
        [, $pago] = Timbres::asignar($c, $p, $d['como'] === 'cobrado', isset($d['monto']) ? (float) $d['monto'] : null, $d['forma'] ?? null, $d['nota'] ?? null, $request->user());
        $txt = number_format($p->timbres) . " timbres ({$p->nombre}) a {$c->nombre}";
        return redirect(route('admin.plataforma.timbres'))->with('ok', ($d['como'] === 'regalo' ? "Regalaste $txt." : "Asignaste $txt." . ($pago ? ' El pago quedó registrado con su recibo.' : '')));
    }
}
