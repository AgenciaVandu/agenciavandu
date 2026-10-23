<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VentaConcepto extends Model
{
    public $timestamps = false;

    protected $fillable = ['venta_id', 'producto_id', 'descripcion', 'unidad', 'cantidad', 'precio', 'importe', 'orden'];

    protected $casts = ['cantidad' => 'float', 'precio' => 'float', 'importe' => 'float'];

    public function venta(): BelongsTo { return $this->belongsTo(Venta::class); }
    public function producto(): BelongsTo { return $this->belongsTo(Producto::class); }

    public function getCantidadTextoAttribute(): string
    {
        $n = rtrim(rtrim(number_format($this->cantidad, 2, '.', ','), '0'), '.');
        $u = (string) $this->unidad;
        // "2 piezas", "3 cajas", "1 pieza"; las abreviaturas (kg, L, m) no cambian
        if ($u !== '' && abs($this->cantidad - 1) > 0.001 && mb_strlen($u) > 3 && ! str_ends_with($u, 's')) {
            $u .= in_array(mb_substr($u, -1), ['a', 'e', 'i', 'o', 'u', 'á', 'é', 'í', 'ó', 'ú'], true) ? 's' : 'es';
        }
        return $n . ($u !== '' ? ' ' . $u : '');
    }
}
