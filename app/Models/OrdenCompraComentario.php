<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrdenCompraComentario extends Model
{
    use \App\Models\Concerns\DeCuenta;

    protected $fillable = ['autor', 'user_id', 'texto'];

    public function orden(): BelongsTo { return $this->belongsTo(OrdenCompra::class, 'orden_compra_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
