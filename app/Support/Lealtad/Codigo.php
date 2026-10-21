<?php

namespace App\Support\Lealtad;

use App\Models\Comensal;

/**
 * El QR del comensal cambia cada minuto: una captura de pantalla vieja ya no sirve para sumar visitas.
 * Formato del QR: NRB:{id}:{minuto}:{firma}. Para escribirlo a mano: {id}-{6 dígitos}.
 */
class Codigo
{
    public const VENTANA = 60;     // segundos que dura cada código
    public const TOLERANCIA = 3;   // minutos hacia atrás que se aceptan (reloj del celular, lentitud al escanear)

    private static function firma(Comensal $c, int $v): string
    {
        return hash_hmac('sha256', $c->id . '|' . $v, $c->qr_secreto . '|' . config('app.key'));
    }

    private static function ventana(?int $t = null): int
    {
        return intdiv($t ?? time(), self::VENTANA);
    }

    public static function qr(Comensal $c, ?int $t = null): string
    {
        $v = self::ventana($t);
        return 'NRB:' . $c->id . ':' . $v . ':' . substr(self::firma($c, $v), 0, 12);
    }

    /** Versión corta para dictarla o escribirla si la cámara falla */
    public static function corto(Comensal $c, ?int $t = null): string
    {
        return $c->id . '-' . self::digitos($c, self::ventana($t));
    }

    private static function digitos(Comensal $c, int $v): string
    {
        return str_pad((string) (hexdec(substr(self::firma($c, $v), 0, 8)) % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /** Segundos que le quedan al código actual */
    public static function restante(?int $t = null): int
    {
        $t ??= time();
        return self::VENTANA - ($t % self::VENTANA);
    }

    /** Quién es, si el código es válido y reciente. null si no sirve */
    public static function leer(string $texto, ?int $t = null): ?Comensal
    {
        $texto = trim($texto);
        $ahora = self::ventana($t);
        if (preg_match('/^NRB:(\d+):(\d+):([a-f0-9]{12})$/', $texto, $m)) {
            $c = Comensal::find((int) $m[1]);
            $v = (int) $m[2];
            if (! $c || $v > $ahora || $v < $ahora - self::TOLERANCIA) return null;
            return hash_equals(substr(self::firma($c, $v), 0, 12), $m[3]) ? $c : null;
        }
        if (preg_match('/^(\d+)\s*-\s*(\d{6})$/', $texto, $m)) {
            $c = Comensal::find((int) $m[1]);
            if (! $c) return null;
            for ($v = $ahora; $v >= $ahora - self::TOLERANCIA; $v--) {
                if (hash_equals(self::digitos($c, $v), $m[2])) return $c;
            }
        }
        return null;
    }

    /** ¿Parece un código de lealtad aunque ya haya vencido? (para decir "pídele que lo actualice") */
    public static function pareceDeLealtad(string $texto): bool
    {
        return (bool) preg_match('/^(NRB:\d+:\d+:[a-f0-9]{12}|\d+\s*-\s*\d{6})$/', trim($texto));
    }
}
