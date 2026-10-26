<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Donde está el dinero del negocio: la caja, una cuenta de banco o la terminal */
class CuentaDinero extends Model
{
    use Concerns\DeCuenta;

    protected $table = 'cuentas_dinero';

    protected $fillable = ['cuenta_id', 'nombre', 'tipo', 'banco', 'formas', 'saldo_inicial', 'saldo_desde', 'activa', 'orden'];

    protected $casts = ['formas' => 'array', 'saldo_inicial' => 'float', 'saldo_desde' => 'date', 'activa' => 'boolean'];

    public const TIPOS = [
        'efectivo' => ['texto' => 'Efectivo (caja)', 'icono' => 'bi-cash-coin',    'formas' => ['efectivo']],
        'banco'    => ['texto' => 'Banco',           'icono' => 'bi-bank',         'formas' => ['transferencia']],
        'terminal' => ['texto' => 'Terminal',        'icono' => 'bi-credit-card',  'formas' => ['tarjeta']],
        'otro'     => ['texto' => 'Otra',            'icono' => 'bi-wallet2',      'formas' => []],
    ];

    /** Formas de pago que se pueden ligar a una cuenta (las de ventas, gastos y cobros de proyectos se traducen a estas) */
    public const FORMAS = ['efectivo' => 'Efectivo', 'transferencia' => 'Transferencia', 'tarjeta' => 'Tarjeta', 'otro' => 'Otro'];

    public function conciliaciones(): HasMany { return $this->hasMany(Conciliacion::class)->latest('fecha')->latest('id'); }

    public function scopeActivas(Builder $q): Builder { return $q->where('activa', true)->orderBy('orden')->orderBy('id'); }

    public function getIconoAttribute(): string { return self::TIPOS[$this->tipo]['icono'] ?? 'bi-wallet2'; }
    public function getTipoTextoAttribute(): string { return self::TIPOS[$this->tipo]['texto'] ?? 'Otra'; }
}
