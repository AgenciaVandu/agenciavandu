<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Lugar de un atleta en una clase: reservada → asistió / falta, o cancelada (con o sin devolver el crédito) */
class Reservacion extends Model
{
    use \App\Models\Concerns\DeCuenta;

    protected $table = 'reservaciones';

    protected $fillable = ['clase_id', 'cliente_id', 'membresia_id', 'estado', 'origen', 'credito_usado', 'credito_devuelto', 'checkin_at', 'checkin_por', 'cancelada_at', 'aviso_at', 'reservada_por'];

    protected $casts = ['checkin_at' => 'datetime', 'cancelada_at' => 'datetime', 'aviso_at' => 'datetime', 'credito_usado' => 'boolean', 'credito_devuelto' => 'boolean'];

    public const ESTADOS = [
        'reservada' => ['texto' => 'Reservada', 'clase' => 'enviada'],
        'asistio'   => ['texto' => 'Asistió',   'clase' => 'aceptada'],
        'falta'     => ['texto' => 'Faltó',     'clase' => 'rechazada'],
        'cancelada' => ['texto' => 'Cancelada', 'clase' => 'cancelada'],
    ];

    public function clase(): BelongsTo { return $this->belongsTo(Clase::class); }
    public function cliente(): BelongsTo { return $this->belongsTo(Cliente::class); }
    public function membresia(): BelongsTo { return $this->belongsTo(Membresia::class); }
    public function registro(): BelongsTo { return $this->belongsTo(User::class, 'checkin_por'); }

    public function getEstadoTextoAttribute(): string { return self::ESTADOS[$this->estado]['texto'] ?? $this->estado; }
    public function getEstadoClaseAttribute(): string { return self::ESTADOS[$this->estado]['clase'] ?? 'borrador'; }
    public function getActivaAttribute(): bool { return $this->estado !== 'cancelada'; }
}
