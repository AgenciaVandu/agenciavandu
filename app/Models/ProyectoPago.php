<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProyectoPago extends Model
{
    protected $fillable = ['clave', 'concepto', 'porcentaje', 'monto', 'antes_de', 'vence_el', 'orden', 'pagado_el', 'metodo', 'referencia'];

    protected $casts = [
        'monto'      => 'float',
        'porcentaje' => 'float',
        'pagado_el'  => 'date',
        'vence_el'   => 'date',
    ];

    public function proyecto(): BelongsTo { return $this->belongsTo(Proyecto::class); }

    public function getPagadoAttribute(): bool
    {
        return $this->pagado_el !== null;
    }

    /** Pendiente y ya pasó su fecha de vencimiento (crédito) */
    public function getVencidoAttribute(): bool
    {
        return ! $this->pagado && $this->vence_el && $this->vence_el->toDateString() < now(config('vandu.zona_horaria'))->toDateString();
    }

    public function getMetodoTextoAttribute(): ?string
    {
        return $this->metodo ? config("vandu.metodos_pago.{$this->metodo}", $this->metodo) : null;
    }

    public function getMontoTextoAttribute(): string
    {
        return '$' . number_format($this->monto, 2);
    }
}
