<?php

namespace App\Models;

use App\Models\Concerns\DeCuenta;
use Illuminate\Database\Eloquent\Model;

class PosVentaDetalle extends Model
{
    use DeCuenta;

    protected $table = 'pos_venta_detalles';
    protected $guarded = ['id', 'cuenta_id'];
    protected $casts = ['cantidad' => 'decimal:2', 'precio' => 'decimal:2', 'importe' => 'decimal:2'];
}