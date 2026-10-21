<?php

namespace App\Support\Fitness;

use App\Models\Clase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Acomodo del calendario por horas: desde qué hora hasta qué hora se dibuja y dónde va cada clase.
 * Las clases que se enciman (dos salones o dos sucursales a la misma hora) se ponen lado a lado.
 */
class Calendario
{
    public const PX_HORA = 56;

    /** @return array{0:int,1:int} primera y última hora que se dibujan */
    public static function horas(Collection $clases): array
    {
        $ini = 6; $fin = 21;
        foreach ($clases as $c) {
            $ini = min($ini, (int) $c->inicio->format('G'));
            $fin = max($fin, (int) ceil(((int) $c->fin->format('G') * 60 + (int) $c->fin->format('i')) / 60));
        }
        return [max(0, $ini), min(24, $fin)];
    }

    /**
     * Posición de cada clase del día: arriba y alto en px, y en qué carril va de cuántos.
     * @return Collection<int, array{clase: Clase, top: int, alto: int, carril: int, carriles: int}>
     */
    public static function acomodar(Collection $delDia, int $horaIni): Collection
    {
        $min = fn (Carbon $t) => (int) $t->format('G') * 60 + (int) $t->format('i');
        $items = $delDia->sortBy(fn ($c) => [$c->inicia_at->timestamp, -$c->termina_at->timestamp])->values()->map(fn (Clase $c) => [
            'clase' => $c, 'ini' => $min($c->inicio), 'fin' => max($min($c->inicio) + 15, $c->fin->isSameDay($c->inicio) ? $min($c->fin) : 24 * 60),
        ])->all();

        // Grupos de clases que se enciman entre sí; dentro de cada grupo, carriles
        $salida = []; $grupo = []; $finGrupo = -1;
        $cerrar = function () use (&$grupo, &$salida) {
            $carriles = [];
            foreach ($grupo as &$it) {
                $l = 0;
                while (isset($carriles[$l]) && $carriles[$l] > $it['ini']) $l++;
                $carriles[$l] = $it['fin'];
                $it['carril'] = $l;
            }
            unset($it);
            foreach ($grupo as $it) $salida[] = $it + ['carriles' => count($carriles)];
            $grupo = [];
        };
        foreach ($items as $it) {
            if ($grupo && $it['ini'] >= $finGrupo) $cerrar();
            $grupo[] = $it;
            $finGrupo = max($finGrupo, $it['fin']);
        }
        if ($grupo) $cerrar();

        $px = self::PX_HORA / 60;
        return collect($salida)->map(fn ($it) => [
            'clase' => $it['clase'],
            'top' => (int) round(($it['ini'] - $horaIni * 60) * $px),
            'alto' => (int) max(22, round(($it['fin'] - $it['ini']) * $px) - 2),
            'carril' => $it['carril'],
            'carriles' => $it['carriles'],
        ]);
    }
}
