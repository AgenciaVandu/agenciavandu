<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Correo extends Model
{
    protected $fillable = ['cliente_id', 'presupuesto_id', 'proyecto_id', 'plantilla', 'para', 'cc', 'asunto', 'cuerpo', 'adjuntos', 'estado', 'error'];

    protected $casts = ['adjuntos' => 'array'];

    public function cliente(): BelongsTo { return $this->belongsTo(Cliente::class); }
    public function presupuesto(): BelongsTo { return $this->belongsTo(Presupuesto::class); }
    public function proyecto(): BelongsTo { return $this->belongsTo(Proyecto::class); }

    public function getPlantillaNombreAttribute(): string
    {
        return \App\Support\PlantillasCorreo::una((string) $this->plantilla)['nombre'] ?? 'Correo';
    }

    public function getEnviadoAttribute(): bool
    {
        return $this->estado === 'enviado';
    }
}
