<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LealtadVisita extends Model
{
    use Concerns\DeCuenta;

    protected $table = 'lealtad_visitas';
    protected $fillable = ['cuenta_id', 'comensal_id', 'fecha', 'monto', 'sucursal_id', 'registrada_por', 'ejemplo'];
    protected $casts = ['fecha' => 'date', 'monto' => 'float', 'ejemplo' => 'boolean'];

    public function comensal(): BelongsTo { return $this->belongsTo(Comensal::class); }
    public function registrador(): BelongsTo { return $this->belongsTo(User::class, 'registrada_por'); }
}
