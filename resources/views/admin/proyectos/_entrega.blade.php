{{-- Entrega o instalación (productos y servicios): la llena el cliente desde su enlace o el equipo aquí --}}
@php
    $envE = \App\Support\Ventas\Envios::ajustes();
    $centroE = $p->entrega_lat ? [$p->entrega_lat, $p->entrega_lng] : ($envE['lat'] ? [(float) $envE['lat'], (float) $envE['lng']] : null);
    $abrir = $errors->hasAny(['direccion', 'nombre', 'telefono']);
    $urlEntrega = $p->url_publica . '#entrega';
    $telE = preg_replace('/\D/', '', (string) $p->entrega_telefono);
@endphp
<section class="panel" id="entrega" x-data="{ editar: {{ $abrir ? 'true' : 'false' }} }">
    <div class="panel-head"><h2>Entrega o instalación</h2>
        @if($p->tiene_entrega)<span class="ayuda">{{ $p->entrega_datos_por === 'cliente' ? 'La mandó el cliente' : 'Capturada por el equipo' }} {{ $p->entrega_datos_at?->locale('es')->diffForHumans() }}</span>@endif</div>
    <div class="panel-body">
        @if($p->tiene_entrega)
            <div x-show="! editar">
                <div class="fw-semibold">{{ $p->entrega_nombre }}</div>
                @if($p->entrega_telefono)<a href="https://wa.me/{{ strlen($telE) === 10 ? '52' . $telE : $telE }}" target="_blank" rel="noopener" class="secundario small text-decoration-none"><i class="bi bi-whatsapp"></i> {{ $p->entrega_telefono }}</a>@endif
                <div class="mt-2">{{ $p->entrega_direccion }}</div>
                @if($p->entrega_referencias)<div class="secundario small">{{ $p->entrega_referencias }}</div>@endif
                @if($p->entrega_notas)<div class="small mt-2 p-2 rounded-3" style="background: var(--sunken); white-space: pre-line">{{ $p->entrega_notas }}</div>@endif
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <a href="{{ $p->entrega_mapa_url }}" target="_blank" rel="noopener" class="btn btn-borde btn-sm"><i class="bi bi-geo-alt me-1"></i> Google Maps</a>
                    @if($p->entrega_waze_url)<a href="{{ $p->entrega_waze_url }}" target="_blank" rel="noopener" class="btn btn-borde btn-sm"><i class="bi bi-sign-turn-right me-1"></i> Waze</a>@endif
                    <button type="button" class="btn btn-fantasma btn-sm" @click="editar = true">Corregir</button>
                </div>
                @unless($p->entrega_lat)<div class="secundario small mt-2"><i class="bi bi-info-circle"></i> Sin ubicación en el mapa; el enlace busca la dirección escrita.</div>@endunless
            </div>
        @else
            <div x-show="! editar">
                <p class="secundario mb-3">Aún no tienes la dirección. Pídesela al cliente: la llena desde el enlace de su proyecto y marca su ubicación en el mapa.</p>
                @if($wa)
                    <a class="btn btn-primario w-100" target="_blank" rel="noopener" href="https://wa.me/{{ $wa }}?text={{ rawurlencode(\App\Support\Proyectos\Entrega::mensaje($p)) }}"><i class="bi bi-whatsapp me-1"></i> Pedírsela por WhatsApp</a>
                @endif
                <button type="button" class="btn btn-borde w-100 mt-2" data-copiar="{{ $urlEntrega }}"><i class="bi bi-link-45deg me-1"></i> Copiar enlace para pedirla</button>
                <button type="button" class="btn btn-fantasma w-100 mt-2" @click="editar = true"><i class="bi bi-pencil me-1"></i> La capturo yo</button>
            </div>
        @endif

        <div x-show="editar" x-cloak>
            <form method="post" action="{{ route('admin.proyectos.entrega', $p) }}" class="d-grid gap-2">
                @csrf @method('PUT')
                <div><label class="form-label small" for="pe-nombre">Quién recibe</label>
                    <input id="pe-nombre" name="nombre" value="{{ old('nombre', $p->entrega_nombre ?: ($p->cliente?->nombre ?: $p->cliente?->empresa)) }}" maxlength="160" class="form-control" required></div>
                <div><label class="form-label small" for="pe-tel">Teléfono</label>
                    <input id="pe-tel" name="telefono" value="{{ old('telefono', $p->entrega_telefono ?: $p->cliente?->telefono) }}" maxlength="30" inputmode="tel" class="form-control" required></div>
                <div><label class="form-label small" for="pe-dir">Dirección</label>
                    <input id="pe-dir" name="direccion" value="{{ old('direccion', $p->entrega_direccion) }}" maxlength="300" class="form-control" placeholder="Calle, número, colonia" required></div>
                <div><label class="form-label small" for="pe-ref">Referencias</label>
                    <input id="pe-ref" name="referencias" value="{{ old('referencias', $p->entrega_referencias) }}" maxlength="300" class="form-control" placeholder="Portón negro, frente al parque…"></div>
                <div><label class="form-label small" for="pe-notas">Indicaciones</label>
                    <textarea id="pe-notas" name="notas" rows="2" maxlength="1000" class="form-control" placeholder="Horario para recibir, piso, acceso…">{{ old('notas', $p->entrega_notas) }}</textarea></div>
                <input type="hidden" name="lat" id="pe-lat" value="{{ old('lat', $p->entrega_lat) }}">
                <input type="hidden" name="lng" id="pe-lng" value="{{ old('lng', $p->entrega_lng) }}">
                @include('admin.pedidos._mapa', ['id' => 'pe', 'lat' => 'pe-lat', 'lng' => 'pe-lng', 'centro' => $centroE])
                <div class="d-flex gap-2 mt-1"><button class="btn btn-primario">Guardar</button><button type="button" class="btn btn-fantasma" @click="editar = false">Cancelar</button></div>
            </form>
        </div>
    </div>
</section>
