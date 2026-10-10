<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Conexiones con servicios externos (p. ej. Dropbox). Los tokens se guardan cifrados. */
class Integracion extends Model
{
    use \App\Models\Concerns\DeCuenta;

    protected $table = 'integraciones';

    protected $fillable = ['proveedor', 'cuenta', 'datos'];

    protected $casts = ['datos' => 'encrypted:array'];

    protected $hidden = ['datos'];

    public static function de(string $proveedor): ?self
    {
        return static::where('proveedor', $proveedor)->first();
    }
}
