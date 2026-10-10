<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Código de verificación de identidad para aceptar o pedir cambios en línea (vigente 24 h) */
class PresupuestoCodigo extends Model
{
    protected $fillable = ['presupuesto_id', 'codigo_hash', 'canal', 'expira_at', 'usado_at', 'intentos'];

    protected $casts = ['expira_at' => 'datetime', 'usado_at' => 'datetime'];

    protected $hidden = ['codigo_hash'];
}
