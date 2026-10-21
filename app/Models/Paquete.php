<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Paquete de clases del gimnasio (clase de prueba, 8 clases, ilimitado…). Es un producto del catálogo
 * (tabla productos, tipo "paquete") con créditos, vigencia y precio; así el punto de venta podrá venderlo después.
 */
class Paquete extends Producto
{
    protected $table = 'productos';

    protected $fillable = ['nombre', 'descripcion', 'precio', 'creditos', 'vigencia_dias', 'es_prueba', 'max_por_dia', 'tipos_clase', 'activo', 'publico', 'ejemplo'];

    protected $attributes = ['tipo' => 'paquete', 'unidad' => 'paquete', 'precio' => 0, 'existencia' => 0, 'controla_existencia' => false, 'publico' => false, 'activo' => true];

    protected $casts = ['precio' => 'float', 'creditos' => 'integer', 'vigencia_dias' => 'integer', 'max_por_dia' => 'integer', 'es_prueba' => 'boolean',
                        'tipos_clase' => 'array', 'activo' => 'boolean', 'publico' => 'boolean', 'ejemplo' => 'boolean', 'controla_existencia' => 'boolean'];

    protected static function booted(): void
    {
        parent::booted();   // enlace (slug) igual que cualquier producto
        static::addGlobalScope('paquete', fn (Builder $q) => $q->where('productos.tipo', 'paquete'));
        static::creating(function (Paquete $p) {
            $p->tipo = 'paquete';
            $p->controla_existencia = false;
        });
    }

    public function membresias(): HasMany { return $this->hasMany(Membresia::class, 'producto_id'); }

    public function getForeignKey(): string { return 'producto_id'; }

    public function getIlimitadoAttribute(): bool { return $this->creditos === null; }

    public function getCreditosTextoAttribute(): string
    {
        if ($this->ilimitado) return 'Ilimitado';
        return $this->creditos . ' ' . ($this->creditos === 1 ? 'clase' : 'clases');
    }

    public function getVigenciaTextoAttribute(): string
    {
        $d = (int) $this->vigencia_dias;
        return match (true) {
            $d <= 0           => 'Sin vigencia',
            $d % 30 === 0     => ($d / 30) . ' ' . ($d === 30 ? 'mes' : 'meses'),
            $d % 7 === 0      => ($d / 7) . ' ' . ($d === 7 ? 'semana' : 'semanas'),
            default           => $d . ' días',
        };
    }
}
