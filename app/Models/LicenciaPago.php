<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un pago de licencia registrado por la plataforma (mensualidad, anualidad, implementación o mantenimiento) */
class LicenciaPago extends Model
{
    protected $fillable = ['cuenta_id', 'licencia_id', 'fecha', 'concepto', 'monto', 'forma', 'referencia', 'periodos', 'cubre_desde', 'cubre_hasta', 'user_id'];

    protected $casts = ['fecha' => 'date', 'monto' => 'float', 'periodos' => 'integer', 'cubre_desde' => 'date', 'cubre_hasta' => 'date'];

    public const CONCEPTOS = ['periodo' => 'Mensualidad o anualidad', 'implementacion' => 'Implementación', 'mantenimiento' => 'Mantenimiento anual', 'otro' => 'Otro'];
    public const FORMAS = ['transferencia' => 'Transferencia', 'efectivo' => 'Efectivo', 'tarjeta' => 'Tarjeta', 'deposito' => 'Depósito', 'otro' => 'Otro'];

    public function licencia(): BelongsTo { return $this->belongsTo(Licencia::class); }
    public function usuario(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
}
