<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cómo se ve el perfil del cliente en una red (nombre, usuario, foto, biografía) */
class RedesPerfil extends Model
{
    protected $table = 'redes_perfiles';

    protected $fillable = ['cliente_id', 'red', 'usuario', 'nombre', 'bio', 'enlace', 'seguidores', 'seguidos', 'avatar', 'avatar_origen'];

    public function cliente(): BelongsTo { return $this->belongsTo(Cliente::class); }
}
