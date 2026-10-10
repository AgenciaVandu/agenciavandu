<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
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
        'invitacion_expira' => 'datetime',
        'ultimo_acceso_at' => 'datetime',
    ];

    public function rol(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Rol::class); }

    public function tareas(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(Tarea::class, 'asignada_a'); }

    public function esSuperAdmin(): bool
    {
        return (bool) $this->rol?->todo;
    }

    public function puede(string $seccion): bool
    {
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
        $u = static::where('invitacion_hash', hash('sha256', $token))->first();
        return $u && $u->activo && $u->invitacion_expira?->isFuture() ? $u : null;
    }

}
