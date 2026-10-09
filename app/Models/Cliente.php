<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Cliente extends Model
{
    protected $fillable = [
        'nombre', 'empresa', 'email', 'telefono',
        'rfc', 'razon_social', 'regimen_fiscal', 'cp_fiscal', 'uso_cfdi', 'email_factura', 'notas',
    ];

    protected static function booted(): void
    {
        // Al borrar el cliente se borra su expediente de constancias
        static::deleting(fn (Cliente $c) => Storage::disk('local')->deleteDirectory("clientes/{$c->id}"));
    }

    public function constancias(): HasMany
    {
        return $this->hasMany(ClienteConstancia::class)->latest('id');
    }

    /** Ya hay lo mínimo para facturar */
    public function getFiscalesCompletosAttribute(): bool
    {
        return filled($this->rfc) && filled($this->razon_social) && filled($this->regimen_fiscal) && filled($this->cp_fiscal);
    }

    public function getRegimenTextoAttribute(): ?string
    {
        return $this->regimen_fiscal ? $this->regimen_fiscal . ' · ' . (config("vandu.sat.regimenes.{$this->regimen_fiscal}") ?? '') : null;
    }

    public function getUsoCfdiTextoAttribute(): ?string
    {
        return $this->uso_cfdi ? $this->uso_cfdi . ' · ' . (config("vandu.sat.usos_cfdi.{$this->uso_cfdi}") ?? '') : null;
    }

    /** Bloque listo para pegar en el sistema de facturación */
    public function getFiscalesTextoAttribute(): string
    {
        return collect([
            'RFC'            => $this->rfc,
            'Razón social'   => $this->razon_social,
            'Régimen fiscal' => $this->regimen_texto,
            'Código postal'  => $this->cp_fiscal,
            'Uso de CFDI'    => $this->uso_cfdi_texto,
            'Correo'         => $this->email_factura ?: $this->email,
        ])->filter()->map(fn ($v, $k) => "$k: $v")->join("\n");
    }

    public function presupuestos(): HasMany
    {
        return $this->hasMany(Presupuesto::class)->latest();
    }

    public function proyectos(): HasMany
    {
        return $this->hasMany(Proyecto::class)->latest();
    }

    /** Teléfono solo con dígitos y lada 52 para wa.me */
    public function getWhatsappAttribute(): ?string
    {
        $tel = preg_replace('/\D/', '', (string) $this->telefono);
        if ($tel === '') {
            return null;
        }
        return strlen($tel) === 10 ? '52' . $tel : $tel;
    }
}
