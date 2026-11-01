<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Serie de facturación del negocio (A, B, NC…) con su siguiente folio. Cada tipo de CFDI tiene una predeterminada */
class FacturaSerie extends Model
{
    use \App\Models\Concerns\DeCuenta;

    protected $table = 'factura_series';

    public const TIPOS = ['I' => 'Facturas', 'E' => 'Notas de crédito', 'P' => 'Complementos de pago', 'G' => 'Facturas globales'];
    public const SUGERIDAS = ['I' => 'A', 'E' => 'NC', 'P' => 'CP', 'G' => 'G'];

    protected $fillable = ['serie', 'tipo', 'descripcion', 'siguiente', 'predeterminada', 'activa'];

    protected $casts = ['siguiente' => 'integer', 'predeterminada' => 'boolean', 'activa' => 'boolean'];

    public function getTipoTextoAttribute(): string { return self::TIPOS[$this->tipo] ?? $this->tipo; }
}
