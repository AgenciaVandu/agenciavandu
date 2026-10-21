<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LealtadCanje extends Model
{
    use Concerns\DeCuenta;

    protected $table = 'lealtad_canjes';
    protected $fillable = ['cuenta_id', 'comensal_id', 'premio_id', 'canjeado_por', 'ejemplo'];
    protected $casts = ['ejemplo' => 'boolean'];

    public function comensal(): BelongsTo { return $this->belongsTo(Comensal::class); }
    public function premio(): BelongsTo { return $this->belongsTo(LealtadPremio::class, 'premio_id'); }
}
