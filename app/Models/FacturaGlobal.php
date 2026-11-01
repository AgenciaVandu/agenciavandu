<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/** Factura global al público en general: junta las ventas de mostrador sin factura de un periodo */
class FacturaGlobal extends Model
{
    use \App\Models\Concerns\DeCuenta;

    protected $table = 'facturas_globales';

    public const PERIODICIDADES = ['day' => 'Diaria', 'week' => 'Semanal', 'fortnight' => 'Quincenal', 'month' => 'Mensual', 'two_months' => 'Bimestral'];

    protected $fillable = ['serie', 'folio', 'periodicidad', 'meses', 'anio', 'desde', 'hasta', 'ventas', 'subtotal', 'iva', 'total',
        'facturapi_id', 'uuid', 'pdf', 'xml', 'cancelacion', 'cancelada_at', 'user_id'];

    protected $casts = ['desde' => 'date', 'hasta' => 'date', 'subtotal' => 'float', 'iva' => 'float', 'total' => 'float', 'cancelada_at' => 'datetime'];

    protected static function booted(): void
    {
        static::deleted(fn (FacturaGlobal $g) => Storage::disk('local')->delete(array_filter([$g->pdf, $g->xml])));
    }

    public function incluidas(): HasMany { return $this->hasMany(Venta::class, 'global_id'); }

    public function getFolioTextoAttribute(): string { return trim(($this->serie ? $this->serie . '-' : '') . ($this->folio ?? $this->id)); }
}
