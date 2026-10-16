<?php

namespace App\Support;

use App\Models\Producto;
use App\Models\ProductoCategoria;
use Illuminate\Support\Facades\DB;

/**
 * Diez productos de ejemplo para que un negocio nuevo vea el inventario funcionando desde el primer día:
 * con existencias, uno en su mínimo, uno agotado y dos servicios. Se quitan con un botón.
 */
class ProductosEjemplo
{
    // clave, nombre, categoría, unidad, precio, costo, existencia (null = servicio), mínimo, descripción
    public const LISTA = [
        ['EJ-001', 'Silla ejecutiva ergonómica', 'Mobiliario', 'pieza', 3490, 2100, 12, 4, "Respaldo de malla, soporte lumbar y ajuste de altura.\nGarantía de 1 año."],
        ['EJ-002', 'Escritorio de 120 cm', 'Mobiliario', 'pieza', 4890, 3100, 3, 4, 'Cubierta de melamina de 25 mm con pasacables.'],
        ['EJ-003', 'Archivero metálico de 3 gavetas', 'Mobiliario', 'pieza', 3290, 2200, 0, 2, 'Lámina de acero con cerradura central.'],
        ['EJ-004', 'Lámpara LED de escritorio', 'Iluminación', 'pieza', 590, 320, 25, 8, 'Luz cálida y fría, brazo ajustable y puerto USB.'],
        ['EJ-005', 'Pintura vinílica blanca 19 L', 'Materiales', 'cubeta', 1890, 1250, 18, 5, 'Acabado mate, lavable, rinde hasta 90 m².'],
        ['EJ-006', 'Cable eléctrico calibre 12, 100 m', 'Materiales', 'rollo', 1650, 1100, 9, 3, 'Cobre THW-LS, 90 °C.'],
        ['EJ-007', 'Playera de algodón personalizada', 'Textiles', 'pieza', 189, 95, 150, 50, 'Algodón 100 %, estampado a una tinta al frente.'],
        ['EJ-008', 'Kit de herramientas de 40 piezas', 'Herramientas', 'kit', 1290, 780, 6, 2, 'Desarmadores, llaves, pinzas y estuche rígido.'],
        ['EJ-009', 'Instalación a domicilio', 'Servicios', 'servicio', 650, null, null, null, 'Entrega, armado e instalación dentro de la ciudad.'],
        ['EJ-010', 'Mantenimiento preventivo', 'Servicios', 'servicio', 980, null, null, null, 'Revisión, limpieza y ajuste en sitio.'],
    ];

    /** Carga los ejemplos en la cuenta activa; regresa cuántos agregó */
    public static function cargar(): int
    {
        $n = 0;
        DB::transaction(function () use (&$n) {
            foreach (self::LISTA as [$clave, $nombre, $cat, $unidad, $precio, $costo, $exis, $min, $desc]) {
                if (Producto::where('clave', $clave)->exists()) continue;
                $p = Producto::create([
                    'clave' => $clave, 'nombre' => $nombre, 'categoria_id' => ProductoCategoria::porNombre($cat)?->id,
                    'unidad' => $unidad, 'precio' => $precio, 'costo' => $costo, 'descripcion' => $desc,
                    'controla_existencia' => $exis !== null, 'minimo' => $min, 'ejemplo' => true,
                ]);
                if ($exis) Inventario::mover($p, 'entrada', $exis, 'Existencia de ejemplo');
                $n++;
            }
        });
        return $n;
    }

    /** Quita los ejemplos (y las categorías que se quedan vacías) */
    public static function quitar(): int
    {
        $productos = Producto::where('ejemplo', true)->get();
        $categorias = $productos->pluck('categoria_id')->filter()->unique();
        foreach ($productos as $p) {
            FotoProducto::borrar($p);
            $p->delete();
        }
        ProductoCategoria::whereIn('id', $categorias)->whereDoesntHave('productos')->delete();
        return $productos->count();
    }
}
