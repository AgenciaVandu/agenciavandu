<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Foto o video de un post (en Dropbox o en el servidor) */
class RedesMedio extends Model
{
    protected $table = 'redes_medios';

    protected $fillable = ['post_id', 'orden', 'tipo', 'origen', 'ruta', 'nombre', 'ancho', 'alto', 'bytes'];

    public function post(): BelongsTo { return $this->belongsTo(RedesPost::class, 'post_id'); }
}
