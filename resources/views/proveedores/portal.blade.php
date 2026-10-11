@extends('proveedores._layout')
@php
    /** @var \App\Models\Proveedor $p */
    $abiertas = $ordenes->filter(fn ($o) => ! in_array($o->estado, ['pagada', 'cancelada'], true));
    $cerradas = $ordenes->diff($abiertas);
    $sinFactura = $ordenes->filter(fn ($o) => in_array($o->estado, ['entregada', 'en_proceso', 'enviada'], true) && ! $o->archivos->contains('tipo', 'factura'));
@endphp
@section('titulo', $p->empresa)

@section('estilos')
    .ordenes a { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; gap: 4px 16px; align-items: center; padding: 15px 20px; text-decoration: none; }
    .ordenes a + a { border-top: 1px solid var(--line); }
    .ordenes a:hover { background: #FAFBFC; }
    .ordenes .n { font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ordenes .s { grid-column: 1; font-size: 13.5px; color: var(--muted); }
    .ordenes .s .tarde { color: var(--red); font-weight: 500; }
    .ordenes .monto { font-weight: 600; text-align: right; grid-row: 1 / span 2; grid-column: 2; }
    .ordenes .est { grid-row: 1 / span 2; grid-column: 3; text-align: right; display: grid; gap: 4px; justify-items: end; }
    .ordenes .ic { font-size: 12px; color: var(--muted); }
    details.term summary { cursor: pointer; list-style: none; color: var(--muted); font-weight: 500; margin: 26px 0 12px; }
    details.term summary::-webkit-details-marker { display: none; }
    @media (max-width: 640px) {
        .ordenes a { grid-template-columns: minmax(0, 1fr) auto; padding: 14px 16px; }
        .ordenes .monto { grid-row: 1; grid-column: 2; }
        .ordenes .est { grid-row: 2; grid-column: 2; }
    }
@endsection

@section('encabezado')
    <h1>Hola, {{ $p->primer_nombre }}</h1>
    <p class="sub">Estas son las órdenes de compra de {{ config('vandu.marca.nombre') }} para {{ $p->empresa }}. Aquí descargas cada una, nos subes tus facturas y archivos, y nos dejas comentarios. Guarda este enlace: siempre está al día.</p>
    <div class="resumen">
        <span><b>{{ $abiertas->count() }}</b> {{ $abiertas->count() === 1 ? 'orden abierta' : 'órdenes abiertas' }}</span>
        @if($sinFactura->count())<span><b>{{ $sinFactura->count() }}</b> sin factura</span>@endif
        <span><b>{{ $ordenes->count() }}</b> en total</span>
    </div>
@endsection

@section('contenido')
    @php
        $fila = function ($o) {
            $fact = $o->archivos->contains('tipo', 'factura');
            $nuevos = $o->comentarios->where('autor', 'agencia')->filter(fn ($m) => ! $o->vista_at || $m->created_at->gt($o->vista_at))->count();
            return view()->make('proveedores._fila', compact('o', 'fact', 'nuevos'))->render();
        };
    @endphp

    <h2>Órdenes abiertas @if($abiertas->count())<small class="num">{{ $abiertas->count() }}</small>@endif</h2>
    @if($abiertas->isEmpty())
        <div class="vacio">No tienes órdenes abiertas. Cuando te enviemos una nueva aparecerá aquí.</div>
    @else
        <div class="caja ordenes">@foreach($abiertas as $o) {!! $fila($o) !!} @endforeach</div>
    @endif

    @if($cerradas->isNotEmpty())
        <details class="term" @if($abiertas->isEmpty()) open @endif>
            <summary>Historial: {{ $cerradas->count() === 1 ? '1 orden pagada o cancelada' : $cerradas->count() . ' órdenes pagadas o canceladas' }} ▾</summary>
            <div class="caja ordenes">@foreach($cerradas as $o) {!! $fila($o) !!} @endforeach</div>
        </details>
    @endif
@endsection
