<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use \App\Models\Concerns\DeCuenta;

    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'rol_id',
        'puesto',
        'telefono',
        'activo',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'invitacion_hash',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'activo' => 'boolean',
        'plataforma' => 'boolean',
        'invitacion_expira' => 'datetime',
        'ultimo_acceso_at' => 'datetime',
    ];

    // El rol se busca sin filtro de cuenta: quien administra la plataforma conserva el suyo al ver otra cuenta
    public function rol(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Rol::class)->withoutGlobalScope('cuenta'); }

    public function tareas(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(Tarea::class, 'asignada_a'); }

    public function esSuperAdmin(): bool
    {
        // Quien administra la plataforma es super admin en la cuenta que esté viendo
        if ($this->plataforma && \App\Support\Cuentas::id() !== $this->cuenta_id) return true;
        return (bool) $this->rol?->todo;
    }

    public function puede(string $seccion): bool
    {
        // Secciones que dependen del giro (p. ej. Redes sociales solo en agencias)
        $modulo = \App\Support\Permisos::MODULOS[$seccion] ?? null;
        if ($modulo && ! \App\Support\Cuentas::actual()?->tiene($modulo)) return false;
        return $this->esSuperAdmin() || (bool) $this->rol?->puede($seccion);
    }

    /** Todavía no acepta su invitación (no ha creado su contraseña ni entrado nunca) */
    public function getPendienteAttribute(): bool
    {
        return $this->invitacion_hash !== null && $this->ultimo_acceso_at === null;
    }

    public function getWhatsappAttribute(): ?string
    {
        $tel = preg_replace('/\D/', '', (string) $this->telefono);
        if ($tel === '') return null;
        return strlen($tel) === 10 ? '52' . $tel : $tel;
    }

    public function getPrimerNombreAttribute(): string
    {
        return \Illuminate\Support\Str::before(trim($this->name), ' ') ?: $this->name;
    }

    /** Genera un enlace nuevo para crear (o cambiar) su contraseña; el anterior deja de servir */
    public function nuevoEnlace(int $dias = 7): string
    {
        $token = \Illuminate\Support\Str::random(48);
        $this->forceFill(['invitacion_hash' => hash('sha256', $token), 'invitacion_expira' => now()->addDays($dias)])->save();
        return route('invitacion', $token);
    }

    public static function porInvitacion(string $token): ?self
    {
        $u = static::sinCuenta()->where('invitacion_hash', hash('sha256', $token))->first();
        if (! $u || ! $u->activo || ! $u->invitacion_expira?->isFuture()) return null;
        \App\Support\Cuentas::activar($u->cuenta_id); // la invitación sale con la marca de su negocio
        return $u;
    }

}
