@php
    /** @var \App\Models\Proyecto $p */
    $sueltos = $p->archivos->where('grupo', 'cliente')->whereNull('solicitud_id')->sortByDesc('id')->values();
    $abiertas = $p->solicitudes->where('estado', 'abierta');
    $recibidos = $p->archivos->where('grupo', 'cliente')->count();
    $nombreSol = \App\Support\Correos::primerNombre((string) $p->cliente?->nombre);
    $archivoFila = function ($a) use ($p) {
        return '<li><i class="bi ' . e($a->icono) . '"></i><a href="' . e(route('admin.proyectos.archivo.ver', [$p, $a])) . '" target="_blank" rel="noopener" class="nombre">' . e($a->nombre) . '</a>'
            . '<span class="peso num">' . e($a->peso_texto) . ' · ' . e($a->created_at->locale('es')->diffForHumans()) . '</span>'
            . '<form method="post" action="' . e(route('admin.proyectos.archivo.borrar', [$p, $a])) . '" onsubmit="return confirm(\'¿Eliminar este archivo?' . ($a->en_dropbox ? ' Se va a la papelera de Dropbox.' : '') . '\')">' . csrf_field() . method_field('delete')
            . '<button class="btn btn-fantasma btn-sm text-danger" title="Eliminar" aria-label="Eliminar ' . e($a->nombre) . '"><i class="bi bi-trash"></i></button></form></li>';
    };
@endphp
<section class="panel" id="archivos-cliente">
    <div class="panel-head">
        <div><h2>Archivos del cliente</h2>
            <span class="ayuda num">{{ $abiertas->count() ? $abiertas->count() . ' ' . ($abiertas->count() === 1 ? 'solicitud abierta' : 'solicitudes abiertas') . ' · ' : '' }}{{ $recibidos }} {{ $recibidos === 1 ? 'archivo recibido' : 'archivos recibidos' }}</span></div>
        @if($dbx)<span class="ayuda d-none d-md-inline"><i class="bi bi-dropbox me-1" style="color:#0061FE"></i> Llegan a “Del cliente”</span>@endif
    </div>
    <div class="panel-body d-grid gap-3">
        <p class="secundario mb-0" style="font-size:13.5px">Pídele al cliente lo que necesitas (logotipo, fotos, audios, textos…). Desde la página de su proyecto puede subir lo que sea: ZIP, video, audio, imágenes y editables de Adobe o Figma.</p>

        @foreach($p->solicitudes as $s)
            <div class="sol {{ $s->abierta ? '' : 'cerrada' }}" x-data="{ editar: {{ $errors->any() && old('_solicitud') == $s->id ? 'true' : 'false' }} }">
                <div class="sol-top">
                    <div class="min-w-0">
                        <div class="sol-titulo">{{ $s->titulo }}
                            @if(! $s->abierta)<span class="sol-pill ok"><i class="bi bi-check2"></i> Recibida</span>
                            @elseif($s->archivos->isNotEmpty())<span class="sol-pill azul">Llegaron {{ $s->archivos->count() }}</span>
                            @elseif($s->vencida)<span class="sol-pill alerta">Venció el {{ $s->fecha_texto }}</span>
                            @else<span class="sol-pill pend">Esperando</span>@endif
                        </div>
                        <div class="sol-meta">@if($s->fecha_limite)Para el {{ $s->fecha_texto }} · @endif Pedida {{ $s->created_at->locale('es')->diffForHumans() }}</div>
                        @if($s->instrucciones)<p class="sol-inst">{{ $s->instrucciones }}</p>@endif
                    </div>
                    <div class="d-flex gap-1 flex-wrap justify-content-end">
                        @if($s->abierta && $wa)
                            <a class="btn btn-borde btn-sm" target="_blank" rel="noopener" title="Pedírselo por WhatsApp"
                               href="https://wa.me/{{ $wa }}?text={{ rawurlencode("Hola {$nombreSol}, para avanzar con {$p->nombre} necesitamos: {$s->titulo}" . ($s->fecha_limite ? " (de preferencia antes del {$s->fecha_texto})" : '') . ". Puedes subir los archivos aquí: {$p->url_publica}#enviar-archivos") }}"><i class="bi bi-whatsapp"></i></a>
                        @endif
                        <form method="post" action="{{ route('admin.proyectos.solicitudes.estado', [$p, $s]) }}">@csrf @method('patch')
                            <button class="btn btn-sm {{ $s->abierta ? 'btn-borde' : 'btn-fantasma' }}">{!! $s->abierta ? '<i class="bi bi-check2 me-1"></i> Ya la tengo' : 'Reabrir' !!}</button>
                        </form>
                        <div class="dropdown">
                            <button class="btn btn-fantasma btn-sm" data-bs-toggle="dropdown" aria-label="Más opciones"><i class="bi bi-three-dots"></i></button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><button type="button" class="dropdown-item" @click="editar = ! editar"><i class="bi bi-pencil me-2"></i>Editar</button></li>
                                <li><form method="post" action="{{ route('admin.proyectos.solicitudes.destroy', [$p, $s]) }}" onsubmit="return confirm('¿Eliminar la solicitud? Los archivos que ya envió se quedan.')">@csrf @method('delete')
                                    <button class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Eliminar</button></form></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <form method="post" action="{{ route('admin.proyectos.solicitudes.update', [$p, $s]) }}" class="sol-form" x-show="editar" x-cloak>
                    @csrf @method('put') <input type="hidden" name="_solicitud" value="{{ $s->id }}">
                    @include('admin.proyectos._solicitud_campos', ['s' => $s, 'pre' => 'sol' . $s->id])
                    <div class="d-flex gap-2"><button class="btn btn-primario btn-sm">Guardar</button><button type="button" class="btn btn-fantasma btn-sm" @click="editar = false">Cancelar</button></div>
                </form>
                @if($s->archivos->isNotEmpty())
                    <ul class="docs sol-archivos">@foreach($s->archivos as $a) {!! $archivoFila($a) !!} @endforeach</ul>
                @endif
            </div>
        @endforeach

        @if($sueltos->isNotEmpty())
            <div class="sol">
                <div class="sol-titulo">Otros archivos que te envió</div>
                <ul class="docs sol-archivos">@foreach($sueltos as $a) {!! $archivoFila($a) !!} @endforeach</ul>
            </div>
        @endif

        <details class="sol-nueva" @if($errors->any() && old('_solicitud') === 'nueva') open @endif>
            <summary class="btn btn-borde btn-sm"><i class="bi bi-plus-lg me-1"></i> Pedir archivos al cliente</summary>
            <form method="post" action="{{ route('admin.proyectos.solicitudes.store', $p) }}" class="sol-form">
                @csrf <input type="hidden" name="_solicitud" value="nueva">
                @include('admin.proyectos._solicitud_campos', ['s' => null, 'pre' => 'solnueva'])
                <button class="btn btn-primario btn-sm">Crear solicitud</button>
                <span class="secundario" style="font-size:13px">Después mándale el enlace del proyecto por WhatsApp o correo.</span>
            </form>
        </details>

        @if($abiertas->isNotEmpty())
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-fantasma btn-sm" data-correo="solicitud_archivos"><i class="bi bi-envelope me-1"></i> Pedírselos por correo</button>
                <button type="button" class="btn btn-fantasma btn-sm" data-copiar="{{ $p->url_publica }}#enviar-archivos"><i class="bi bi-link-45deg me-1"></i> Copiar enlace para subir</button>
            </div>
        @endif
    </div>
</section>
