<?php

namespace App\Support\Licencias;

use App\Models\Cuenta;
use App\Models\Licencia;
use App\Models\LicenciaPago;
use App\Models\User;
use Illuminate\Support\Carbon;

/** Lo que pasa al guardar una licencia o registrar un pago */
class Licencias
{
    /**
     * Lleva al negocio lo que dice su licencia: sucursales contratadas y funciones extra (gastos, IA).
     * Las funciones que no vienen en el plan ni en sus extras se apagan, salvo las de su tipo de empresa.
     */
    public static function aplicar(Licencia $l): void
    {
        $c = Cuenta::find($l->cuenta_id);
        if (! $c || ! $l->plan) return;
        $a = $c->ajustes ?? [];
        $a['sucursales_max'] = $l->sucursalesMax();
        $antes = (array) ($a['modulos'] ?? []);
        $extras = \App\Http\Controllers\Admin\PlataformaController::EXTRAS;
        $a['modulos'] = array_values(array_unique(array_merge(array_diff($antes, $extras), array_intersect($extras, $l->funciones()))));
        // Recién prendido Gastos: todo el equipo puede subir los suyos (igual que al prenderlo a mano)
        if (in_array('gastos', $a['modulos'], true) && ! in_array('gastos', $antes, true)) {
            \App\Models\Rol::sinCuenta()->where('cuenta_id', $c->id)->where('todo', false)->get()
                ->each(fn ($r) => $r->update(['permisos' => array_values(array_unique(array_merge($r->permisos ?? [], ['gastos'])))]));
        }
        $c->forceFill(['ajustes' => $a])->save();
        Estado::olvidar();
    }

    /**
     * Registra un pago. Mensualidad/anualidad y mantenimiento recorren el vencimiento N periodos desde donde estaba
     * (si ya había vencido hace mucho, desde la fecha del pago, para no cobrar meses que no usó).
     */
    public static function pagar(Licencia $l, array $d, ?User $quien): LicenciaPago
    {
        $tz = config('vandu.zona_horaria');
        $p = new LicenciaPago($d + ['cuenta_id' => $l->cuenta_id, 'licencia_id' => $l->id, 'user_id' => $quien?->id]);
        $recorre = in_array($d['concepto'], ['periodo', 'mantenimiento'], true) && ($d['periodos'] ?? 0) > 0;
        if ($recorre) {
            $fecha = Carbon::parse($d['fecha'], $tz)->startOfDay();
            $desde = $l->vence_el ? Carbon::parse($l->vence_el->toDateString(), $tz) : $fecha->copy();
            // Si dejó pasar más que su gracia y su solo lectura, el nuevo periodo empieza el día que paga
            if ($l->vence_el && $desde->copy()->addDays($l->dias_gracia + $l->dias_lectura)->lt($fecha)) $desde = $fecha->copy();
            $anual = $d['concepto'] === 'mantenimiento' || $l->periodo === 'anual';
            $hasta = $anual ? $desde->copy()->addYearsNoOverflow($d['periodos']) : $desde->copy()->addMonthsNoOverflow($d['periodos']);
            $p->cubre_desde = $desde->toDateString();
            $p->cubre_hasta = $hasta->toDateString();
            $l->vence_el = $hasta->toDateString();
            $l->avisos = [];
        } else {
            $p->periodos = 0;
        }
        if ($d['concepto'] === 'implementacion') $l->implementacion_pagada_el = $d['fecha'];
        $l->save();
        $p->save();
        Estado::olvidar();
        return $p;
    }

    /** Borra un pago; si fue el último que recorrió el vencimiento, lo regresa */
    public static function borrarPago(LicenciaPago $p): void
    {
        $l = $p->licencia;
        if ($p->cubre_hasta && $l->vence_el && $p->cubre_hasta->toDateString() === $l->vence_el->toDateString()) {
            $l->vence_el = $p->cubre_desde;
        }
        if ($p->concepto === 'implementacion' && $l->implementacion_pagada_el?->toDateString() === $p->fecha->toDateString()) $l->implementacion_pagada_el = null;
        $l->save();
        $p->delete();
        Estado::olvidar();
    }

    /** Usuarios activos que cuentan contra el límite (quien administra la plataforma no cuenta) */
    public static function usuariosActivos(Cuenta $c): int
    {
        return User::sinCuenta()->where('cuenta_id', $c->id)->where('activo', true)->where(fn ($q) => $q->where('plataforma', false)->orWhereNull('plataforma'))->count();
    }

    /** null si cabe otro usuario activo; si no, el mensaje para el dueño */
    public static function topeUsuarios(?Cuenta $c): ?string
    {
        if (! $c) return null;
        $e = Estado::de($c);
        $max = $e->licencia?->usuariosMax();
        if ($max === null || self::usuariosActivos($c) < $max) return null;
        return "Tu plan {$e->licencia->plan->nombre} incluye {$max} " . ($max === 1 ? 'usuario activo' : 'usuarios activos') . '. Desactiva a alguien o pide usuarios adicionales.';
    }
}
