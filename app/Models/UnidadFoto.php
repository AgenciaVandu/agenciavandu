<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnidadFoto extends Model
{
    protected $table = 'unidad_fotos';

    protected $fillable = ['unidad_id', 'archivo', 'orden'];

    public function unidad(): BelongsTo { return $this->belongsTo(Unidad::class); }

    public function getUrlAttribute(): string
    {
        return route('autos.foto', [$this->unidad_id, basename($this->archivo)]);
    }

    public function getMiniaturaUrlAttribute(): string
    {
        return route('autos.foto', [$this->unidad_id, 'min-' . basename($this->archivo)]);
    }
}
