{{-- Para hoy: lo que hay que hacer, arriba de las cifras. Cada renglón lleva a donde se resuelve. --}}
@php
    $hoyLista = \App\Support\Inicio\Hoy::pendientes(auth()->user());
@endphp
<section class="panel hoy-panel">
    <div class="panel-head"><div><h2>Para hoy</h2><span class="ayuda">{{ $hoyLista ? 'Toca cada uno para resolverlo' : 'Nada urgente' }}</span></div></div>
    <div>
        @forelse($hoyLista as $h)
            <a href="{{ $h['url'] }}" class="hoy-fila {{ $h['color'] }}"><span class="ico"><i class="bi {{ $h['icono'] }}"></i></span>
                <span class="txt"><b>{{ $h['titulo'] }}</b><span>{{ $h['detalle'] }}</span></span><i class="bi bi-chevron-right"></i></a>
        @empty
            <div class="hoy-fila"><span class="ico" style="background: var(--green-soft); color: var(--green-ink)"><i class="bi bi-check2"></i></span>
                <span class="txt"><b>Todo al día</b><span>No hay entregas, cobros ni tareas pendientes para hoy.</span></span></div>
        @endforelse
    </div>
</section>
<style>
    .hoy-panel { margin-bottom: 20px; }
    .hoy-fila { display: flex; gap: 14px; align-items: center; padding: 13px 20px; border-top: 1px solid #F1F2F4; color: inherit; text-decoration: none; }
    .hoy-fila:first-child { border-top: 0; } a.hoy-fila:hover { background: #F8F9FA; }
    .hoy-fila .ico { width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; font-size: 17px; flex: none; background: var(--sunken); color: var(--text-2); }
    .hoy-fila.rojo .ico { background: var(--red-soft); color: var(--red); } .hoy-fila.ambar .ico { background: var(--amber-soft); color: var(--amber); }
    .hoy-fila .txt { display: grid; } .hoy-fila .txt b { font-weight: 600; font-size: 15px; } .hoy-fila .txt span { font-size: 13px; color: var(--muted); }
    .hoy-fila .bi-chevron-right { margin-left: auto; color: var(--muted); }
</style>
