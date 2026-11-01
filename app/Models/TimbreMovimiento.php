<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Entradas (compra, regalo, ajuste) y salidas (uso) de timbres de un negocio */
class TimbreMovimiento extends Model
{
    protected $table = 'timbre_movimientos';

    public const TIPOS = ['compra' => 'Compra', 'regalo' => 'Regalo', 'ajuste' => 'Ajuste', 'uso' => 'Factura timbrada', 'uso_plan' => 'Factura timbrada (del plan)'];

    protected $fillable = ['cuenta_id', 'tipo', 'cantidad', 'paquete_id', 'licencia_pago_id', 'monto', 'referencia', 'nota', 'user_id'];

    protected $casts = ['cantidad' => 'integer', 'monto' => 'float'];

    public function paquete() { return $this->belongsTo(TimbrePaquete::class); }
}
