<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Sucursal del negocio (lote, tienda). Cuántas puede tener lo define la plataforma */
class Sucursal extends Model
{
    use \App\Models\Concerns\DeCuenta;

    protected $table = 'sucursales';

    protected $fillable = ['nombre', 'direccion', 'telefono', 'activa'];

    protected $casts = ['activa' => 'boolean'];

    public function unidades(): HasMany { return $this->hasMany(Unidad::class); }
    public function usuarios(): HasMany { return $this->hasMany(User::class); }

    /** Sucursales contratadas por el negocio activo */
    public static function limite(): int
    {
        return max(1, (int) (\App\Support\Cuentas::actual()?->ajuste('sucursales_max', 1) ?? 1));
    }
}
