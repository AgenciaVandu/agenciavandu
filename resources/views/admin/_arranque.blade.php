{{-- Arranque guiado: primeros pasos del negocio y datos de ejemplo para probar --}}
@php
    $pasosA = \App\Support\Inicio\Arranque::pasos(auth()->user());
    $ejA = auth()->user()->puede('configuracion') ? \App\Support\Inicio\Arranque::ejemplos() : null;
    $hayEjA = $ejA ? (bool) ($ejA[1])() : false;
    // Ofrecer ejemplos solo mientras arranca (con pasos pendientes) o si ya los tiene cargados
    $ofrecerA = $ejA && ($hayEjA || $pasosA) && ! \App\Support\Cuentas::actual()?->tiene('fitness') && ! \App\Support\Cuentas::actual()?->tiene('lealtad'); // esos dos traen su propio aviso de ejemplos
    $hechosA = collect($pasosA)->where('hecho', true)->count();
@endphp
@if($pasosA || $ofrecerA)
<div class="panel arr">
    @if($pasosA)
        <div class="arr-pasos"><b class="me-1">Primeros pasos</b>
            <span class="avance"><span class="b"><span style="width: {{ round($hechosA / count($pasosA) * 100) }}%"></span></span>{{ $hechosA }} de {{ count($pasosA) }}</span>
            @foreach(collect($pasosA)->where('hecho', false) as $p)<a href="{{ $p['url'] }}" class="arr-paso"><i class="bi bi-circle"></i> {{ $p['texto'] }}</a>@endforeach
        </div>
    @endif
    @if($ofrecerA)
        <div class="arr-ej {{ $pasosA ? 'con-borde' : '' }}">
            @if($hayEjA)
                <span><i class="bi bi-flask me-1"></i> Tienes {{ $ejA[0] }} para practicar.</span>
                <form method="post" action="{{ route('admin.ejemplos.quitar') }}" onsubmit="return confirm({{ Js::from('¿Borrar los ' . $ejA[0] . '? Lo que tú capturaste no se toca.') }})">@csrf @method('DELETE')
                    <button class="btn btn-borde btn-sm"><i class="bi bi-trash3 me-1"></i> Borrar ejemplos</button></form>
            @else
                <span><i class="bi bi-flask me-1"></i> ¿Quieres probar antes de capturar lo tuyo? Carga {{ $ejA[0] }}.</span>
                <form method="post" action="{{ route('admin.ejemplos') }}">@csrf<button class="btn btn-borde btn-sm">Cargar ejemplos</button></form>
            @endif
        </div>
    @endif
</div>
<style>
    .arr { margin-bottom: 20px; }
    .arr-pasos { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 14px 18px; }
    .arr-paso { display: inline-flex; gap: 6px; align-items: center; padding: 6px 12px; border-radius: 99px; border: 1px solid var(--line); font-size: 13.5px; color: inherit; text-decoration: none; }
    .arr-paso:hover { border-color: var(--marca); }
    .arr .avance { display: inline-flex; align-items: center; gap: 8px; font-size: 13px; color: var(--muted); margin-right: 6px; }
    .arr .avance .b { width: 72px; height: 6px; border-radius: 3px; background: #EEF0F3; overflow: hidden; } .arr .avance .b span { display: block; height: 100%; background: #00A862; }
    .arr-ej { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; padding: 12px 18px; font-size: 14px; color: var(--text-2); }
    .arr-ej.con-borde { border-top: 1px solid var(--line); }
</style>
@endif
