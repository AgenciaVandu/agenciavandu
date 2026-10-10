<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Un negocio dentro de la plataforma (una agencia, un estudio de arquitectura…) */
class Cuenta extends Model
{
    protected $fillable = ['nombre', 'giro', 'activa', 'ajustes', 'notas'];

    protected $casts = ['ajustes' => 'array', 'activa' => 'boolean'];

    public function usuarios(): HasMany { return $this->hasMany(User::class)->withoutGlobalScope('cuenta'); }

    public function ajuste(string $clave, $defecto = null)
    {
        return data_get($this->ajustes ?? [], $clave, $defecto);
    }

    public function getGiroInfoAttribute(): array
    {
        return config("vandu.giros.{$this->giro}") ?? config('vandu.giros.agencia');
    }

    public function tiene(string $modulo): bool
    {
        return in_array($modulo, $this->giro_info['modulos'] ?? [], true);
    }

    /** Logo como data URI (para el panel, la vista del cliente, el PDF y los correos) */
    public function logo(bool $oscuro = false): ?string
    {
        foreach ($oscuro ? ['logo_oscuro', 'logo'] : ['logo', 'logo_oscuro'] as $k) {
            $ruta = $this->ajuste("marca.$k");
            if ($ruta && is_file(storage_path('app/' . $ruta))) {
                $mime = str_ends_with($ruta, '.svg') ? 'image/svg+xml' : (str_ends_with($ruta, '.png') ? 'image/png' : 'image/jpeg');
                return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents(storage_path('app/' . $ruta)));
            }
        }
        return null;
    }
}
