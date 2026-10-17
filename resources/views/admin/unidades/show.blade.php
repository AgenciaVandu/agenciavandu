@extends('admin.layout')
@section('titulo', $u->nombre)

@php
    $estimada = $u->mensualidad_estimada;
    $venta = $u->venta;
    $waTexto = rawurlencode($u->nombre . ' · $' . number_format($u->precio, 0) . "\n" . $enlace);
@endphp

@push('head')
<style>
    .ficha-u { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 20px; align-items: start; }
    .galeria { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; }
    .galeria a { display: block; aspect-ratio: 4 / 3; border-radius: 10px; overflow: hidden; background: var(--sunken); }
    .galeria a:first-child { grid-column: span 4; aspect-ratio: 16 / 9; }
    .galeria img { width: 100%; height: 100%; object-fit: cover; }
    .datos-u { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px 18px; }
    .datos-u .k { font-size: 12.5px; color: var(--muted); }
    .datos-u .v { font-weight: 500; }
    .lista-ficha { columns: 2; margin: 0; padding-left: 18px; }
    .precio-u { font-size: 30px; font-weight: 650; letter-spacing: -.01em; }
    .enlace-caja { display: flex; gap: 6px; }
    .enlace-caja input { font-size: 13px; background: var(--sunken); }
    @media (max-width: 1199.98px) { .ficha-u { grid-template-columns: minmax(0, 1fr); } }
    @media (max-width: 575.98px) { .datos-u { grid-template-columns: repeat(2, minmax(0, 1fr)); } .lista-ficha { columns: 1; } }
</style>
@endpush

@section('contenido')
<div class="migas"><a href="{{ route('admin.unidades.index') }}">Unidades</a> <i class="bi bi-chevron-right small"></i> <span>{{ $u->nombre }}</span></div>
<div class="page-head">
    <div>
        <h1>{{ $u->marca }} {{ $u->modelo }} {{ $u->anio }}</h1>
        <p class="sub mb-0"><span class="estado estado-{{ \App\Models\Unidad::ESTADOS[$u->estado]['clase'] }}">{{ \App\Models\Unidad::ESTADOS[$u->estado]['texto'] }}</span>
            {{ collect([$u->version, $u->placas, $u->sucursal?->nombre])->filter()->join(' · ') }}</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        @if($u->publico && $u->estado !== 'vendido')<a href="{{ $enlace }}" target="_blank" rel="noopener" class="btn btn-borde"><i class="bi bi-box-arrow-up-right me-1"></i> Ver su página</a>@endif
        @if(auth()->user()->puede('unidades'))<a href="{{ route('admin.unidades.edit', $u) }}" class="btn btn-borde"><i class="bi bi-pencil me-1"></i> Editar</a>@endif
        @if($puedeVender && ! $venta)<a href="{{ route('admin.creditos.create', $u) }}" class="btn btn-primario"><i class="bi bi-bag-check me-1"></i> Vender</a>@endif
    </div>
</div>

<div class="ficha-u">
    <div class="d-grid gap-4">
        <section class="panel panel-body">
            @if($u->fotos->isEmpty())
                <div class="vacio py-4"><div class="ico"><i class="bi bi-camera"></i></div><h3>Sin fotos</h3><p>Agrégale fotos al editarla: es lo que más vende.</p></div>
            @else
                <div class="galeria">
                    @foreach($u->fotos as $f)<a href="{{ $f->url }}" target="_blank" rel="noopener"><img src="{{ $loop->first ? $f->url : $f->miniatura_url }}" alt="Foto {{ $loop->iteration }}" loading="lazy"></a>@endforeach
                </div>
            @endif
        </section>

        <section class="panel panel-body">
            <h2 class="fs-6 fw-semibold mb-3">Datos</h2>
            <div class="datos-u">
                @foreach(['Marca' => $u->marca, 'Modelo' => $u->modelo, 'Versión' => $u->version, 'Año' => $u->anio, 'Tipo' => $u->tipo, 'Color' => $u->color,
                          'Kilometraje' => $u->kilometraje !== null ? number_format($u->kilometraje) . ' km' : null, 'Transmisión' => $u->transmision, 'Combustible' => $u->combustible,
                          'Placas' => $u->placas, 'No. de serie' => $u->serie, 'Vendedor' => $u->vendedor?->name] as $k => $v)
                    @if($v)<div><div class="k">{{ $k }}</div><div class="v">{{ $v }}</div></div>@endif
                @endforeach
            </div>
            @if($u->descripcion)<p class="mt-3 mb-0" style="white-space: pre-line">{{ $u->descripcion }}</p>@endif
            @if($u->ficha_lineas)
                <h3 class="fs-6 fw-semibold mt-4 mb-2">Ficha técnica</h3>
                <ul class="lista-ficha">@foreach($u->ficha_lineas as $l)<li>{{ $l }}</li>@endforeach</ul>
            @endif
        </section>
    </div>

    <div class="d-grid gap-4">
        <section class="panel panel-body">
            <div class="secundario small">Precio</div>
            <div class="precio-u num">${{ number_format($u->precio, 2) }}</div>
            @if($estimada && $u->estado !== 'vendido')<div class="secundario small">Desde ${{ number_format($estimada['mensualidad'], 0) }} al mes a {{ $estimada['plazo'] }} meses, con ${{ number_format($estimada['enganche'], 0) }} de enganche</div>@endif
            @if($veCosto && $u->costo !== null)<div class="secundario small mt-2">Costo ${{ number_format($u->costo, 0) }} · utilidad ${{ number_format($u->precio - $u->costo, 0) }}</div>@endif
        </section>

        @if($venta)
            <section class="panel panel-body">
                <h2 class="fs-6 fw-semibold mb-1">Vendida</h2>
                <p class="secundario small mb-2">{{ \App\Models\Credito::TIPOS[$venta->tipo] }} · {{ $venta->fecha->locale('es')->isoFormat('D MMM YYYY') }} · {{ $venta->cliente?->nombre }}</p>
                @if($puedeVender)<a href="{{ route('admin.creditos.show', $venta) }}" class="btn btn-borde btn-sm">Ver venta{{ $venta->tipo === 'credito' ? ' y cobranza' : '' }}</a>@endif
            </section>
        @elseif($u->publico)
            <section class="panel panel-body">
                <h2 class="fs-6 fw-semibold mb-1">Compartir</h2>
                <p class="small text-secondary mb-3">Con tu enlace, el WhatsApp de la página te escribe a ti.</p>
                <div class="enlace-caja mb-2">
                    <input class="form-control num" value="{{ $enlace }}" readonly aria-label="Enlace de la unidad" onfocus="this.select()">
                    <button type="button" class="btn btn-borde text-nowrap" data-copiar="{{ $enlace }}"><i class="bi bi-clipboard"></i> Copiar</button>
                </div>
                <a class="btn btn-borde w-100" href="https://wa.me/?text={{ $waTexto }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp me-1"></i> Mandar por WhatsApp</a>
            </section>
        @endif
    </div>
</div>
@endsection
