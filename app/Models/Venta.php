<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** Venta directa de productos: en mostrador o como pedido con envío. Se cobra de contado o a crédito con abonos */
class Venta extends Model
{
    use Concerns\DeCuenta;

    protected $fillable = ['cuenta_id', 'folio', 'cliente_id', 'cliente_nombre', 'cliente_telefono', 'presupuesto_id', 'user_id', 'sucursal_id', 'fecha',
        'subtotal', 'descuento', 'con_iva', 'iva_porcentaje', 'iva', 'envio_costo', 'total', 'pagado', 'credito', 'vence_el', 'estado', 'notas',
        'lleva_envio', 'envio_nombre', 'envio_telefono', 'envio_direccion', 'envio_referencias', 'envio_lat', 'envio_lng', 'envio_km', 'envio_costo_manual',
        'repartidor_id', 'token', 'datos_at', 'datos_por', 'entregada_at', 'cancelada_at'];

    protected $casts = ['fecha' => 'date', 'vence_el' => 'date', 'subtotal' => 'float', 'descuento' => 'float', 'con_iva' => 'boolean', 'iva_porcentaje' => 'float',
        'iva' => 'float', 'envio_costo' => 'float', 'total' => 'float', 'pagado' => 'float', 'credito' => 'boolean', 'lleva_envio' => 'boolean',
        'envio_lat' => 'float', 'envio_lng' => 'float', 'envio_km' => 'float', 'envio_costo_manual' => 'boolean',
        'datos_at' => 'datetime', 'entregada_at' => 'datetime', 'cancelada_at' => 'datetime'];

    public const ESTADOS = [
        'por_surtir' => ['texto' => 'Por surtir', 'clase' => 'pend', 'icono' => 'bi-box-seam'],
        'listo'      => ['texto' => 'Listo',      'clase' => 'azul', 'icono' => 'bi-bag-check'],
        'en_camino'  => ['texto' => 'En camino',  'clase' => 'azul', 'icono' => 'bi-truck'],
        'entregada'  => ['texto' => 'Entregado',  'clase' => 'ok',   'icono' => 'bi-check2-circle'],
        'cancelada'  => ['texto' => 'Cancelada',  'clase' => 'gris', 'icono' => 'bi-x-circle'],
    ];

    public const FORMAS = ['efectivo' => 'Efectivo', 'tarjeta' => 'Tarjeta', 'transferencia' => 'Transferencia', 'otro' => 'Otro'];

    protected static function booted(): void
    {
        static::creating(function (Venta $v) {
            $v->folio ??= static::siguienteFolio();
            $v->token ??= Str::random(32);
            $v->fecha ??= now(config('vandu.zona_horaria'))->toDateString();
        });
    }

    public static function siguienteFolio(): string
    {
        $ultimo = static::where('folio', 'like', 'V-%')->orderByDesc('id')->value('folio');
        return 'V-' . str_pad((string) ($ultimo ? ((int) Str::after($ultimo, 'V-')) + 1 : 1), 4, '0', STR_PAD_LEFT);
    }

    public function conceptos(): HasMany { return $this->hasMany(VentaConcepto::class)->orderBy('orden')->orderBy('id'); }
    public function pagos(): HasMany { return $this->hasMany(VentaPago::class)->orderBy('fecha')->orderBy('id'); }
    public function cliente(): BelongsTo { return $this->belongsTo(Cliente::class); }
    public function vendedor(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function repartidor(): BelongsTo { return $this->belongsTo(User::class, 'repartidor_id'); }
    public function presupuesto(): BelongsTo { return $this->belongsTo(Presupuesto::class); }

    public function scopeVigentes(Builder $q): Builder { return $q->where('estado', '!=', 'cancelada'); }
    public function scopePorCobrar(Builder $q): Builder { return $q->vigentes()->whereColumn('pagado', '<', 'total'); }
    public function scopePorEntregar(Builder $q): Builder { return $q->whereIn('estado', ['por_surtir', 'listo', 'en_camino']); }

    /** Recalcula importes con los conceptos, el descuento, el IVA y el envío */
    public function recalcular(): void
    {
        $this->subtotal = round($this->conceptos()->sum('importe'), 2);
        $base = max(0, round($this->subtotal - $this->descuento, 2));
        $this->iva = $this->con_iva ? round($base * $this->iva_porcentaje / 100, 2) : 0.0;
        $this->total = round($base + $this->iva + $this->envio_costo, 2);
        $this->pagado = round($this->pagos()->sum('monto'), 2);
    }

    public function getSaldoAttribute(): float { return max(0, round($this->total - $this->pagado, 2)); }
    public function getPagadaAttribute(): bool { return $this->saldo < 0.01; }
    public function getVencidaAttribute(): bool { return ! $this->pagada && $this->vence_el && $this->vence_el->lt(now(config('vandu.zona_horaria'))->startOfDay()) && $this->estado !== 'cancelada'; }

    /** Lo vendido antes de IVA (para Finanzas sin IVA) */
    public function getNetoAttribute(): float { return round($this->total - $this->iva, 2); }

    public function getPagoTextoAttribute(): string
    {
        if ($this->estado === 'cancelada') return 'Cancelada';
        if ($this->pagada) return 'Pagada';
        if ($this->pagado > 0) return 'Abonó ' . number_format($this->pagado, 0);
        return $this->credito ? 'A crédito' : 'Por cobrar';
    }

    public function getPagoClaseAttribute(): string
    {
        return match (true) { $this->estado === 'cancelada' => 'gris', $this->pagada => 'ok', $this->vencida => 'mal', default => 'pend' };
    }

    public function getEstadoTextoAttribute(): string { return self::ESTADOS[$this->estado]['texto'] ?? $this->estado; }
    public function getEstadoClaseAttribute(): string { return self::ESTADOS[$this->estado]['clase'] ?? 'gris'; }

    public function getQuienAttribute(): string { return $this->cliente?->empresa ?: ($this->cliente?->nombre ?: ($this->cliente_nombre ?: 'Mostrador')); }

    public function getWhatsappAttribute(): ?string
    {
        $t = preg_replace('/\D/', '', (string) ($this->envio_telefono ?: $this->cliente_telefono ?: $this->cliente?->telefono));
        if ($t === '') return null;
        return strlen($t) === 10 ? '52' . $t : $t;
    }

    /** Enlace para el cliente: llena sus datos de envío y ve su nota */
    public function getUrlClienteAttribute(): string { return route('pedido.publico', $this->token); }

    public function getMapaUrlAttribute(): ?string
    {
        if ($this->envio_lat && $this->envio_lng) return 'https://www.google.com/maps/search/?api=1&query=' . $this->envio_lat . ',' . $this->envio_lng;
        return $this->envio_direccion ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($this->envio_direccion) : null;
    }

    public function getWazeUrlAttribute(): ?string
    {
        return $this->envio_lat && $this->envio_lng ? 'https://waze.com/ul?ll=' . $this->envio_lat . ',' . $this->envio_lng . '&navigate=yes' : null;
    }

    public static function dinero(float $n): string { return '$' . number_format($n, 2); }
}
