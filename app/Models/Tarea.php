<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Trabajo asignado a alguien del equipo, con su carpeta de Dropbox para entregar */
class Tarea extends Model
{
    public const ESTADOS = [
        'pendiente' => ['texto' => 'Pendiente',   'color' => '#9AA0AC', 'icono' => 'bi-circle'],
        'en_curso'  => ['texto' => 'En curso',    'color' => '#2557D6', 'icono' => 'bi-play-circle'],
        'revision'  => ['texto' => 'En revisión', 'color' => '#A35A00', 'icono' => 'bi-eye'],
        'terminada' => ['texto' => 'Terminada',   'color' => '#047A4B', 'icono' => 'bi-check-circle-fill'],
    ];

    protected $fillable = ['titulo', 'descripcion', 'cliente_id', 'proyecto_id', 'asignada_a', 'creada_por', 'fecha_limite', 'urgente', 'estado', 'carpeta', 'entregada_at', 'terminada_at'];

    protected $casts = [
        'fecha_limite' => 'date',
        'urgente'      => 'boolean',
        'entregada_at' => 'datetime',
        'terminada_at' => 'datetime',
    ];

    public function cliente(): BelongsTo { return $this->belongsTo(Cliente::class); }
    public function proyecto(): BelongsTo { return $this->belongsTo(Proyecto::class); }
    public function responsable(): BelongsTo { return $this->belongsTo(User::class, 'asignada_a'); }
    public function autor(): BelongsTo { return $this->belongsTo(User::class, 'creada_por'); }
    public function archivos(): HasMany { return $this->hasMany(TareaArchivo::class)->latest('id'); }
    public function comentarios(): HasMany { return $this->hasMany(TareaComentario::class)->oldest('id'); }

    public function scopeAbiertas(Builder $q): Builder { return $q->where('estado', '!=', 'terminada'); }

    public function getVencidaAttribute(): bool
    {
        return $this->estado !== 'terminada' && $this->fecha_limite && $this->fecha_limite->lt(now(config('vandu.zona_horaria'))->startOfDay());
    }

    /** "Hoy", "Mañana", "Vie 16 oct", "Venció hace 2 días" */
    public function getCuandoAttribute(): ?string
    {
        if (! $this->fecha_limite) return null;
        $hoy = now(config('vandu.zona_horaria'))->startOfDay();
        $d = (int) $hoy->diffInDays($this->fecha_limite, false);
        return match (true) {
            $d === 0 => 'Hoy',
            $d === 1 => 'Mañana',
            $d < 0 && $this->estado !== 'terminada' => 'Venció ' . ($d === -1 ? 'ayer' : 'hace ' . abs($d) . ' días'),
            default => ucfirst($this->fecha_limite->locale('es')->isoFormat($d > 0 && $d < 7 ? 'dddd D' : 'ddd D MMM')),
        };
    }

    public function getDondeAttribute(): ?string
    {
        $c = $this->cliente ?? $this->proyecto?->cliente;
        $n = $c ? ($c->empresa ?: $c->nombre) : null;
        return collect([$n, $this->proyecto?->nombre])->filter()->unique()->implode(' · ') ?: null;
    }

    /** Puede trabajarla: es suya, o gestiona tareas */
    public function puedeVer(User $u): bool
    {
        return $u->puede('tareas') || $this->asignada_a === $u->id;
    }
}
