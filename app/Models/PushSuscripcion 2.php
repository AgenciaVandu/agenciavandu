<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un dispositivo (celular, compu) donde se activaron las notificaciones push */
class PushSuscripcion extends Model
{
    use \App\Models\Concerns\DeCuenta;

    protected $table = 'push_suscripciones';

    protected $fillable = ['user_id', 'endpoint_hash', 'endpoint', 'p256dh', 'auth', 'dispositivo', 'eventos', 'ultimo_envio_at'];

    protected $casts = ['eventos' => 'array', 'ultimo_envio_at' => 'datetime'];

    protected $hidden = ['endpoint', 'p256dh', 'auth'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function quiere(string $evento): bool
    {
        return $this->eventos === null || in_array($evento, $this->eventos, true);
    }

    /** "iPhone · Safari", "Mac · Chrome"… a partir del navegador */
    public static function nombreDispositivo(?string $ua): string
    {
        $ua = (string) $ua;
        $equipo = match (true) {
            str_contains($ua, 'iPhone')    => 'iPhone',
            str_contains($ua, 'iPad')      => 'iPad',
            str_contains($ua, 'Android')   => 'Android',
            str_contains($ua, 'Macintosh') => 'Mac',
            str_contains($ua, 'Windows')   => 'Windows',
            str_contains($ua, 'Linux')     => 'Linux',
            default                        => 'Dispositivo',
        };
        $nav = match (true) {
            str_contains($ua, 'Edg/')                                    => 'Edge',
            str_contains($ua, 'Firefox') || str_contains($ua, 'FxiOS')   => 'Firefox',
            str_contains($ua, 'CriOS') || str_contains($ua, 'Chrome')    => 'Chrome',
            str_contains($ua, 'Safari')                                  => 'Safari',
            default                                                      => null,
        };
        return $nav ? "$equipo · $nav" : $equipo;
    }
}
