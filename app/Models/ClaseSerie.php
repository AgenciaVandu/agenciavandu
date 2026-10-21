<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Clase que se repite cada semana (p. ej. HYROX Engine lunes, miércoles y viernes a las 7:00) */
class ClaseSerie extends Model
{
    use \App\Models\Concerns\DeCuenta;

    protected $table = 'clase_series';

    protected $fillable = ['clase_tipo_id', 'sucursal_id', 'coach_id', 'dias', 'hora', 'duracion', 'cupo', 'desde', 'hasta', 'activa'];

    protected $casts = ['dias' => 'array', 'desde' => 'date', 'hasta' => 'date', 'activa' => 'boolean', 'duracion' => 'integer', 'cupo' => 'integer'];

    public const DIAS = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];

    public function tipo(): BelongsTo { return $this->belongsTo(ClaseTipo::class, 'clase_tipo_id'); }
    public function sucursal(): BelongsTo { return $this->belongsTo(Sucursal::class); }
    public function coach(): BelongsTo { return $this->belongsTo(User::class, 'coach_id'); }
    public function clases(): HasMany { return $this->hasMany(Clase::class, 'serie_id'); }

    public function getDiasTextoAttribute(): string
    {
        $d = collect($this->dias ?? [])->map(fn ($n) => (int) $n)->sort()->values();
        if ($d->all() === [1, 2, 3, 4, 5]) return 'Lunes a viernes';
        if ($d->all() === [1, 2, 3, 4, 5, 6]) return 'Lunes a sábado';
        if ($d->count() === 7) return 'Todos los días';
        return $d->map(fn ($n) => self::DIAS[$n] ?? $n)->join(', ');
    }
}
