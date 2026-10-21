<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** El comensal se unió al programa de este restaurante y aceptó compartirle nombre, teléfono y correo */
class LealtadInscripcion extends Model
{
    use Concerns\DeCuenta;

    protected $table = 'lealtad_inscripciones';
    protected $fillable = ['cuenta_id', 'comensal_id', 'consentido_at', 'baja_at', 'origen', 'inscrito_por', 'ejemplo'];
    protected $casts = ['consentido_at' => 'datetime', 'baja_at' => 'datetime', 'ejemplo' => 'boolean'];

    public function comensal(): BelongsTo { return $this->belongsTo(Comensal::class); }

    public function scopeActivas(Builder $q): Builder { return $q->whereNull('baja_at'); }
}
