<?php

namespace App\Support\Ventas;

use App\Models\Venta;
use App\Support\Cuentas;

/**
 * Cómo cobra el negocio sus envíos (Ajustes de pedidos) y el costo de cada uno.
 * La distancia es en línea recta desde el negocio, ajustada por calles (factor 1.3): gratis y sin servicios de mapas.
 */
class Envios
{
    public const MODOS = ['gratis' => 'Sin costo', 'fijo' => 'Costo fijo', 'km' => 'Por kilómetro'];

    public static function ajustes(): array
    {
        return array_merge(['modo' => 'gratis', 'fijo' => 0, 'base' => 0, 'por_km' => 0, 'minimo' => 0, 'factor' => 1.3, 'lat' => null, 'lng' => null, 'direccion' => null],
            (array) Cuentas::actual()?->ajuste('envios', []));
    }

    /** Kilómetros desde el negocio hasta ese punto (null si falta alguna ubicación) */
    public static function km(?float $lat, ?float $lng): ?float
    {
        $a = self::ajustes();
        if ($lat === null || $lng === null || $a['lat'] === null || $a['lng'] === null) return null;
        $r = 6371.0;
        [$la1, $lo1, $la2, $lo2] = array_map('deg2rad', [(float) $a['lat'], (float) $a['lng'], $lat, $lng]);
        $h = sin(($la2 - $la1) / 2) ** 2 + cos($la1) * cos($la2) * sin(($lo2 - $lo1) / 2) ** 2;
        return round(2 * $r * asin(min(1, sqrt($h))) * (float) ($a['factor'] ?: 1.3), 1);
    }

    /** Costo según la tarifa del negocio. Por km sin distancia: null (se captura a mano) */
    public static function costo(?float $km): ?float
    {
        $a = self::ajustes();
        return match ($a['modo']) {
            'fijo'  => round((float) $a['fijo'], 2),
            'km'    => $km === null ? null : round(max((float) $a['minimo'], (float) $a['base'] + (float) $a['por_km'] * $km)),
            default => 0.0,
        };
    }

    /** Guarda los datos de envío (los llene el cliente o el vendedor) y recalcula el costo si no se puso a mano */
    public static function guardarDatos(Venta $v, array $d, string $por): void
    {
        $v->fill([
            'lleva_envio' => true,
            'envio_nombre' => $d['nombre'] ?? $v->envio_nombre, 'envio_telefono' => $d['telefono'] ?? $v->envio_telefono,
            'envio_direccion' => $d['direccion'] ?? $v->envio_direccion, 'envio_referencias' => $d['referencias'] ?? null,
            'envio_lat' => isset($d['lat']) && $d['lat'] !== '' ? round((float) $d['lat'], 7) : null,
            'envio_lng' => isset($d['lng']) && $d['lng'] !== '' ? round((float) $d['lng'], 7) : null,
            'datos_at' => now(), 'datos_por' => $por,
        ]);
        $v->envio_km = self::km($v->envio_lat, $v->envio_lng);
        if (! $v->envio_costo_manual && ($c = self::costo($v->envio_km)) !== null) $v->envio_costo = $c;
        if ($v->estado === 'entregada' && ! $v->entregada_at) $v->estado = 'por_surtir';
        $v->recalcular();
        $v->save();
    }
}
