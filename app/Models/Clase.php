<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Una sesión concreta del gimnasio: tipo, sucursal, entrenador, horario y el cupo que pone el gerente.
 * Las horas se guardan en UTC; para mostrarlas se usa `inicio`/`fin` (zona del negocio).
 */
class Clase extends Model
{
    use \App\Models\Concerns\DeCuenta;

    protected $fillable = ['clase_tipo_id', 'serie_id', 'sucursal_id', 'coach_id', 'inicia_at', 'termina_at', 'cupo', 'estado', 'motivo_cancelacion', 'entrenamiento', 'notas', 'ejemplo'];

    protected $casts = ['inicia_at' => 'datetime', 'termina_at' => 'datetime', 'entrenamiento' => 'array', 'cupo' => 'integer', 'ejemplo' => 'boolean'];

    public function tipo(): BelongsTo { return $this->belongsTo(ClaseTipo::class, 'clase_tipo_id'); }
    public function serie(): BelongsTo { return $this->belongsTo(ClaseSerie::class, 'serie_id'); }
    public function sucursal(): BelongsTo { return $this->belongsTo(Sucursal::class); }
    public function coach(): BelongsTo { return $this->belongsTo(User::class, 'coach_id'); }
    public function reservaciones(): HasMany { return $this->hasMany(Reservacion::class); }

    /** Reservaciones que ocupan lugar (no canceladas) */
    public function ocupadas(): HasMany { return $this->hasMany(Reservacion::class)->where('estado', '!=', 'cancelada'); }

    public function scopeProgramadas(Builder $q): Builder { return $q->where('estado', 'programada'); }

    /** Clases de un día del negocio (de 00:00 a 23:59 en su zona horaria) */
    public function scopeDelDia(Builder $q, Carbon|string $dia): Builder
    {
        $tz = config('vandu.zona_horaria');
        $d = $dia instanceof Carbon ? $dia->copy()->setTimezone($tz) : Carbon::parse($dia, $tz);
        return $q->whereBetween('inicia_at', [$d->copy()->startOfDay()->utc(), $d->copy()->endOfDay()->utc()]);
    }

    public function scopeEntre(Builder $q, Carbon $desde, Carbon $hasta): Builder
    {
        return $q->where('inicia_at', '>=', $desde->copy()->utc())->where('inicia_at', '<=', $hasta->copy()->utc());
    }

    public function getInicioAttribute(): Carbon { return $this->inicia_at->copy()->setTimezone(config('vandu.zona_horaria')); }
    public function getFinAttribute(): Carbon { return $this->termina_at->copy()->setTimezone(config('vandu.zona_horaria')); }

    public function getHorarioAttribute(): string
    {
        return $this->inicio->format('H:i') . '–' . $this->fin->format('H:i');
    }

    public function getFechaTextoAttribute(): string
    {
        return ucfirst($this->inicio->locale('es')->isoFormat('dddd D [de] MMMM'));
    }

    /** Lugares ocupados (usa el conteo precargado si lo hay) */
    public function getOcupadosAttribute(): int
    {
        return (int) ($this->ocupadas_count ?? $this->ocupadas()->count());
    }

    public function getLibresAttribute(): int
    {
        return max(0, $this->cupo - $this->ocupados);
    }

    public function getCanceladaAttribute(): bool { return $this->estado === 'cancelada'; }
    public function getPasadaAttribute(): bool { return $this->termina_at->isPast(); }
    public function getEnCursoAttribute(): bool { return $this->inicia_at->isPast() && ! $this->termina_at->isPast(); }

    public function getNombreAttribute(): string { return $this->tipo?->nombre ?? 'Clase'; }
    public function getColorAttribute(): string { return $this->tipo?->color ?? '#13161D'; }

    /** Bloques del entrenamiento con sus ejercicios, listos para mostrarse */
    public function getBloquesAttribute(): array
    {
        return collect($this->entrenamiento ?? [])->filter(fn ($b) => ! empty($b['nombre']) || ! empty($b['ejercicios']))->values()->all();
    }
}
