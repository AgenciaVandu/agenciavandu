<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProyectoEtapa extends Model
{
    use \App\Models\Concerns\DeCuenta;

    public const ESTADOS = ['pendiente' => 'Pendiente', 'en_curso' => 'En curso', 'completada' => 'Completada'];

    protected $fillable = ['clave', 'nombre', 'descripcion', 'orden', 'estado', 'es_fecha', 'dias', 'fecha_inicio', 'fecha_fin', 'completada_at'];

    protected $casts = [
        'es_fecha'      => 'boolean',
        'dias'          => 'integer',
        'fecha_inicio'  => 'date',
        'fecha_fin'     => 'date',
        'completada_at' => 'datetime',
    ];

    public function proyecto(): BelongsTo { return $this->belongsTo(Proyecto::class); }

    public function archivos(): HasMany
    {
        return $this->hasMany(ProyectoArchivo::class, 'etapa_id')->orderBy('orden')->orderBy('id');
    }

    /** Cuándo debe quedar lista (un evento: su día; una etapa: su último día) */
    public function getFechaLimiteAttribute(): ?\Illuminate\Support\Carbon
    {
        return $this->es_fecha ? $this->fecha_inicio : ($this->fecha_fin ?? $this->fecha_inicio);
    }

    /** "Hoy", "Mañana", "En 3 días", "Hace 2 días" respecto a su fecha límite */
    public function getFaltaTextoAttribute(): ?string
    {
        if (! $this->fecha_limite) return null;
        $hoy = now(config('vandu.zona_horaria'))->startOfDay();
        $d = (int) $hoy->diffInDays($this->fecha_limite->copy()->startOfDay(), false);
        return match (true) {
            $d === 0 => 'hoy', $d === 1 => 'mañana', $d === -1 => 'ayer',
            $d > 1 => "en $d días", default => 'hace ' . abs($d) . ' días',
        };
    }

    /** Texto de fechas para mostrar: "12 – 16 oct" o "Sáb 18 oct" */
    public function getFechasTextoAttribute(): ?string
    {
        $f = fn ($d, $fmt) => ucfirst($d->locale('es')->isoFormat($fmt));
        if ($this->es_fecha) {
            return $this->fecha_inicio ? $f($this->fecha_inicio, 'ddd D [de] MMMM') : null;
        }
        if (! $this->fecha_inicio) return null;
        if (! $this->fecha_fin || $this->fecha_fin->isSameDay($this->fecha_inicio)) return $f($this->fecha_inicio, 'D [de] MMMM');
        return $this->fecha_inicio->isSameMonth($this->fecha_fin)
            ? $this->fecha_inicio->day . ' – ' . $f($this->fecha_fin, 'D [de] MMMM')
            : $f($this->fecha_inicio, 'D MMM') . ' – ' . $f($this->fecha_fin, 'D MMM');
    }
}
