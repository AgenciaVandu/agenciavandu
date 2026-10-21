<?php

namespace App\Http\Controllers\Atleta;

use App\Http\Controllers\Controller;
use App\Models\Clase;
use App\Models\ClaseTipo;
use App\Models\PushSuscripcion;
use App\Models\Reservacion;
use App\Support\Cuentas;
use App\Support\Fitness\Ajustes;
use App\Support\Fitness\Fotos;
use App\Support\Fitness\Reservas;
use App\Support\Push\Notificar;
use App\Support\Push\WebPush;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/** El portal del atleta: sus clases, reservar y cancelar, su credencial QR, su historial y su perfil */
class PortalController extends Controller
{
    private function atleta()
    {
        return auth('atleta')->user();
    }

    public function inicio()
    {
        $a = $this->atleta();
        $proximas = $a->reservaciones()->getQuery()->reorder()->with(['clase.tipo', 'clase.coach', 'clase.sucursal'])->where('estado', 'reservada')
            ->whereHas('clase', fn ($q) => $q->where('termina_at', '>', now()))->get()->sortBy(fn ($r) => $r->clase->inicia_at)->values();
        return view('atleta.inicio', [
            'a' => $a, 'proximas' => $proximas, 'membresia' => $a->membresia_actual,
            'membresias' => $a->membresiasVigentes(), 'asistencias' => $a->reservaciones()->where('estado', 'asistio')->count(),
            'hoy' => $this->clasesDelDia(now(config('vandu.zona_horaria'))->toDateString())->take(4),
        ]);
    }

    /** Agenda para reservar: un día a la vez, con los próximos días arriba */
    public function clases(Request $request)
    {
        $tz = config('vandu.zona_horaria');
        $dias = (int) Ajustes::get('dias_adelante');
        $hoy = now($tz)->startOfDay();
        $fecha = $request->query('dia') && strtotime($request->query('dia')) ? Carbon::parse($request->query('dia'), $tz)->startOfDay() : $hoy->copy();
        $fecha = $fecha->max($hoy)->min($hoy->copy()->addDays($dias));
        $tipo = (int) $request->query('tipo') ?: null;
        \App\Support\Fitness\Agenda::completarTodas();

        $clases = $this->clasesDelDia($fecha->toDateString(), $tipo);
        $mias = $this->atleta()->reservaciones()->whereIn('clase_id', $clases->pluck('id'))->where('estado', '!=', 'cancelada')->pluck('id', 'clase_id');

        return view('atleta.clases', [
            'a' => $this->atleta(), 'clases' => $clases, 'mias' => $mias, 'fecha' => $fecha, 'tipo' => $tipo,
            'semana' => collect(range(0, min(13, $dias)))->map(fn ($i) => $hoy->copy()->addDays($i)),
            'tipos' => ClaseTipo::activos()->ordenados()->get(), 'puedeReservar' => (bool) Ajustes::get('atleta_reserva'),
            'membresia' => $this->atleta()->membresia_actual,
        ]);
    }

    private function clasesDelDia(string $dia, ?int $tipo = null)
    {
        return Clase::with(['tipo', 'coach', 'sucursal'])->withCount('ocupadas')->programadas()->delDia($dia)
            ->where('termina_at', '>', now())->when($tipo, fn ($q) => $q->where('clase_tipo_id', $tipo))->orderBy('inicia_at')->get();
    }

    public function clase(Clase $clase)
    {
        $clase->load(['tipo', 'coach', 'sucursal'])->loadCount('ocupadas');
        $mia = $this->atleta()->reservaciones()->where('clase_id', $clase->id)->where('estado', '!=', 'cancelada')->first();
        return view('atleta.clase', ['a' => $this->atleta(), 'c' => $clase, 'mia' => $mia, 'puedeReservar' => (bool) Ajustes::get('atleta_reserva')]);
    }

    public function reservar(Clase $clase)
    {
        abort_unless(Ajustes::get('atleta_reserva'), 403, 'Las reservaciones se hacen en recepción.');
        Reservas::reservar($clase, $this->atleta(), 'atleta');
        $a = $this->atleta();
        Notificar::evento('fitness_reserva', "{$a->nombre} reservó {$clase->nombre}", $clase->fecha_texto . ', ' . $clase->horario . ' · ' . ($clase->ocupados) . ' de ' . $clase->cupo . ' lugares', route('admin.clases.show', $clase), 'clase-' . $clase->id);
        return back()->with('ok', "¡Listo! Tienes lugar en {$clase->nombre}, {$clase->fecha_texto} a las {$clase->inicio->format('H:i')}.");
    }

    public function cancelar(Reservacion $reservacion)
    {
        abort_unless($reservacion->cliente_id === $this->atleta()->id, 404);
        abort_unless(Ajustes::get('atleta_reserva'), 403, 'Para cancelar, avisa en recepción.');
        $reservacion->load('clase');
        $devuelto = Reservas::cancelar($reservacion);
        $a = $this->atleta();
        Notificar::evento('fitness_reserva', "{$a->nombre} canceló {$reservacion->clase->nombre}", $reservacion->clase->fecha_texto . ', ' . $reservacion->clase->horario . ' · se liberó un lugar', route('admin.clases.show', $reservacion->clase), 'clase-' . $reservacion->clase_id);
        return back()->with('ok', $devuelto ? 'Cancelada. Te regresamos tu clase.' : 'Cancelada. Como fue con menos de ' . Ajustes::get('horas_cancelar') . ' horas, la clase se descuenta de tu paquete.');
    }

    public function credencial()
    {
        return view('atleta.credencial', ['a' => $this->atleta(), 'membresia' => $this->atleta()->membresia_actual]);
    }

    public function historial()
    {
        $a = $this->atleta();
        return view('atleta.historial', [
            'a' => $a,
            'reservaciones' => $a->reservaciones()->with('clase.tipo')->whereIn('estado', ['asistio', 'falta', 'cancelada'])->paginate(30),
            'membresias' => $a->membresias()->where('estado', '!=', 'cancelada')->get(),
            'asistencias' => $a->reservaciones()->where('estado', 'asistio')->count(),
        ]);
    }

    public function perfil()
    {
        return view('atleta.perfil', ['a' => $this->atleta(), 'clavePush' => rescue(fn () => WebPush::claves()['publica'], null, false)]);
    }

    public function guardarPerfil(Request $request)
    {
        $a = $this->atleta();
        $d = $request->validate([
            'nombre'   => 'required|string|max:120',
            'telefono' => 'nullable|string|max:30',
            'foto'     => 'nullable|image|max:12288',
            'actual'   => 'nullable|required_with:password|string',
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ], ['actual.required_with' => 'Escribe tu contraseña actual para cambiarla.', 'password.confirmed' => 'Las contraseñas nuevas no coinciden.', 'password.min' => 'Usa al menos 8 caracteres.']);
        if (! empty($d['password'])) {
            if (! Hash::check($d['actual'] ?? '', $a->password)) return back()->withErrors(['actual' => 'Tu contraseña actual no es correcta.']);
            $a->forceFill(['password' => $d['password']]);
        }
        $a->forceFill(['nombre' => $d['nombre'], 'telefono' => $d['telefono'] ?? $a->telefono])->save();
        if ($request->hasFile('foto')) {
            try { Fotos::guardar($a, $request->file('foto')); } catch (\RuntimeException $e) { return back()->withErrors(['foto' => $e->getMessage()]); }
        }
        return back()->with('ok', 'Guardado.');
    }

    public function foto()
    {
        return Fotos::responder($this->atleta());
    }

    /** Activar avisos en este celular */
    public function suscribir(Request $request)
    {
        $d = $request->validate([
            'endpoint'    => ['required', 'url', 'max:1000', 'starts_with:https://'],
            'keys.p256dh' => ['required', 'string', 'max:120'],
            'keys.auth'   => ['required', 'string', 'max:60'],
        ]);
        $a = $this->atleta();
        $s = PushSuscripcion::sinCuenta()->firstOrNew(['endpoint_hash' => hash('sha256', $d['endpoint'])]);
        $nueva = ! $s->exists;
        $s->cuenta_id = $a->cuenta_id;
        $s->fill(['user_id' => null, 'cliente_id' => $a->id, 'endpoint' => $d['endpoint'], 'p256dh' => $d['keys']['p256dh'], 'auth' => $d['keys']['auth'],
                  'dispositivo' => PushSuscripcion::nombreDispositivo($request->userAgent())]);
        $s->touch();
        $s->save();
        if ($nueva) \App\Support\Fitness\Avisos::atleta($a, 'Listo, los avisos están activos', 'Te avisaremos antes de tu clase y cuando tu paquete esté por terminarse.', 'bienvenida');
        return response()->json(['ok' => true]);
    }

    public function desuscribir(Request $request)
    {
        $endpoint = (string) $request->input('endpoint');
        PushSuscripcion::where('cliente_id', $this->atleta()->id)->where('endpoint_hash', hash('sha256', $endpoint))->delete();
        return response()->json(['ok' => true]);
    }
}
