@extends('admin.layout')
@section('titulo', 'Redes sociales')

@push('head')
<style>
    .rd-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 14px; }
    .rd-card { display: flex; flex-direction: column; gap: 12px; padding: 18px; text-decoration: none; color: var(--text); transition: border-color .12s, box-shadow .12s; }
    .rd-card:hover { border-color: var(--line-strong); box-shadow: 0 6px 18px rgba(15,18,25,.06); color: var(--text); }
    .rd-card .top { display: flex; align-items: center; gap: 12px; }
    .rd-card .top .principal { font-weight: 600; }
    .rd-barra { display: flex; height: 8px; border-radius: 99px; overflow: hidden; background: var(--sunken); }
    .rd-barra span { display: block; height: 100%; }
    .rd-ley { display: flex; flex-wrap: wrap; gap: 4px 12px; font-size: 12.5px; color: var(--muted); }
    .rd-ley i { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 4px; }
    .rd-alerta { font-size: 13px; color: #B45309; background: #FFF7E6; border-radius: 8px; padding: 6px 10px; }
</style>
@endpush

@section('contenido')
<div class="page-head">
    <div>
        <h1>Redes sociales</h1>
        <p class="sub">Planea el contenido de cada cliente, muéstrale cómo queda en cada red y consigue su aprobación.</p>
    </div>
    @if($disponibles->isNotEmpty())
        <form method="post" action="{{ route('admin.redes.activar') }}" class="d-flex gap-2">@csrf
            <select name="cliente_id" class="form-select" required aria-label="Cliente" style="min-width: 220px">
                <option value="">Agregar un cliente…</option>
                @foreach($disponibles as $d)<option value="{{ $d->id }}">{{ $d->empresa ?: $d->nombre }}</option>@endforeach
            </select>
            <button class="btn btn-primario text-nowrap"><i class="bi bi-plus-lg me-1"></i> Agregar</button>
        </form>
    @endif
</div>

@if($clientes->isEmpty())
    <div class="panel"><div class="vacio">
        <div class="ico"><i class="bi bi-grid-3x3-gap"></i></div>
        <h3>Todavía no manejas redes de ningún cliente</h3>
        <p>Elige un cliente arriba para crear su calendario de contenido.</p>
    </div></div>
@else
    <div class="rd-grid">
        @foreach($clientes as $c)
            <a href="{{ route('admin.redes.cliente', $c) }}" class="panel rd-card">
                <div class="top">
                    @include('admin._avatar', ['nombre' => $c->empresa ?: $c->nombre])
                    <div class="min-w-0">
                        <div class="principal text-truncate">{{ $c->empresa ?: $c->nombre }}</div>
                        <div class="secundario" style="font-size:13px">{{ $c->total_mes }} {{ $c->total_mes === 1 ? 'post' : 'posts' }} en {{ now(config('vandu.zona_horaria'))->locale('es')->isoFormat('MMMM') }}</div>
                    </div>
                </div>
                @if($c->total_mes)
                    <div class="rd-barra" aria-hidden="true">
                        @foreach(\App\Models\RedesPost::ESTADOS as $k => $e)
                            @if($c->resumen[$k])<span style="width: {{ $c->resumen[$k] / $c->total_mes * 100 }}%; background: {{ $e['color'] }}"></span>@endif
                        @endforeach
                    </div>
                    <div class="rd-ley">
                        @foreach(\App\Models\RedesPost::ESTADOS as $k => $e)
                            @if($c->resumen[$k])<span><i style="background: {{ $e['color'] }}"></i>{{ $c->resumen[$k] }} {{ mb_strtolower($e['texto']) }}</span>@endif
                        @endforeach
                    </div>
                @endif
                @if($c->comentarios_nuevos)<div class="rd-alerta"><i class="bi bi-chat-square-dots me-1"></i> {{ $c->comentarios_nuevos }} {{ $c->comentarios_nuevos === 1 ? 'post con cambios pedidos' : 'posts con cambios pedidos' }}</div>@endif
                <div class="secundario" style="font-size:13px">
                    @if($c->proximo)Próximo: {{ $c->proximo->fecha_local->locale('es')->isoFormat('ddd D MMM, H:mm') }} · {{ $c->proximo->titulo ?: \Illuminate\Support\Str::limit((string) $c->proximo->texto, 40) ?: 'Sin texto' }}
                    @else Sin publicaciones próximas @endif
                </div>
            </a>
        @endforeach
    </div>
@endif
@endsection
