<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Paquete de timbres que vende la plataforma (no es de un negocio) */
class TimbrePaquete extends Model
{
    protected $table = 'timbre_paquetes';

    protected $fillable = ['nombre', 'timbres', 'precio', 'activo', 'orden'];

    protected $casts = ['timbres' => 'integer', 'precio' => 'float', 'activo' => 'boolean'];

    public function getPorTimbreAttribute(): float { return $this->timbres ? round($this->precio / $this->timbres, 2) : 0; }
}
