<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un gasto suelto (no viene de una orden de compra): ticket, factura o captura a mano */
class Gasto extends Model
{
    use Concerns\DeCuenta;

    protected $fillable = ['cuenta_id', 'user_id', 'fecha', 'concepto', 'categoria', 'proveedor', 'rfc', 'subtotal', 'iva', 'total', 'forma_pago',
                           'proyecto_id', 'sucursal_id', 'origen', 'foto', 'xml', 'uuid', 'notas', 'lectura'];

    protected $casts = ['fecha' => 'date', 'subtotal' => 'float', 'iva' => 'float', 'total' => 'float', 'lectura' => 'array'];

    public const CATEGORIAS = [
        'combustible'   => ['texto' => 'Gasolina y transporte',       'icono' => 'bi-fuel-pump'],
        'comida'        => ['texto' => 'Comida y viáticos',           'icono' => 'bi-cup-hot'],
        'materiales'    => ['texto' => 'Materiales e insumos',        'icono' => 'bi-box-seam'],
        'herramienta'   => ['texto' => 'Herramienta y equipo',        'icono' => 'bi-tools'],
        'oficina'       => ['texto' => 'Papelería y oficina',         'icono' => 'bi-paperclip'],
        'servicios'     => ['texto' => 'Luz, agua, internet y teléfono', 'icono' => 'bi-lightning-charge'],
        'renta'         => ['texto' => 'Renta',                       'icono' => 'bi-house-door'],
        'mantenimiento' => ['texto' => 'Mantenimiento y limpieza',    'icono' => 'bi-wrench-adjustable'],
        'publicidad'    => ['texto' => 'Publicidad',                  'icono' => 'bi-megaphone'],
        'software'      => ['texto' => 'Software y suscripciones',    'icono' => 'bi-cloud'],
        'nomina'        => ['texto' => 'Sueldos y honorarios',        'icono' => 'bi-people'],
        'impuestos'     => ['texto' => 'Impuestos y comisiones',      'icono' => 'bi-bank'],
        'otros'         => ['texto' => 'Otros',                       'icono' => 'bi-three-dots'],
    ];

    public const FORMAS = ['efectivo' => 'Efectivo', 'tarjeta' => 'Tarjeta', 'transferencia' => 'Transferencia', 'otro' => 'Otro'];

    public function usuario(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function proyecto(): BelongsTo { return $this->belongsTo(Proyecto::class); }
    public function sucursal(): BelongsTo { return $this->belongsTo(Sucursal::class); }

    /** Entre dos fechas (cualquiera puede ser null = sin límite) */
    public function scopeEntre(Builder $q, ?string $desde, ?string $hasta): Builder
    {
        return $q->when($desde, fn ($w) => $w->whereDate('fecha', '>=', $desde))->when($hasta, fn ($w) => $w->whereDate('fecha', '<=', $hasta));
    }

    public function monto(bool $conIva): float
    {
        return $conIva ? $this->total : $this->subtotal;
    }

    public function getCategoriaTextoAttribute(): string
    {
        return self::CATEGORIAS[$this->categoria]['texto'] ?? 'Otros';
    }

    public function getCategoriaIconoAttribute(): string
    {
        return self::CATEGORIAS[$this->categoria]['icono'] ?? 'bi-three-dots';
    }
}
