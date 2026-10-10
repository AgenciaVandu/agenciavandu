<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Comentario, cambio pedido o aprobación en un post (del cliente o de la agencia) */
class RedesComentario extends Model
{
    use \App\Models\Concerns\DeCuenta;

    public const UPDATED_AT = null;

    protected $table = 'redes_comentarios';

    protected $fillable = ['post_id', 'tipo', 'actor', 'autor', 'texto'];
}
