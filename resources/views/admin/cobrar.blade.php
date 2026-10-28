@extends('admin.layout')
@section('titulo', 'Registrar pago')

@php
    $d = fn ($n) => '$' . number_format($n, 2);
    $hoyF = now(config('vandu.zona_horaria'))->toDateString();
@endphp

@section('contenido')
<div class="page-head">
    <div>
        @if($cliente)<a href="{{ route('admin.cobrar') }}" class="regresar"><i class="bi bi-arrow-left"></i> Otro cliente</a>@endif
        <h1>{{ $cliente ? 'Cobrar a ' . ($cliente->empresa ?: $cliente->nombre) : 'Registrar pago' }}</h1>
        <p class="sub">{{ $cliente ? 'Todo lo que te debe, en un solo lugar. Elige qué te pagó.' : '¿Quién te pagó? Aquí están los que te deben, primero lo vencido.' }}</p>
    </div>
</div>

@if(! $cliente)
    <div class="panel cb-panel" x-data="{ q: '' }">
        <div class="p-3 border-bottom"><input type="search" class="form-control" placeholder="Busca al cliente por nombre…" x-model="q" autofocus></div>
        @forelse($clientes as $c)
            @php $url = $c['cliente_id'] ? route('admin.cobrar', ['cliente' => $c['cliente_id']]) : null; @endphp
            <a @if($url) href="{{ $url }}" @endif class="cb-fila" x-show="! q || {{ Js::from(mb_strtolower($c['nombre'])) }}.includes(q.toLowerCase())">
                <span class="cb-av">{{ mb_substr($c['nombre'], 0, 1) }}</span>
                <span class="cb-t"><b>{{ $c['nombre'] }}</b><span>{{ $c['n'] }} {{ $c['n'] === 1 ? 'pago pendiente' : 'pagos pendientes' }}@if($c['vencido']) · <span class="text-danger">algo ya venció</span>@endif</span></span>
                <b class="num ms-auto">{{ $d($c['monto']) }}</b><i class="bi bi-chevron-right secundario"></i>
            </a>
        @empty
            <div class="vacio"><div class="ico"><i class="bi bi-check2-circle"></i></div><h3>Nadie te debe</h3><p>Cuando tengas pagos pendientes de proyectos, ventas o créditos, aparecen aquí.</p></div>
        @endforelse
        @if($todos->isNotEmpty())
            <div class="p-3 border-top" x-show="q.length > 1">
                <div class="secundario small mb-2">Otros clientes</div>
                @foreach($todos as $t)
                    @php $n = $t->empresa ?: $t->nombre; @endphp
                    <a href="{{ route('admin.cobrar', ['cliente' => $t->id]) }}" class="d-block py-1 text-decoration-none" x-show="{{ Js::from(mb_strtolower($n . ' ' . $t->nombre)) }}.includes(q.toLowerCase())">{{ $n }}</a>
                @endforeach
            </div>
        @endif
    </div>
@else
    @if($items->isNotEmpty())<div class="cb-total panel"><span>Te debe en total</span><b class="num">{{ $d($items->sum('monto')) }}</b></div>@endif
    @forelse($items as $i)
        <div class="panel cb-item {{ $i['vencido'] ? 'vencido' : '' }}" x-data="{ abierto: {{ $loop->first ? 'true' : 'false' }} }">
            <button type="button" class="cb-cab" @click="abierto = ! abierto">
                <span class="cb-t"><b>{{ $i['titulo'] }}</b><span>{{ $i['detalle'] }}@if($i['vence']) · {{ $i['vencido'] ? 'venció' : 'vence' }} el {{ $i['vence']->locale('es')->isoFormat('D MMM') }}@endif</span></span>
                <b class="num ms-auto">{{ $d($i['monto']) }}</b><i class="bi" :class="abierto ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
            </button>
            <div x-show="abierto" x-cloak class="cb-form">
                @if($i['tipo'] === 'proyecto')
                    <form method="post" action="{{ route('admin.proyectos.pago', [$i['modelo']->proyecto_id, $i['id']]) }}" class="row g-2 align-items-end">
                        @csrf @method('PATCH') <input type="hidden" name="accion" value="pagar">
                        <div class="col-sm-3"><label class="form-label small">Monto</label><input name="monto" type="number" step="0.01" min="0" value="{{ $i['monto'] }}" class="form-control"></div>
                        <div class="col-sm-3"><label class="form-label small">Fecha</label><input name="pagado_el" type="date" value="{{ $hoyF }}" max="{{ $hoyF }}" class="form-control"></div>
                        <div class="col-sm-3"><label class="form-label small">Cómo pagó</label><select name="metodo" class="form-select">@foreach(config('vandu.metodos_pago') as $k => $m)<option value="{{ $k }}">{{ $m }}</option>@endforeach</select></div>
                        <div class="col-sm-3"><button class="btn btn-primario w-100"><i class="bi bi-check2 me-1"></i> Registrar</button></div>
                    </form>
                @elseif($i['tipo'] === 'venta')
                    <form method="post" action="{{ route('admin.pedidos.abonar', $i['id']) }}" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-sm-3"><label class="form-label small">Monto</label><input name="monto" type="number" step="0.01" min="0.01" max="{{ $i['monto'] }}" value="{{ $i['monto'] }}" class="form-control" required></div>
                        <div class="col-sm-3"><label class="form-label small">Fecha</label><input name="fecha" type="date" value="{{ $hoyF }}" max="{{ $hoyF }}" class="form-control"></div>
                        <div class="col-sm-3"><label class="form-label small">Cómo pagó</label><select name="forma" class="form-select">@foreach(\App\Models\Venta::FORMAS as $k => $m)<option value="{{ $k }}">{{ $m }}</option>@endforeach</select></div>
                        <div class="col-sm-3"><button class="btn btn-primario w-100"><i class="bi bi-check2 me-1"></i> Registrar</button></div>
                        <div class="col-12 secundario small">Si te pagó una parte, cambia el monto: queda como abono.</div>
                    </form>
                @else
                    <p class="secundario small mb-2">Los créditos calculan intereses y moratorios por letra; el pago se registra en su ficha.</p>
                    <a href="{{ $i['url'] }}" class="btn btn-primario"><i class="bi bi-cash-coin me-1"></i> Registrar en cobranza</a>
                @endif
                <a href="{{ $i['url'] }}" class="secundario small d-inline-block mt-2 text-decoration-none">Ver {{ $i['tipo'] === 'proyecto' ? 'el proyecto' : ($i['tipo'] === 'venta' ? 'la venta' : 'el crédito') }} →</a>
            </div>
        </div>
    @empty
        <div class="panel"><div class="vacio"><div class="ico"><i class="bi bi-check2-circle"></i></div><h3>No te debe nada</h3><p>{{ $cliente->empresa ?: $cliente->nombre }} está al corriente.</p>
            <a href="{{ route('admin.clientes.show', $cliente) }}" class="btn btn-borde mt-2">Ver su ficha</a></div></div>
    @endforelse
@endif

<style>
    .cb-panel, .cb-item, .cb-total { max-width: 760px; }
    .cb-fila { display: flex; gap: 12px; align-items: center; padding: 13px 18px; border-top: 1px solid #F1F2F4; color: inherit; text-decoration: none; }
    a.cb-fila[href]:hover { background: #F8F9FA; }
    .cb-av { width: 36px; height: 36px; border-radius: 50%; background: var(--sunken); display: grid; place-items: center; font-weight: 600; flex: none; }
    .cb-t { display: grid; text-align: left; min-width: 0; } .cb-t b { font-size: 15px; font-weight: 600; } .cb-t > span { font-size: 13px; color: var(--muted); }
    .cb-total { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; margin-bottom: 14px; background: var(--ink); color: #fff; border-color: var(--ink); }
    .cb-total b { font-size: 24px; }
    .cb-item { margin-bottom: 10px; } .cb-item.vencido { border-left: 3px solid var(--red); }
    .cb-cab { display: flex; gap: 12px; align-items: center; width: 100%; padding: 14px 18px; background: none; border: 0; color: inherit; }
    .cb-form { padding: 4px 18px 16px; }
</style>
@endsection
