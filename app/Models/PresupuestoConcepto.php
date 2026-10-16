<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PresupuestoConcepto extends Model
{
    use \App\Models\Concerns\DeCuenta;

    public const COPIABLES = ['producto_id', 'titulo', 'descripcion', 'cantidad', 'precio', 'orden', 'costos', 'utilidad', 'utilidad_modo', 'costo_proveedor', 'gasolina'];

    protected $fillable = self::COPIABLES;

    public function producto(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Producto::class); }
    public function presupuesto(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Presupuesto::class); }

    protected $casts = [
        'cantidad'        => 'float',
        'precio'          => 'float',
        'costos'          => 'array',
        'costo_proveedor' => 'float',
        'gasolina'        => 'float',
        'utilidad'        => 'float',
    ];

    /* ---------- Costeo interno (componentes del negocio + utilidad). El cliente nunca lo ve ---------- */

    /** Costos capturados: [{clave, nombre, modo, clase, monto}] (incluye el costeo viejo de proveedor + gasolina) */
    public function getCostosListaAttribute(): array
    {
        $lista = array_values(array_filter((array) $this->costos, fn ($c) => isset($c['monto']) && $c['monto'] !== '' && $c['monto'] !== null));
        if (! $lista && ($this->costo_proveedor !== null || $this->gasolina !== null)) {
            if ($this->costo_proveedor !== null) $lista[] = ['clave' => 'proveedor', 'nombre' => 'Proveedor o maquila', 'modo' => 'unidad', 'clase' => 'tercero', 'monto' => $this->costo_proveedor];
            if ($this->gasolina !== null) $lista[] = ['clave' => 'gasolina', 'nombre' => 'Gasolina y traslados', 'modo' => 'fijo', 'clase' => 'operativo', 'monto' => $this->gasolina];
        }
        return $lista;
    }

    public function getTieneCosteoAttribute(): bool
    {
        return (bool) $this->costos_lista;
    }

    /** Lo que te cuesta a ti: cada componente × cantidad (por cantidad) o una vez (monto fijo) */
    public function getCostoAttribute(): float
    {
        return round(collect($this->costos_lista)->sum(fn ($c) => \App\Support\Costeo::importe($c, $this->cantidad)), 2);
    }

    /** Costo de una clase: tercero (proveedores, maquila…) u operativo (tiempo, traslados…) */
    public function costoDe(string $clase): float
    {
        return round(collect($this->costos_lista)->where('clase', $clase)->sum(fn ($c) => \App\Support\Costeo::importe($c, $this->cantidad)), 2);
    }

    /** Utilidad real según el precio que quedó (importe − costo) */
    public function getGananciaAttribute(): float
    {
        return round($this->importe - $this->costo, 2);
    }

    /**
     * Precio unitario que resulta del costeo. La misma fórmula que el editor:
     * costo total + utilidad (% sobre el costo o monto fijo), entre la cantidad.
     */
    public static function precioDesdeCosteo(float $cantidad, float $costo, ?float $utilidad, string $modo = 'pct'): float
    {
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
