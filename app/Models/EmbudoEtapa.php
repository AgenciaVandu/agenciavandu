<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/** Un paso del embudo de ventas. Cada negocio arma el suyo; siempre hay una etapa de Ganado y una de Perdido */
class EmbudoEtapa extends Model
{
    use \App\Models\Concerns\DeCuenta;

    protected $table = 'embudo_etapas';

    protected $fillable = ['nombre', 'orden', 'probabilidad', 'tipo'];

    protected $casts = ['probabilidad' => 'integer', 'orden' => 'integer'];

    public const TIPOS = ['abierta' => 'En proceso', 'ganada' => 'Ganado', 'perdida' => 'Perdido'];

    /** Embudo de fábrica (se ajusta en Ventas → Etapas) */
    public const FABRICA = [
        ['nombre' => 'Nuevo',               'probabilidad' => 10,  'tipo' => 'abierta'],
        ['nombre' => 'Contactado',          'probabilidad' => 20,  'tipo' => 'abierta'],
        ['nombre' => 'Reunión o diagnóstico', 'probabilidad' => 40, 'tipo' => 'abierta'],
        ['nombre' => 'Propuesta enviada',   'probabilidad' => 60,  'tipo' => 'abierta'],
        ['nombre' => 'Negociación',         'probabilidad' => 80,  'tipo' => 'abierta'],
        ['nombre' => 'Ganado',              'probabilidad' => 100, 'tipo' => 'ganada'],
        ['nombre' => 'Perdido',             'probabilidad' => 0,   'tipo' => 'perdida'],
    ];

    public function prospectos(): HasMany { return $this->hasMany(Prospecto::class, 'etapa_id'); }

    public function scopeOrdenadas(Builder $q): Builder { return $q->orderBy('orden')->orderBy('id'); }

    /** Etapas de la cuenta activa (si no tiene, se crean las de fábrica) */
    public static function todas(): Collection
    {
        $e = static::ordenadas()->get();
        if ($e->isEmpty()) {
            foreach (self::FABRICA as $i => $f) static::create($f + ['orden' => $i]);
            $e = static::ordenadas()->get();
        }
        return $e;
    }

    public static function abiertas(): Collection { return static::todas()->where('tipo', 'abierta')->values(); }
    public static function ganada(): self { return static::todas()->firstWhere('tipo', 'ganada'); }
    public static function perdida(): self { return static::todas()->firstWhere('tipo', 'perdida'); }
    public static function primera(): self { return static::abiertas()->first() ?? static::todas()->first(); }

    /** Primera etapa de una cuenta cualquiera (para migraciones y el formulario del sitio) */
    public static function primeraDe(int $cuentaId): int
    {
        $q = fn () => static::sinCuenta()->where('cuenta_id', $cuentaId);
        if (! $q()->exists()) {
            foreach (self::FABRICA as $i => $f) {
                $e = new static($f + ['orden' => $i]);
                $e->cuenta_id = $cuentaId;
                $e->save();
            }
        }
        return (int) $q()->where('tipo', 'abierta')->orderBy('orden')->orderBy('id')->value('id');
    }

    public function getCerradaAttribute(): bool { return $this->tipo !== 'abierta'; }
}
