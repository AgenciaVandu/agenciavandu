@php $e = \App\Models\Tarea::ESTADOS[$t->estado]; @endphp
<a href="{{ route('admin.tareas.show', $t) }}" class="tr-item">
    <i class="bi {{ $e['icono'] }} ico" style="color: {{ $e['color'] }}" title="{{ $e['texto'] }}"></i>
    <span class="n text-truncate">{{ $t->titulo }}@if($t->urgente)<span class="etq-urg">Urgente</span>@endif</span>
    <span class="s">
        @if($t->donde)<span><i class="bi bi-building"></i> {{ $t->donde }}</span>@endif
        @if($t->archivos_count)<span><i class="bi bi-paperclip"></i> {{ $t->archivos_count }} {{ $t->archivos_count === 1 ? 'archivo' : 'archivos' }}</span>@endif
        @if($t->estado === 'terminada' && $t->terminada_at)<span>Terminada {{ $t->terminada_at->locale('es')->diffForHumans() }}</span>@endif
    </span>
    <span class="der">
        @if($t->cuando && $t->estado !== 'terminada')<span class="fecha {{ $t->vencida ? 'vencida' : '' }}"><i class="bi bi-calendar3"></i> {{ $t->cuando }}</span>@endif
        @if($gestiona)
            @if($t->responsable)
                <span class="d-inline-flex align-items-center gap-2" title="{{ $t->responsable->name }}">@include('admin._avatar', ['nombre' => $t->responsable->name])<span class="secundario d-none d-md-inline" style="font-size:13px">{{ $t->responsable->primer_nombre }}</span></span>
            @else
                <span class="secundario" style="font-size:13px">Sin asignar</span>
            @endif
        @endif
    </span>
</a>
