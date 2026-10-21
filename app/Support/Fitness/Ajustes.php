<?php

namespace App\Support\Fitness;

use App\Support\Cuentas;

/**
 * Reglas del gimnasio (Cuenta.ajustes.fitness). El gerente las cambia en Configuración → Reglas del gimnasio.
 */
class Ajustes
{
    public const DEFECTO = [
        'horas_cancelar'   => 6,    // cancelar con al menos estas horas de anticipación devuelve el crédito
        'reservar_hasta'   => 0,    // minutos antes de empezar en que se cierra la reservación (0 = hasta que empieza)
        'dias_adelante'    => 14,   // cuántos días hacia adelante puede reservar el atleta
        'checkin_antes'    => 30,   // el QR sirve desde estos minutos antes de la clase…
        'checkin_despues'  => 20,   // …hasta estos minutos después de que empezó
        'aviso_clase'      => 60,   // minutos antes: "tu clase empieza pronto"
        'aviso_vence'      => 3,    // días antes de que venza el paquete
        'max_por_dia'      => 0,    // clases al día por atleta (0 = sin límite; el paquete puede poner el suyo)
        'atleta_reserva'   => true, // el atleta puede reservar y cancelar desde su portal
    ];

    public static function todas(): array
    {
        return array_merge(self::DEFECTO, (array) (Cuentas::actual()?->ajuste('fitness') ?? []));
    }

    public static function get(string $clave)
    {
        return self::todas()[$clave] ?? null;
    }

    public static function guardar(array $valores): void
    {
        $c = Cuentas::actual();
        $a = $c->ajustes ?? [];
        $a['fitness'] = array_intersect_key(array_merge(self::todas(), $valores), self::DEFECTO);
        $c->forceFill(['ajustes' => $a])->save();
    }
}
