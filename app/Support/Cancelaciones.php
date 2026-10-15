<?php

namespace App\Support;

use App\Models\OrdenCompra;
use App\Models\Proyecto;
use Illuminate\Support\Facades\DB;

/**
 * Cancelar (y reactivar) proyectos y órdenes de compra, siempre con un motivo.
 *  - Proyecto cancelado: su cotización pasa a "Cancelada" (deja de contar como venta ganada) y sus pagos
 *    pendientes dejan de contar como por cobrar. Lo ya cobrado se queda registrado como cobrado.
 *  - Orden cancelada: deja de contar como gasto del proyecto y como pendiente de pago al proveedor.
 */
class Cancelaciones
{
    /** Órdenes de compra del proyecto que todavía se pueden cancelar */
    public static function ordenesAbiertas(Proyecto $p)
    {
        return OrdenCompra::where('proyecto_id', $p->id)->whereNotIn('estado', ['pagada', 'cancelada'])->get();
    }

    /** @return int órdenes canceladas junto con el proyecto */
    public static function cancelarProyecto(Proyecto $p, string $motivo, bool $conOrdenes = false): int
    {
        return DB::transaction(function () use ($p, $motivo, $conOrdenes) {
            $p->forceFill(['estado' => 'cancelado', 'cancelado_at' => now(), 'motivo_cancelacion' => $motivo])->save();

            $c = $p->presupuesto;
            if ($c && $c->estado === 'aceptada') {
                $c->forceFill(['estado' => 'cancelada', 'cancelada_at' => now(), 'motivo_cancelacion' => $motivo])->save();
                Aceptacion::registrar($c, 'estado', 'agencia', null, 'Se canceló el proyecto: ' . $motivo, ['de' => 'aceptada', 'a' => 'cancelada'], false);
            }

            $n = 0;
            if ($conOrdenes) {
                foreach (self::ordenesAbiertas($p) as $o) {
                    self::cancelarOrden($o, 'Se canceló el proyecto ' . $p->nombre . ': ' . $motivo);
                    $n++;
                }
            }
            return $n;
        });
    }

    public static function reactivarProyecto(Proyecto $p, string $estado = 'activo'): void
    {
        DB::transaction(function () use ($p, $estado) {
            $p->forceFill(['estado' => $estado, 'cancelado_at' => null, 'motivo_cancelacion' => null])->save();
            $c = $p->presupuesto;
            if ($c && $c->estado === 'cancelada') {
                $c->forceFill(['estado' => 'aceptada', 'cancelada_at' => null, 'motivo_cancelacion' => null])->save();
                Aceptacion::registrar($c, 'estado', 'agencia', null, 'Se reactivó el proyecto', ['de' => 'cancelada', 'a' => 'aceptada'], false);
            }
        });
    }

    public static function cancelarOrden(OrdenCompra $o, string $motivo): void
    {
        $o->forceFill(['estado' => 'cancelada', 'cancelada_at' => now(), 'motivo_cancelacion' => $motivo])->save();
    }
}
