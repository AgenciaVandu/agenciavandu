<?php

namespace App\Support\Fitness\Horarios;

use App\Models\Clase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Lo que pasó en las clases de las últimas semanas, ya agrupado para analizar horarios:
 * por celda (día × hora) y por "ranura" (la misma clase, el mismo día de la semana, a la misma hora).
 * Solo cuenta clases que ya terminaron y no se cancelaron.
 */
final class Datos
{
    public const DIAS = [1 => 'lunes', 2 => 'martes', 3 => 'miércoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sábado', 7 => 'domingo'];
    public const DIAS_CORTOS = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];

    /**
     * @param Collection $clases filas planas por clase
     * @param array $celdas [dia][hora] => ['ocupacion', 'clases', 'asistencias', 'reservas', 'cupo']
     * @param Collection $ranuras por tipo + día + hora de inicio
     */
    public function __construct(
        public int $semanas,
        public Carbon $desde,
        public Carbon $hasta,
        public Collection $clases,
        public array $celdas,
        public Collection $ranuras,
        public array $horas,
    ) {}

    public static function cargar(int $semanas = 8, ?int $sucursal = null): self
    {
        $tz = config('vandu.zona_horaria');
        $hasta = now($tz);
        $desde = $hasta->copy()->subWeeks($semanas)->startOfDay();

        $filas = Clase::with('tipo')->programadas()
            ->where('inicia_at', '>=', $desde->copy()->utc())->where('termina_at', '<', now())
            ->when($sucursal, fn ($q) => $q->where('sucursal_id', $sucursal))
            ->withCount([
                'ocupadas',
                'reservaciones as asistencias_count' => fn ($q) => $q->where('estado', 'asistio'),
                'reservaciones as faltas_count' => fn ($q) => $q->where('estado', 'falta'),
                'reservaciones as canceladas_count' => fn ($q) => $q->where('estado', 'cancelada'),
            ])
            ->orderBy('inicia_at')->get()
            ->map(fn (Clase $c) => (object) [
                'id' => $c->id, 'tipo_id' => $c->clase_tipo_id, 'tipo' => $c->nombre, 'color' => $c->color, 'serie_id' => $c->serie_id,
                'sucursal_id' => $c->sucursal_id, 'fecha' => $c->inicio->toDateString(), 'semana' => $c->inicio->format('o-W'),
                'dia' => $c->inicio->dayOfWeekIso, 'hora' => (int) $c->inicio->format('G'), 'inicio' => $c->inicio->format('H:i'),
                'duracion' => (int) $c->inicia_at->diffInMinutes($c->termina_at), 'cupo' => (int) $c->cupo,
                'reservas' => (int) $c->ocupadas_count, 'asistencias' => (int) $c->asistencias_count,
                'faltas' => (int) $c->faltas_count, 'canceladas' => (int) $c->canceladas_count,
                'ocupacion' => $c->cupo ? min(100, (int) round($c->ocupadas_count / $c->cupo * 100)) : 0,
            ]);

        $celdas = [];
        foreach ($filas->groupBy(fn ($f) => $f->dia . '|' . $f->hora) as $k => $g) {
            [$d, $h] = array_map('intval', explode('|', $k));
            $cupo = $g->sum('cupo');
            $celdas[$d][$h] = [
                'ocupacion' => $cupo ? (int) round($g->sum('reservas') / $cupo * 100) : 0,
                'clases' => $g->count(), 'asistencias' => $g->sum('asistencias'), 'reservas' => $g->sum('reservas'), 'cupo' => $cupo,
                'tipos' => $g->pluck('tipo')->unique()->values()->all(),
            ];
        }

        $ranuras = $filas->groupBy(fn ($f) => $f->tipo_id . '|' . $f->dia . '|' . $f->inicio)->map(fn (Collection $g) => (object) [
            'tipo_id' => $g->first()->tipo_id, 'tipo' => $g->first()->tipo, 'color' => $g->first()->color, 'dia' => $g->first()->dia,
            'hora' => $g->first()->hora, 'inicio' => $g->first()->inicio, 'duracion' => (int) round($g->avg('duracion')),
            'cupo' => (int) round($g->avg('cupo')), 'veces' => $g->count(), 'semanas' => $g->pluck('semana')->unique()->count(),
            'llenas' => $g->filter(fn ($f) => $f->cupo && $f->reservas >= $f->cupo)->count(),
            'ocupacion' => $g->sum('cupo') ? (int) round($g->sum('reservas') / $g->sum('cupo') * 100) : 0,
            'asistencias' => $g->sum('asistencias'), 'faltas' => $g->sum('faltas'), 'serie_id' => $g->last()->serie_id,
            'sucursal_id' => $g->last()->sucursal_id,
        ])->values();

        $horas = $filas->isEmpty() ? range(6, 21) : range(min(6, $filas->min('hora')), max(20, $filas->max('hora')));
        return new self($semanas, $desde, $hasta, $filas, $celdas, $ranuras, $horas);
    }

    public function suficiente(): bool
    {
        return $this->clases->count() >= 8 && $this->clases->pluck('semana')->unique()->count() >= 2;
    }

    public static function cuando(int $dia, string|int $hora): string
    {
        $h = is_int($hora) ? sprintf('%02d:00', $hora) : $hora;
        return self::DIAS[$dia] . ' ' . $h;
    }

    /** Qué tan respaldado está algo según cuántas semanas distintas se observó */
    public static function confianza(int $semanas): string
    {
        return $semanas >= 6 ? 'alta' : ($semanas >= 3 ? 'media' : 'baja');
    }
}
