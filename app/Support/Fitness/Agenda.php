<?php

namespace App\Support\Fitness;

use App\Models\Clase;
use App\Models\ClaseSerie;
use Illuminate\Support\Carbon;

/**
 * Clases que se repiten: la serie genera sus clases para las próximas semanas.
 * Se llama al crear la serie y todos los días desde el programador (para ir completando el calendario).
 */
class Agenda
{
    public const SEMANAS = 8;

    /** @return int clases creadas */
    public static function generar(ClaseSerie $s, int $semanas = self::SEMANAS): int
    {
        if (! $s->activa) return 0;
        $tz = config('vandu.zona_horaria');
        $hoy = now($tz)->startOfDay();
        $desde = $s->desde->copy()->setTimezone($tz)->startOfDay()->max($hoy);
        $hasta = $hoy->copy()->addWeeks($semanas);
        if ($s->hasta) $hasta = $hasta->min(Carbon::parse($s->hasta->toDateString(), $tz)->endOfDay());

        [$h, $m] = array_map('intval', explode(':', $s->hora) + [0, 0]);
        $dias = array_map('intval', $s->dias ?? []);
        $existentes = $s->clases()->pluck('inicia_at')->map(fn ($d) => Carbon::parse($d)->utc()->format('Y-m-d H:i'))->flip();

        $n = 0;
        for ($d = $desde->copy(); $d->lte($hasta); $d->addDay()) {
            if (! in_array($d->dayOfWeekIso, $dias, true)) continue;
            $inicio = Carbon::create($d->year, $d->month, $d->day, $h, $m, 0, $tz);
            if ($inicio->isPast()) continue;
            if (isset($existentes[$inicio->copy()->utc()->format('Y-m-d H:i')])) continue;
            Clase::create([
                'clase_tipo_id' => $s->clase_tipo_id, 'serie_id' => $s->id, 'sucursal_id' => $s->sucursal_id, 'coach_id' => $s->coach_id,
                'inicia_at' => $inicio->copy()->utc(), 'termina_at' => $inicio->copy()->addMinutes($s->duracion)->utc(), 'cupo' => $s->cupo,
                'entrenamiento' => null,
            ]);
            $n++;
        }
        return $n;
    }

    /**
     * Termina una serie: ya no genera más y se quitan sus clases futuras que no tengan a nadie inscrito.
     * @return int clases que se quedan porque ya tienen reservaciones
     */
    public static function terminar(ClaseSerie $s): int
    {
        $s->update(['activa' => false, 'hasta' => now(config('vandu.zona_horaria'))->toDateString()]);
        $futuras = $s->clases()->where('inicia_at', '>', now())->withCount('ocupadas')->get();
        $futuras->where('ocupadas_count', 0)->each->delete();
        return $futuras->where('ocupadas_count', '>', 0)->count();
    }

    /** Completa el calendario de todas las series activas (una vez al día por negocio basta) */
    public static function completarTodas(): void
    {
        $clave = \App\Support\Cuentas::clave('fitness.agenda.' . now(config('vandu.zona_horaria'))->toDateString());
        if (\Illuminate\Support\Facades\Cache::has($clave)) return;
        ClaseSerie::where('activa', true)->get()->each(fn ($s) => self::generar($s));
        \Illuminate\Support\Facades\Cache::put($clave, true, now()->addDay());
    }

    /** Aplica a las clases futuras de la serie un cambio de entrenador, sucursal o cupo */
    public static function actualizarFuturas(ClaseSerie $s): void
    {
        $s->clases()->where('inicia_at', '>', now())->where('estado', 'programada')->get()->each(function (Clase $c) use ($s) {
            $c->update(['coach_id' => $s->coach_id, 'sucursal_id' => $s->sucursal_id, 'cupo' => max($s->cupo, $c->ocupados)]);
        });
    }

    /**
     * Cambió el día, la hora o la duración del horario fijo: sus próximas clases se acomodan al nuevo horario.
     * Las que no tienen a nadie se quitan y se vuelven a generar; las que ya tienen inscritos se mueven
     * (si su día sigue en el horario) y se les avisa. Devuelve cuántas movió y cuántas dejó como estaban.
     * @return array{movidas:int, quedan:int}
     */
    public static function reprogramar(ClaseSerie $s): array
    {
        $tz = config('vandu.zona_horaria');
        [$h, $m] = array_map('intval', explode(':', $s->hora) + [0, 0]);
        $dias = array_map('intval', $s->dias ?? []);
        $movidas = 0; $quedan = 0;
        $futuras = $s->clases()->where('inicia_at', '>', now())->where('estado', 'programada')->withCount('ocupadas')->get();
        foreach ($futuras as $c) {
            $dia = $c->inicio;
            $nuevo = Carbon::create($dia->year, $dia->month, $dia->day, $h, $m, 0, $tz);
            $igual = $nuevo->equalTo($c->inicio) && $c->termina_at->diffInMinutes($c->inicia_at, true) === (int) $s->duracion;
            if ($igual && in_array($dia->dayOfWeekIso, $dias, true)) continue;
            if ($c->ocupadas_count === 0) { $c->delete(); continue; }
            if (! in_array($dia->dayOfWeekIso, $dias, true) || $nuevo->isPast()) { $c->update(['serie_id' => null]); $quedan++; continue; }
            $antes = $c->horario;
            $c->update(['inicia_at' => $nuevo->copy()->utc(), 'termina_at' => $nuevo->copy()->addMinutes($s->duracion)->utc()]);
            $movidas++;
            if (! $nuevo->equalTo($dia)) {
                foreach ($c->ocupadas()->with('cliente')->get() as $r) {
                    Avisos::atleta($r->cliente, 'Cambió el horario de tu clase de ' . $c->nombre,
                        $c->fecha_texto . ': ahora es ' . $c->fresh()->horario . ' (antes ' . $antes . ').', 'clase-' . $c->id);
                }
            }
        }
        self::generar($s);
        return ['movidas' => $movidas, 'quedan' => $quedan];
    }
}
