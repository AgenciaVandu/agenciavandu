<?php

namespace App\Models;

use App\Support\Credito as Calculo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/** Venta de una unidad: de contado, con crédito del lote (letras y cobranza) o con una financiera */
class Credito extends Model
{
    use \App\Models\Concerns\DeCuenta;

    protected $table = 'creditos';

    public const TIPOS = [
        'contado'    => 'De contado',
        'credito'    => 'Crédito del lote',
        'financiera' => 'Banco o financiera',
    ];

    protected $fillable = ['unidad_id', 'cliente_id', 'vendedor_id', 'sucursal_id', 'tipo', 'fecha', 'precio', 'enganche', 'financiado', 'tasa', 'plazo',
                           'mensualidad', 'moratorio_modo', 'moratorio_valor', 'dias_gracia', 'descuento_adelanto', 'financiera', 'estado', 'notas'];

    protected $casts = ['fecha' => 'date', 'precio' => 'float', 'enganche' => 'float', 'financiado' => 'float', 'tasa' => 'float',
                        'mensualidad' => 'float', 'moratorio_valor' => 'float', 'descuento_adelanto' => 'float'];

    public function unidad(): BelongsTo { return $this->belongsTo(Unidad::class); }
    public function cliente(): BelongsTo { return $this->belongsTo(Cliente::class); }
    public function vendedor(): BelongsTo { return $this->belongsTo(User::class, 'vendedor_id'); }
    public function sucursal(): BelongsTo { return $this->belongsTo(Sucursal::class); }
    public function letras(): HasMany { return $this->hasMany(CreditoLetra::class)->orderBy('numero'); }
    public function pagos(): HasMany { return $this->hasMany(CreditoPago::class)->latest('fecha')->latest('id'); }

    public function scopeConCobranza($q) { return $q->where('tipo', 'credito')->where('estado', 'activo'); }

    /** Letras vencidas sin pagar (pasados los días de gracia) */
    public function atrasadas(?Carbon $hoy = null)
    {
        $hoy = ($hoy ?? now(config('vandu.zona_horaria')))->copy()->startOfDay();
        return $this->letras->filter(fn ($l) => ! $l->pagada && $l->vence_el->copy()->addDays((int) $this->dias_gracia)->lt($hoy));
    }

    public function getProximaAttribute(): ?CreditoLetra
    {
        return $this->letras->first(fn ($l) => ! $l->pagada);
    }

    /** Lo que falta por cobrar (letras pendientes, sin moratorios) */
    public function getPorCobrarAttribute(): float
    {
        return round($this->letras->filter(fn ($l) => ! $l->pagada)->sum('monto'), 2);
    }

    public function getSaldoCapitalAttribute(): float
    {
        return round($this->letras->filter(fn ($l) => ! $l->pagada)->sum('capital'), 2);
    }

    public function getVencidoAttribute(): float
    {
        return round($this->atrasadas()->sum('monto'), 2);
    }

    public function getCobradoAttribute(): float
    {
        return round($this->letras->sum(fn ($l) => (float) $l->pagado), 2);
    }

    /** Rango del termómetro según las letras atrasadas */
    public function getTermometroAttribute(): array
    {
        return Calculo::termometro($this->atrasadas()->count());
    }
}
