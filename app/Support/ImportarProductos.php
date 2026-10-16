<?php

namespace App\Support;

use App\Models\Producto;
use App\Models\ProductoCategoria;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Importa el catálogo desde el Excel que ya usa el negocio, sea cual sea el orden o el nombre de sus columnas.
 * Se sugiere qué columna es qué; el negocio lo confirma y esa relación se guarda para la próxima vez.
 */
class ImportarProductos
{
    /** Campo => [texto, nombres comunes de la columna en los Excel de los negocios] */
    public const CAMPOS = [
        'clave'       => ['Clave o SKU',          ['clave', 'sku', 'codigo', 'cod', 'codigo de producto', 'codigo producto', 'modelo', 'referencia', 'ref', 'no parte', 'numero de parte', 'id producto', 'cve']],
        'nombre'      => ['Nombre',               ['nombre', 'producto', 'articulo', 'nombre del producto', 'nombre producto', 'concepto', 'item', 'descripcion corta']],
        'categoria'   => ['Categoría',            ['categoria', 'familia', 'linea', 'grupo', 'departamento', 'tipo', 'clasificacion', 'subcategoria']],
        'descripcion' => ['Descripción',          ['descripcion', 'detalle', 'detalles', 'especificaciones', 'caracteristicas', 'descripcion larga', 'observaciones']],
        'unidad'      => ['Unidad',               ['unidad', 'um', 'u m', 'unidad de medida', 'medida', 'presentacion', 'unidad venta']],
        'precio'      => ['Precio de venta',      ['precio', 'precio venta', 'precio de venta', 'precio publico', 'pvp', 'precio lista', 'p venta', 'precio unitario', 'pu', 'venta', 'precio 1', 'lista']],
        'costo'       => ['Costo',                ['costo', 'precio compra', 'precio de compra', 'costo unitario', 'precio costo', 'p compra', 'compra', 'costo promedio', 'ultimo costo']],
        'existencia'  => ['Existencia',           ['existencia', 'existencias', 'stock', 'inventario', 'cantidad', 'disponible', 'piezas', 'cant', 'exist', 'inv']],
        'minimo'      => ['Mínimo (para avisar)', ['minimo', 'stock minimo', 'existencia minima', 'punto de reorden', 'reorden', 'min']],
    ];

    public static function normalizar(string $t): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($t))));
    }

    /**
     * Qué columna va con cada campo. Primero lo que el negocio eligió la vez pasada (por nombre de columna),
     * luego nombres exactos y al final nombres parecidos. Cada columna se usa una sola vez.
     * @return array<string, int|null>
     */
    public static function sugerir(array $encabezados, array $guardado = []): array
    {
        $norm = array_map([self::class, 'normalizar'], $encabezados);
        $mapa = array_fill_keys(array_keys(self::CAMPOS), null);
        $usadas = [];
        $tomar = function (string $campo, int $i) use (&$mapa, &$usadas) {
            if ($mapa[$campo] !== null || isset($usadas[$i])) return;
            $mapa[$campo] = $i;
            $usadas[$i] = true;
        };

        foreach ($guardado as $campo => $nombre) {
            if (! array_key_exists($campo, $mapa) || $nombre === null) continue;
            $i = array_search(self::normalizar((string) $nombre), $norm, true);
            if ($i !== false) $tomar($campo, $i);
        }
        foreach (self::CAMPOS as $campo => [, $sinonimos]) {
            foreach ($sinonimos as $s) {
                $i = array_search($s, $norm, true);
                if ($i !== false) { $tomar($campo, $i); break; }
            }
        }
        foreach (self::CAMPOS as $campo => [, $sinonimos]) {
            if ($mapa[$campo] !== null) continue;
            foreach ($norm as $i => $h) {
                if ($h === '' || isset($usadas[$i])) continue;
                foreach ($sinonimos as $s) {
                    if (strlen($s) >= 4 && preg_match('/\b' . preg_quote($s, '/') . '\b/', $h)) { $tomar($campo, $i); continue 3; }
                }
            }
        }
        // Muchos catálogos llaman "Descripción" al nombre del producto
        if ($mapa['nombre'] === null && $mapa['descripcion'] !== null) {
            $mapa['nombre'] = $mapa['descripcion'];
            $mapa['descripcion'] = null;
        }
        return $mapa;
    }

    /** "$1,234.50", "1.234,50", "12 pzas" → número; vacío → null */
    public static function numero(?string $v): ?float
    {
        $v = trim((string) $v);
        if ($v === '' || $v === '-') return null;
        $v = preg_replace('/[^\d.,\-]/', '', $v);
        if ($v === '' || $v === '-') return null;
        if (str_contains($v, ',') && str_contains($v, '.')) {
            // El último separador es el decimal
            $v = strrpos($v, ',') > strrpos($v, '.') ? str_replace(['.', ','], ['', '.'], $v) : str_replace(',', '', $v);
        } elseif (str_contains($v, ',')) {
            // "1,234" miles; "12,5" decimal
            $v = preg_match('/,\d{3}$/', $v) ? str_replace(',', '', $v) : str_replace(',', '.', $v);
        }
        return is_numeric($v) ? (float) $v : null;
    }

    /**
     * Aplica la importación.
     * $opciones: iva_incluido (bool: los precios del archivo traen IVA), existencias ('reemplazar' | 'sumar' | 'ignorar').
     * @return array{nuevos:int, actualizados:int, omitidas:int, errores:array<int,string>}
     */
    public static function aplicar(array $filas, array $mapa, array $opciones): array
    {
        $col = fn (array $f, string $campo) => isset($mapa[$campo]) && $mapa[$campo] !== null ? trim((string) ($f[$mapa[$campo]] ?? '')) : null;
        $factorIva = ! empty($opciones['iva_incluido']) ? 1 + (float) config('vandu.iva_porcentaje', 16) / 100 : 1;
        $modoExis = $opciones['existencias'] ?? 'reemplazar';
        $res = ['nuevos' => 0, 'actualizados' => 0, 'omitidas' => 0, 'errores' => []];

        // Lo que ya existe, por clave y por nombre
        $existentes = Producto::get(['id', 'clave', 'nombre']);
        $porClave = $existentes->filter(fn ($p) => $p->clave !== null)->keyBy(fn ($p) => mb_strtolower($p->clave));
        $porNombre = $existentes->keyBy(fn ($p) => mb_strtolower($p->nombre));
        $categorias = [];
        $vistas = [];

        DB::transaction(function () use ($filas, $col, $factorIva, $modoExis, $porClave, $porNombre, &$categorias, &$vistas, &$res) {
            foreach ($filas as $n => $f) {
                $fila = $n + 2;   // +1 por el encabezado, +1 porque Excel cuenta desde 1
                $clave = $col($f, 'clave');
                $nombre = $col($f, 'nombre');
                if (($nombre === null || $nombre === '') && ($clave === null || $clave === '')) { $res['omitidas']++; continue; }

                $llave = $clave !== null && $clave !== '' ? 'c:' . mb_strtolower($clave) : 'n:' . mb_strtolower((string) $nombre);
                if (isset($vistas[$llave])) { $res['errores'][$fila] = 'Repetido en el archivo (fila ' . $vistas[$llave] . '); se tomó la primera.'; $res['omitidas']++; continue; }
                $vistas[$llave] = $fila;

                $p = ($clave !== null && $clave !== '' ? $porClave->get(mb_strtolower($clave)) : null)
                    ?? ($nombre !== null && $nombre !== '' && ($clave === null || $clave === '') ? $porNombre->get(mb_strtolower($nombre)) : null);
                $p = $p ? Producto::find($p->id) : null;
                if (! $p && ($nombre === null || $nombre === '')) { $res['errores'][$fila] = 'La clave ' . $clave . ' no existe y la fila no trae nombre.'; $res['omitidas']++; continue; }

                $datos = [];
                if ($nombre !== null && $nombre !== '') $datos['nombre'] = mb_substr($nombre, 0, 200);
                if ($clave !== null && $clave !== '') $datos['clave'] = mb_substr($clave, 0, 60);
                if (($t = $col($f, 'categoria')) !== null && $t !== '') {
                    $datos['categoria_id'] = $categorias[mb_strtolower($t)] ??= ProductoCategoria::porNombre($t)?->id;
                }
                if (($t = $col($f, 'descripcion')) !== null && $t !== '') $datos['descripcion'] = mb_substr($t, 0, 5000);
                if (($t = $col($f, 'unidad')) !== null && $t !== '') $datos['unidad'] = mb_substr(mb_strtolower($t), 0, 30);
                if (($v = self::numero($col($f, 'precio'))) !== null) $datos['precio'] = round(max(0, $v) / $factorIva, 2);
                if (($v = self::numero($col($f, 'costo'))) !== null) $datos['costo'] = round(max(0, $v) / $factorIva, 2);
                if (($v = self::numero($col($f, 'minimo'))) !== null) $datos['minimo'] = max(0, $v);
                $exis = self::numero($col($f, 'existencia'));

                if ($p) {
                    $p->update($datos);
                    $res['actualizados']++;
                } else {
                    $p = Producto::create($datos + ['precio' => 0, 'unidad' => 'pieza', 'controla_existencia' => true, 'publico' => true, 'activo' => true]);
                    $res['nuevos']++;
                }

                if ($exis !== null && $modoExis !== 'ignorar' && $p->controla_existencia) {
                    $modoExis === 'sumar'
                        ? Inventario::mover($p, 'importacion', $exis, 'Importación de Excel (se sumó)')
                        : Inventario::mover($p, 'importacion', 0, 'Importación de Excel', null, $exis);
                }
            }
        });

        return $res;
    }
}
