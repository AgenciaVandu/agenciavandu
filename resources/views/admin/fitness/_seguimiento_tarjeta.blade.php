{{-- Tarjeta de una persona en el seguimiento. $c = Cliente con etapa_seguimiento, detalle y siguiente (tarea) --}}
@php
    $det = $c->detalle;
    $paq = match ($det['paquete']) { 'prueba' => $paquetes['prueba'], 'pagado' => $paquetes['pagado'], default => $det['paquete'] };
    $ficha = route('admin.clientes.show', array_filter([$c, 'ver' => 'atleta', 'paquete' => $paq]));
    $t = $c->siguiente;
    $tz = config('vandu.zona_horaria');
@endphp
<article class="seg-t" x-data="{ mas: false }">
    <a href="{{ route('admin.clientes.show', [$c, 'ver' => 'atleta']) }}" class="quien">
        <x-fitness.avatar :atleta="$c" />
        <div class="min-w-0"><b class="d-block">{{ $c->nombre }}</b>
            @if($c->telefono)<span class="secundario small num text-nowrap">{{ $c->telefono }}</span>@endif</div>
    </a>
    <div class="linea {{ $det['tono'] }}">{{ $det['linea'] }}</div>
    @if($det['nota'])<div class="nota">{{ $det['nota'] }}</div>@endif
    @if($t)
        <div class="tarea {{ $t->vencida ? 'vencida' : '' }}"><i class="bi bi-alarm"></i> {{ $t->titulo }}@if($t->cuando) · {{ $t->cuando }}@endif</div>
    @endif

    <div class="acciones">
            @if($c->whatsapp)
                <a href="https://wa.me/{{ $c->whatsapp }}?text={{ rawurlencode($det['whatsapp']) }}" target="_blank" rel="noopener" class="btn btn-borde wa" title="Escribirle por WhatsApp"><i class="bi bi-whatsapp"></i></a>
            @endif
            @if(! empty($det['agenda']))
                <a href="{{ route('admin.clases.index') }}" class="btn btn-borde" title="Elige una clase en la agenda y agrégalo">{{ $det['accion'] }}</a>
            @elseif($det['accion'] && $gestiona)
                <a href="{{ $ficha }}{{ $det['paquete'] ? '#asignar' : '' }}" class="btn btn-borde" @if($det['paquete']) data-recargar @endif>{{ $det['accion'] }}</a>
            @endif
    </div>
    <button type="button" class="mas" @click="mas = !mas" :aria-expanded="mas" title="Recordatorio o descartar"><i class="bi bi-three-dots"></i></button>
    <div class="panel-mas" x-show="mas" x-cloak>
            <form method="post" action="{{ route('admin.seguimiento.tarea', $c) }}" class="d-grid gap-2">
                @csrf
                <div class="t">Recordarme escribirle</div>
                <input name="titulo" class="form-control" value="{{ $c->etapa_seguimiento === 'renovar' || $c->etapa_seguimiento === 'vencido' ? 'Ofrecer renovación a ' : 'Dar seguimiento a ' }}{{ $c->primer_nombre }}" required maxlength="160" aria-label="Qué hay que hacer">
                <div class="cuando">
                    <input name="fecha_limite" type="date" class="form-control num" value="{{ now($tz)->addDay()->toDateString() }}" required aria-label="Día">
                    <input name="hora" type="time" class="form-control num" aria-label="Hora">
                </div>
                @if($equipo->count() > 1)
                    <select name="asignada_a" class="form-select" aria-label="Quién">
                        @foreach($equipo as $m)<option value="{{ $m->id }}" @selected($m->id === auth()->id())>{{ $m->name }}</option>@endforeach</select>
                @endif
                <button class="btn btn-borde btn-sm"><i class="bi bi-alarm me-1"></i> Agendar</button>
            </form>
            @if(in_array($c->etapa_seguimiento, ['interesado', 'prueba', 'vencido'], true))
                <form method="post" action="{{ route('admin.seguimiento.descartar', $c) }}" class="d-flex gap-2">
                    @csrf
                    <select name="motivo" class="form-select" required aria-label="Motivo">
                        <option value="">Descartar porque…</option>
                        @foreach(\App\Support\Fitness\Embudo::MOTIVOS as $mo)<option>{{ $mo }}</option>@endforeach</select>
                    <button class="btn btn-borde btn-sm text-danger" title="Descartar"><i class="bi bi-archive"></i></button>
                </form>
            @endif
    </div>
</article>
