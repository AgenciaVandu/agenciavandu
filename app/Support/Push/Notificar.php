<?php

namespace App\Support\Push;

use App\Models\Notificacion;
use App\Models\PushSuscripcion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Manda una notificación push a todos los dispositivos que la quieran.
 * En una visita web se envía después de responder, para que el cliente no espere nada.
 */
class Notificar
{
    public static function evento(string $evento, string $titulo, ?string $cuerpo = null, ?string $url = null, ?string $etiqueta = null): void
    {
        $enviar = fn () => static::ahora($evento, $titulo, $cuerpo, $url, $etiqueta);

        if (app()->runningInConsole() || app()->runningUnitTests()) {
            $enviar();
        } else {
            app()->terminating($enviar);
        }
    }

    /** @return int cuántos dispositivos lo recibieron */
    public static function ahora(string $evento, string $titulo, ?string $cuerpo = null, ?string $url = null, ?string $etiqueta = null, ?iterable $destinos = null): int
    {
        try {
            if (! Schema::hasTable('push_suscripciones')) return 0;

            $destinos ??= PushSuscripcion::all()->filter->quiere($evento);
            $mensaje = array_filter([
                'titulo'   => $titulo,
                'cuerpo'   => $cuerpo,
                'url'      => $url ?: route('admin.resumen'),
                'etiqueta' => $etiqueta ?: $evento,
                'evento'   => $evento,
            ]);

            $entregadas = 0;
            foreach ($destinos as $s) {
                try {
                    $codigo = WebPush::enviar($s, $mensaje, $evento === 'resumen_diario' ? 6 * 3600 : 86400, $evento === 'mensaje_sitio' ? 'high' : 'normal');
                    if ($codigo >= 200 && $codigo < 300) {
                        $entregadas++;
                        $s->forceFill(['ultimo_envio_at' => now()])->save();
                    } elseif (in_array($codigo, [404, 410], true)) {
                        $s->delete(); // el navegador ya la dio de baja
                    } else {
                        Log::warning("Push: el servicio respondió $codigo", ['dispositivo' => $s->dispositivo]);
                    }
                } catch (Throwable $e) {
                    Log::warning('Push: ' . $e->getMessage(), ['dispositivo' => $s->dispositivo]);
                }
            }

            if ($evento !== 'prueba') Notificacion::create(['evento' => $evento, 'titulo' => $titulo, 'cuerpo' => $cuerpo, 'url' => $mensaje['url'], 'entregadas' => $entregadas]);

            return $entregadas;
        } catch (Throwable $e) {
            Log::warning('Push: ' . $e->getMessage());
            return 0;
        }
    }

    /** Hay que avisar de esta visita: primera vez, o la anterior fue hace rato */
    public static function visitaNueva($ultimaVista): bool
    {
        return ! $ultimaVista || $ultimaVista->lt(now()->subHours(config('vandu.push.repetir_vista_horas', 3)));
    }
}
