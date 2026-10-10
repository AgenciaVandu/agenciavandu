<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Un tipo de proyecto con sus etapas y pagos (cambios a uno de fábrica, o uno creado por la agencia) */
class ProyectoTipo extends Model
{
    use \App\Models\Concerns\DeCuenta;

    protected $fillable = ['clave', 'propio', 'nombre', 'icono', 'etapas', 'pagos', 'opciones'];

    protected $casts = ['propio' => 'boolean', 'etapas' => 'array', 'pagos' => 'array', 'opciones' => 'array'];
}
