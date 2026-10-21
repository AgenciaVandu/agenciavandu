<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Paquete de clases asignado a un atleta: créditos (vacío = ilimitado), vigencia y su precio,
 * que es lo que cuenta en Finanzas (cobrado si tiene fecha de pago, si no, pendiente de cobro).
 */
class Membresia extends Model
{
    use \App\Models\Concerns\DeCuenta;

    protected $fillable = ['cliente_id', 'producto_id', 'nombre', 'precio', 'creditos', 'usados', 'es_prueba', 'max_por_dia', 'tipos_clase',
                           'inicia_el', 'vence_el', 'estado', 'pagado_el', 'metodo_pago', 'notas', 'asignada_por', 'ejemplo'];

    protected $casts = ['inicia_el' => 'date', 'vence_el' => 'date', 'pagado_el' => 'date', 'precio' => 'float', 'creditos' => 'integer', 'usados' => 'integer',
                        'es_prueba' => 'boolean', 'tipos_clase' => 'array', 'aviso_credito_at' => 'datetime', 'aviso_vence_at' => 'datetime', 'ejemplo' => 'boolean'];

    public const METODOS = ['efectivo' => 'Efectivo', 'transferencia' => 'Transferencia', 'tarjeta' => 'Tarjeta'];

    public function cliente(): BelongsTo { return $this->belongsTo(Cliente::class); }
    public function paquete(): BelongsTo { return $this->belongsTo(Paquete::class, 'producto_id'); }
    public function asignador(): BelongsTo { return $this->belongsTo(User::class, 'asignada_por'); }
    public function reservaciones(): HasMany { return $this->hasMany(Reservacion::class); }

    public static function hoy(): string
    {
        return now(config('vandu.zona_horaria'))->toDateString();
    }

    /** Activas y dentro de su vigencia hoy (o en $fecha) */
    public function scopeVigentes(Builder $q, ?string $fecha = null): Builder
    {
        $f = $fecha ?? static::hoy();
        return $q->where('estado', 'activa')->whereDate('inicia_el', '<=', $f)->whereDate('vence_el', '>=', $f);
    }

    public function getIlimitadaAttribute(): bool { return $this->creditos === null; }

    public function getRestantesAttribute(): ?int
    {
        return $this->ilimitada ? null : max(0, $this->creditos - $this->usados);
    }

    public function getRestantesTextoAttribute(): string
    {
        if ($this->ilimitada) return 'Ilimitada';
        return $this->restantes . ' de ' . $this->creditos . ' ' . ($this->creditos === 1 ? 'clase' : 'clases');
    }

    public function vigenteEl(string $fecha): bool
    {
        return $this->estado === 'activa' && $this->inicia_el->toDateString() <= $fecha && $this->vence_el->toDateString() >= $fecha;
    }

    public function getVigenteAttribute(): bool { return $this->vigenteEl(static::hoy()); }

    /** Le quedan créditos (o es ilimitada) */
    public function getConCreditosAttribute(): bool { return $this->ilimitada || $this->restantes > 0; }

    public function permiteTipo(?int $tipoId): bool
    {
        return empty($this->tipos_clase) || in_array($tipoId, array_map('intval', $this->tipos_clase), true);
    }

    public function getDiasRestantesAttribute(): int
    {
        return (int) Carbon::parse(static::hoy())->diffInDays($this->vence_el, false);
    }

    public function getPagadaAttribute(): bool { return $this->pagado_el !== null; }

    /** Estado para mostrar: [texto, clase css] */
    public function getEstadoVistaAttribute(): array
    {
        $hoy = static::hoy();
        return match (true) {
            $this->estado === 'cancelada'              => ['Cancelada', 'cancelada'],
            $this->inicia_el->toDateString() > $hoy    => ['Inicia ' . $this->inicia_el->locale('es')->isoFormat('D MMM'), 'enviada'],
            $this->vence_el->toDateString() < $hoy     => ['Vencida', 'borrador'],
            ! $this->con_creditos                      => ['Sin créditos', 'rechazada'],
            default                                    => ['Vigente', 'aceptada'],
        };
    }
}
