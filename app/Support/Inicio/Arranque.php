<?php

namespace App\Support\Inicio;

use App\Models\Cuenta;
use App\Models\User;
use App\Support\Cuentas;

/**
 * Arranque guiado: los primeros pasos de cada tipo de negocio y los datos de ejemplo para probar el panel.
 * Los ejemplos se marcan como tales y se borran con un botón sin tocar lo que el negocio capturó.
 */
class Arranque
{
    /** Pasos del negocio (solo durante sus primeros 30 días y para quien lo configura) */
    public static function pasos(User $u): array
    {
        $c = Cuentas::actual();
        if (! $c || ! $u->puede('configuracion') || ! $c->created_at || $c->created_at->lt(now()->subDays(30))) return [];
        $p = [];
        $paso = fn (bool $hecho, string $texto, string $ruta, array $param = []) => ['hecho' => $hecho, 'texto' => $texto, 'url' => route($ruta, $param)];
        $p[] = $paso((bool) $c->ajuste('marca.logo'), 'Sube tu logo', 'admin.negocio');
        if ($c->tiene('stock')) {
            $p[] = $paso(\App\Models\Producto::where('ejemplo', false)->exists(), 'Carga tus productos', 'admin.productos.importar');
            $p[] = $paso((bool) $c->ajuste('envios.modo'), 'Pon tu tarifa de envío', 'admin.pedidos.ajustes');
            $p[] = $paso(\App\Models\Venta::exists(), 'Registra tu primera venta', 'admin.pedidos.create');
        } elseif ($c->tiene('fitness')) {
            $p[] = $paso(\App\Models\Paquete::exists(), 'Crea tus paquetes y precios', 'admin.paquetes');
            $p[] = $paso(\App\Models\Clase::where('ejemplo', false)->exists(), 'Agenda tus clases', 'admin.clases.index');
            $p[] = $paso(\App\Models\Cliente::where('ejemplo', false)->exists(), 'Registra a tu primer atleta', 'admin.clientes.create');
        } elseif ($c->tiene('lealtad')) {
            $p[] = $paso(\App\Models\LealtadCampana::where('ejemplo', false)->exists(), 'Crea tu primera campaña', 'admin.lealtad.campanas');
            $p[] = $paso(\App\Models\LealtadVisita::where('ejemplo', false)->exists(), 'Escanea la primera visita', 'admin.lealtad.escanear');
        } elseif ($c->tiene('autos')) {
            $p[] = $paso(\App\Models\Unidad::where('ejemplo', false)->exists(), 'Da de alta tu primera unidad', 'admin.unidades.create');
            $p[] = $paso(\App\Models\Cliente::where('ejemplo', false)->exists(), 'Registra a tu primer cliente', 'admin.clientes.create');
        } elseif ($c->tiene('cotizaciones')) {
            $p[] = $paso((bool) ($c->ajuste('pago.clabe') || $c->ajuste('emisor.clabe') || $c->ajuste('pago.banco')), 'Pon tus datos de pago', 'admin.negocio');
            $p[] = $paso(\App\Models\Cliente::exists(), 'Registra a tu primer cliente', 'admin.clientes.create');
            $p[] = $paso(\App\Models\Presupuesto::exists(), 'Manda tu primera cotización', 'admin.presupuestos.create');
        }
        if ($u->puede('usuarios')) $p[] = $paso(User::count() > 1, 'Invita a tu equipo', 'admin.usuarios');
        return collect($p)->every('hecho') ? [] : $p;
    }

    /** Qué ejemplos tiene este tipo de negocio: [nombre, hay(), cargar(), quitar()] */
    public static function ejemplos(?Cuenta $c = null): ?array
    {
        $c ??= Cuentas::actual();
        return match (true) {
            (bool) $c?->tiene('stock')   => ['productos de ejemplo', fn () => \App\Models\Producto::where('ejemplo', true)->exists(), fn () => \App\Support\ProductosEjemplo::cargar(), fn () => \App\Support\ProductosEjemplo::quitar()],
            (bool) $c?->tiene('fitness') => ['clases, paquetes y atletas de ejemplo', fn () => \App\Support\Fitness\Ejemplo::hay(), fn () => \App\Support\Fitness\Ejemplo::cargar(), fn () => \App\Support\Fitness\Ejemplo::quitar()],
            (bool) $c?->tiene('lealtad') => ['campaña y comensales de ejemplo', fn () => \App\Support\Lealtad\Ejemplo::hay(), fn () => \App\Support\Lealtad\Ejemplo::cargar(), fn () => \App\Support\Lealtad\Ejemplo::quitar()],
            (bool) $c?->tiene('autos')   => ['unidades y créditos de ejemplo', fn () => \App\Support\AutosEjemplo::hay(), fn () => \App\Support\AutosEjemplo::cargar(), fn () => \App\Support\AutosEjemplo::quitar()],
            default => null,
        };
    }
}
