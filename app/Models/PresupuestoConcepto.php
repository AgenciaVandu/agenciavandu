<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PresupuestoConcepto extends Model
{
    protected $fillable = ['titulo', 'descripcion', 'cantidad', 'precio', 'orden'];

    protected $casts = [
        'cantidad' => 'float',
        'precio'   => 'float',
    ];

    /** Texto corto para listados: el título o, si no hay, la descripción */
    public function getResumenAttribute(): string
    {
        return trim($this->titulo ?: explode("\n", (string) $this->descripcion)[0]);
    }

    public function getImporteAttribute(): float
    {
        return round($this->cantidad * $this->precio, 2);
    }

    /** 1 en lugar de 1.00, pero conserva 1.5 */
    public function getCantidadTextoAttribute(): string
    {
        return rtrim(rtrim(number_format($this->cantidad, 2, '.', ''), '0'), '.');
    }
}
