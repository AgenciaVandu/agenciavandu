<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cuenta;
use App\Models\Licencia;
use App\Models\LicenciaPago;
use App\Models\Plan;
use App\Support\Cuentas;
use App\Support\Licencias\Estado;
use App\Support\Licencias\Licencias;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Plataforma > licencias: catálogo de planes y extras, el acuerdo de cada negocio y sus pagos */
class LicenciasController extends Controller
{
    private function hoy(): string
    {
        return now(config('vandu.zona_horaria'))->toDateString();
    }

    /* ---------------- Planes y precios ---------------- */

    public function planes()
    {
        return view('admin.plataforma.planes', [
            'planes' => Plan::planes()->get(),
            'extras' => Plan::extras()->get(),
            'usoPlan' => Licencia::selectRaw('plan_id, count(*) as n')->groupBy('plan_id')->pluck('n', 'plan_id'),
            'pago'   => Estado::datosPago(),
        ]);
    }

    public function guardarPlanes(Request $request)
    {
        $d = $request->validate([
            'p'                  => 'required|array',
            'p.*.nombre'         => 'required|string|max:80',
            'p.*.descripcion'    => 'nullable|string|max:255',
            'p.*.precio_mensual' => 'required|numeric|min:0|max:999999',
            'p.*.precio_anual'   => 'nullable|numeric|min:0|max:9999999',
            'p.*.usuarios'       => 'nullable|integer|min:1|max:9999',
            'p.*.sucursales'     => 'nullable|integer|min:0|max:99',
            'p.*.incluye'        => 'nullable|array',
            'p.*.incluye.*'      => Rule::in(PlataformaController::EXTRAS),
            'p.*.activo'         => 'nullable|boolean',
        ]);
        foreach ($d['p'] as $id => $x) {
            $plan = Plan::find($id);
            if (! $plan) continue;
            $plan->update([
                'nombre' => $x['nombre'], 'descripcion' => $x['descripcion'] ?? null, 'precio_mensual' => $x['precio_mensual'],
                'precio_anual' => $plan->tipo === 'plan' ? ($x['precio_anual'] ?? null) : null,
                'usuarios' => $x['usuarios'] ?? null, 'sucursales' => $x['sucursales'] ?? null,
                'incluye' => array_values($x['incluye'] ?? []), 'activo' => (bool) ($x['activo'] ?? false),
            ]);
        }
        // Lo que cambia en un plan llega a los negocios que lo tienen (sucursales y funciones)
        Licencia::with('plan')->whereNotNull('plan_id')->get()->each(fn ($l) => Licencias::aplicar($l));
        return back()->with('ok', 'Planes y precios guardados. Los precios ya acordados con cada negocio no cambian.');
    }

    public function nuevoPlan(Request $request)
    {
        $d = $request->validate(['tipo' => 'required|in:plan,extra', 'nombre' => 'required|string|max:80']);
        $clave = \Illuminate\Support\Str::slug($d['nombre']);
        $base = $clave; $n = 2;
        while (Plan::where('clave', $clave)->exists()) $clave = $base . '-' . $n++;
        Plan::create($d + ['clave' => $clave, 'precio_mensual' => 0, 'orden' => (int) Plan::where('tipo', $d['tipo'])->max('orden') + 1, 'incluye' => [],
            'sucursales' => $d['tipo'] === 'plan' ? 1 : null]);
        return back()->with('ok', ($d['tipo'] === 'plan' ? 'Plan' : 'Extra') . " “{$d['nombre']}” creado. Ponle precio y límites.");
    }

    public function datosPago(Request $request)
    {
        $d = $request->validate([
            'banco' => 'nullable|string|max:60', 'titular' => 'nullable|string|max:120', 'clabe' => ['nullable', 'regex:/^\d{18}$/'],
            'whatsapp' => 'nullable|string|max:30', 'nota' => 'nullable|string|max:200', 'iva' => 'nullable|in:sin,incluido',
        ], ['clabe.regex' => 'La CLABE tiene 18 dígitos.']);
        $c = Cuenta::find(Cuentas::principalId());
        $a = $c->ajustes ?? [];
        data_set($a, 'licencias.iva', $d['iva'] ?? 'sin');
        unset($d['iva']);
        data_set($a, 'licencias.pago', array_map(fn ($v) => $v === null ? null : trim($v), $d) + ['iva' => $a['licencias']['iva']]);
        $c->forceFill(['ajustes' => $a])->save();
        return back()->with('ok', 'Datos de pago guardados. Salen en los avisos de vencimiento y en los correos.');
    }

    /* ---------------- Licencia de un negocio ---------------- */

    public function ficha(Cuenta $cuenta)
    {
        abort_if($cuenta->id === Cuentas::principalId(), 404);
        $l = Licencia::with(['plan', 'pagos.usuario', 'comprobantes.usuario' => fn ($q) => $q->withoutGlobalScope('cuenta')])->where('cuenta_id', $cuenta->id)->first();
        return view('admin.plataforma.licencia', [
            'cuenta' => $cuenta,
            'l'      => $l,
            'e'      => Estado::de($cuenta),
            'planes' => Plan::planes()->get(),
            'extras' => Plan::extras()->get(),
            'uso'    => [
                'usuarios'   => Licencias::usuariosActivos($cuenta),
                'sucursales' => \App\Models\Sucursal::sinCuenta()->where('cuenta_id', $cuenta->id)->count(),
                'ia'         => \App\Support\IA\Anthropic::usadasEsteMes($cuenta),
            ],
        ]);
    }

    public function guardar(Request $request, Cuenta $cuenta)
    {
        abort_if($cuenta->id === Cuentas::principalId(), 404);
        $d = $request->validate([
            'tipo'           => ['required', Rule::in(array_keys(Licencia::TIPOS))],
            'plan_id'        => ['nullable', Rule::exists('planes', 'id')->where('tipo', 'plan')],
            'periodo'        => ['required', Rule::in(array_keys(Licencia::PERIODOS))],
            'precio'         => 'nullable|numeric|min:0|max:9999999',
            'extras'         => 'nullable|array',
            'extras.*.cantidad' => 'nullable|integer|min:0|max:99',
            'extras.*.precio'   => 'nullable|numeric|min:0|max:999999',
            'implementacion' => 'nullable|numeric|min:0|max:9999999',
            'mantenimiento'  => 'nullable|numeric|min:0|max:9999999',
            'inicio'         => 'required|date',
            'vence_el'       => 'nullable|date',
            'dias_gracia'    => 'required|integer|min:0|max:60',
            'dias_lectura'   => 'required|integer|min:0|max:90',
            'notas'          => 'nullable|string|max:2000',
        ], ['plan_id.exists' => 'Elige un plan válido.']);
        if ($d['tipo'] === 'suscripcion' && empty($d['plan_id'])) return back()->withErrors(['plan_id' => 'Una suscripción necesita un plan.'])->withInput();

        $claves = Plan::extras()->pluck('clave', 'id');
        $extras = collect($d['extras'] ?? [])->filter(fn ($x, $id) => isset($claves[$id]) && (int) ($x['cantidad'] ?? 0) > 0)
            ->map(fn ($x, $id) => ['clave' => $claves[$id], 'cantidad' => (int) $x['cantidad'], 'precio' => (float) ($x['precio'] ?? Plan::find($id)->precio_mensual)])->values()->all();

        $l = Licencia::firstOrNew(['cuenta_id' => $cuenta->id]);
        $nueva = ! $l->exists;
        $l->fill([
            'tipo' => $d['tipo'], 'plan_id' => $d['plan_id'] ?? null, 'periodo' => $d['periodo'], 'extras' => $extras,
            'implementacion' => (float) ($d['implementacion'] ?? 0), 'mantenimiento' => $d['tipo'] === 'perpetua' ? (float) ($d['mantenimiento'] ?? 0) : null,
            'inicio' => $d['inicio'], 'vence_el' => $d['vence_el'] ?? null, 'dias_gracia' => $d['dias_gracia'], 'dias_lectura' => $d['dias_lectura'], 'notas' => $d['notas'] ?? null,
        ]);
        $l->setRelation('plan', $l->plan_id ? Plan::find($l->plan_id) : null);
        // Sin precio capturado: el del plan más sus extras
        $l->precio = $request->filled('precio') ? (float) $d['precio'] : ($d['tipo'] === 'cortesia' ? 0 : $l->precioSugerido());
        // Suscripción nueva sin vencimiento: el primer periodo empieza en su inicio (se recorre al registrar el pago)
        if ($nueva && $d['tipo'] === 'suscripcion' && ! $l->vence_el) $l->vence_el = $d['inicio'];
        $l->save();
        Licencias::aplicar($l->fresh('plan'));
        return redirect()->route('admin.plataforma.licencia', $cuenta)->with('ok', $nueva ? "Licencia de {$cuenta->nombre} creada." : 'Licencia guardada.');
    }

    public function pagar(Request $request, Cuenta $cuenta)
    {
        $l = Licencia::where('cuenta_id', $cuenta->id)->firstOrFail();
        $d = $request->validate([
            'fecha'      => 'required|date|before_or_equal:' . now(config('vandu.zona_horaria'))->addDay()->toDateString(),
            'concepto'   => ['required', Rule::in(array_keys(LicenciaPago::CONCEPTOS))],
            'monto'      => 'required|numeric|min:0|max:9999999',
            'periodos'   => 'nullable|integer|min:0|max:36',
            'forma'      => ['nullable', Rule::in(array_keys(LicenciaPago::FORMAS))],
            'referencia' => 'nullable|string|max:120',
        ]);
        $d['periodos'] = (int) ($d['periodos'] ?? 0);
        if (in_array($d['concepto'], ['periodo', 'mantenimiento'], true) && $d['periodos'] < 1) $d['periodos'] = 1;
        $p = Licencias::pagar($l, $d, $request->user());
        $l->refresh();
        return back()->with('ok', 'Pago de $' . number_format($p->monto, 2) . ' registrado.' . ($p->cubre_hasta ? ' Queda pagado hasta el ' . $p->cubre_hasta->locale('es')->isoFormat('D [de] MMMM [de] YYYY') . '.' : ''));
    }

    /** Confirmar el "Ya pagué" del dueño: se registra como pago (recorre el vencimiento) y se le avisa */
    public function confirmar(Request $request, Cuenta $cuenta, \App\Models\LicenciaComprobante $comprobante)
    {
        abort_unless($comprobante->cuenta_id === $cuenta->id && $comprobante->estado === 'pendiente', 404);
        $l = $comprobante->licencia;
        $d = $request->validate([
            'concepto' => ['required', Rule::in(array_keys(LicenciaPago::CONCEPTOS))],
            'periodos' => 'nullable|integer|min:0|max:36',
            'monto'    => 'required|numeric|min:0|max:9999999',
            'forma'    => ['nullable', Rule::in(array_keys(LicenciaPago::FORMAS))],
        ]);
        $d['periodos'] = in_array($d['concepto'], ['periodo', 'mantenimiento'], true) ? max(1, (int) ($d['periodos'] ?? 1)) : 0;
        $p = Licencias::pagar($l, $d + ['fecha' => $comprobante->fecha->toDateString(), 'referencia' => $comprobante->referencia, 'forma' => $d['forma'] ?? 'transferencia'], $request->user());
        $comprobante->update(['estado' => 'aprobado', 'licencia_pago_id' => $p->id]);
        $this->avisar($cuenta, $comprobante, 'Tu pago quedó registrado', 'Confirmamos tu pago de $' . number_format($p->monto, 2) . '.'
            . ($p->cubre_hasta ? ' Tu plan queda pagado hasta el ' . $p->cubre_hasta->locale('es')->isoFormat('D [de] MMMM [de] YYYY') . '.' : '') . ' Tu recibo está en Mi plan.');
        return back()->with('ok', 'Comprobante confirmado y pago registrado.');
    }

    public function rechazar(Request $request, Cuenta $cuenta, \App\Models\LicenciaComprobante $comprobante)
    {
        abort_unless($comprobante->cuenta_id === $cuenta->id && $comprobante->estado === 'pendiente', 404);
        $d = $request->validate(['motivo' => 'required|string|max:300'], ['motivo.required' => 'Escribe por qué no se aceptó, para que el negocio sepa qué hacer.']);
        $comprobante->update(['estado' => 'rechazado', 'motivo' => $d['motivo']]);
        $this->avisar($cuenta, $comprobante, 'Revisamos tu comprobante', 'No pudimos confirmar tu comprobante de $' . number_format($comprobante->monto, 2) . ': ' . $d['motivo']);
        return back()->with('ok', 'Comprobante rechazado; se le avisó al negocio.');
    }

    private function avisar(Cuenta $cuenta, \App\Models\LicenciaComprobante $comp, string $asunto, string $texto): void
    {
        $u = $comp->user_id ? \App\Models\User::sinCuenta()->find($comp->user_id) : null;   // es de otro negocio
        if (! $u?->email) return;
        $url = ($cuenta->url_base ?: rtrim(config('app.url'), '/')) . '/admin/mi-plan';
        try {
            \Illuminate\Support\Facades\Mail::to($u->email, $u->name)->send(new \App\Mail\CorreoVandu(asunto: "$asunto · {$cuenta->nombre}", titulo: $asunto,
                cuerpo: "Hola {$u->primer_nombre},\n\n$texto", boton: 'Ver mi plan', url: $url));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function comprobante(Cuenta $cuenta, \App\Models\LicenciaComprobante $comprobante)
    {
        abort_unless($comprobante->cuenta_id === $cuenta->id && $comprobante->archivo && \Illuminate\Support\Facades\Storage::exists($comprobante->archivo), 404);
        return response()->file(\Illuminate\Support\Facades\Storage::path($comprobante->archivo));
    }

    public function recibo(Cuenta $cuenta, LicenciaPago $pago)
    {
        abort_unless($pago->cuenta_id === $cuenta->id, 404);
        return \App\Support\Licencias\Recibo::pdf($pago);
    }

    public function borrarPago(Cuenta $cuenta, LicenciaPago $pago)
    {
        abort_unless($pago->cuenta_id === $cuenta->id, 404);
        Licencias::borrarPago($pago);
        return back()->with('ok', 'Pago borrado.');
    }
}
