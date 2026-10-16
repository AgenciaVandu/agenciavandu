<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Entrada, salida o ajuste de existencias. Nunca se edita: si hubo un error se corrige con otro movimiento */
class ProductoMovimiento extends Model
{
    use \App\Models\Concerns\DeCuenta;

    public const UPDATED_AT = null;

    public const TIPOS = [
        'entrada'     => ['texto' => 'Entrada',               'icono' => 'bi-box-arrow-in-down'],
        'salida'      => ['texto' => 'Salida',                'icono' => 'bi-box-arrow-up'],
        'ajuste'      => ['texto' => 'Ajuste de inventario',  'icono' => 'bi-sliders'],
        'venta'       => ['texto' => 'Cotización aceptada',   'icono' => 'bi-check-circle'],
        'devolucion'  => ['texto' => 'Regresó al inventario', 'icono' => 'bi-arrow-counterclockwise'],
        'importacion' => ['texto' => 'Importación',           'icono' => 'bi-file-earmark-spreadsheet'],
    ];

    protected $table = 'producto_movimientos';

    protected $fillable = ['producto_id', 'tipo', 'cantidad', 'existencia', 'presupuesto_id', 'user_id', 'nota'];

    protected $casts = ['cantidad' => 'float', 'existencia' => 'float'];

    public function producto(): BelongsTo { return $this->belongsTo(Producto::class); }
    public function presupuesto(): BelongsTo { return $this->belongsTo(Presupuesto::class); }
    public function usuario(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }

    public function getTipoInfoAttribute(): array
    {
        return self::TIPOS[$this->tipo] ?? ['texto' => ucfirst($this->tipo), 'icono' => 'bi-dot'];
    }
}
