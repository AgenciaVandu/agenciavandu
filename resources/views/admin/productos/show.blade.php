@extends('admin.layout')
@section('titulo', $producto->nombre)

@php
    $p = $producto;
    $estadoExis = ! $p->controla_existencia ? '' : ($p->agotado ? 'mal' : ($p->bajo_minimo ? 'alerta' : ''));
    $waTexto = rawurlencode($p->nombre . ($p->clave ? ' (' . $p->clave . ')' : '') . "\n" . $enlace);
    $fecha = fn ($d) => $d->copy()->setTimezone(config('vandu.zona_horaria'))->locale('es')->isoFormat('D MMM YYYY, h:mm a');
@endphp

@push('head')
<style>
    .ficha { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 20px; align-items: start; }
    .cabecera { display: flex; align-items: center; gap: 16px; min-width: 0; }
    .cabecera .foto { width: 72px; height: 72px; border-radius: 14px; flex: none; background: var(--sunken); border: 1px solid var(--line); display: grid; place-items: center; color: var(--faint); font-size: 26px; overflow: hidden; }
    .cabecera .foto img { width: 100%; height: 100%; object-fit: cover; }
    .chip { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; padding: 2px 10px; border-radius: 99px; background: var(--surface); border: 1px solid var(--line); }
    .mini { display: grid; grid-template-columns: repeat(4, 1fr); }
    .mini > div { padding: 16px 20px; min-width: 0; }
    .mini > div + div { border-left: 1px solid var(--line); }
    .mini .v { font-size: 22px; font-weight: 600; letter-spacing: -.01em; }
    .mini .v.alerta { color: var(--amber); } .mini .v.mal { color: var(--red); }
    .mini .k { font-size: 13px; color: var(--muted); }
    .mini .s { font-size: 12.5px; color: var(--muted); }
    .mov-tipos { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
    .mov-tipos label { margin: 0; }
    .mov-tipos input { position: absolute; opacity: 0; pointer-events: none; }
    .mov-tipos span { display: flex; flex-direction: column; align-items: center; gap: 2px; padding: 10px 6px; border: 1px solid var(--line); border-radius: 10px; font-size: 13px; cursor: pointer; text-align: center; background: var(--surface); }
    .mov-tipos span i { font-size: 18px; }
    .mov-tipos input:checked + span { border-color: var(--ink); box-shadow: inset 0 0 0 1px var(--ink); font-weight: 500; }
    .mov-tipos input:focus-visible + span { outline: 2px solid var(--blue); outline-offset: 2px; }
    .historial li { display: grid; grid-template-columns: 32px minmax(0, 1fr) auto; gap: 12px; padding: 12px 20px; border-top: 1px solid var(--line); align-items: center; }
    .historial li:first-child { border-top: 0; }
    .historial .ico { width: 32px; height: 32px; border-radius: 9px; background: var(--sunken); display: grid; place-items: center; color: var(--text-2); }
    .historial .cant { font-weight: 600; text-align: right; white-space: nowrap; }
    .historial .cant.mas { color: var(--green-ink); } .historial .cant.menos { color: var(--red); }
    .historial .det { font-size: 13px; color: var(--muted); }
    .enlace-caja { display: flex; gap: 6px; }
    .enlace-caja input { font-size: 13px; background: var(--sunken); }
    .qr { display: grid; place-items: center; padding: 12px; background: #fff; border: 1px solid var(--line); border-radius: 12px; }
    .qr canvas, .qr img { width: 168px !important; height: 168px !important; }
    .descripcion { white-space: pre-line; color: var(--text-2); font-size: 14.5px; }
    @media (max-width: 1199.98px) { .ficha { grid-template-columns: minmax(0, 1fr); } }
    @media (max-width: 767.98px) { .mini { grid-template-columns: repeat(2, 1fr); } .mini > div:nth-child(3) { border-left: 0; } .mini > div:nth-child(n+3) { border-top: 1px solid var(--line); } }
</style>
@endpush

@section('contenido')
<div class="migas"><a href="{{ route('admin.productos.index') }}">Productos</a> <i class="bi bi-chevron-right small"></i> <span>{{ $p->nombre }}</span></div>
<div class="page-head">
    <div class="cabecera">
        <span class="foto">@if($p->miniatura_url)<img src="{{ $p->miniatura_url }}" alt="">@else<i class="bi bi-image"></i>@endif</span>
        <div style="min-width: 0">
            <h1 class="text-break">{{ $p->nombre }}</h1>
            <p class="sub mb-0">
                @if($p->categoria)<span class="chip">{{ $p->categoria->nombre }}</span> @endif
                @if($p->clave)<span class="num">{{ $p->clave }}</span> · @endif
                por {{ $p->unidad }}
                @unless($p->activo) · <span class="estado estado-cancelada">Desactivado</span>@endunless
            </p>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        @if($p->publico && $p->activo)
            <a href="{{ $enlace }}" target="_blank" rel="noopener" class="btn btn-borde"><i class="bi bi-box-arrow-up-right me-1"></i> Ver su página</a>
        @endif
        @if($admin)
            <a href="{{ route('admin.productos.edit', $p) }}" class="btn btn-primario"><i class="bi bi-pencil me-1"></i> Editar</a>
        @endif
    </div>
</div>

<div class="ficha">
    <div class="d-grid gap-4">
        <div class="panel mini">
            <div><div class="k">Existencia</div><div class="v num {{ $estadoExis }}">{{ $p->controla_existencia ? \App\Models\Producto::numero($p->existencia) : '—' }}</div>
                <div class="s">{{ $p->controla_existencia ? ($p->minimo !== null ? 'mínimo ' . \App\Models\Producto::numero($p->minimo) : $p->unidadPara($p->existencia)) : 'No lleva inventario' }}</div></div>
            <div><div class="k">En cotizaciones abiertas</div><div class="v num">{{ \App\Models\Producto::numero($enCotizaciones) }}</div>
                <div class="s">enviadas o en negociación</div></div>
            <div><div class="k">Precio</div><div class="v num">${{ number_format($p->precio, 2) }}</div><div class="s">antes de IVA</div></div>
            @if($admin)
                <div><div class="k">Costo</div><div class="v num">{{ $p->costo !== null ? '$' . number_format($p->costo, 2) : '—' }}</div>
                    <div class="s">{{ $p->margen !== null ? 'margen ' . $p->margen . '%' : 'sin costo capturado' }}</div></div>
            @else
                <div><div class="k">Disponible</div><div class="v num">{{ $p->controla_existencia ? \App\Models\Producto::numero(max(0, $p->existencia)) : 'Sí' }}</div><div class="s">para vender hoy</div></div>
            @endif
        </div>

        @if($p->descripcion)
            <section class="panel panel-body"><h2 class="fs-6 fw-semibold mb-2">Descripción</h2><div class="descripcion">{{ $p->descripcion }}</div></section>
        @endif

        <section class="panel">
            <div class="panel-head"><h2>Movimientos</h2><span class="ayuda">{{ $p->controla_existencia ? 'Cada entrada y salida, con quién la hizo' : 'Este producto no lleva inventario' }}</span></div>
            @if($movimientos->isEmpty())
                <div class="vacio">
                    <div class="ico"><i class="bi bi-arrow-left-right"></i></div>
                    <h3>Sin movimientos todavía</h3>
                    <p>{{ $p->controla_existencia ? 'Registra la primera entrada o haz un conteo para empezar.' : 'Activa "Llevar existencias" al editar el producto para controlar su inventario.' }}</p>
                </div>
            @else
                <ul class="historial list-unstyled mb-0">
                    @foreach($movimientos as $m)
                        <li>
                            <span class="ico"><i class="bi {{ $m->tipo_info['icono'] }}"></i></span>
                            <div style="min-width: 0">
                                <div class="fw-medium">{{ $m->tipo_info['texto'] }}
                                    @if($m->presupuesto)<a href="{{ route('admin.presupuestos.edit', $m->presupuesto) }}" class="num">{{ $m->presupuesto->folio }}</a>@endif</div>
                                <div class="det text-truncate">{{ $fecha($m->created_at) }}@if($m->usuario) · {{ $m->usuario->primer_nombre }}@endif @if($m->nota && ! $m->presupuesto) · {{ $m->nota }}@endif</div>
                            </div>
                            <div>
                                <div class="cant num {{ $m->cantidad > 0 ? 'mas' : 'menos' }}">{{ $m->cantidad > 0 ? '+' : '−' }}{{ \App\Models\Producto::numero(abs($m->cantidad)) }}</div>
                                <div class="det text-end num">quedan {{ \App\Models\Producto::numero($m->existencia) }}</div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    <div class="d-grid gap-4">
        @if($admin && $p->controla_existencia)
            <section class="panel panel-body" x-data="{ tipo: {{ Js::from(old('tipo', 'entrada')) }} }">
                <h2 class="fs-6 fw-semibold mb-3">Mover existencias</h2>
                <form method="post" action="{{ route('admin.productos.movimiento', $p) }}" class="d-grid gap-3">
                    @csrf
                    <div class="mov-tipos" role="radiogroup" aria-label="Tipo de movimiento">
                        <label><input type="radio" name="tipo" value="entrada" x-model="tipo"><span><i class="bi bi-box-arrow-in-down"></i> Entrada</span></label>
                        <label><input type="radio" name="tipo" value="salida" x-model="tipo"><span><i class="bi bi-box-arrow-up"></i> Salida</span></label>
                        <label><input type="radio" name="tipo" value="ajuste" x-model="tipo"><span><i class="bi bi-sliders"></i> Conteo</span></label>
                    </div>
                    <div>
                        <label class="form-label" for="cantidad" x-text="tipo === 'ajuste' ? '¿Cuántas contaste?' : (tipo === 'entrada' ? '¿Cuántas llegaron?' : '¿Cuántas salen?')">Cantidad</label>
                        <div class="input-group"><input type="number" name="cantidad" id="cantidad" step="0.01" min="0" class="form-control num" required inputmode="decimal" value="{{ old('cantidad') }}"><span class="input-group-text">{{ $p->unidadPara(2) }}</span></div>
                        <div class="form-text" x-show="tipo === 'ajuste'" x-cloak>Hoy el sistema dice {{ \App\Models\Producto::numero($p->existencia) }}. Escribe lo que hay físicamente y ajustamos la diferencia.</div>
                    </div>
                    <div>
                        <label class="form-label" for="nota">Nota <span class="text-secondary fw-normal">(opcional)</span></label>
                        <input name="nota" id="nota" class="form-control" maxlength="255" :placeholder="tipo === 'entrada' ? 'Factura del proveedor, pedido…' : (tipo === 'salida' ? 'Merma, muestra, uso interno…' : 'Inventario de fin de mes')">
                    </div>
                    <button class="btn btn-primario">Registrar</button>
                </form>
                <p class="small text-secondary mt-3 mb-0">Las ventas no se registran aquí: al aceptarse una cotización con este producto se descuenta solo.</p>
            </section>
        @endif

        @if($p->publico && $p->activo)
            <section class="panel panel-body">
                <h2 class="fs-6 fw-semibold mb-1">Compartir</h2>
                <p class="small text-secondary mb-3">Con tu enlace, el botón de WhatsApp de la página te escribe a ti{{ auth()->user()->whatsapp ? '' : ' (agrega tu teléfono en Mi cuenta para que funcione)' }}.</p>
                <div class="enlace-caja mb-2">
                    <input class="form-control num" value="{{ $enlace }}" readonly aria-label="Enlace del producto" onfocus="this.select()">
                    <button type="button" class="btn btn-borde text-nowrap" data-copiar="{{ $enlace }}"><i class="bi bi-clipboard"></i> Copiar</button>
                </div>
                <a class="btn btn-borde w-100 mb-3" href="https://wa.me/?text={{ $waTexto }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp me-1"></i> Mandar por WhatsApp</a>
                <div class="qr" id="qr-producto" data-texto="{{ $p->url }}" aria-label="Código QR de la página del producto"></div>
                <p class="small text-secondary mt-2 mb-0 text-center">Para etiquetas, mostrador o exhibición.</p>
            </section>
        @elseif($admin)
            <section class="panel panel-body">
                <h2 class="fs-6 fw-semibold mb-1">Página pública apagada</h2>
                <p class="small text-secondary mb-0">{{ $p->activo ? 'Este producto es interno: no tiene página para compartir.' : 'Está desactivado, así que su página no se muestra.' }} Puedes cambiarlo al editarlo.</p>
            </section>
        @endif

        @if($admin && $p->proveedor)
            <section class="panel panel-body">
                <h2 class="fs-6 fw-semibold mb-2">Proveedor</h2>
                <a href="{{ route('admin.proveedores.show', $p->proveedor) }}" class="d-flex align-items-center gap-2 text-decoration-none">@include('admin._avatar', ['nombre' => $p->proveedor->empresa]) <span>{{ $p->proveedor->empresa }}</span></a>
                <a href="{{ route('admin.ordenes.create', ['proveedor' => $p->proveedor_id]) }}" class="btn btn-borde btn-sm mt-3"><i class="bi bi-plus-lg"></i> Pedirle más</a>
            </section>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const pintar = () => {
        const el = document.getElementById('qr-producto');
        if (!el || el.dataset.listo) return;
        el.dataset.listo = '1';
        new QRCode(el, { text: el.dataset.texto, width: 336, height: 336, correctLevel: QRCode.CorrectLevel.M });
    };
    if (!window.vanduQrEscucha) { window.vanduQrEscucha = true; document.addEventListener('vandu:pagina', () => window.vanduQrPintar?.()); }
    window.vanduQrPintar = () => window.QRCode && pintar();
    if (window.QRCode) return pintar();
    const s = document.createElement('script');
    s.src = 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js';
    s.onload = pintar;
    document.head.appendChild(s);
})();
</script>
@endpush
