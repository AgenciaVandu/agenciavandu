<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cada vez que alguien intenta entrar por un torniquete: quién, si pasó y por qué */
class Acceso extends Model
{
    use Concerns\DeCuenta;

    public const UPDATED_AT = null;

    protected $table = 'accesos';
    protected $fillable = ['cuenta_id', 'dispositivo_id', 'cliente_id', 'reservacion_id', 'permitido', 'motivo', 'metodo', 'mensaje'];
    protected $casts = ['permitido' => 'boolean'];

    public const MOTIVOS = [
        'adelante'    => 'Entró a su clase',
        'reingreso'   => 'Volvió a entrar',
        'sin_reserva' => 'Sin reserva a esta hora',
        'repetido'    => 'Ya había entrado',
        'vencido'     => 'QR vencido',
        'desconocido' => 'Código desconocido',
        'inactivo'    => 'Atleta dado de baja',
        'fija'        => 'Credencial impresa no permitida',
        'otro_metodo' => 'Sin QR',
        'boton'       => 'Abierto con el botón',
    ];

    public function dispositivo(): BelongsTo { return $this->belongsTo(AccesoDispositivo::class, 'dispositivo_id'); }
    public function cliente(): BelongsTo { return $this->belongsTo(Cliente::class); }
    public function reservacion(): BelongsTo { return $this->belongsTo(Reservacion::class); }

    public function getMotivoTextoAttribute(): string
    {
        return self::MOTIVOS[$this->motivo] ?? $this->motivo;
    }
}
