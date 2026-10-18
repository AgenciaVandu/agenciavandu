<?php

namespace App\Models;

use App\Models\Concerns\DeCuenta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosVenta extends Model
{
    use DeCuenta;

    protected $table = 'pos_ventas';
    protected $guarded = ['id', 'cuenta_id'];
    protected $casts = ['cancelada_at' => 'datetime', 'subtotal' => 'decimal:2', 'descuento' => 'decimal:2', 'impuestos' => 'decimal:2', 'total' => 'decimal:2', 'recibido' => 'decimal:2', 'cambio' => 'decimal:2'];

    public function detalles(): HasMany
    {
        return $this->hasMany(PosVentaDetalle::class, 'pos_venta_id');
    }
}