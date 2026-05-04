<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cotizacion extends Model
{
    protected $fillable = ['name', 'lastname', 'phone', 'email', 'service'];
}