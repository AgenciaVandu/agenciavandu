<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Código de verificación para que el cliente apruebe o comente su contenido (24 h) */
class RedesCodigo extends Model
{
    protected $table = 'redes_codigos';

    protected $fillable = ['cliente_id', 'codigo_hash', 'canal', 'expira_at', 'intentos'];

    protected $casts = ['expira_at' => 'datetime'];

    protected $hidden = ['codigo_hash'];
}
