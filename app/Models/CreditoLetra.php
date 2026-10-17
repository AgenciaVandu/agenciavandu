<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Una mensualidad del crédito: su capital, su interés y cómo se pagó */
class CreditoLetra extends Model
{
    public $timestamps = false;

    protected $table = 'credito_letras';

    protected $fillable = ['credito_id', 'numero', 'vence_el', 'capital', 'interes', 'monto', 'pago_id', 'pagada_el', 'pagado', 'moratorio', 'descuento', 'adelantada'];

    protected $casts = ['vence_el' => 'date', 'pagada_el' => 'date', 'capital' => 'float', 'interes' => 'float', 'monto' => 'float',
                        'pagado' => 'float', 'moratorio' => 'float', 'descuento' => 'float', 'adelantada' => 'boolean'];

    public function credito(): BelongsTo { return $this->belongsTo(Credito::class); }
    public function pago(): BelongsTo { return $this->belongsTo(CreditoPago::class, 'pago_id'); }

    public function getPagadaAttribute(): bool
    {
        return $this->pagada_el !== null;
    }
}
