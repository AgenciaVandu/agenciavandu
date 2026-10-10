<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Una sección de la galería del proyecto: "Primera entrega", "Fotos adicionales"… */
class GaleriaSeccion extends Model
{
    use \App\Models\Concerns\DeCuenta;

    protected $table = 'galeria_secciones';

    protected $fillable = ['proyecto_id', 'nombre', 'carpeta', 'orden', 'zip_url'];

    public function proyecto(): BelongsTo { return $this->belongsTo(Proyecto::class); }
    public function archivos(): HasMany { return $this->hasMany(ProyectoArchivo::class, 'seccion_id')->orderBy('orden')->orderBy('id'); }
}
