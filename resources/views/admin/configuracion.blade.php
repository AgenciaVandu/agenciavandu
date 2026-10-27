@extends('admin.layout')
@section('titulo', 'Configuración')

@push('head')
<style>
    .cf-grupo { margin-bottom: 26px; }
    .cf-grupo h2 { font-size: 12.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); margin: 0 0 10px; }
    .cf-tarjetas { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 12px; }
    .cf-t { display: flex; gap: 14px; align-items: flex-start; padding: 16px 18px; text-decoration: none; color: inherit; }
    .cf-t:hover { border-color: var(--line-strong); }
    .cf-t .ico { width: 40px; height: 40px; border-radius: 11px; background: var(--sunken); display: grid; place-items: center; font-size: 19px; flex: none; color: var(--text-2); }
    .cf-t b { display: block; font-size: 15px; } .cf-t span { font-size: 13px; color: var(--muted); }
    .cf-t .est { display: inline-block; margin-top: 4px; font-size: 12px; font-weight: 500; }
    .cf-t .est.ok { color: var(--green-ink); } .cf-t .est.no { color: var(--red); }
</style>
@endpush

@section('contenido')
<div class="page-head">
    <div>
        <h1>Configuración</h1>
        <p class="sub">Todo lo que ajustas de tu negocio, en un solo lugar.</p>
    </div>
</div>

@foreach($grupos as $titulo => $tarjetas)
    <section class="cf-grupo">
        <h2>{{ $titulo }}</h2>
        <div class="cf-tarjetas">
            @foreach($tarjetas as $t)
                <a href="{{ $t['url'] }}" class="panel cf-t">
                    <span class="ico"><i class="bi {{ $t['icono'] }}"></i></span>
                    <span><b>{{ $t['titulo'] }}</b><span>{{ $t['ayuda'] }}</span>
                        @if($t['estado'])<span class="est {{ $t['estado'] === 'Conectado' ? 'ok' : 'no' }}">● {{ $t['estado'] }}</span>@endif</span>
                </a>
            @endforeach
        </div>
    </section>
@endforeach
@endsection
