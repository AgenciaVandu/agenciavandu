<?php

namespace App\Support\Fitness;

use App\Models\Cliente;
use App\Models\Membresia;
use App\Models\Paquete;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/** Asignar un paquete a un atleta (a mano; sin pagos en línea en esta etapa) */
class Membresias
{
    public static function asignar(Cliente $c, Paquete $p, array $d, ?User $por = null): Membresia
    {
        if ((bool) $p->es_prueba && Membresia::where('cliente_id', $c->id)->where('es_prueba', true)->where('estado', '!=', 'cancelada')->exists()) {
            throw ValidationException::withMessages(['producto_id' => "{$c->primer_nombre} ya tomó su clase de prueba."]);
        }
        $tz = config('vandu.zona_horaria');
        $inicio = Carbon::parse($d['inicia_el'] ?? now($tz)->toDateString(), $tz);
        $vence = ! empty($d['vence_el']) ? Carbon::parse($d['vence_el'], $tz)
            : $inicio->copy()->addDays(max(1, (int) ($p->vigencia_dias ?: 30)) - 1);

        $pagado = ($d['pagado'] ?? false) ? ($d['pagado_el'] ?? now($tz)->toDateString()) : null;
        return Membresia::create([
            'cliente_id'  => $c->id,
            'producto_id' => $p->id,
            'nombre'      => $p->nombre,
            'precio'      => array_key_exists('precio', $d) && $d['precio'] !== null && $d['precio'] !== '' ? (float) $d['precio'] : (float) $p->precio,
            'creditos'    => $p->creditos,
            'usados'      => 0,
            'es_prueba'   => (bool) $p->es_prueba,
            'max_por_dia' => $p->max_por_dia,
            'tipos_clase' => $p->tipos_clase ?: null,
            'inicia_el'   => $inicio->toDateString(),
            'vence_el'    => $vence->toDateString(),
            'estado'      => 'activa',
            'pagado_el'   => $pagado,
            'metodo_pago' => $pagado ? ($d['metodo_pago'] ?? 'efectivo') : null,
            'notas'       => $d['notas'] ?? null,
            'asignada_por' => $por?->id,
        ]);
    }
}
