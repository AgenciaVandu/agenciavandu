<?php

namespace App\Support;

use App\Models\Giro;
use Throwable;

/**
 * Tipos de empresa: los de fábrica (config/vandu.php) más los que se crean en Plataforma.
 * Al arrancar se mezclan en config('vandu.giros'), así todo el panel los trata igual.
 */
class Giros
{
    private static ?array $fabrica = null;

    /** Módulos que se pueden prender por tipo de empresa */
    public const MODULOS = [
        'stock' => ['texto' => 'Productos e inventario', 'icono' => 'bi-box-seam', 'ayuda' => 'Catálogo con existencias, importar de Excel, cotizar desde el catálogo y página por producto.'],
        'autos' => ['texto' => 'Lote de autos', 'icono' => 'bi-car-front', 'ayuda' => 'Unidades con fotos y ficha técnica, venta de contado, a crédito o con financiera, cobranza y termómetro de clientes.'],
        'fitness' => ['texto' => 'Gimnasio y clases', 'icono' => 'bi-lightning-charge', 'ayuda' => 'Agenda de clases con cupo, atletas con paquetes de clases, reservaciones, QR en la entrada y portal del atleta.'],
        'sin_cotizaciones' => ['texto' => 'Sin Cotizaciones', 'icono' => 'bi-eye-slash', 'ayuda' => 'Oculta Cotizaciones para negocios que no cotizan.'],
        'sin_proveedores' => ['texto' => 'Sin Proveedores', 'icono' => 'bi-eye-slash', 'ayuda' => 'Oculta Proveedores y órdenes de compra.'],
        'sin_archivos' => ['texto' => 'Sin Archivos ni Dropbox', 'icono' => 'bi-eye-slash', 'ayuda' => 'Oculta Archivos y la conexión con Dropbox para negocios que no manejan entregas.'],
        'lealtad' => ['texto' => 'Programa de lealtad', 'icono' => 'bi-qr-code', 'ayuda' => 'Campañas con premios por visitas y el QR de la red de restaurantes: el comensal se une y suma visitas en cada restaurante.'],
        'sin_clientes' => ['texto' => 'Sin Clientes', 'icono' => 'bi-eye-slash', 'ayuda' => 'Oculta la sección de Clientes (por ejemplo, en restaurantes los clientes son comensales del programa de lealtad).'],
        'sin_ventas' => ['texto' => 'Sin Ventas', 'icono' => 'bi-eye-slash', 'ayuda' => 'Oculta el embudo de Ventas.'],
        'sin_finanzas' => ['texto' => 'Sin Finanzas', 'icono' => 'bi-eye-slash', 'ayuda' => 'Oculta Finanzas para negocios que no registran cobros en el panel.'],
        'sin_tareas' => ['texto' => 'Sin Tareas', 'icono' => 'bi-eye-slash', 'ayuda' => 'Oculta Tareas para negocios que no trabajan con pendientes asignados (por ejemplo, un gimnasio).'],
        'sin_proyectos' => ['texto' => 'Sin Proyectos', 'icono' => 'bi-eye-slash', 'ayuda' => 'Oculta la sección de Proyectos (etapas y entregas) para negocios que no la usan.'],
        'gastos' => ['texto' => 'Gastos con lectura de tickets', 'icono' => 'bi-receipt', 'ayuda' => 'Registrar gastos sueltos con foto del ticket (la IA lo lee), XML de factura o a mano. Se suman en Finanzas.'],
        'redes' => ['texto' => 'Redes sociales', 'icono' => 'bi-grid-3x3-gap', 'ayuda' => 'Calendario de contenido y aprobaciones de sus clientes.'],
    ];

    public const ICONOS = ['bi-briefcase', 'bi-shop', 'bi-buildings', 'bi-tools', 'bi-truck', 'bi-heart-pulse', 'bi-mortarboard', 'bi-cup-hot',
                           'bi-scissors', 'bi-car-front', 'bi-house-door', 'bi-lightning-charge', 'bi-flower1', 'bi-camera', 'bi-megaphone', 'bi-gear'];

    public static function cargar(): void
    {
        self::$fabrica ??= config('vandu.giros');
        $todos = self::$fabrica;
        try {
            foreach (Giro::orderBy('nombre')->get() as $g) {
                if (! isset(self::$fabrica[$g->clave])) $todos[$g->clave] = $g->comoConfig();
            }
        } catch (Throwable) {
            // antes de migrar
        }
        config(['vandu.giros' => $todos]);
    }

    public static function deFabrica(string $clave): bool
    {
        self::$fabrica ??= config('vandu.giros');
        return isset(self::$fabrica[$clave]);
    }

    /** Un solo proceso sencillo para que cada negocio arme los suyos */
    public static function proyectosEnBlanco(): array
    {
        return [
            'general' => [
                'nombre' => 'Proyecto general', 'icono' => 'bi-kanban',
                'pagos'  => [
                    ['clave' => 'anticipo', 'concepto' => 'Anticipo', 'porcentaje' => 50, 'antes_de' => 'inicio'],
                    ['clave' => 'saldo', 'concepto' => 'Saldo', 'porcentaje' => 50, 'antes_de' => 'entrega'],
                ],
                'etapas' => [
                    ['clave' => 'inicio', 'nombre' => 'Inicio', 'dias' => 3, 'descripcion' => 'Cámbiale el nombre y agrega las etapas de tu proceso.'],
                    ['clave' => 'entrega', 'nombre' => 'Entrega', 'dias' => 1, 'fecha' => true, 'descripcion' => ''],
                ],
            ],
        ];
    }

    /** Costeo genérico para un tipo de empresa nuevo */
    public static function costeoBase(bool $conProductos): array
    {
        return [
            'utilidad'    => 30,
            'componentes' => array_values(array_filter([
                $conProductos ? ['clave' => 'producto', 'nombre' => 'Costo del producto', 'modo' => 'unidad', 'clase' => 'tercero'] : null,
                ['clave' => 'materiales', 'nombre' => 'Materiales o insumos', 'modo' => 'fijo', 'clase' => 'tercero'],
                ['clave' => 'mano_obra', 'nombre' => 'Mano de obra', 'modo' => 'fijo', 'clase' => 'operativo'],
                ['clave' => 'traslados', 'nombre' => 'Traslados y envíos', 'modo' => 'fijo', 'clase' => 'operativo'],
            ])),
        ];
    }
}
