<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    protected $fillable = [
        'nombre', 'empresa', 'email', 'telefono',
        'rfc', 'razon_social', 'uso_cfdi', 'notas',
    ];

    public function presupuestos(): HasMany
    {
        return $this->hasMany(Presupuesto::class)->latest();
    }

    public function proyectos(): HasMany
    {
        return $this->hasMany(Proyecto::class)->latest();
    }

    /** Teléfono solo con dígitos y lada 52 para wa.me */
    public function getWhatsappAttribute(): ?string
    {
        $tel = preg_replace('/\D/', '', (string) $this->telefono);
        if ($tel === '') {
            return null;
        }
        return strlen($tel) === 10 ? '52' . $tel : $tel;
    }
}
