<?php

namespace App\Models\Concerns;

use App\Models\Membresia;
use App\Models\Reservacion;
use App\Support\Cuentas;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** Lo que un Cliente tiene como atleta de un gimnasio (giro fitness) */
trait EsAtleta
{
    public function membresias(): HasMany { return $this->hasMany(Membresia::class)->latest('inicia_el')->latest('id'); }
    public function reservaciones(): HasMany { return $this->hasMany(Reservacion::class)->latest('id'); }

    /** Membresías vigentes hoy, la que vence primero adelante (es la que se usa al reservar) */
    public function membresiasVigentes(?string $fecha = null)
    {
        return $this->membresias()->getQuery()->reorder()->vigentes($fecha)->orderBy('vence_el')->orderBy('id')->get();
    }

    public function getMembresiaActualAttribute(): ?Membresia
    {
        $vig = $this->relationLoaded('membresias') ? $this->membresias->filter->vigente->sortBy(fn ($m) => [$m->con_creditos ? 0 : 1, $m->vence_el->toDateString()]) : null;
        return $vig ? $vig->first() : $this->membresiasVigentes()->sortBy(fn ($m) => $m->con_creditos ? 0 : 1)->first();
    }

    /** Código de su credencial (QR). Se crea la primera vez que se pide */
    public function getQrAttribute(): string
    {
        if (! $this->qr_codigo) {
            do { $c = Str::random(32); } while (static::sinCuenta()->where('qr_codigo', $c)->exists());
            $this->forceFill(['qr_codigo' => $c])->saveQuietly();
        }
        return 'FIT:' . $this->qr_codigo;
    }

    /** Si pierde su credencial o se la prestó a alguien: el QR anterior deja de servir */
    public function nuevoQr(): string
    {
        $this->forceFill(['qr_codigo' => null])->saveQuietly();
        return $this->qr;
    }

    public function getFotoUrlAttribute(): ?string
    {
        return $this->foto ? route('admin.atletas.foto', [$this->id, 'v' => substr(md5((string) $this->foto), 0, 8)]) : null;
    }

    public function getInicialesAttribute(): string
    {
        return collect(explode(' ', trim((string) $this->nombre)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->join('');
    }

    public function getPrimerNombreAttribute(): string
    {
        return Str::before(trim((string) $this->nombre), ' ') ?: (string) $this->nombre;
    }

    /** El correo es su usuario del portal: siempre en minúsculas y sin espacios */
    public function setEmailAttribute($v): void
    {
        $this->attributes['email'] = is_string($v) && trim($v) !== '' ? mb_strtolower(trim($v)) : $v;
    }

    /** ¿Ya tiene acceso a su portal? */
    public function getConAccesoAttribute(): bool { return $this->password !== null; }

    /** Enlace para crear (o cambiar) su contraseña del portal; el anterior deja de servir */
    public function nuevoAcceso(int $dias = 7): string
    {
        $token = Str::random(48);
        $this->forceFill(['acceso_hash' => hash('sha256', $token), 'acceso_expira' => now()->addDays($dias)])->saveQuietly();
        return Cuentas::enlace(route('atleta.acceso', $token), $this->cuenta_id);
    }

    public static function porAcceso(string $token): ?self
    {
        return static::sinCuenta()->where('acceso_hash', hash('sha256', $token))->where('acceso_expira', '>', now())->first();
    }
}
