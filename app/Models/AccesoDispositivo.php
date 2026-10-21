<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** Un torniquete o puerta con lector (Control iD iDFace en modo online) */
class AccesoDispositivo extends Model
{
    use Concerns\DeCuenta;

    protected $table = 'acceso_dispositivos';
    protected $fillable = ['cuenta_id', 'sucursal_id', 'nombre', 'token', 'accion', 'parametros', 'reingreso', 'credencial_fija', 'activo', 'ultimo_contacto_at'];
    protected $casts = ['reingreso' => 'boolean', 'credencial_fija' => 'boolean', 'activo' => 'boolean', 'ultimo_contacto_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(fn ($d) => $d->token ??= self::nuevoToken());
    }

    public static function nuevoToken(): string
    {
        do { $t = Str::lower(Str::random(32)); } while (static::sinCuenta()->where('token', $t)->exists());
        return $t;
    }

    public function sucursal(): BelongsTo { return $this->belongsTo(Sucursal::class); }
    public function accesos(): HasMany { return $this->hasMany(Acceso::class, 'dispositivo_id'); }

    /** La dirección del "servidor" que se escribe en el equipo */
    public function getUrlAttribute(): string
    {
        return url('api/controlid/' . $this->token);
    }

    /** ¿Ha hablado con Nerby en los últimos minutos? */
    public function getEnLineaAttribute(): bool
    {
        return $this->ultimo_contacto_at !== null && $this->ultimo_contacto_at->gt(now()->subMinutes(10));
    }
}
