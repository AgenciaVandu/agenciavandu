<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Otra razón social (empresa) del mismo cliente. La principal son los datos fiscales del propio cliente.
 * Tiene los mismos campos y textos que el cliente para usarse igual en facturas y correos.
 */
class ClienteRazon extends Model
{
    use \App\Models\Concerns\DeCuenta;

    protected $table = 'cliente_razones';

    protected $fillable = ['nombre', 'razon_social', 'rfc', 'regimen_fiscal', 'cp_fiscal', 'uso_cfdi', 'email_factura', 'orden'];

    public function cliente(): BelongsTo { return $this->belongsTo(Cliente::class); }

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

    /** Método de pago y correo: los del cliente si esta razón no tiene los suyos */
    public function getMetodoPagoTextoAttribute(): ?string { return $this->cliente?->metodo_pago_texto; }
    public function getMetodoPagoAttribute(): ?string { return $this->cliente?->metodo_pago; }

    public function getFiscalesTextoAttribute(): string
    {
        return collect([
            'RFC'            => $this->rfc,
            'Razón social'   => $this->razon_social,
            'Régimen fiscal' => $this->regimen_texto,
            'Código postal'  => $this->cp_fiscal,
            'Uso de CFDI'    => $this->uso_cfdi_texto,
            'Método de pago' => $this->metodo_pago_texto,
            'Correo'         => $this->email_factura ?: $this->cliente?->email_factura ?: $this->cliente?->email,
        ])->filter()->map(fn ($v, $k) => "$k: $v")->join("\n");
    }
}
