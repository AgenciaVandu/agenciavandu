<?php

namespace App\Support\Fitness\Horarios;

/** Punto de entrada: arma los datos y le pide hallazgos al analizador configurado */
class Horarios
{
    public static function analizador(): Analizador
    {
        // Cuando exista el analizador con IA, se elige aquí (p. ej. por plan del negocio) sin tocar la pantalla
        return app(Reglas::class);
    }

    /** @return array{datos: Datos, hallazgos: Hallazgo[], analizador: Analizador} */
    public static function analizar(int $semanas = 8, ?int $sucursal = null): array
    {
        $datos = Datos::cargar($semanas, $sucursal);
        $a = self::analizador();
        return ['datos' => $datos, 'hallazgos' => $a->hallazgos($datos), 'analizador' => $a];
    }
}
