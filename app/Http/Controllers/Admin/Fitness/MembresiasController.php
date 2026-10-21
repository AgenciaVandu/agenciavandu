<?php

namespace App\Http\Controllers\Admin\Fitness;

use App\Models\Cliente;
use App\Models\Membresia;
use App\Models\Paquete;
use App\Support\Fitness\Membresias;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Asignar paquetes a un atleta, registrar el pago y cancelar */
class MembresiasController extends FitnessController
{
    public function store(Request $request, Cliente $cliente)
    {
        $d = $request->validate([
            'producto_id' => ['required', Rule::exists('productos', 'id')->where('cuenta_id', \App\Support\Cuentas::id())->where('tipo', 'paquete')],
            'precio'      => 'nullable|numeric|min:0|max:9999999',
            'inicia_el'   => 'required|date',
            'vence_el'    => 'nullable|date|after_or_equal:inicia_el',
            'pagado'      => 'nullable|boolean',
            'pagado_el'   => 'nullable|date',
            'metodo_pago' => ['nullable', Rule::in(array_keys(Membresia::METODOS))],
            'notas'       => 'nullable|string|max:1000',
        ], ['producto_id.required' => 'Elige el paquete.']);
        $p = Paquete::findOrFail($d['producto_id']);
        $m = Membresias::asignar($cliente, $p, $d + ['pagado' => $request->boolean('pagado')], $request->user());
        if ($cliente->activo === false) $cliente->forceFill(['activo' => true])->save();
        return redirect()->route('admin.clientes.show', [$cliente, 'ver' => 'atleta'])
            ->with('ok', "Se asignó {$m->nombre} a {$cliente->primer_nombre}" . ($m->pagada ? ' (pagado).' : '. Queda pendiente de pago.'));
    }

    /** Marcar pagada (o quitar el pago) y corregir vigencia, créditos o precio */
    public function update(Request $request, Membresia $membresia)
    {
        $d = $request->validate([
            'precio'      => 'required|numeric|min:0|max:9999999',
            'vence_el'    => 'required|date|after_or_equal:' . $membresia->inicia_el->toDateString(),
            'creditos'    => 'nullable|integer|min:' . max(1, $membresia->usados) . '|max:1000',
            'pagado'      => 'nullable|boolean',
            'pagado_el'   => 'nullable|date',
            'metodo_pago' => ['nullable', Rule::in(array_keys(Membresia::METODOS))],
            'notas'       => 'nullable|string|max:1000',
        ], ['creditos.min' => 'Ya usó ' . $membresia->usados . ' clases; no puede tener menos.']);
        $pagado = $request->boolean('pagado');
        $membresia->update([
            'precio' => $d['precio'], 'vence_el' => $d['vence_el'],
            'creditos' => $membresia->ilimitada ? null : ($d['creditos'] ?? $membresia->creditos),
            'pagado_el' => $pagado ? ($d['pagado_el'] ?? $membresia->pagado_el?->toDateString() ?? now(config('vandu.zona_horaria'))->toDateString()) : null,
            'metodo_pago' => $pagado ? ($d['metodo_pago'] ?? 'efectivo') : null,
            'notas' => $d['notas'] ?? null,
            // Si se extiende la vigencia o se agregan clases, se puede volver a avisar
            'aviso_vence_at' => $d['vence_el'] !== $membresia->vence_el->toDateString() ? null : $membresia->aviso_vence_at,
            'aviso_credito_at' => ($d['creditos'] ?? $membresia->creditos) !== $membresia->creditos ? null : $membresia->aviso_credito_at,
        ]);
        return back()->with('ok', 'Membresía guardada.');
    }

    public function cancelar(Request $request, Membresia $membresia)
    {
        $pendientes = $membresia->reservaciones()->where('estado', 'reservada')->whereHas('clase', fn ($q) => $q->where('inicia_at', '>', now()))->get();
        foreach ($pendientes as $r) \App\Support\Fitness\Reservas::cancelar($r, true);
        $membresia->update(['estado' => 'cancelada']);
        return back()->with('ok', 'Membresía cancelada; ya no cuenta en Finanzas.' . ($pendientes->count() ? " Se liberaron {$pendientes->count()} lugares que tenía reservados." : ''));
    }
}
