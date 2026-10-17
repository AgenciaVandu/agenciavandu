<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditoPago extends Model
{
    protected $table = 'credito_pagos';

    protected $fillable = ['credito_id', 'fecha', 'monto', 'metodo', 'nota', 'user_id'];

    protected $casts = ['fecha' => 'date', 'monto' => 'float'];

    public function credito(): BelongsTo { return $this->belongsTo(Credito::class); }
    public function letras(): HasMany { return $this->hasMany(CreditoLetra::class, 'pago_id')->orderBy('numero'); }
    public function usuario(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
}
