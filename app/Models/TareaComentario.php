<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TareaComentario extends Model
{
    protected $fillable = ['tarea_id', 'user_id', 'texto'];

    public function tarea(): BelongsTo { return $this->belongsTo(Tarea::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
