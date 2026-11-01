<?php

namespace App\Support\Facturas;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Catálogos del SAT para facturar: clave de producto o servicio (c_ClaveProdServ), clave de unidad (c_ClaveUnidad)
 * y objeto de impuesto (c_ObjetoImp). La búsqueda usa los catálogos completos de Facturapi; sin conexión,
 * las claves más usadas.
 */
class CatalogoSat
{
    public const OBJETOS = [
        '01' => 'No objeto de impuesto',
        '02' => 'Sí objeto de impuesto',
        '03' => 'Sí objeto del impuesto y no obligado al desglose',
        '04' => 'Sí objeto del impuesto y no causa impuesto',
    ];

    /** Las más usadas, para cuando no hay conexión con Facturapi */
    public const UNIDADES = [
        'H87' => 'Pieza', 'E48' => 'Unidad de servicio', 'ACT' => 'Actividad', 'XUN' => 'Unidad', 'KGM' => 'Kilogramo', 'GRM' => 'Gramo',
        'LTR' => 'Litro', 'MLT' => 'Mililitro', 'MTR' => 'Metro', 'MTK' => 'Metro cuadrado', 'MTQ' => 'Metro cúbico', 'XBX' => 'Caja',
        'XPK' => 'Paquete', 'PR' => 'Par', 'SET' => 'Conjunto', 'KT' => 'Kit', 'HUR' => 'Hora', 'DAY' => 'Día', 'MON' => 'Mes',
        'ANN' => 'Año', 'TNE' => 'Tonelada', 'LBR' => 'Libra', 'XLT' => 'Lote', 'A9' => 'Tarifa',
    ];

    public const PRODUCTOS = [
        '01010101' => 'No existe en el catálogo',
        '84111506' => 'Servicios de facturación',
        '78102203' => 'Servicios de envío, recogida o entrega de correo',
    ];

    /** @return array<int, array{clave: string, texto: string}> */
    public static function buscar(string $tipo, string $q): array
    {
        $q = trim(preg_replace('/\s+/u', ' ', $q));
        if (mb_strlen($q) < 2) return self::locales($tipo, $q);
        $ruta = $tipo === 'unidades' ? 'units' : 'products';
        $r = Cache::remember('sat:' . $ruta . ':' . md5(Str::lower(Str::ascii($q))), now()->addDays(7), function () use ($ruta, $q) {
            $key = Timbrado::clave() ?: config('services.facturapi.user_key');
            if (! $key) return null;
            try {
                $res = Http::withToken((string) $key)->acceptJson()->timeout(10)->get(Timbrado::API . "/catalogs/$ruta", ['q' => $q, 'limit' => 20]);
                if (! $res->successful()) return null;
                $datos = $res->json('data') ?? $res->json();
                return collect(is_array($datos) ? $datos : [])
                    ->map(fn ($x) => ['clave' => (string) ($x['key'] ?? $x['code'] ?? ''), 'texto' => (string) ($x['description'] ?? $x['name'] ?? '')])
                    ->filter(fn ($x) => $x['clave'] !== '')->values()->all();
            } catch (\Throwable) {
                return null;
            }
        });
        return $r !== null && $r !== [] ? array_slice($r, 0, 20) : self::locales($tipo, $q);
    }

    private static function locales(string $tipo, string $q): array
    {
        $lista = $tipo === 'unidades' ? self::UNIDADES : self::PRODUCTOS;
        $n = Str::lower(Str::ascii($q));
        return collect($lista)->filter(fn ($t, $k) => $n === '' || str_contains(Str::lower(Str::ascii($k . ' ' . $t)), $n))
            ->map(fn ($t, $k) => ['clave' => (string) $k, 'texto' => $t])->values()->all();
    }

    public static function valida(string $tipo, ?string $clave): bool
    {
        if ($clave === null || $clave === '') return true;
        return $tipo === 'unidades' ? (bool) preg_match('/^[A-Z0-9]{1,3}$/', $clave) : (bool) preg_match('/^\d{8}$/', $clave);
    }
}
