<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un pago de licencia registrado por la plataforma (mensualidad, anualidad, implementación o mantenimiento) */
class LicenciaPago extends Model
{
    protected $fillable = ['cuenta_id', 'licencia_id', 'fecha', 'concepto', 'monto', 'iva', 'forma', 'referencia', 'cuenta_dinero_id', 'periodos', 'cubre_desde', 'cubre_hasta', 'user_id'];

    protected $casts = ['fecha' => 'date', 'monto' => 'float', 'iva' => 'float', 'periodos' => 'integer', 'cubre_desde' => 'date', 'cubre_hasta' => 'date'];

    public const CONCEPTOS = ['periodo' => 'Mensualidad o anualidad', 'implementacion' => 'Implementación', 'mantenimiento' => 'Mantenimiento anual', 'otro' => 'Otro'];
    public const FORMAS = ['transferencia' => 'Transferencia', 'efectivo' => 'Efectivo', 'tarjeta' => 'Tarjeta', 'deposito' => 'Depósito', 'otro' => 'Otro'];

    public function licencia(): BelongsTo { return $this->belongsTo(Licencia::class); }
    public function usuario(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function cuenta(): BelongsTo { return $this->belongsTo(Cuenta::class); }

    /** Folio del recibo: R-000123 */
    public function getFolioAttribute(): string { return 'R-' . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT); }

    public function getConceptoTextoAttribute(): string
    {
        if ($this->concepto === 'periodo') return $this->licencia?->periodo === 'anual' ? ($this->periodos > 1 ? "Anualidad ({$this->periodos} años)" : 'Anualidad') : ($this->periodos > 1 ? "Mensualidad ({$this->periodos} meses)" : 'Mensualidad');
        if ($this->concepto === 'mantenimiento') return 'Mantenimiento anual' . ($this->periodos > 1 ? " ({$this->periodos} años)" : '');
        return self::CONCEPTOS[$this->concepto] ?? $this->concepto;
    }
}
