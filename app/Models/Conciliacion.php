<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Una comparación del saldo real (estado de cuenta, conteo de caja) contra el saldo del panel */
class Conciliacion extends Model
{
    use Concerns\DeCuenta;

    protected $table = 'conciliaciones';

    protected $fillable = ['cuenta_id', 'cuenta_dinero_id', 'fecha', 'saldo_real', 'saldo_panel', 'diferencia', 'ajustada', 'nota', 'user_id'];

    protected $casts = ['fecha' => 'date', 'saldo_real' => 'float', 'saldo_panel' => 'float', 'diferencia' => 'float', 'ajustada' => 'boolean'];

    public function cuentaDinero(): BelongsTo { return $this->belongsTo(CuentaDinero::class); }
    public function usuario(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
}
