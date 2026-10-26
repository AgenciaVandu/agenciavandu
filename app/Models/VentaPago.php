<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cobro de una venta: el pago completo de contado o cada abono de un crédito */
class VentaPago extends Model
{
    use Concerns\DeCuenta;

    protected $fillable = ['cuenta_id', 'venta_id', 'monto', 'fecha', 'forma', 'user_id', 'nota'];

    protected $casts = ['monto' => 'float', 'fecha' => 'date'];

    protected static function booted(): void
    {
        static::saving(fn (VentaPago $p) => \App\Support\Dinero\Movimientos::asignar($p, $p->forma));
    }

    public function venta(): BelongsTo { return $this->belongsTo(Venta::class); }
    public function usuario(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
}
