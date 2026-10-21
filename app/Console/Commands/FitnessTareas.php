<?php

namespace App\Console\Commands;

use App\Models\ClaseSerie;
use App\Models\Cuenta;
use App\Support\Cuentas;
use App\Support\Fitness\Agenda;
use App\Support\Fitness\Avisos;
use App\Support\Fitness\Reservas;
use Illuminate\Console\Command;

/**
 * Lo que corre solo en los gimnasios (cada 5 minutos):
 *  - avisos al atleta: su clase está por empezar, le queda 1 clase, su paquete vence pronto;
 *  - las reservaciones de clases que ya terminaron y nadie marcó quedan como falta;
 *  - el horario fijo se va completando semanas hacia adelante.
 */
class FitnessTareas extends Command
{
    protected $signature = 'vandu:fitness';
    protected $description = 'Avisos a los atletas, faltas y horario fijo de los gimnasios';

    public function handle(): int
    {
        foreach (Cuenta::where('activa', true)->orderBy('id')->get()->filter->tiene('fitness') as $cuenta) {
            Cuentas::como($cuenta, function () use ($cuenta) {
                $n = Avisos::programados();
                $faltas = Reservas::cerrarFaltas();
                $clases = ClaseSerie::where('activa', true)->get()->sum(fn ($s) => Agenda::generar($s));
                $this->line("{$cuenta->nombre}: {$n['clase']} avisos de clase, {$n['credito']} de último crédito, {$n['vence']} de vencimiento, $faltas faltas, $clases clases nuevas");
            });
        }
        return self::SUCCESS;
    }
}
