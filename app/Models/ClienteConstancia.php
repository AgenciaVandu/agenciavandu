<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** Constancia de Situación Fiscal guardada en el expediente del cliente (privada, solo panel) */
class ClienteConstancia extends Model
{
    protected $table = 'cliente_constancias';

    protected $fillable = ['nombre', 'ruta', 'mime', 'peso', 'emitida_el'];

    protected $casts = ['peso' => 'integer', 'emitida_el' => 'date'];

    protected static function booted(): void
    {
        static::deleted(fn (ClienteConstancia $c) => Storage::disk('local')->delete($c->ruta));
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function getPesoTextoAttribute(): string
    {
        return $this->peso >= 1048576 ? number_format($this->peso / 1048576, 1) . ' MB' : max(1, round($this->peso / 1024)) . ' KB';
    }

    public function getEsPdfAttribute(): bool
    {
        return str_contains((string) $this->mime, 'pdf');
    }
}
