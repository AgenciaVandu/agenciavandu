<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dinero que se mueve sin ser venta ni gasto: traspasos, retiros del dueño, aportaciones y ajustes de conciliación */
class MovimientoDinero extends Model
{
    use Concerns\DeCuenta;

    protected $table = 'movimientos_dinero';

    protected $fillable = ['cuenta_id', 'fecha', 'tipo', 'de_id', 'a_id', 'monto', 'nota', 'user_id'];

    protected $casts = ['fecha' => 'date', 'monto' => 'float'];

    public const TIPOS = [
        'traspaso'   => ['texto' => 'Traspaso',              'icono' => 'bi-arrow-left-right'],
        'retiro'     => ['texto' => 'Retiro del dueño',      'icono' => 'bi-box-arrow-up-right'],
        'aportacion' => ['texto' => 'Aportación del dueño',  'icono' => 'bi-box-arrow-in-down-right'],
        'ajuste'     => ['texto' => 'Ajuste de conciliación', 'icono' => 'bi-sliders'],
    ];

    public function de(): BelongsTo { return $this->belongsTo(CuentaDinero::class, 'de_id'); }
    public function a(): BelongsTo { return $this->belongsTo(CuentaDinero::class, 'a_id'); }
    public function usuario(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }

    public function getTipoTextoAttribute(): string { return self::TIPOS[$this->tipo]['texto'] ?? $this->tipo; }
}
