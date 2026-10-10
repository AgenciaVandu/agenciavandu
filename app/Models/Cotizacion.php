<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cotizacion extends Model
{
    use \App\Models\Concerns\DeCuenta;

    protected $fillable = ['name', 'lastname', 'phone', 'email', 'service'];
}