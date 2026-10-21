<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Persona del programa de lealtad. Es de la plataforma, no de un restaurante: una sola cuenta y un solo QR
 * para todos los restaurantes a los que se une. Cada restaurante solo la ve si está inscrita en su programa.
 */
class Comensal extends Model implements AuthenticatableContract
{
    use Authenticatable;

    protected $table = 'comensales';

    protected $fillable = ['nombre', 'email', 'telefono', 'password', 'ejemplo'];

    protected $hidden = ['password', 'remember_token', 'qr_secreto', 'acceso_hash'];

    protected $casts = ['password' => 'hashed', 'acceso_expira' => 'datetime', 'ultimo_acceso_at' => 'datetime', 'ejemplo' => 'boolean'];

    protected static function booted(): void
    {
        static::creating(fn (Comensal $c) => $c->qr_secreto ??= Str::random(48));
    }

    public function inscripciones(): HasMany { return $this->hasMany(LealtadInscripcion::class)->withoutGlobalScope('cuenta'); }

    public function setEmailAttribute($v): void
    {
        $this->attributes['email'] = mb_strtolower(trim((string) $v));
    }

    public function getPrimerNombreAttribute(): string
    {
        return Str::before(trim($this->nombre), ' ') ?: $this->nombre;
    }

    public function getInicialesAttribute(): string
    {
        return collect(explode(' ', trim($this->nombre)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->join('');
    }

    public function getWhatsappAttribute(): ?string
    {
        $t = preg_replace('/\D/', '', (string) $this->telefono);
        if ($t === '') return null;
        return strlen($t) === 10 ? '52' . $t : $t;
    }

    /** Enlace para crear una contraseña nueva (7 días); el anterior deja de servir */
    public function nuevoAcceso(): string
    {
        $token = Str::random(48);
        $this->forceFill(['acceso_hash' => hash('sha256', $token), 'acceso_expira' => now()->addDays(7)])->saveQuietly();
        return route('lealtad.acceso', $token);
    }

    public static function porAcceso(string $token): ?self
    {
        return static::where('acceso_hash', hash('sha256', $token))->where('acceso_expira', '>', now())->first();
    }
}
