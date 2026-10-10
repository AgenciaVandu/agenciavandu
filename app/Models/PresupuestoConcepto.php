<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PresupuestoConcepto extends Model
{
    use \App\Models\Concerns\DeCuenta;

    public const COPIABLES = ['titulo', 'descripcion', 'cantidad', 'precio', 'orden', 'costo_proveedor', 'gasolina', 'utilidad', 'utilidad_modo'];

    protected $fillable = self::COPIABLES;

    protected $casts = [
        'cantidad'        => 'float',
        'precio'          => 'float',
        'costo_proveedor' => 'float',
        'gasolina'        => 'float',
        'utilidad'        => 'float',
    ];

    /* ---------- Costeo interno (proveedor + gasolina + utilidad) ---------- */

    public function getTieneCosteoAttribute(): bool
    {
        return $this->costo_proveedor !== null || $this->gasolina !== null;
    }

    /** Lo que te cuesta a ti: proveedor por pieza × cantidad + gasolina del concepto */
    public function getCostoAttribute(): float
    {
        return round($this->cantidad * (float) $this->costo_proveedor + (float) $this->gasolina, 2);
    }

    /** Utilidad real según el precio que quedó (importe − costo) */
    public function getGananciaAttribute(): float
    {
        return round($this->importe - $this->costo, 2);
    }

    /**
     * Precio unitario que resulta del costeo. La misma fórmula que el editor:
     * (cantidad × proveedor + gasolina) + utilidad (% sobre el costo o monto fijo), entre la cantidad.
     */
    public static function precioDesdeCosteo(float $cantidad, ?float $proveedor, ?float $gasolina, ?float $utilidad, string $modo = 'pct'): float
    {
        $costo = $cantidad * (float) $proveedor + (float) $gasolina;
        $total = $modo === 'monto' ? $costo + (float) $utilidad : $costo * (1 + (float) $utilidad / 100);
        return $cantidad > 0 ? round($total / $cantidad, 2) : 0.0;
    }

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
