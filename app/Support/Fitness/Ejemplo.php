<?php

namespace App\Support\Fitness;

use App\Models\Clase;
use App\Models\ClaseSerie;
use App\Models\ClaseTipo;
use App\Models\Cliente;
use App\Models\Ejercicio;
use App\Models\Membresia;
use App\Models\Paquete;
use Illuminate\Support\Facades\DB;

/**
 * Gimnasio de ejemplo para que un negocio nuevo vea todo funcionando el primer día: tipos de clase HYROX,
 * biblioteca de ejercicios, paquetes con precio, horario fijo de la semana y 6 atletas con paquete y reservaciones.
 * Todo queda marcado como ejemplo y se quita con un botón (lo que el gimnasio capture después se queda).
 */
class Ejemplo
{
    // nombre, descripción, duración, cupo, color
    public const TIPOS = [
        ['HYROX Engine', 'Resistencia y carrera con estaciones HYROX. Para todos los niveles.', 60, 16, '#E8590C'],
        ['HYROX Simulation', 'Simulacro de competencia: 8 km de carrera y las 8 estaciones.', 90, 12, '#B42318'],
        ['Axion Fuerza', 'Fuerza con barra y mancuernas, técnica y progresión.', 60, 12, '#2557D6'],
        ['Strength & Conditioning', 'Fuerza más acondicionamiento metabólico.', 60, 14, '#7048E8'],
        ['Mobility', 'Movilidad y recuperación activa.', 45, 20, '#00A86B'],
    ];

    // nombre, categoría, unidad
    public const EJERCICIOS = [
        ['Running', 'Carrera', 'm'], ['SkiErg', 'Estación HYROX', 'm'], ['Sled Push', 'Estación HYROX', 'm'], ['Sled Pull', 'Estación HYROX', 'm'],
        ['Burpee Broad Jumps', 'Estación HYROX', 'm'], ['Rowing', 'Estación HYROX', 'm'], ['Farmer Carry', 'Estación HYROX', 'm'],
        ['Sandbag Lunges', 'Estación HYROX', 'm'], ['Wall Balls', 'Estación HYROX', 'reps'], ['Back Squat', 'Fuerza', 'kg'], ['Deadlift', 'Fuerza', 'kg'],
        ['Kettlebell Swing', 'Acondicionamiento', 'reps'], ['Assault Bike', 'Acondicionamiento', 'cal'], ['Plancha', 'Core', 'seg'],
    ];

    // nombre, créditos (null = ilimitado), vigencia en días, precio, prueba
    public const PAQUETES = [
        ['Clase de prueba', 1, 7, 150, true],
        ['Paquete 4 clases', 4, 30, 600, false],
        ['Paquete 8 clases', 8, 30, 1050, false],
        ['Paquete 12 clases', 12, 30, 1400, false],
        ['Membresía ilimitada', null, 30, 1650, false],
    ];

    // tipo (índice), días, hora, cupo
    public const HORARIO = [
        [0, [1, 3, 5], '06:00', 16], [0, [1, 3, 5], '19:00', 16], [2, [2, 4], '07:00', 12], [3, [2, 4], '19:00', 14],
        [4, [6], '09:00', 20], [1, [6], '10:00', 12],
    ];

    // nombre, teléfono, correo, paquete (índice), clases usadas, días desde que lo compró, pagado
    public const ATLETAS = [
        ['Sofía Medina Cervera', '999 341 2290', 'sofia@ejemplo.mx', 4, 0, 10, true],
        ['Diego Herrera Puc', '999 520 1873', 'diego@ejemplo.mx', 2, 6, 20, true],
        ['Valeria Canul Ek', '999 218 4410', 'valeria@ejemplo.mx', 2, 7, 25, true],
        ['Andrés Pool Chi', '999 677 0912', 'andres@ejemplo.mx', 3, 3, 8, false],
        ['Mariana Uc Dzib', '999 145 3366', 'mariana@ejemplo.mx', 1, 1, 4, true],
        ['Rodrigo Kú Balam', '999 803 5521', 'rodrigo@ejemplo.mx', 0, 0, 1, true],
    ];

    public static function hay(): bool
    {
        return ClaseTipo::where('ejemplo', true)->exists();
    }

    public static function cargar(): void
    {
        if (self::hay()) return;
        DB::transaction(function () {
            $tz = config('vandu.zona_horaria');
            $tipos = collect(self::TIPOS)->map(fn ($t, $i) => ClaseTipo::create(['nombre' => $t[0], 'descripcion' => $t[1], 'duracion' => $t[2], 'cupo' => $t[3], 'color' => $t[4], 'orden' => $i, 'ejemplo' => true]));
            foreach (self::EJERCICIOS as [$n, $cat, $u]) Ejercicio::create(['nombre' => $n, 'categoria' => $cat, 'unidad' => $u, 'ejemplo' => true]);
            $paquetes = collect(self::PAQUETES)->map(fn ($p) => Paquete::create(['nombre' => $p[0], 'creditos' => $p[1], 'vigencia_dias' => $p[2], 'precio' => $p[3], 'es_prueba' => $p[4], 'ejemplo' => true]));

            $sucursal = \App\Models\Sucursal::first()?->id;
            $coach = \App\Models\User::orderBy('id')->value('id');
            foreach (self::HORARIO as [$t, $dias, $hora, $cupo]) {
                $s = ClaseSerie::create(['clase_tipo_id' => $tipos[$t]->id, 'sucursal_id' => $sucursal, 'coach_id' => $coach, 'dias' => $dias, 'hora' => $hora,
                    'duracion' => $tipos[$t]->duracion, 'cupo' => $cupo, 'desde' => now($tz)->startOfWeek()->toDateString(), 'activa' => true]);
                Agenda::generar($s);
                $s->clases()->update(['ejemplo' => true]);
            }
            // Un entrenamiento de muestra en las clases de HYROX Engine
            $wod = [['nombre' => 'Calentamiento', 'formato' => '10 min', 'ejercicios' => [['ejercicio_id' => null, 'nombre' => 'Running', 'cantidad' => '800 m', 'nota' => 'ritmo suave']]],
                    ['nombre' => 'Engine', 'formato' => '4 rondas', 'ejercicios' => [['ejercicio_id' => null, 'nombre' => 'SkiErg', 'cantidad' => '500 m', 'nota' => ''], ['ejercicio_id' => null, 'nombre' => 'Wall Balls', 'cantidad' => '20', 'nota' => '6/9 kg'], ['ejercicio_id' => null, 'nombre' => 'Sled Push', 'cantidad' => '25 m', 'nota' => '']]]];
            Clase::where('clase_tipo_id', $tipos[0]->id)->update(['entrenamiento' => json_encode($wod)]);

            $proximas = Clase::programadas()->where('inicia_at', '>', now())->orderBy('inicia_at')->get();
            foreach (self::ATLETAS as $i => [$nombre, $tel, $email, $p, $usadas, $hace, $pagado]) {
                $c = Cliente::create(['nombre' => $nombre, 'telefono' => $tel, 'email' => $email, 'origen' => 'ejemplo', 'ejemplo' => true]);
                $paq = $paquetes[$p];
                $m = Membresias::asignar($c, $paq, ['inicia_el' => now($tz)->subDays($hace)->toDateString(), 'pagado' => $pagado], null);
                $m->forceFill(['usados' => $usadas, 'ejemplo' => true])->save();
                // Cada quien con 1 o 2 clases reservadas esta semana
                foreach ($proximas->slice($i * 2, 2) as $clase) {
                    try { Reservas::reservar($clase, $c, 'panel'); } catch (\Throwable) {}
                }
            }
        });
    }

    public static function quitar(): void
    {
        DB::transaction(function () {
            $tipos = ClaseTipo::where('ejemplo', true)->pluck('id');
            Clase::whereIn('clase_tipo_id', $tipos)->delete();
            ClaseSerie::whereIn('clase_tipo_id', $tipos)->delete();
            ClaseTipo::whereIn('id', $tipos)->get()->each(fn ($t) => $t->clases()->exists() ? $t->update(['ejemplo' => false]) : $t->delete());
            Ejercicio::where('ejemplo', true)->delete();
            Membresia::where('ejemplo', true)->delete();
            Cliente::where('ejemplo', true)->get()->each->delete();
            Paquete::where('ejemplo', true)->get()->each(fn ($p) => $p->membresias()->exists() ? $p->update(['ejemplo' => false]) : $p->delete());
        });
    }
}
