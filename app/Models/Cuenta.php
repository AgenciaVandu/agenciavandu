<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Un negocio dentro de la plataforma (una agencia, un estudio de arquitectura…) */
class Cuenta extends Model
{
    protected $fillable = ['nombre', 'slug', 'giro', 'dominio', 'activa', 'ajustes', 'notas'];

    protected static function booted(): void
    {
        // Nombre corto para los enlaces del negocio sin dominio propio (p. ej. /atleta/axion-fit)
        static::creating(fn (Cuenta $c) => $c->slug ??= static::slugLibre($c->nombre));
    }

    public static function slugLibre(string $nombre, ?int $ignorar = null): string
    {
        $base = \Illuminate\Support\Str::slug($nombre) ?: 'negocio';
        $slug = $base;
        if (in_array($slug, \App\Support\Fitness\Portal::RESERVADAS, true)) $slug = $base = "$base-gym";
        for ($i = 2; static::where('slug', $slug)->when($ignorar, fn ($q) => $q->whereKeyNot($ignorar))->exists(); $i++) $slug = "$base-$i";
        return $slug;
    }

    protected $casts = ['ajustes' => 'array', 'activa' => 'boolean'];

    public function licencia(): \Illuminate\Database\Eloquent\Relations\HasOne { return $this->hasOne(Licencia::class); }
    public function usuarios(): HasMany { return $this->hasMany(User::class)->withoutGlobalScope('cuenta'); }

    /** "https://app.losos.mx" si tiene dominio propio */
    public function getUrlBaseAttribute(): ?string
    {
        return $this->dominio ? 'https://' . $this->dominio : null;
    }

    public function ajuste(string $clave, $defecto = null)
    {
        return data_get($this->ajustes ?? [], $clave, $defecto);
    }

    public function getGiroInfoAttribute(): array
    {
        return config("vandu.giros.{$this->giro}") ?? config('vandu.giros.agencia');
    }

    /** Módulos del giro, más los que se le activen a un negocio en particular (ajustes.modulos) */
    public function tiene(string $modulo): bool
    {
        // Proyectos, Cotizaciones, Proveedores, Archivos (Dropbox) y Tareas vienen prendidos; un tipo de empresa los apaga con "sin_…"
        if (in_array($modulo, ['proyectos', 'cotizaciones', 'proveedores', 'archivos', 'tareas', 'clientes', 'ventas', 'finanzas'], true)) return ! $this->tiene('sin_' . $modulo);
        return in_array($modulo, $this->giro_info['modulos'] ?? [], true)
            || in_array($modulo, (array) $this->ajuste('modulos', []), true);
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
