<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Archivos que le pedimos al cliente para un proyecto */
class ProyectoSolicitud extends Model
{
    use \App\Models\Concerns\DeCuenta;

    protected $table = 'proyecto_solicitudes';

    protected $fillable = ['titulo', 'instrucciones', 'fecha_limite', 'estado', 'recibida_at'];

    protected $casts = ['fecha_limite' => 'date', 'recibida_at' => 'datetime'];

    public function proyecto(): BelongsTo { return $this->belongsTo(Proyecto::class); }
    public function archivos(): HasMany { return $this->hasMany(ProyectoArchivo::class, 'solicitud_id')->orderBy('id'); }

    public function getAbiertaAttribute(): bool { return $this->estado === 'abierta'; }

    public function getVencidaAttribute(): bool
    {
        return $this->abierta && $this->fecha_limite && $this->fecha_limite->lt(now(config('vandu.zona_horaria'))->startOfDay());
    }

    public function getFechaTextoAttribute(): ?string
    {
        return $this->fecha_limite?->locale('es')->isoFormat('D [de] MMMM');
    }
}
