{{-- Inicio que guía: accesos grandes según el giro y primeros pasos para negocios nuevos --}}
@php
    $cu = \App\Support\Cuentas::actual();
    $us = auth()->user();
    $accesos = [];
    if ($cu?->tiene('stock') && $us->puede('vender')) {
        $porEntregar = \App\Models\Venta::porEntregar()->count();
        // Te deben: ventas a crédito más pagos pendientes de proyectos (con IVA, lo que van a pagar)
        $porCobrar = \App\Models\Venta::porCobrar()->get()->sum('saldo')
            + ($cu->tiene('proyectos') ? \App\Models\ProyectoPago::whereNull('pagado_el')->whereHas('proyecto', fn ($q) => $q->whereNotIn('estado', \App\Models\Proyecto::SIN_COBRO))->sum('monto') : 0);
        $bajos = $us->puede('productos') ? \App\Models\Producto::activos()->bajos()->count() : null;
        $accesos = array_values(array_filter([
            ['url' => route('admin.pedidos.create'), 'icono' => 'bi-bag-plus', 'titulo' => 'Nueva venta', 'dato' => 'Mostrador o pedido', 'principal' => true],
            ['url' => route('admin.pedidos', ['ver' => 'entregar']), 'icono' => 'bi-truck', 'titulo' => 'Por entregar', 'dato' => $porEntregar ? $porEntregar . ($porEntregar === 1 ? ' pedido' : ' pedidos') : 'Todo entregado'],
            ['url' => route('admin.cobrar'), 'icono' => 'bi-cash-coin', 'titulo' => 'Te deben', 'dato' => $porCobrar > 0 ? '$' . number_format($porCobrar, 0) : 'Nadie te debe'],
            $bajos !== null ? ['url' => route('admin.productos.index', ['estado' => 'bajos']), 'icono' => 'bi-box-seam', 'titulo' => 'Inventario bajo', 'dato' => $bajos ? $bajos . ($bajos === 1 ? ' producto' : ' productos') : 'Todo en orden'] : null,
        ]));
    }
    $pasos = []; // los primeros pasos viven en admin._arranque
@endphp
@if($accesos || $pasos)
<style>
    .ir-accesos { display: grid; grid-template-columns: repeat({{ max(1, count($accesos)) }}, minmax(0, 1fr)); gap: 12px; margin-bottom: 20px; }
    .ir-acceso { display: flex; gap: 12px; align-items: center; padding: 16px 18px; border-radius: 16px; border: 1px solid var(--line); background: var(--surface, #fff); color: inherit; text-decoration: none; }
    .ir-acceso:hover { border-color: var(--marca); }
    .ir-acceso i { font-size: 22px; width: 44px; height: 44px; border-radius: 12px; display: grid; place-items: center; background: var(--marca-soft); color: var(--marca); flex: none; }
    .ir-acceso b { display: block; font-size: 15px; } .ir-acceso span { font-size: 13px; color: var(--muted); }
    .ir-acceso.principal { background: var(--marca); border-color: var(--marca); color: #fff; } .ir-acceso.principal span { color: rgba(255,255,255,.8); }
    .ir-acceso.principal i { background: rgba(255,255,255,.18); color: #fff; }
    .ir-pasos { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 14px 18px; margin-bottom: 20px; }
    .ir-paso { display: inline-flex; gap: 6px; align-items: center; padding: 6px 12px; border-radius: 99px; border: 1px solid var(--line); font-size: 13.5px; color: inherit; text-decoration: none; }
    .ir-pasos .avance { display: inline-flex; align-items: center; gap: 8px; font-size: 13px; color: var(--muted); margin-right: 6px; }
    .ir-pasos .avance .b { width: 72px; height: 6px; border-radius: 3px; background: #EEF0F3; overflow: hidden; } .ir-pasos .avance .b span { display: block; height: 100%; background: #00A862; }
    @media (max-width: 575.98px) { .ir-pasos { padding: 12px 14px; } .ir-acceso { padding: 12px; gap: 10px; } .ir-acceso i { width: 38px; height: 38px; font-size: 19px; } .ir-acceso b { font-size: 14px; } .ir-acceso span { font-size: 12px; } }
    @media (max-width: 991.98px) { .ir-accesos { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>
@if($pasos)
    <div class="panel ir-pasos"><b class="me-1">Primeros pasos</b>
        <span class="avance"><span class="b"><span style="width: {{ round($hechos / count($pasos) * 100) }}%"></span></span>{{ $hechos }} de {{ count($pasos) }}</span>
        @foreach($pendientesPasos as $p)<a href="{{ $p['url'] }}" class="ir-paso"><i class="bi bi-circle"></i> {{ $p['texto'] }}</a>@endforeach
    </div>
@endif
@if($accesos)
    <div class="ir-accesos">
        @foreach($accesos as $a)<a href="{{ $a['url'] }}" class="ir-acceso {{ ! empty($a['principal']) ? 'principal' : '' }}"><i class="bi {{ $a['icono'] }}"></i><div><b>{{ $a['titulo'] }}</b><span>{{ $a['dato'] }}</span></div></a>@endforeach
    </div>
@endif
@endif
