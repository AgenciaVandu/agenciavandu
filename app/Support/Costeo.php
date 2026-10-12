<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Componentes de costeo del negocio activo (cada giro costea distinto).
 * Se configuran en Configuración → Costeo; cada concepto de una cotización guarda una copia de los que usó,
 * así cambiar o borrar un componente no altera cotizaciones ya hechas.
 */
class Costeo
{
    public const MODOS = [
        'unidad' => ['texto' => 'Por cantidad', 'ayuda' => 'Se multiplica por la cantidad del concepto (p. ej. costo por pieza)', 'corto' => 'c/u'],
        'fijo'   => ['texto' => 'Monto fijo',   'ayuda' => 'Una sola vez por concepto (p. ej. flete, licencia, horas totales)', 'corto' => 'total'],
    ];

    public const CLASES = [
        'tercero'   => ['texto' => 'Gasto de terceros', 'ayuda' => 'Le pagas a alguien más: proveedor, maquila, freelance, subcontrato', 'corto' => 'Terceros'],
        'operativo' => ['texto' => 'Gasto operativo',   'ayuda' => 'Lo pones tú: tiempo del equipo, traslados, herramientas, licencias', 'corto' => 'Operativo'],
    ];

    /** @return array<int, array{clave:string,nombre:string,modo:string,clase:string}> */
    public static function componentes(): array
    {
        return array_values(config('vandu.costeo.componentes') ?? []);
    }

    /** Utilidad (% sobre el costo) que se propone al costear */
    public static function utilidad(): float
    {
        return (float) (config('vandu.costeo.utilidad') ?? 30);
    }

    /** Deja una lista de componentes limpia: nombre, modo y clase válidos, y claves únicas y estables */
    public static function limpiar(array $lista): array
    {
        $usadas = [];
        $limpia = [];
        foreach ($lista as $c) {
            $nombre = trim((string) ($c['nombre'] ?? ''));
            if ($nombre === '') continue;
            $clave = Str::slug((string) ($c['clave'] ?? '') ?: $nombre, '_') ?: 'costo';
            $base = Str::limit($clave, 36, '');
            for ($i = 2; isset($usadas[$clave]); $i++) $clave = $base . '_' . $i;
            $usadas[$clave] = true;
            $limpia[] = [
                'clave'  => $clave,
                'nombre' => Str::limit($nombre, 60, ''),
                'modo'   => isset(self::MODOS[$c['modo'] ?? '']) ? $c['modo'] : 'fijo',
                'clase'  => isset(self::CLASES[$c['clase'] ?? '']) ? $c['clase'] : 'operativo',
            ];
        }
        return $limpia;
    }

    /** Costo de un componente dentro de un concepto */
    public static function importe(array $c, float $cantidad): float
    {
        $m = (float) ($c['monto'] ?? 0);
        return round(($c['modo'] ?? 'fijo') === 'unidad' ? $m * $cantidad : $m, 2);
    }
}
