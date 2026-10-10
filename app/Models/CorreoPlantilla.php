<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Cambios a una plantilla de fábrica, o una plantilla creada desde el panel */
class CorreoPlantilla extends Model
{
    use \App\Models\Concerns\DeCuenta;

    protected $fillable = ['clave', 'propia', 'nombre', 'icono', 'para', 'asunto', 'titulo', 'cuerpo', 'boton', 'resumen', 'activa'];

    protected $casts = ['propia' => 'boolean', 'para' => 'array', 'resumen' => 'boolean', 'activa' => 'boolean'];

    protected static function booted(): void
    {
        static::saved(fn () => \App\Support\PlantillasCorreo::limpiar());
        static::deleted(fn () => \App\Support\PlantillasCorreo::limpiar());
    }
}
