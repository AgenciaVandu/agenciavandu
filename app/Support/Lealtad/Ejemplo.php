<?php

namespace App\Support\Lealtad;

use App\Models\Comensal;
use App\Models\LealtadCampana;
use App\Models\LealtadCanje;
use App\Models\LealtadInscripcion;
use App\Models\LealtadVisita;
use App\Support\Cuentas;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Programa de ejemplo para un restaurante nuevo (o para enseñarlo): una campaña vigente con historia,
 * una próxima (temporada navideña) y comensales con visitas y premios canjeados. Todo marcado como ejemplo.
 */
class Ejemplo
{
    public const COMENSALES = [
        // nombre, teléfono, visitas por semana (aprox.)
        ['Ana Lucía Peraza', '999 120 4455', 2.2], ['Jorge Ancona Ruiz', '999 431 2201', 1.6], ['Mariana Solís Cetina', '999 287 9930', 1.2],
        ['Ricardo Pech Canul', '999 655 1182', 1.0], ['Fernanda Gómez Rivero', '999 214 7764', .9], ['Luis Ángel Chan', '999 370 5528', .8],
        ['Paulina Villanueva', '999 902 3146', .7], ['Diego Ortiz Baeza', '999 118 6603', .6], ['Sofía Medina Loría', '999 563 0091', .55],
        ['Héctor Rosado Pool', '999 744 8820', .5], ['Valeria Ek Tun', '999 309 2275', .45], ['Andrés Novelo Ku', '999 681 4430', .4],
        ['Camila Herrera Dzib', '999 235 9917', .35], ['Emilio Castro May', '999 847 1066', .3], ['Regina Pacheco Uc', '999 156 3389', .3],
        ['Óscar Manzanilla', '999 492 7702', .25], ['Natalia Cámara Sosa', '999 378 6614', .2], ['Iván Couoh Moo', '999 663 0257', .2],
        ['Daniela Quintal Ek', '999 527 8843', .15], ['Gabriel Tec Chi', '999 801 2290', .15], ['Ximena Loría Peón', '999 940 6635', .1],
        ['Tomás Aké Canché', '999 172 4408', .1],
    ];

    public static function hay(): bool
    {
        return LealtadCampana::where('ejemplo', true)->exists();
    }

    public static function cargar(): void
    {
        if (self::hay()) return;
        $cuenta = Cuentas::actual();
        $tz = config('vandu.zona_horaria');
        $hoy = now($tz)->startOfDay();
        mt_srand(4 + $cuenta->id);

        DB::transaction(function () use ($cuenta, $hoy, $tz) {
            $otono = LealtadCampana::create(['nombre' => 'Ruta del sabor', 'descripcion' => 'Junta 10 visitas y gana una cena para dos. Premios a la 3 y a la 6.',
                'inicia_el' => $hoy->copy()->subDays(42)->toDateString(), 'termina_el' => $hoy->copy()->addDays(40)->toDateString(), 'meta_visitas' => 10, 'dias_canje' => 15, 'ejemplo' => true]);
            foreach ([[3, 'Postre de la casa', 'Marquesita o flan, el que prefieras'], [6, 'Bebida de autor', 'Cualquier bebida de la carta'], [10, 'Cena para dos', 'Dos platos fuertes y dos bebidas']] as [$v, $n, $d]) {
                $otono->premios()->create(['visitas' => $v, 'nombre' => $n, 'descripcion' => $d, 'cuenta_id' => $cuenta->id]);
            }
            $nov = Carbon::create($hoy->month > 11 ? $hoy->year + 1 : $hoy->year, 11, 1, 0, 0, 0, $tz);
            if ($nov->lte($hoy)) $nov = $hoy->copy()->addDays(10);
            $navidad = LealtadCampana::create(['nombre' => 'Temporada navideña', 'descripcion' => 'Del 1 de noviembre al 1 de enero: junta 8 visitas y gana una botella de vino.',
                'inicia_el' => $nov->toDateString(), 'termina_el' => $nov->copy()->addMonths(2)->toDateString(), 'meta_visitas' => 8, 'dias_canje' => 20, 'ejemplo' => true]);
            foreach ([[4, 'Café y pan dulce', 'Para dos personas'], [8, 'Botella de vino de la casa', 'Para llevar o para la mesa']] as [$v, $n, $d]) {
                $navidad->premios()->create(['visitas' => $v, 'nombre' => $n, 'descripcion' => $d, 'cuenta_id' => $cuenta->id]);
            }

            $premios = $otono->premios()->get();
            foreach (self::COMENSALES as $i => [$nombre, $tel, $porSemana]) {
                $email = Str::slug($nombre, '.') . '+' . $cuenta->id . '@ejemplo.nerby.mx';
                $p = Comensal::firstOrCreate(['email' => $email], ['nombre' => $nombre, 'telefono' => $tel, 'password' => Str::random(32), 'ejemplo' => true]);
                $desde = $hoy->copy()->subDays(mt_rand(3, 45));
                $ins = new LealtadInscripcion(['comensal_id' => $p->id, 'consentido_at' => $desde->copy()->setTime(mt_rand(13, 21), mt_rand(0, 59))->utc(), 'origen' => $i % 3 ? 'portal' : 'caja', 'ejemplo' => true]);
                $ins->cuenta_id = $cuenta->id;
                $ins->save();

                // Visitas desde que se unió, con su frecuencia (una por día como máximo)
                $visitas = 0;
                for ($d = $desde->copy(); $d->lte($hoy); $d->addDay()) {
                    $prob = $porSemana / 7 * ($d->isFriday() || $d->isSaturday() ? 1.6 : ($d->isMonday() ? .5 : 1));
                    if (! $d->isSameDay($desde) && mt_rand(0, 999) / 1000 > $prob) continue;
                    $v = new LealtadVisita(['comensal_id' => $p->id, 'fecha' => $d->toDateString(), 'monto' => mt_rand(25, 120) * 10, 'ejemplo' => true]);
                    $v->cuenta_id = $cuenta->id;
                    $v->created_at = $d->copy()->setTime(mt_rand(13, 22), mt_rand(0, 59))->utc();
                    $v->save();
                    $visitas++;
                }
                // Algunos ya canjearon lo que desbloquearon
                foreach ($premios as $pr) {
                    if ($visitas >= $pr->visitas && mt_rand(0, 9) < 7) {
                        $k = new LealtadCanje(['comensal_id' => $p->id, 'premio_id' => $pr->id, 'ejemplo' => true]);
                        $k->cuenta_id = $cuenta->id;
                        $k->save();
                    }
                }
            }
        });
    }

    public static function quitar(): void
    {
        DB::transaction(function () {
            $ids = LealtadInscripcion::where('ejemplo', true)->pluck('comensal_id');
            LealtadCanje::where('ejemplo', true)->delete();
            LealtadVisita::where('ejemplo', true)->delete();
            LealtadInscripcion::where('ejemplo', true)->delete();
            LealtadCampana::where('ejemplo', true)->get()->each->delete();
            // Comensales inventados que ya no están en ningún restaurante
            Comensal::whereIn('id', $ids)->where('ejemplo', true)->whereDoesntHave('inscripciones')->delete();
        });
    }
}
