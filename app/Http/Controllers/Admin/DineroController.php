<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conciliacion;
use App\Models\CuentaDinero;
use App\Models\MovimientoDinero;
use App\Support\Dinero\Movimientos;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Finanzas > Cuentas: dar de alta caja, banco y terminal, mover dinero entre ellas y conciliar contra el saldo real */
class DineroController extends Controller
{
    private function volver(array $extra = [])
    {
        return redirect()->route('admin.finanzas', array_merge(['seccion' => 'dinero'], $extra));
    }

    private function hoy(): string
    {
        return now(config('vandu.zona_horaria'))->toDateString();
    }

    /** Las tres de casi cualquier negocio en un clic */
    public function basicas(Request $request)
    {
        abort_if(CuentaDinero::exists(), 422);
        $desde = $request->validate(['saldo_desde' => 'nullable|date|before_or_equal:today'])['saldo_desde'] ?? now(config('vandu.zona_horaria'))->startOfMonth()->toDateString();
        $creadas = collect(['efectivo' => 'Caja', 'banco' => 'Banco', 'terminal' => 'Terminal'])->values()->map(fn ($nombre, $i) => CuentaDinero::create([
            'nombre' => $nombre, 'tipo' => array_keys(CuentaDinero::TIPOS)[$i], 'formas' => array_values(CuentaDinero::TIPOS)[$i]['formas'],
            'saldo_inicial' => 0, 'saldo_desde' => $desde, 'orden' => $i,
        ]));
        $n = $creadas->sum(fn ($c) => Movimientos::asignarPendientes($c));
        return $this->volver()->with('ok', 'Listas tu caja, tu banco y tu terminal' . ($n ? " ($n movimientos ya registrados quedaron en su cuenta)" : '') . '. Ahora captura con cuánto empezó cada una en "Editar".');
    }

    public function guardar(Request $request, ?CuentaDinero $cuentaDinero = null)
    {
        if ($cuentaDinero && ! $cuentaDinero->exists) $cuentaDinero = null;   // al crear, el contenedor pasa un modelo vacío
        $d = $request->validate([
            'nombre'        => 'required|string|max:80',
            'tipo'          => ['required', Rule::in(array_keys(CuentaDinero::TIPOS))],
            'banco'         => 'nullable|string|max:60',
            'formas'        => 'nullable|array',
            'formas.*'      => Rule::in(array_keys(CuentaDinero::FORMAS)),
            'saldo_inicial' => 'nullable|numeric|min:-99999999|max:99999999',
            'saldo_desde'   => 'required|date|before_or_equal:today',
            'activa'        => 'nullable|boolean',
        ], ['saldo_desde.before_or_equal' => 'La fecha del saldo inicial no puede ser futura.']);
        $d['saldo_inicial'] = round((float) ($d['saldo_inicial'] ?? 0), 2);
        $d['formas'] = array_values($d['formas'] ?? ($cuentaDinero ? [] : CuentaDinero::TIPOS[$d['tipo']]['formas']));
        $d['activa'] = $cuentaDinero ? $request->boolean('activa', true) : true;

        $c = $cuentaDinero ?? new CuentaDinero(['orden' => (int) CuentaDinero::max('orden') + 1]);
        $c->fill($d)->save();
        // Cada forma de pago va a una sola cuenta
        foreach (CuentaDinero::where('id', '!=', $c->id)->get() as $otra) {
            $quedan = array_values(array_diff((array) $otra->formas, $d['formas']));
            if ($quedan !== (array) $otra->formas) $otra->update(['formas' => $quedan]);
        }
        $n = Movimientos::asignarPendientes($c);
        return $this->volver(['cuenta' => $c->id])->with('ok', ($cuentaDinero ? 'Cuenta actualizada' : 'Cuenta creada') . ($n ? ". Se le asignaron $n movimientos que ya estaban registrados." : '.'));
    }

    public function movimiento(Request $request)
    {
        $ids = CuentaDinero::pluck('id')->all();
        $d = $request->validate([
            'tipo'  => ['required', Rule::in(['traspaso', 'retiro', 'aportacion'])],
            'de_id' => ['nullable', 'required_if:tipo,traspaso,retiro', Rule::in($ids)],
            'a_id'  => ['nullable', 'required_if:tipo,traspaso,aportacion', Rule::in($ids), 'different:de_id'],
            'monto' => 'required|numeric|min:0.01|max:99999999',
            'fecha' => 'required|date|before_or_equal:today',
            'nota'  => 'nullable|string|max:200',
        ], [
            'a_id.different' => 'Elige dos cuentas distintas.',
            'de_id.required_if' => 'Elige de qué cuenta sale.',
            'a_id.required_if' => 'Elige a qué cuenta entra.',
        ]);
        if ($d['tipo'] === 'retiro') $d['a_id'] = null;
        if ($d['tipo'] === 'aportacion') $d['de_id'] = null;
        MovimientoDinero::create($d + ['user_id' => $request->user()->id]);
        return $this->volver()->with('ok', MovimientoDinero::TIPOS[$d['tipo']]['texto'] . ' de $' . number_format($d['monto'], 2) . ' registrado.');
    }

    public function borrarMovimiento(MovimientoDinero $movimiento)
    {
        $movimiento->delete();
        return $this->volver()->with('ok', 'Movimiento borrado.');
    }

    /** Compara el saldo real (estado de cuenta o conteo de caja) con el del panel; si se pide, registra el ajuste */
    public function conciliar(Request $request, CuentaDinero $cuentaDinero)
    {
        $d = $request->validate([
            'fecha'      => 'required|date|before_or_equal:today|after_or_equal:' . $cuentaDinero->saldo_desde->toDateString(),
            'saldo_real' => 'required|numeric|min:-99999999|max:99999999',
            'ajustar'    => 'nullable|boolean',
            'nota'       => 'nullable|string|max:200',
        ], ['fecha.after_or_equal' => 'La fecha no puede ser anterior al saldo inicial de la cuenta.']);

        $panel = (float) (Movimientos::saldos($d['fecha'])[$cuentaDinero->id]['saldo'] ?? $cuentaDinero->saldo_inicial);
        $dif = round((float) $d['saldo_real'] - $panel, 2);
        $ajustar = $request->boolean('ajustar') && abs($dif) >= 0.01;
        Conciliacion::create(['cuenta_dinero_id' => $cuentaDinero->id, 'fecha' => $d['fecha'], 'saldo_real' => round((float) $d['saldo_real'], 2),
            'saldo_panel' => $panel, 'diferencia' => $dif, 'ajustada' => $ajustar, 'nota' => $d['nota'] ?? null, 'user_id' => $request->user()->id]);
        if ($ajustar) {
            MovimientoDinero::create(['tipo' => 'ajuste', 'fecha' => $d['fecha'], 'monto' => abs($dif), 'de_id' => $dif < 0 ? $cuentaDinero->id : null,
                'a_id' => $dif > 0 ? $cuentaDinero->id : null, 'nota' => $d['nota'] ?: 'Conciliación de ' . $cuentaDinero->nombre, 'user_id' => $request->user()->id]);
        }

        $msg = abs($dif) < 0.01 ? "{$cuentaDinero->nombre} cuadra con el panel." :
            ($ajustar ? "Se registró un ajuste de $" . number_format(abs($dif), 2) . " y {$cuentaDinero->nombre} quedó cuadrada." :
             "{$cuentaDinero->nombre} tiene una diferencia de " . ($dif < 0 ? '−' : '') . '$' . number_format(abs($dif), 2) . '. Revisa los movimientos sin cuenta o que falten por registrar.');
        return $this->volver(['cuenta' => $cuentaDinero->id])->with('ok', $msg);
    }

    /** Quita la cuenta a todo lo que pasó por ella (cobros, ventas, gastos, órdenes y licencias); no borra esos registros */
    private function soltar(array $ids): void
    {
        if (! $ids) return;
        foreach ([\App\Models\ProyectoPago::class, \App\Models\VentaPago::class, \App\Models\Gasto::class, \App\Models\OrdenCompra::class] as $m) {
            $m::whereIn('cuenta_dinero_id', $ids)->update(['cuenta_dinero_id' => null]);
        }
        \App\Models\LicenciaPago::whereIn('cuenta_dinero_id', $ids)->update(['cuenta_dinero_id' => null]);
    }

    private function puedeBorrar(Request $request): void
    {
        abort_unless($request->user()->plataforma || $request->user()->esSuperAdmin(), 403, 'Solo quien administra el negocio puede borrar cuentas.');
    }

    /** Eliminar una cuenta: sus traspasos, ajustes y conciliaciones se van; sus cobros y gastos se quedan sin cuenta */
    public function borrar(Request $request, CuentaDinero $cuentaDinero)
    {
        $this->puedeBorrar($request);
        \Illuminate\Support\Facades\DB::transaction(function () use ($cuentaDinero) {
            $this->soltar([$cuentaDinero->id]);
            MovimientoDinero::where('de_id', $cuentaDinero->id)->orWhere('a_id', $cuentaDinero->id)->delete();
            Conciliacion::where('cuenta_dinero_id', $cuentaDinero->id)->delete();
            $cuentaDinero->delete();
        });
        Movimientos::olvidar();
        return $this->volver()->with('ok', "Se eliminó “{$cuentaDinero->nombre}”. Sus cobros y gastos siguen registrados, sin cuenta.");
    }

    /**
     * Reiniciar: borra todas las cuentas de dinero, traspasos, retiros, aportaciones, ajustes y conciliaciones.
     * Ventas, cobros, gastos y órdenes de compra se quedan como están (solo pierden la cuenta asignada).
     */
    public function reiniciar(Request $request)
    {
        $this->puedeBorrar($request);
        $request->validate(['confirmar' => ['required', function ($a, $v, $fail) {
            if (mb_strtoupper(trim((string) $v)) !== 'REINICIAR') $fail('Escribe REINICIAR para confirmar.');
        }]], ['confirmar.required' => 'Escribe REINICIAR para confirmar.']);
        $n = CuentaDinero::count();
        \Illuminate\Support\Facades\DB::transaction(function () {
            $ids = CuentaDinero::pluck('id')->all();
            $this->soltar($ids);
            MovimientoDinero::query()->delete();
            Conciliacion::query()->delete();
            CuentaDinero::query()->delete();
        });
        Movimientos::olvidar();
        return $this->volver()->with('ok', "Listo, se reiniciaron tus cuentas ($n). Tus ventas, cobros y gastos siguen igual; vuelve a crear tu caja y tu banco desde la fecha que quieras.");
    }

    /** Cambia la cuenta de un cobro, venta, gasto u orden pagada */
    public function asignar(Request $request)
    {
        $d = $request->validate([
            'clase' => ['required', Rule::in(array_keys(Movimientos::CLASES))],
            'id'    => 'required|integer',
            'cuenta_dinero_id' => ['nullable', Rule::in(CuentaDinero::pluck('id')->all())],
        ]);
        abort_if($d['clase'] === 'licencia' && ! Movimientos::cobraLicencias(), 404);
        $m = (Movimientos::CLASES[$d['clase']])::findOrFail($d['id']);
        $m->forceFill(['cuenta_dinero_id' => $d['cuenta_dinero_id'] ?: null])->saveQuietly();
        return back()->with('ok', 'Movimiento asignado.');
    }
}
