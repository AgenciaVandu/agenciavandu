<?php

namespace App\Models\Concerns;

use App\Models\Cuenta;
use App\Support\Cuentas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lo que pertenece a un negocio: siempre se lee y se guarda dentro de la cuenta activa.
 * Para cruzar cuentas (enlaces públicos, la plataforma) hay que pedirlo a propósito con sinCuenta().
 */
trait DeCuenta
{
    public static function bootDeCuenta(): void
    {
        static::addGlobalScope('cuenta', function (Builder $q) {
            $q->where($q->getModel()->getTable() . '.cuenta_id', Cuentas::id());
        });
        static::creating(function ($m) {
            $m->cuenta_id ??= Cuentas::id();
        });
    }

    public function cuenta(): BelongsTo { return $this->belongsTo(Cuenta::class); }

    /** Consulta en todas las cuentas (solo para resolver enlaces públicos y la plataforma) */
    public static function sinCuenta(): Builder
    {
        return static::withoutGlobalScope('cuenta');
    }
}
