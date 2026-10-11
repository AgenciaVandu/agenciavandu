<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdenCompraConcepto extends Model
{
    public $timestamps = false;

    protected $fillable = ['descripcion', 'cantidad', 'unidad', 'precio', 'orden'];

    protected $casts = ['cantidad' => 'float', 'precio' => 'float'];

    public function getImporteAttribute(): float
    {
        return round($this->cantidad * $this->precio, 2);
    }

    /** "20", "1.5 m²", "9 piezas" */
    public function getCantidadTextoAttribute(): string
    {
        $n = rtrim(rtrim(number_format($this->cantidad, 2), '0'), '.');
        return trim($n . ' ' . ($this->unidad ?? ''));
    }
}
