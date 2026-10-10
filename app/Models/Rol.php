<?php

namespace App\Models;

use App\Support\Permisos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Rol del equipo: qué secciones del panel puede ver. El super admin lo puede todo. */
class Rol extends Model
{
    protected $table = 'roles';

    protected $fillable = ['nombre', 'descripcion', 'permisos', 'todo', 'orden'];

    protected $casts = ['permisos' => 'array', 'todo' => 'boolean'];

    public function usuarios(): HasMany { return $this->hasMany(User::class, 'rol_id'); }

    public function puede(string $seccion): bool
    {
        return $this->todo || in_array($seccion, $this->permisos ?? [], true);
    }

    /** "Proyectos, Tareas y Clientes" */
    public function getResumenAttribute(): string
    {
        if ($this->todo) return 'Todo el panel';
        $n = collect($this->permisos ?? [])->map(fn ($p) => Permisos::SECCIONES[$p]['texto'] ?? null)->filter()->values();
        return $n->isEmpty() ? 'Solo sus tareas' : 'Sus tareas, ' . \Illuminate\Support\Arr::join($n->map(fn ($t) => mb_strtolower($t))->all(), ', ', ' y ');
    }
}
