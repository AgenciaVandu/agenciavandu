<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/** Campaña de un restaurante: vigencia, meta de visitas y premios que se desbloquean en el camino */
class LealtadCampana extends Model
{
    use Concerns\DeCuenta;

    protected $table = 'lealtad_campanas';
    protected $fillable = ['cuenta_id', 'nombre', 'descripcion', 'inicia_el', 'termina_el', 'meta_visitas', 'dias_canje', 'activa', 'ejemplo'];
    protected $casts = ['inicia_el' => 'date', 'termina_el' => 'date', 'activa' => 'boolean', 'ejemplo' => 'boolean', 'meta_visitas' => 'integer', 'dias_canje' => 'integer'];

    public function premios(): HasMany { return $this->hasMany(LealtadPremio::class, 'campana_id')->withoutGlobalScope('cuenta')->orderBy('visitas'); }

    public static function hoy(): string { return now(config('vandu.zona_horaria'))->toDateString(); }

    /** Cuentan visitas hoy */
    public function scopeVigentes(Builder $q, ?string $fecha = null): Builder
    {
        $f = $fecha ?? static::hoy();
        return $q->where('activa', true)->whereDate('inicia_el', '<=', $f)->whereDate('termina_el', '>=', $f);
    }

    /** Vigentes o ya terminadas pero todavía con premios por canjear */
    public function scopeConCanje(Builder $q): Builder
    {
        return $q->where('activa', true)->whereDate('inicia_el', '<=', static::hoy())
            ->whereRaw('termina_el >= ?', [now(config('vandu.zona_horaria'))->subDays(90)->toDateString()]);
    }

    public function getVigenteAttribute(): bool
    {
        $h = static::hoy();
        return $this->activa && $this->inicia_el->toDateString() <= $h && $this->termina_el->toDateString() >= $h;
    }

    public function getCanjeHastaAttribute(): Carbon
    {
        return $this->termina_el->copy()->addDays((int) $this->dias_canje);
    }

    public function getSePuedeCanjearAttribute(): bool
    {
        return $this->activa && $this->inicia_el->toDateString() <= static::hoy() && $this->canje_hasta->toDateString() >= static::hoy();
    }

    public function getEstadoAttribute(): string
    {
        $h = static::hoy();
        if (! $this->activa) return 'pausada';
        if ($this->inicia_el->toDateString() > $h) return 'proxima';
        if ($this->termina_el->toDateString() >= $h) return 'vigente';
        return $this->canje_hasta->toDateString() >= $h ? 'canje' : 'terminada';
    }

    public function getVigenciaTextoAttribute(): string
    {
        $mismoAnio = $this->inicia_el->year === $this->termina_el->year;
        return $this->inicia_el->locale('es')->isoFormat($mismoAnio ? 'D [de] MMMM' : 'D [de] MMMM YYYY') . ' al ' . $this->termina_el->locale('es')->isoFormat('D [de] MMMM YYYY');
    }
}
