<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notificacion;
use App\Models\PushSuscripcion;
use App\Support\Push\Notificar;
use App\Support\Push\WebPush;
use Illuminate\Http\Request;

/** Notificaciones push: activarlas en cada dispositivo y elegir qué avisos llegan */
class NotificacionController extends Controller
{
    public function index(Request $request)
    {
        $u = $request->user();
        $eventos = self::eventosDe($u);
        return view('admin.notificaciones', [
            'eventos'      => $eventos,
            'dispositivos' => PushSuscripcion::where('user_id', $u->id)->latest('updated_at')->get(),
            'recientes'    => Notificacion::whereIn('evento', array_keys($eventos))->latest('id')->take(8)->get(),
            'clave'        => WebPush::claves()['publica'],
            'hora'         => config('vandu.push.resumen_hora'),
            'cron'         => $u->puede('configuracion') ? self::estadoCron() : null,
            'ultimoResumen'=> Notificacion::where('evento', 'resumen_diario')->latest('id')->first(),
        ]);
    }

    /** Avisos que esta persona puede recibir según su rol */
    public static function eventosDe($u): array
    {
        return array_filter(config('vandu.push.eventos'), fn ($e, $k) => Notificar::puedeRecibir($u, $k), ARRAY_FILTER_USE_BOTH);
    }

    /** ¿Está corriendo el cron del servidor? (el programador deja un "latido" cada minuto) */
    public static function estadoCron(): array
    {
        $ultimo = \Illuminate\Support\Facades\Cache::get('vandu.cron.latido');
        return [
            'activo'  => $ultimo && $ultimo > now()->subMinutes(3)->timestamp,
            'ultimo'  => $ultimo ? \Illuminate\Support\Carbon::createFromTimestamp($ultimo) : null,
            'comando' => 'cd ' . base_path() . ' && php artisan schedule:run >> /dev/null 2>&1',
        ];
    }

    /** Alta (o actualización) de este dispositivo. Conserva los avisos que ya había elegido. */
    public function suscribir(Request $request)
    {
        $datos = $request->validate([
            'endpoint'    => ['required', 'url', 'max:1000', 'starts_with:https://'],
            'keys.p256dh' => ['required', 'string', 'max:120'],
            'keys.auth'   => ['required', 'string', 'max:60'],
        ]);

        $s = PushSuscripcion::firstOrNew(['endpoint_hash' => hash('sha256', $datos['endpoint'])]);
        $nueva = ! $s->exists;
        $s->fill([
            'user_id'     => $request->user()->id,
            'endpoint'    => $datos['endpoint'],
            'p256dh'      => $datos['keys']['p256dh'],
            'auth'        => $datos['keys']['auth'],
            'dispositivo' => PushSuscripcion::nombreDispositivo($request->userAgent()),
        ]);
        $s->touch();
        $s->save();

        if ($nueva && $request->boolean('bienvenida')) {
            Notificar::ahora('prueba', 'Listo, las notificaciones están activas', $request->user()->puede('cotizaciones') ? 'Aquí te avisaremos cuando un cliente abra su cotización y más.' : 'Aquí te avisaremos cuando te asignen una tarea o te comenten.', route('admin.notificaciones'), 'bienvenida', [$s]);
        }

        return response()->json($this->fila($s));
    }

    public function preferencias(Request $request, PushSuscripcion $suscripcion)
    {
        abort_unless($suscripcion->user_id === $request->user()->id, 404);
        $validos = array_keys(self::eventosDe($request->user()));
        $eventos = array_values(array_intersect($request->input('eventos', []), $validos));
        $suscripcion->update(['eventos' => count($eventos) === count($validos) ? null : $eventos]);

        return response()->json($this->fila($suscripcion));
    }

    public function prueba(Request $request, PushSuscripcion $suscripcion)
    {
        abort_unless($suscripcion->user_id === $request->user()->id, 404);
        $n = Notificar::ahora('prueba', 'Notificación de prueba', 'Si ves esto en ' . $suscripcion->dispositivo . ', todo funciona.', route('admin.notificaciones'), 'prueba', [$suscripcion]);

        return response()->json(['ok' => $n > 0, 'mensaje' => $n ? 'Enviada. Debe llegarte en unos segundos.' : 'No se pudo entregar. Desactiva y vuelve a activar en este dispositivo.']);
    }

    public function destroy(Request $request, PushSuscripcion $suscripcion)
    {
        abort_unless($suscripcion->user_id === $request->user()->id, 404);
        $suscripcion->delete();

        return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('ok', 'Dispositivo quitado.');
    }

    private function fila(PushSuscripcion $s): array
    {
        $todos = array_keys(self::eventosDe($s->user ?? auth()->user()));
        return ['id' => $s->id, 'dispositivo' => $s->dispositivo, 'eventos' => $s->eventos ?? $todos];
    }
}
