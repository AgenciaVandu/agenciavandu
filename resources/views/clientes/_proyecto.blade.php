@php
    $mini = $fotos->get($pr->id, collect());
    $mini = $mini->count() >= 4 ? $mini->take(4) : $mini->take(1);
    $total = (int) ($enGaleria[$pr->id] ?? 0);
    $pendientes = $pr->pagos->whereNull('pagado_el');
    $estado = match ($pr->estado) { 'terminado' => ['Terminado', 'ok'], 'pausado' => ['En pausa', 'gris'], default => ['En curso', 'curso'] };
@endphp
<article class="pry {{ $mini->isEmpty() ? 'sin-fotos' : '' }}">
    <div class="info">
        <div class="tipo"><span class="pill {{ $estado[1] }}">{{ $estado[0] }}</span> {{ $pr->tipo_nombre }}</div>
        <h3>{{ $pr->nombre }}</h3>
        @if($pr->etapas->isNotEmpty())
            <div class="avance">
                <span class="sig">@if($pr->siguiente_paso)Sigue: <b>{{ $pr->siguiente_paso }}</b>@endif</span>
                <span class="pct num">{{ $pr->progreso }}%</span>
                <div class="barra"><span style="width: {{ $pr->progreso }}%"></span></div>
            </div>
        @endif
        @if($pendientes->isNotEmpty() && $pr->estado !== 'terminado')
            <span class="sig">Pago pendiente: <b class="num">{{ $dinero($pendientes->sum('monto')) }}</b></span>
        @endif
        <div class="acc">
            <a class="btn prim" href="{{ $pr->url_publica }}">Ver avance</a>
            @if($total)
                <a class="btn" href="{{ $pr->url_entrega }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg> Ver entrega · {{ $total }}</a>
            @endif
        </div>
    </div>
    @if($mini->isNotEmpty())
        <a class="fotos {{ $mini->count() === 1 ? 'una' : '' }}" href="{{ $pr->url_entrega }}" aria-label="Ver la entrega de {{ $pr->nombre }}">
            @foreach($mini as $a)<img src="{{ $miniatura($a) }}" alt="" loading="lazy" decoding="async">@endforeach
            @if($total > $mini->count())<span class="mas">+{{ $total - $mini->count() }}</span>@endif
        </a>
    @endif
</article>
