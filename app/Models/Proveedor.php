<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Quien nos fabrica, imprime, borda o fotografía: recibe órdenes de compra y nos sube facturas y archivos */
class Proveedor extends Model
{
    use \App\Models\Concerns\DeCuenta;

    protected $table = 'proveedores';

    protected $fillable = ['empresa', 'nombre', 'rubro', 'telefono', 'email', 'rfc', 'razon_social', 'direccion', 'notas'];

    protected $casts = ['portal_visto_at' => 'datetime'];

    /** Rubros sugeridos; se puede escribir cualquier otro */
    public const RUBROS = ['Impresión', 'Bordados', 'Fotografía', 'Artículos promocionales', 'Rotulación', 'Instalación'];

    protected static function booted(): void
    {
        static::deleting(function (Proveedor $p) {
            $p->ordenes()->get()->each->delete();
            Storage::disk('local')->deleteDirectory("proveedores/{$p->id}");
        });
    }

    public function ordenes(): HasMany
    {
        return $this->hasMany(OrdenCompra::class)->latest('fecha')->latest('id');
    }

    /** Proyectos en los que ha trabajado (los de sus órdenes) */
    public function proyectos()
    {
        return Proyecto::whereIn('id', $this->ordenes()->whereNotNull('proyecto_id')->select('proyecto_id'))->with('cliente')->latest('id');
    }

    /** Rubros para sugerir: los de fábrica y los que ya escribiste */
    public static function rubros(): array
    {
        return collect(self::RUBROS)->merge(static::whereNotNull('rubro')->distinct()->pluck('rubro'))
            ->map(fn ($r) => trim($r))->filter()->unique(fn ($r) => mb_strtolower($r))->sort()->values()->all();
    }

    /** Enlace del proveedor con todas sus órdenes (se crea la primera vez que se pide) */
    public function getPortalUrlAttribute(): string
    {
        if (! $this->portal_token) {
            $this->forceFill(['portal_token' => Str::random(32)])->saveQuietly();
        }
        return route('proveedor.portal', $this->portal_token);
    }

    public function getWhatsappAttribute(): ?string
    {
        $tel = preg_replace('/\D/', '', (string) $this->telefono);
        if ($tel === '') return null;
        return strlen($tel) === 10 ? '52' . $tel : $tel;
    }

    public function getPrimerNombreAttribute(): string
    {
        return \App\Support\Correos::primerNombre((string) ($this->nombre ?: $this->empresa));
    }
}
