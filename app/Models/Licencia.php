<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** El acuerdo de un negocio con la plataforma: plan, precio, extras, implementación y hasta cuándo está pagado */
class Licencia extends Model
{
    protected $fillable = ['cuenta_id', 'plan_id', 'tipo', 'periodo', 'precio', 'extras', 'implementacion', 'implementacion_pagada_el', 'mantenimiento',
                           'inicio', 'vence_el', 'dias_gracia', 'dias_lectura', 'notas', 'avisos'];

    protected $casts = ['precio' => 'float', 'extras' => 'array', 'implementacion' => 'float', 'implementacion_pagada_el' => 'date', 'mantenimiento' => 'float',
                        'inicio' => 'date', 'vence_el' => 'date', 'dias_gracia' => 'integer', 'dias_lectura' => 'integer', 'avisos' => 'array'];

    public const TIPOS = [
        'suscripcion' => ['texto' => 'Suscripción',          'ayuda' => 'Paga cada mes o cada año. Al vencer: gracia, solo lectura y suspensión.'],
        'perpetua'    => ['texto' => 'Licencia de por vida', 'ayuda' => 'Ya pagó el sistema. Solo vence el mantenimiento y nunca se bloquea.'],
        'cortesia'    => ['texto' => 'Cortesía o demo',      'ayuda' => 'Sin cobro y sin vencimiento.'],
    ];
    public const PERIODOS = ['mensual' => 'Mensual', 'anual' => 'Anual'];

    public function cuenta(): BelongsTo { return $this->belongsTo(Cuenta::class); }
    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
    public function pagos(): HasMany { return $this->hasMany(LicenciaPago::class)->latest('fecha')->latest('id'); }

    /** Extras con su plan: [['plan' => Plan, 'cantidad' => 2, 'precio' => 150.0], …] */
    public function extrasDetalle(): array
    {
        $planes = Plan::whereIn('clave', collect($this->extras ?? [])->pluck('clave'))->get()->keyBy('clave');
        return collect($this->extras ?? [])->filter(fn ($e) => isset($planes[$e['clave']]) && ($e['cantidad'] ?? 0) > 0)
            ->map(fn ($e) => ['plan' => $planes[$e['clave']], 'cantidad' => (int) $e['cantidad'], 'precio' => (float) ($e['precio'] ?? $planes[$e['clave']]->precio_mensual)])->values()->all();
    }

    /** Usuarios que puede tener activos (null = sin límite) */
    public function usuariosMax(): ?int
    {
        if (! $this->plan || $this->plan->usuarios === null) return null;
        return $this->plan->usuarios + collect($this->extrasDetalle())->sum(fn ($e) => ($e['plan']->usuarios ?? 0) * $e['cantidad']);
    }

    public function sucursalesMax(): int
    {
        return max(1, ($this->plan?->sucursales ?? 1) + collect($this->extrasDetalle())->sum(fn ($e) => ($e['plan']->sucursales ?? 0) * $e['cantidad']));
    }

    /** Funciones extra (gastos, ia) que trae el plan o algún extra */
    public function funciones(): array
    {
        return array_values(array_unique(array_merge((array) ($this->plan?->incluye ?? []), ...array_map(fn ($e) => (array) ($e['plan']->incluye ?? []), $this->extrasDetalle()))));
    }

    /** Precio sugerido por periodo: plan + extras (los extras se cobran por mes; en anual, 10 meses) */
    public function precioSugerido(): float
    {
        $meses = $this->periodo === 'anual' ? 10 : 1;
        return round(($this->plan?->precio($this->periodo) ?? 0) + collect($this->extrasDetalle())->sum(fn ($e) => $e['precio'] * $e['cantidad'] * $meses), 2);
    }
}
