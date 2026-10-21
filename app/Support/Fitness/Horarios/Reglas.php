<?php

namespace App\Support\Fitness\Horarios;

use App\Models\Clase;
use Illuminate\Support\Carbon;

/**
 * Analizador de horarios con reglas claras sobre los datos del gimnasio (sin IA).
 * Cada regla dice qué encontró, con qué números y qué conviene hacer.
 */
class Reglas implements Analizador
{
    public const MAX = 6;

    public function nombre(): string
    {
        return 'análisis automático';
    }

    public function hallazgos(Datos $d): array
    {
        if (! $d->suficiente()) return [];
        $todos = array_merge($this->llenas($d), $this->huecos($d), $this->vacias($d), $this->faltas($d), $this->tendencia($d), $this->pico($d), $this->concentracion($d));
        usort($todos, fn (Hallazgo $a, Hallazgo $b) => $b->peso <=> $a->peso);
        return array_slice($todos, 0, self::MAX);
    }

    /** Clases que se llenan casi siempre: hay demanda para otra */
    private function llenas(Datos $d): array
    {
        return $d->ranuras->filter(fn ($r) => $r->semanas >= 3 && ($r->llenas / $r->veces >= .75 || $r->ocupacion >= 90))
            ->sortByDesc(fn ($r) => [$r->llenas / $r->veces, $r->ocupacion])->take(2)
            ->map(function ($r) use ($d) {
                $fin = Carbon::createFromFormat('H:i', $r->inicio)->addMinutes($r->duracion)->format('H:i');
                return new Hallazgo(
                    clave: "llena-{$r->tipo_id}-{$r->dia}-{$r->inicio}",
                    tono: 'oportunidad',
                    titulo: "<b>{$r->tipo}</b> del <b>" . Datos::cuando($r->dia, $r->inicio) . '</b> se llenó <b>' . $r->llenas . ' de ' . $r->veces . '</b> veces.',
                    detalle: "Ocupación promedio de {$r->ocupacion}% con cupo de {$r->cupo}. Hay gente para una clase más a esa hora.",
                    accion: ['texto' => "Programar otra a las $fin", 'url' => self::programar($r->dia, $fin, $r->tipo_id)],
                    confianza: Datos::confianza($r->semanas),
                    peso: 90 + $r->llenas,
                    celdas: [[$r->dia, $r->hora]],
                );
            })->values()->all();
    }

    /** Una hora que se llena casi toda la semana, pero un día entre semana no tiene clase */
    private function huecos(Datos $d): array
    {
        $out = [];
        foreach ($d->horas as $h) {
            $fuertes = collect(range(1, 7))->filter(fn ($dia) => ($d->celdas[$dia][$h]['clases'] ?? 0) >= 2 && ($d->celdas[$dia][$h]['ocupacion'] ?? 0) >= 80);
            if ($fuertes->count() < 3) continue;
            $sinClase = collect(range(1, 5))->first(fn ($dia) => ! isset($d->celdas[$dia][$h]));
            if (! $sinClase) continue;
            $tipo = $d->ranuras->where('hora', $h)->sortByDesc('ocupacion')->first();
            $out[] = new Hallazgo(
                clave: "hueco-$sinClase-$h",
                tono: 'oportunidad',
                titulo: sprintf('Las <b>%02d:00</b> se llenan %s; el <b>%s</b> no tienes clase a esa hora.', $h, self::lista($fuertes->map(fn ($x) => Datos::DIAS[$x])->all()), Datos::DIAS[$sinClase]),
                detalle: 'Ocupación a esa hora: ' . $fuertes->map(fn ($x) => Datos::DIAS_CORTOS[$x] . ' ' . $d->celdas[$x][$h]['ocupacion'] . '%')->join(', ') . '.',
                accion: ['texto' => 'Programar el ' . Datos::DIAS[$sinClase], 'url' => self::programar($sinClase, sprintf('%02d:00', $h), $tipo?->tipo_id)],
                confianza: $fuertes->count() >= 4 ? 'alta' : 'media',
                peso: 85,
                celdas: $fuertes->map(fn ($x) => [$x, $h])->push([$sinClase, $h])->values()->all(),
            );
            if (count($out) >= 1) break;
        }
        return $out;
    }

    /** Horarios que casi nadie reserva */
    private function vacias(Datos $d): array
    {
        return $d->ranuras->filter(fn ($r) => $r->veces >= 3 && $r->ocupacion <= 30)->sortBy('ocupacion')->take(2)
            ->map(function ($r) {
                $prom = round($r->ocupacion * $r->cupo / 100, 1);
                $sig = self::siguiente($r);
                return new Hallazgo(
                    clave: "vacia-{$r->tipo_id}-{$r->dia}-{$r->inicio}",
                    tono: 'alerta',
                    titulo: "<b>{$r->tipo}</b> del <b>" . Datos::cuando($r->dia, $r->inicio) . "</b> promedia <b>{$r->ocupacion}%</b> de ocupación.",
                    detalle: 'En sus últimas ' . $r->veces . ' clases reservaron ' . (fmod($prom, 1.0) == 0.0 ? (int) $prom : $prom) . " personas en promedio de {$r->cupo} lugares. Considera moverla a un horario con más demanda o quitarla.",
                    accion: $sig ? ['texto' => 'Revisar ese horario', 'url' => route('admin.clases.index', ['fecha' => $sig->inicio->toDateString(), 'clase' => $sig->id])] : null,
                    confianza: Datos::confianza($r->semanas),
                    peso: 70 - $r->ocupacion,
                    celdas: [[$r->dia, $r->hora]],
                );
            })->values()->all();
    }

    /** Un día de la semana con muchas más faltas que el resto */
    private function faltas(Datos $d): array
    {
        $porDia = $d->clases->groupBy('dia')->map(fn ($g) => ['f' => $g->sum('faltas'), 't' => $g->sum('faltas') + $g->sum('asistencias')]);
        $tf = $porDia->sum('f'); $tt = $porDia->sum('t');
        if ($tt < 30) return [];
        $peor = $porDia->filter(fn ($x) => $x['t'] >= 15)->sortByDesc(fn ($x) => $x['f'] / $x['t'])->keys()->first();
        if (! $peor) return [];
        $tasa = (int) round($porDia[$peor]['f'] / $porDia[$peor]['t'] * 100);
        $resto = ($tt - $porDia[$peor]['t']) ? (int) round(($tf - $porDia[$peor]['f']) / ($tt - $porDia[$peor]['t']) * 100) : 0;
        if ($tasa < 10 || $tasa < $resto * 1.5) return [];
        return [new Hallazgo(
            clave: "faltas-$peor",
            tono: 'alerta',
            titulo: 'Los <b>' . Datos::DIAS[$peor] . "</b> falta el <b>$tasa%</b> de quienes reservan; el resto de la semana, $resto%.",
            detalle: 'Son lugares que otros pudieron usar. Un aviso más cerca de la clase o una regla de cancelación más firme suele bajarlo.',
            accion: ['texto' => 'Revisar reglas del gimnasio', 'url' => route('admin.fitness-ajustes')],
            confianza: $porDia[$peor]['t'] >= 40 ? 'alta' : 'media',
            peso: 60,
            celdas: collect($d->celdas[$peor] ?? [])->keys()->map(fn ($h) => [$peor, $h])->values()->all(),
        )];
    }

    /** ¿Viene más o menos gente que antes? Mitad reciente contra la anterior */
    private function tendencia(Datos $d): array
    {
        $mitad = $d->hasta->copy()->subWeeks(intdiv($d->semanas, 2))->toDateString();
        $antes = $d->clases->filter(fn ($f) => $f->fecha < $mitad)->sum('asistencias');
        $ahora = $d->clases->filter(fn ($f) => $f->fecha >= $mitad)->sum('asistencias');
        if ($antes < 10 || $ahora < 1) return [];
        $cambio = (int) round(($ahora - $antes) / $antes * 100);
        if (abs($cambio) < 15) return [];
        $sem = intdiv($d->semanas, 2);
        return [new Hallazgo(
            clave: 'tendencia',
            tono: $cambio > 0 ? 'dato' : 'alerta',
            titulo: ($cambio > 0 ? 'Las asistencias <b>subieron ' : 'Las asistencias <b>bajaron ') . abs($cambio) . "%</b> en las últimas $sem semanas.",
            detalle: "$ahora asistencias contra $antes en las $sem semanas anteriores." . ($cambio < 0 ? ' Revisa quién dejó de venir en Seguimiento.' : ''),
            accion: $cambio < 0 ? ['texto' => 'Ver vencidos y por renovar', 'url' => route('admin.seguimiento')] : null,
            confianza: $d->semanas >= 8 ? 'alta' : 'media',
            peso: $cambio < 0 ? 75 : 45,
        )];
    }

    private function pico(Datos $d): array
    {
        $mejor = null;
        foreach ($d->celdas as $dia => $hs) foreach ($hs as $h => $c) {
            if ($c['clases'] >= 2 && (! $mejor || $c['ocupacion'] > $mejor[2]['ocupacion'])) $mejor = [$dia, $h, $c];
        }
        if (! $mejor) return [];
        [$dia, $h, $c] = $mejor;
        return [new Hallazgo(
            clave: 'pico',
            tono: 'dato',
            titulo: sprintf('Tu horario más lleno es el <b>%s a las %02d:00</b>, con <b>%d%%</b> de ocupación.', Datos::DIAS[$dia], $h, $c['ocupacion']),
            detalle: $c['reservas'] . ' lugares reservados de ' . $c['cupo'] . ' en ' . $c['clases'] . ' clases (' . implode(', ', $c['tipos']) . ').',
            confianza: Datos::confianza($c['clases']),
            peso: 40,
            celdas: [[$dia, $h]],
        )];
    }

    /** Pocos horarios cargan casi todo el gimnasio */
    private function concentracion(Datos $d): array
    {
        $total = $d->ranuras->sum('asistencias');
        if ($d->ranuras->count() < 6 || $total < 20) return [];
        $top = $d->ranuras->sortByDesc('asistencias')->take(3);
        $pct = (int) round($top->sum('asistencias') / $total * 100);
        if ($pct < 35) return [];
        return [new Hallazgo(
            clave: 'concentracion',
            tono: 'dato',
            titulo: "Tus 3 horarios más fuertes juntan el <b>$pct%</b> de todas las asistencias.",
            detalle: $top->map(fn ($r) => $r->tipo . ' ' . Datos::DIAS_CORTOS[$r->dia] . ' ' . $r->inicio)->join(', ') . '. Cuida que esas clases tengan a tus mejores entrenadores y cupo suficiente.',
            confianza: 'media',
            peso: 35,
            celdas: $top->map(fn ($r) => [$r->dia, $r->hora])->values()->all(),
        )];
    }

    /* ---------- utilidades ---------- */

    /** Enlace a la agenda con la ventana de programar abierta en ese día y hora */
    public static function programar(int $dia, string $hora, ?int $tipo): string
    {
        $tz = config('vandu.zona_horaria');
        $f = now($tz)->addDay()->startOfDay();
        while ($f->dayOfWeekIso !== $dia) $f->addDay();
        return route('admin.clases.index', array_filter(['fecha' => $f->toDateString(), 'programar' => $hora, 'tipo' => $tipo]));
    }

    private static function siguiente(object $r): ?Clase
    {
        return Clase::programadas()->where('clase_tipo_id', $r->tipo_id)->where('inicia_at', '>', now())->orderBy('inicia_at')->take(60)->get()
            ->first(fn ($c) => $c->inicio->dayOfWeekIso === $r->dia && $c->inicio->format('H:i') === $r->inicio);
    }

    private static function lista(array $xs): string
    {
        $xs = array_values($xs);
        return count($xs) <= 1 ? ($xs[0] ?? '') : implode(', ', array_slice($xs, 0, -1)) . ' y ' . end($xs);
    }
}
