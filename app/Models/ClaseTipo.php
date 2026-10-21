<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Tipo de entrenamiento del gimnasio (HYROX Engine, Mobility…) con su duración, cupo y color */
class ClaseTipo extends Model
{
    use \App\Models\Concerns\DeCuenta;

    protected $table = 'clase_tipos';

    protected $fillable = ['nombre', 'descripcion', 'duracion', 'cupo', 'color', 'activo', 'orden', 'ejemplo'];

    protected $casts = ['duracion' => 'integer', 'cupo' => 'integer', 'activo' => 'boolean', 'ejemplo' => 'boolean'];

    public const COLORES = ['#13161D', '#00A86B', '#2557D6', '#E8590C', '#B42318', '#7048E8', '#0C8599', '#E64980', '#F59F00', '#5C940D'];

    public function clases(): HasMany { return $this->hasMany(Clase::class); }

    public function scopeActivos(Builder $q): Builder { return $q->where('activo', true); }
    public function scopeOrdenados(Builder $q): Builder { return $q->orderBy('orden')->orderBy('nombre'); }
}
