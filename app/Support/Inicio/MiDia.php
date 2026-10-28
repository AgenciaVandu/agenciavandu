<?php

namespace App\Support\Inicio;

use App\Models\User;
use App\Support\Permisos;

/** Inicio para quien no ve las cifras del negocio (vendedor, recepción, diseñador…): sus pendientes y sus herramientas */
class MiDia
{
    private const ACCESOS = [
        ['vender', 'admin.pedidos.create', 'bi-bag-plus', 'Nueva venta', 'Mostrador o pedido'],
        ['vender', 'admin.pedidos', 'bi-bag', 'Ventas', 'Por entregar y por cobrar'],
        ['ventas', 'admin.ventas.index', 'bi-funnel', 'Prospectos', 'Quién te preguntó'],
        ['cotizaciones', 'admin.presupuestos.create', 'bi-file-earmark-plus', 'Nueva cotización', 'Precio para un cliente'],
        ['cotizaciones', 'admin.presupuestos.index', 'bi-file-earmark-text', 'Cotizaciones', 'Enviadas y en negociación'],
        ['clientes', 'admin.clientes.index', 'bi-people', 'Clientes', 'Fichas y contacto'],
        ['proyectos', 'admin.proyectos.index', 'bi-kanban', 'Proyectos', 'Etapas y entregas'],
        ['productos', 'admin.productos.index', 'bi-box-seam', 'Productos', 'Catálogo y existencias'],
        ['proveedores', 'admin.proveedores.index', 'bi-truck', 'Proveedores', 'Órdenes de compra'],
        ['clases', 'admin.clases.index', 'bi-calendar-week', 'Agenda', 'Clases y reservaciones'],
        ['asistencia', 'admin.escanear', 'bi-qr-code-scan', 'Escanear', 'Entrada con QR'],
        ['membresias', 'admin.paquetes', 'bi-ticket-perforated', 'Paquetes', 'Asignar y renovar'],
        ['unidades', 'admin.unidades.index', 'bi-car-front', 'Unidades', 'El lote'],
        ['cobranza', 'admin.creditos.index', 'bi-cash-coin', 'Cobranza', 'Pagos y atrasos'],
        ['redes', 'admin.redes', 'bi-grid-3x3-gap', 'Redes sociales', 'Calendario y aprobaciones'],
        ['canjes', 'admin.lealtad.escanear', 'bi-qr-code-scan', 'Escanear', 'Visitas y premios'],
        ['comensales', 'admin.comensales', 'bi-person-hearts', 'Comensales', 'El programa'],
        ['gastos', 'admin.gastos.create', 'bi-receipt', 'Subir gasto', 'Foto del ticket'],
    ];

    /** Sus herramientas, en el orden en que más se usan */
    public static function accesos(User $u): array
    {
        $r = [];
        foreach (self::ACCESOS as [$sec, $ruta, $icono, $titulo, $ayuda]) {
            if ($u->puede($sec) && Permisos::puedeRuta($u, $ruta) && \Illuminate\Support\Facades\Route::has($ruta)) $r[$ruta] = ['url' => route($ruta), 'icono' => $icono, 'titulo' => $titulo, 'ayuda' => $ayuda];
        }
        if (\App\Support\Cuentas::actual()?->tiene('tareas') !== false && Permisos::puedeRuta($u, 'admin.tareas.index'))
            $r['admin.tareas.index'] = ['url' => route('admin.tareas.index'), 'icono' => 'bi-check2-square', 'titulo' => 'Mis tareas', 'ayuda' => 'Lo que te asignaron'];
        return array_values($r);
    }
}
