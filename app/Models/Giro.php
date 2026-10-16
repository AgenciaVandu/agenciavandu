<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tipo de empresa creado desde Plataforma. Se suma a los de fábrica (config/vandu.php → giros)
 * y define con qué arranca cada negocio nuevo: módulos, roles, tipos de proyecto y costeo.
 */
class Giro extends Model
{
    protected $fillable = ['clave', 'nombre', 'icono', 'descripcion', 'modulos', 'roles', 'proyectos', 'costeo'];

    protected $casts = ['modulos' => 'array', 'roles' => 'array', 'proyectos' => 'array', 'costeo' => 'array'];

    /** Como lo espera config('vandu.giros.*') */
    public function comoConfig(): array
    {
        return array_filter([
            'nombre'      => $this->nombre,
            'icono'       => $this->icono ?: 'bi-briefcase',
            'descripcion' => $this->descripcion,
            'modulos'     => array_values($this->modulos ?? []),
            'roles'       => array_values($this->roles ?? []),
            'proyectos'   => $this->proyectos ?: null,
            'costeo'      => $this->costeo ?: null,
            'propio'      => true,
        ], fn ($v) => $v !== null);
    }
}
