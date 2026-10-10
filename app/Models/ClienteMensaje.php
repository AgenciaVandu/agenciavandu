<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un mensaje que el contacto mandó desde el formulario de agenciavandu.com */
class ClienteMensaje extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['cliente_id', 'servicio', 'datos'];

    protected $casts = ['datos' => 'array'];

    public function cliente(): BelongsTo { return $this->belongsTo(Cliente::class); }
}
