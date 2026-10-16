<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Enlace anterior de un producto: sigue funcionando y redirige al actual */
class ProductoSlug extends Model
{
    protected $table = 'producto_slugs';

    protected $fillable = ['producto_id', 'slug'];
}
