<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** El dueño avisa "Ya pagué" con su comprobante; la plataforma lo confirma (se vuelve un pago) o lo rechaza */
class LicenciaComprobante extends Model
{
    protected $fillable = ['cuenta_id', 'licencia_id', 'user_id', 'fecha', 'monto', 'referencia', 'nota', 'archivo', 'estado', 'motivo', 'licencia_pago_id'];

    protected $casts = ['fecha' => 'date', 'monto' => 'float'];

    public function cuenta(): BelongsTo { return $this->belongsTo(Cuenta::class); }
    public function licencia(): BelongsTo { return $this->belongsTo(Licencia::class); }
    public function usuario(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function pago(): BelongsTo { return $this->belongsTo(LicenciaPago::class, 'licencia_pago_id'); }

    public function getEsImagenAttribute(): bool
    {
        return (bool) preg_match('/\.(jpe?g|png|webp)$/i', (string) $this->archivo);
    }
}
