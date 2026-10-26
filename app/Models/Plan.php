<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Un plan de la plataforma (Fundador, Esencial…) o un extra que se suma (IA, usuario, sucursal…). No es de ningún negocio. */
class Plan extends Model
{
    protected $table = 'planes';

    protected $fillable = ['clave', 'tipo', 'nombre', 'descripcion', 'precio_mensual', 'precio_anual', 'usuarios', 'sucursales', 'incluye', 'activo', 'orden'];

    protected $casts = ['precio_mensual' => 'float', 'precio_anual' => 'float', 'usuarios' => 'integer', 'sucursales' => 'integer', 'incluye' => 'array', 'activo' => 'boolean'];

    public function scopePlanes(Builder $q): Builder { return $q->where('tipo', 'plan')->orderBy('orden')->orderBy('id'); }
    public function scopeExtras(Builder $q): Builder { return $q->where('tipo', 'extra')->orderBy('orden')->orderBy('id'); }

    /** Precio por periodo: el anual, o 10 mensualidades si no se definió */
    public function precio(string $periodo): float
    {
        return $periodo === 'anual' ? ($this->precio_anual ?? $this->precio_mensual * 10) : $this->precio_mensual;
    }
}
