<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LealtadPremio extends Model
{
    use Concerns\DeCuenta;

    protected $table = 'lealtad_premios';
    protected $fillable = ['cuenta_id', 'campana_id', 'visitas', 'nombre', 'descripcion'];
    protected $casts = ['visitas' => 'integer'];

    public function campana(): BelongsTo { return $this->belongsTo(LealtadCampana::class, 'campana_id')->withoutGlobalScope('cuenta'); }
}
