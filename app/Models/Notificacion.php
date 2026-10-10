<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Historial de notificaciones push enviadas */
class Notificacion extends Model
{
    protected $table = 'notificaciones';

    public const UPDATED_AT = null;

    protected $fillable = ['evento', 'titulo', 'cuerpo', 'url', 'entregadas'];
}
