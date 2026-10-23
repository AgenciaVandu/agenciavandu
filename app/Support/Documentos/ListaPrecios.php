<?php

namespace App\Support\Documentos;

use App\Models\Cuenta;
use App\Models\Producto;
use App\Models\User;
use App\Support\IA\Anthropic;
use App\Support\ImportarProductos;
use Illuminate\Support\Collection;

/**
 * Lista de precios de un proveedor (PDF o foto) → costos nuevos para el catálogo.
 * La IA saca los renglones; aquí se emparejan con los productos (por clave, por nombre o por parecido)
 * y la persona revisa antes de aplicar. Los Excel van por Importar.
 */
class ListaPrecios
{
    public const MAX_FILAS = 300;

    /** @return array{ok: bool, filas?: array, con_iva?: ?bool, proveedor?: ?string, motivo?: string, mensaje?: string} */
    public static function leer(string $bytes, string $mime, Cuenta $c, ?User $u): array
    {
        if (! str_contains($mime, 'pdf')) {
            $bytes = \App\Support\Gastos\LectorTicket::preparar($bytes) ?? $bytes;
            $mime = 'image/jpeg';
        }
        $r = Anthropic::herramienta($c, $u, 'lista', [Anthropic::archivo($bytes, $mime), ['type' => 'text', 'text' => self::instrucciones()]], self::herramienta(), 16000, 120);
        if (! $r['ok']) return $r;
        $e = $r['entrada'];
        if (($e['es_lista'] ?? true) === false) return ['ok' => false, 'motivo' => 'no_es', 'mensaje' => 'Ese documento no parece una lista de precios.'];
        $filas = collect((array) ($e['filas'] ?? []))->map(fn ($f) => [
            'clave'       => is_string($f['clave'] ?? null) ? mb_substr(trim($f['clave']), 0, 60) : null,
            'descripcion' => is_string($f['descripcion'] ?? null) ? mb_substr(trim(preg_replace('/\s+/', ' ', $f['descripcion'])), 0, 200) : null,
            'precio'      => is_numeric($f['precio'] ?? null) && $f['precio'] > 0 ? round((float) $f['precio'], 2) : null,
            'unidad'      => is_string($f['unidad'] ?? null) ? mb_substr(trim($f['unidad']), 0, 30) : null,
        ])->filter(fn ($f) => $f['precio'] !== null && ($f['clave'] || $f['descripcion']))->take(self::MAX_FILAS)->values()->all();
        if (! $filas) return ['ok' => false, 'motivo' => 'vacia', 'mensaje' => 'No encontramos precios en el documento.'];
        return ['ok' => true, 'filas' => $filas, 'con_iva' => is_bool($e['precios_con_iva'] ?? null) ? $e['precios_con_iva'] : null,
                'proveedor' => is_string($e['proveedor'] ?? null) ? mb_substr(trim($e['proveedor']), 0, 120) : null];
    }

    /**
     * Empareja cada renglón con un producto del catálogo.
     * @return array<int, array{fila: array, producto: ?Producto, como: ?string}>  como = clave | nombre | parecido
     */
    public static function emparejar(array $filas, ?int $proveedorId = null): array
    {
        $productos = Producto::where('activo', true)->get(['id', 'clave', 'nombre', 'unidad', 'costo', 'precio', 'proveedor_id']);
        $porClave = $productos->filter(fn ($p) => filled($p->clave))->keyBy(fn ($p) => self::llave($p->clave));
        $porNombre = $productos->keyBy(fn ($p) => ImportarProductos::normalizar($p->nombre));
        // Para "parecido" se prefieren los productos de ese proveedor
        $candidatos = $proveedorId && $productos->where('proveedor_id', $proveedorId)->isNotEmpty() ? $productos->where('proveedor_id', $proveedorId) : $productos;
        $usados = [];

        return collect($filas)->map(function ($f) use ($porClave, $porNombre, $candidatos, &$usados) {
            $p = null; $como = null;
            if ($f['clave'] && ($x = $porClave->get(self::llave($f['clave'])))) { $p = $x; $como = 'clave'; }
            elseif ($f['descripcion'] && ($x = $porNombre->get(ImportarProductos::normalizar($f['descripcion'])))) { $p = $x; $como = 'nombre'; }
            elseif ($f['descripcion']) {
                [$x, $pct] = self::parecido($f['descripcion'], $candidatos);
                if ($x && $pct >= 72) { $p = $x; $como = 'parecido'; }
            }
            if ($p && isset($usados[$p->id])) { $p = null; $como = null; }   // un producto no se actualiza dos veces
            if ($p) $usados[$p->id] = true;
            return ['fila' => $f, 'producto' => $p, 'como' => $como];
        })->all();
    }

    private static function llave(string $clave): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower($clave));
    }

    /** El producto con el nombre más parecido y qué tanto (0–100) */
    private static function parecido(string $texto, Collection $productos): array
    {
        $a = ImportarProductos::normalizar($texto);
        $palabrasA = array_filter(explode(' ', $a), fn ($w) => strlen($w) > 2);
        $mejor = [null, 0];
        foreach ($productos as $p) {
            $b = ImportarProductos::normalizar($p->nombre);
            similar_text($a, $b, $pct);
            $palabrasB = array_filter(explode(' ', $b), fn ($w) => strlen($w) > 2);
            if ($palabrasA && $palabrasB) {
                $comunes = count(array_intersect($palabrasA, $palabrasB)) / max(count($palabrasA), count($palabrasB)) * 100;
                $pct = max($pct, $comunes);
            }
            if ($pct > $mejor[1]) $mejor = [$p, $pct];
        }
        return $mejor;
    }

    private static function instrucciones(): string
    {
        return "Es una lista de precios de un proveedor de un negocio en México (PDF o foto). Saca cada producto con la herramienta.\n"
            . "- clave: el código, SKU, modelo o número de parte, si viene. descripcion: el nombre del producto como aparece.\n"
            . "- precio: el precio unitario en pesos, como número. Si hay varias columnas de precio (por volumen, mayoreo), usa la de una pieza o la primera.\n"
            . "- precios_con_iva: true si la lista dice que los precios incluyen IVA, false si dice más IVA o antes de IVA, null si no lo dice.\n"
            . "- No inventes renglones ni precios; omite encabezados, totales y notas. Máximo " . self::MAX_FILAS . " renglones.";
    }

    private static function herramienta(): array
    {
        return [
            'name' => 'registrar_lista',
            'description' => 'Registra los renglones de una lista de precios de proveedor.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'es_lista' => ['type' => 'boolean'],
                    'proveedor' => ['type' => ['string', 'null']],
                    'precios_con_iva' => ['type' => ['boolean', 'null']],
                    'filas' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                        'clave' => ['type' => ['string', 'null']], 'descripcion' => ['type' => ['string', 'null']],
                        'precio' => ['type' => ['number', 'null']], 'unidad' => ['type' => ['string', 'null']],
                    ], 'required' => ['descripcion', 'precio']]],
                ],
                'required' => ['es_lista', 'filas'],
            ],
        ];
    }
}
