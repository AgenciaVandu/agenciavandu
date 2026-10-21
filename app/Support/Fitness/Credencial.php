<?php

namespace App\Support\Fitness;

use App\Models\Cliente;

/**
 * La credencial del atleta en su celular cambia cada minuto: una captura de pantalla vieja no abre el torniquete.
 * El código se calcula en el mismo celular (sirve aunque no tenga señal en la entrada) con un secreto que solo conoce su portal.
 * Formato: FIT2:{id}:{minuto}:{firma 12}. La credencial impresa sigue siendo FIT:{código fijo}.
 */
class Credencial
{
    public const VENTANA = 60;
    public const ATRAS = 3;      // minutos de retraso aceptados (lentitud al escanear)
    public const ADELANTE = 1;   // reloj del celular un poco adelantado

    /** Secreto del atleta para firmar. Cambia si se le genera un QR nuevo */
    public static function secreto(Cliente $c): string
    {
        $c->qr; // se asegura de que tenga código
        return hash_hmac('sha256', 'fit2|' . $c->id . '|' . $c->qr_codigo, (string) config('app.key'));
    }

    public static function firma(Cliente $c, int $v, ?string $secreto = null): string
    {
        return substr(hash_hmac('sha256', $c->id . '|' . $v, $secreto ?? self::secreto($c)), 0, 12);
    }

    public static function qr(Cliente $c, ?int $t = null): string
    {
        $v = intdiv($t ?? time(), self::VENTANA);
        return 'FIT2:' . $c->id . ':' . $v . ':' . self::firma($c, $v);
    }

    /**
     * Lee un código de la credencial.
     * @return array{0: ?Cliente, 1: string} [atleta, 'dinamica'|'fija'|'vencido'|'desconocido']
     */
    public static function leer(string $texto, ?int $t = null): array
    {
        $texto = trim($texto);
        if (preg_match('/^FIT2:(\d+):(\d+):([a-f0-9]{12})$/', $texto, $m)) {
            $c = Cliente::find((int) $m[1]);   // dentro de la cuenta activa
            if (! $c || ! $c->qr_codigo) return [null, 'desconocido'];
            $s = self::secreto($c);
            $v = (int) $m[2];
            if (! hash_equals(self::firma($c, $v, $s), $m[3])) return [null, 'desconocido'];
            $ahora = intdiv($t ?? time(), self::VENTANA);
            if ($v < $ahora - self::ATRAS || $v > $ahora + self::ADELANTE) return [$c, 'vencido'];
            return [$c, 'dinamica'];
        }
        $codigo = str_starts_with($texto, 'FIT:') ? substr($texto, 4) : $texto;
        if (preg_match('/^[A-Za-z0-9]{32}$/', $codigo) && ($c = Cliente::where('qr_codigo', $codigo)->first())) return [$c, 'fija'];
        return [null, 'desconocido'];
    }
}
