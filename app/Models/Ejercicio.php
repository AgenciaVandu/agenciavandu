<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Ejercicio de la biblioteca del gimnasio (Running, SkiErg, Wall Balls…) */
class Ejercicio extends Model
{
    use \App\Models\Concerns\DeCuenta;

    protected $fillable = ['nombre', 'categoria', 'unidad', 'instrucciones', 'activo', 'ejemplo'];

    protected $casts = ['activo' => 'boolean', 'ejemplo' => 'boolean'];

    public const UNIDADES = ['m' => 'metros', 'km' => 'kilómetros', 'cal' => 'calorías', 'reps' => 'repeticiones', 'kg' => 'kilos', 'seg' => 'segundos', 'min' => 'minutos'];

    public function scopeActivos(Builder $q): Builder { return $q->where('activo', true); }

    /** Categorías que ya usa el gimnasio (para sugerirlas) */
    public static function categorias(): array
    {
        return static::whereNotNull('categoria')->distinct()->orderBy('categoria')->pluck('categoria')->all();
    }
}
