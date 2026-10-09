{{-- Historial de correos. Uso: @include('admin.correos._historial', ['correos' => $coleccion, 'plantilla' => 'bienvenida']) --}}
<section class="panel">
    <div class="panel-head"><h2>Correos</h2>
        <button type="button" class="btn btn-borde btn-sm" data-correo="{{ $plantilla ?? '' }}"><i class="bi bi-envelope me-1"></i> Nuevo</button>
    </div>
    @if($correos->isEmpty())
        <div class="panel-body secundario" style="font-size:14px">Aún no le has enviado correos desde el panel.</div>
    @else
        <ul class="list-unstyled m-0 correos-lista">
            @foreach($correos as $c)
                <li class="{{ $loop->first ? '' : 'border-top' }}">
                    <details>
                        <summary class="d-flex gap-2 px-3 py-2 align-items-start">
                            <i class="bi {{ $c->enviado ? 'bi-check-circle-fill text-success' : 'bi-exclamation-circle-fill text-danger' }} mt-1" title="{{ $c->enviado ? 'Enviado' : 'No se envió' }}"></i>
                            <span class="flex-grow-1" style="min-width:0">
                                <span class="d-block text-truncate fw-medium" style="font-size:14px">{{ $c->asunto }}</span>
                                <span class="d-block secundario text-truncate" style="font-size:12.5px">{{ $c->plantilla_nombre }} · {{ $c->created_at->timezone(config('vandu.zona_horaria'))->locale('es')->isoFormat('D MMM, H:mm') }} · {{ $c->para }}</span>
                            </span>
                        </summary>
                        <div class="px-3 pb-3" style="font-size:13.5px">
                            @unless($c->enviado)<div class="text-danger mb-2"><i class="bi bi-exclamation-triangle me-1"></i>{{ \Illuminate\Support\Str::limit($c->error, 220) }}</div>@endunless
                            @if($c->cc)<div class="secundario">CC: {{ $c->cc }}</div>@endif
                            @if($c->adjuntos)<div class="secundario"><i class="bi bi-paperclip"></i> {{ implode(', ', $c->adjuntos) }}</div>@endif
                            <div class="mt-2" style="white-space: pre-line; color: var(--text-2, #3f4450)">{{ $c->cuerpo }}</div>
                        </div>
                    </details>
                </li>
            @endforeach
        </ul>
    @endif
</section>
