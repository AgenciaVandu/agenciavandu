@php
    $url = route('admin.ordenes.archivo', [$o, $a]);
    $deProv = $a->autor === 'proveedor';
@endphp
<div class="archivo">
    <a href="{{ $url }}" target="_blank" rel="noopener" class="ic {{ $a->tipo === 'factura' ? 'fact' : ($deProv ? 'prov' : '') }} text-decoration-none" aria-hidden="true" tabindex="-1"><i class="bi {{ $a->icono }}"></i></a>
    <div style="min-width: 0">
        <a href="{{ $url }}" target="_blank" rel="noopener" class="n d-block text-reset text-decoration-none" title="{{ $a->nombre }}">{{ $a->nombre }}</a>
        <div class="s">{{ $a->tipo_texto }} · {{ $a->extension }} · {{ $a->peso_texto }} · {{ $deProv ? 'lo subió ' . $o->proveedor->empresa : 'lo subió ' . ($a->user?->primer_nombre ?? $a->user?->name ?? 'tu equipo') }} {{ $a->created_at->locale('es')->diffForHumans() }}</div>
    </div>
    <div class="d-flex gap-1">
        <a href="{{ $url }}?descargar=1" class="btn btn-fantasma btn-icono" title="Descargar" aria-label="Descargar {{ $a->nombre }}"><i class="bi bi-download"></i></a>
        <form method="post" action="{{ route('admin.ordenes.archivo.borrar', [$o, $a]) }}" onsubmit="return confirm({{ Js::from('¿Quitar ' . $a->nombre . '?') }})">
            @csrf @method('delete')
            <button class="btn btn-fantasma btn-icono" title="Quitar" aria-label="Quitar {{ $a->nombre }}"><i class="bi bi-trash"></i></button>
        </form>
    </div>
</div>
