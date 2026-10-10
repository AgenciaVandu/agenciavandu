<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Una publicación del calendario de contenido */
class RedesPost extends Model
{
    public const ESTADOS = [
        'borrador'  => ['texto' => 'Borrador',        'color' => '#8A90A0'],
        'revision'  => ['texto' => 'En revisión',     'color' => '#2F6FEB'],
        'cambios'   => ['texto' => 'Cambios pedidos', 'color' => '#D97706'],
        'aprobado'  => ['texto' => 'Aprobado',        'color' => '#047A4B'],
        'publicado' => ['texto' => 'Publicado',       'color' => '#13161D'],
    ];

    protected $table = 'redes_posts';

    protected $fillable = ['cliente_id', 'titulo', 'redes', 'formato', 'fecha', 'texto', 'estado', 'aprobado_at', 'aprobado_por'];

    protected $casts = ['redes' => 'array', 'fecha' => 'datetime', 'aprobado_at' => 'datetime'];

    public function cliente(): BelongsTo { return $this->belongsTo(Cliente::class); }

    public function medios(): HasMany { return $this->hasMany(RedesMedio::class, 'post_id')->orderBy('orden')->orderBy('id'); }

    public function comentarios(): HasMany { return $this->hasMany(RedesComentario::class, 'post_id')->orderBy('id'); }

    public function getFechaLocalAttribute()
    {
        return $this->fecha?->copy()->setTimezone(config('vandu.zona_horaria'));
    }

    public function getEstadoTextoAttribute(): string
    {
        return self::ESTADOS[$this->estado]['texto'] ?? $this->estado;
    }

    /** El cliente todavía puede aprobar o pedir cambios */
    public function getRespondibleAttribute(): bool
    {
        return in_array($this->estado, ['revision', 'cambios'], true);
    }
}
