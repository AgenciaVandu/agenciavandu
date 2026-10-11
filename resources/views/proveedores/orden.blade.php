@extends('proveedores._layout')
@php
    /** @var \App\Models\Proveedor $p */
    /** @var \App\Models\OrdenCompra $o */
    $token = $p->portal_token;
    $din = fn ($v) => \App\Models\OrdenCompra::dinero($v);
    $facturas = $o->archivos->where('tipo', 'factura');
    $deAgencia = $o->archivos->where('autor', 'agencia')->where('tipo', '!=', 'factura');
    $suyos = $o->archivos->where('autor', 'proveedor')->where('tipo', '!=', 'factura');
    $tz = config('vandu.zona_horaria');
    $cuando = fn ($f) => $f->copy()->setTimezone($tz)->locale('es')->isoFormat('D MMM, h:mm a');
    $puedeSubir = $o->estado !== 'cancelada';
    $tipos = collect(\App\Models\OrdenCompra::TIPOS_ARCHIVO)->except(['factura', 'diseno']);
    $icono = fn ($a) => match (true) {
        $a->tipo === 'factura' => '🧾',
        $a->es_imagen => '🖼️',
        str_contains((string) $a->mime, 'pdf') => '📄',
        default => '📎',
    };
@endphp
@section('titulo', $o->folio . ' · ' . $o->titulo)

@section('estilos')
    .grid2 { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 14px; align-items: start; margin-top: 18px; }
    .lat { display: grid; gap: 14px; }
    .k { font-size: 13px; color: var(--muted); }
    .v { font-weight: 600; }
    .tabla { width: 100%; border-collapse: collapse; font-size: 15px; }
    .tabla th { font-size: 13px; font-weight: 500; color: var(--muted); text-align: left; padding: 10px 20px; border-bottom: 1px solid var(--line); }
    .tabla td { padding: 12px 20px; border-bottom: 1px solid var(--line); vertical-align: top; }
    .tabla .r { text-align: right; white-space: nowrap; }
    .tabla .c { text-align: center; white-space: nowrap; }
    .tabla td.desc { white-space: pre-line; }
    .tabla tr.tot td { border-bottom: 0; padding-top: 6px; padding-bottom: 6px; }
    .tabla tr.tot.final td { font-weight: 700; font-size: 16.5px; }
    .indicaciones { white-space: pre-line; }
    .arch { display: grid; grid-template-columns: 36px minmax(0, 1fr) auto; gap: 12px; align-items: center; padding: 12px 20px; border-top: 1px solid var(--line); }
    .arch:first-child { border-top: 0; }
    .arch .ic { width: 36px; height: 36px; border-radius: 10px; background: var(--mist); display: grid; place-items: center; font-size: 17px; }
    .arch .n { font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; text-decoration: none; display: block; }
    .arch .s { font-size: 13px; color: var(--muted); }
    .arch form { margin: 0; }
    .quitar { background: none; border: 0; color: var(--muted); font: inherit; font-size: 13px; cursor: pointer; text-decoration: underline; padding: 4px; }
    .subir { display: grid; gap: 12px; padding: 18px 20px; border-top: 1px solid var(--line); background: #FAFBFC; }
    .subir .fila { display: grid; grid-template-columns: 200px minmax(0, 1fr); gap: 12px; }
    .ayuda { font-size: 13px; color: var(--muted); margin: 0; }
    .destacada { border: 1.5px solid var(--ink); }
    .chat { display: grid; gap: 10px; padding: 18px 20px; }
    .msj { max-width: 85%; padding: 10px 14px; border-radius: 14px; background: var(--mist); }
    .msj.yo { justify-self: end; background: var(--ink); color: #fff; }
    .msj .quien { font-size: 12px; opacity: .7; margin-bottom: 2px; }
    .msj .texto { white-space: pre-line; overflow-wrap: anywhere; }
    .titulo-caja { display: flex; justify-content: space-between; align-items: baseline; gap: 10px; padding: 16px 20px 0; }
    .titulo-caja h3 { margin: 0; font-size: 16px; font-weight: 600; }
    .titulo-caja span { font-size: 13px; color: var(--muted); }
    .titulo-caja + .arch, .titulo-caja + .chat, .titulo-caja + p { margin-top: 8px; }
    @media (max-width: 760px) {
        .grid2 { grid-template-columns: minmax(0, 1fr); }
        .lat { order: -1; }
        .subir .fila { grid-template-columns: minmax(0, 1fr); }
        .tabla th, .tabla td { padding-left: 14px; padding-right: 14px; }
        .tabla .ocultar { display: none; }
    }
@endsection

@section('encabezado')
    <a class="volver" href="{{ route('proveedor.portal', $token) }}">← Todas tus órdenes</a>
    <h1 style="margin-top: 10px">{{ $o->titulo }}</h1>
    <p class="sub num">Orden de compra {{ $o->folio }} · {{ $o->fecha?->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}</p>
    <div class="resumen">
        <span>Estado: <b>{{ $o->estado_texto }}</b></span>
        @if($o->fecha_entrega)<span>{{ $o->entrega_texto }}</span>@endif
        <span>Total <b class="num">{{ $din($o->total) }}</b></span>
    </div>
@endsection

@section('contenido')
<div class="grid2">
    <div>
        <div class="caja">
            <table class="tabla">
                <thead><tr><th>Concepto</th><th class="c">Cantidad</th><th class="r ocultar">Precio</th><th class="r">Importe</th></tr></thead>
                <tbody>
                @foreach($o->conceptos as $c)
                    <tr>
                        <td class="desc">{{ $c->descripcion }}</td>
                        <td class="c num">{{ $c->cantidad_texto }}</td>
                        <td class="r num ocultar">{{ $din($c->precio) }}</td>
                        <td class="r num">{{ $din($c->importe) }}</td>
                    </tr>
                @endforeach
                <tr class="tot"><td colspan="3" class="r" style="color: var(--muted)">Subtotal</td><td class="r num">{{ $din($o->subtotal) }}</td></tr>
                @if($o->con_iva)<tr class="tot"><td colspan="3" class="r" style="color: var(--muted)">IVA {{ rtrim(rtrim(number_format($o->iva_porcentaje, 2), '0'), '.') }}%</td><td class="r num">{{ $din($o->iva) }}</td></tr>@endif
                <tr class="tot final"><td colspan="3" class="r">Total</td><td class="r num">{{ $din($o->total) }}</td></tr>
                </tbody>
            </table>
        </div>

        @if(trim((string) $o->indicaciones) !== '')
            <div class="caja"><div class="cuerpo"><div class="k" style="margin-bottom: 6px">Indicaciones</div><div class="indicaciones">{{ $o->indicaciones }}</div></div></div>
        @endif

        @if($deAgencia->isNotEmpty())
            <div class="caja">
                <div class="titulo-caja"><h3>Archivos que te compartimos</h3><span>Diseños y referencias</span></div>
                @foreach($deAgencia as $a)
                    <div class="arch">
                        <span class="ic" aria-hidden="true">{{ $icono($a) }}</span>
                        <div style="min-width: 0"><a class="n" href="{{ route('proveedor.archivo', [$token, $o->id, $a->id]) }}" target="_blank" rel="noopener">{{ $a->nombre }}</a>
                            <div class="s">{{ $a->tipo_texto }} · {{ $a->peso_texto }}</div></div>
                        <a class="btn chico" href="{{ route('proveedor.archivo', [$token, $o->id, $a->id]) }}?descargar=1">Descargar</a>
                    </div>
                @endforeach
            </div>
        @endif

        <h2 id="facturas">Tu factura</h2>
        <div class="caja {{ $facturas->isEmpty() && $puedeSubir && $o->estado !== 'pagada' ? 'destacada' : '' }}">
            @if($facturas->isEmpty())
                <div class="cuerpo"><p class="ayuda" style="font-size: 14.5px">Sube aquí tu factura de esta orden, de preferencia el <b>PDF y el XML</b>. Puedes elegir los dos archivos a la vez.</p></div>
            @endif
            @foreach($facturas as $a)
                <div class="arch">
                    <span class="ic" aria-hidden="true">{{ $icono($a) }}</span>
                    <div style="min-width: 0"><a class="n" href="{{ route('proveedor.archivo', [$token, $o->id, $a->id]) }}" target="_blank" rel="noopener">{{ $a->nombre }}</a>
                        <div class="s">{{ $a->extension }} · {{ $a->peso_texto }} · {{ $cuando($a->created_at) }}</div></div>
                    @if($o->estado !== 'pagada')
                        <form method="post" action="{{ route('proveedor.quitar', [$token, $o->id, $a->id]) }}" onsubmit="return confirm('¿Quitar este archivo?')">@csrf @method('delete')<button class="quitar">Quitar</button></form>
                    @endif
                </div>
            @endforeach
            @if($puedeSubir && $o->estado !== 'pagada')
                <form class="subir" method="post" action="{{ route('proveedor.subir', [$token, $o->id]) }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="tipo" value="factura">
                    <div>
                        <label for="facturas-input">{{ $facturas->isEmpty() ? 'Archivos de la factura' : 'Agregar otra factura o complemento' }}</label>
                        <input type="file" id="facturas-input" name="archivos[]" accept=".pdf,.xml,application/pdf,text/xml,application/xml" multiple required>
                    </div>
                    <div><button class="btn prim">Subir factura</button></div>
                </form>
            @endif
        </div>

        <h2 id="archivos">Tus archivos</h2>
        <div class="caja">
            @if($suyos->isEmpty())
                <div class="cuerpo"><p class="ayuda" style="font-size: 14.5px">Muestras, fotos del avance, catálogos, ponchados o cualquier archivo que necesitemos revisar.</p></div>
            @endif
            @foreach($suyos as $a)
                <div class="arch">
                    <span class="ic" aria-hidden="true">{{ $icono($a) }}</span>
                    <div style="min-width: 0"><a class="n" href="{{ route('proveedor.archivo', [$token, $o->id, $a->id]) }}" target="_blank" rel="noopener">{{ $a->nombre }}</a>
                        <div class="s">{{ $a->tipo_texto }} · {{ $a->peso_texto }} · {{ $cuando($a->created_at) }}</div></div>
                    @if($o->estado !== 'pagada')
                        <form method="post" action="{{ route('proveedor.quitar', [$token, $o->id, $a->id]) }}" onsubmit="return confirm('¿Quitar este archivo?')">@csrf @method('delete')<button class="quitar">Quitar</button></form>
                    @endif
                </div>
            @endforeach
            @if($puedeSubir)
                <form class="subir" method="post" action="{{ route('proveedor.subir', [$token, $o->id]) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="fila">
                        <div>
                            <label for="tipo">¿Qué es?</label>
                            <select name="tipo" id="tipo">
                                @foreach($tipos as $k => $t)<option value="{{ $k }}">{{ $t['texto'] }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label for="archivos-input">Archivos</label>
                            <input type="file" id="archivos-input" name="archivos[]" multiple required>
                        </div>
                    </div>
                    <p class="ayuda">Hasta 10 archivos a la vez, de máximo 50 MB cada uno. Si pesan más, mándalos en ZIP.</p>
                    <div><button class="btn prim">Subir archivos</button></div>
                </form>
            @endif
        </div>

        <h2 id="comentarios">Comentarios</h2>
        <div class="caja">
            @if($o->comentarios->isNotEmpty())
                <div class="chat">
                    @foreach($o->comentarios as $m)
                        <div class="msj {{ $m->autor === 'proveedor' ? 'yo' : '' }}">
                            <div class="quien">{{ $m->autor === 'proveedor' ? 'Tú' : config('vandu.marca.nombre') }} · {{ $cuando($m->created_at) }}</div>
                            <div class="texto">{{ $m->texto }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
            <form class="subir" method="post" action="{{ route('proveedor.comentar', [$token, $o->id]) }}">
                @csrf
                <div>
                    <label for="texto">{{ $o->comentarios->isEmpty() ? '¿Alguna duda o aviso sobre esta orden?' : 'Responder' }}</label>
                    <textarea id="texto" name="texto" rows="3" maxlength="3000" required>{{ old('texto') }}</textarea>
                </div>
                <div><button class="btn prim">Enviar comentario</button></div>
            </form>
        </div>
    </div>

    <aside class="lat">
        <div class="caja"><div class="cuerpo" style="display: grid; gap: 10px">
            <a class="btn prim" href="{{ route('proveedor.pdf', [$token, $o->id]) }}">Descargar orden en PDF</a>
            <a class="btn" href="{{ route('proveedor.pdf', [$token, $o->id]) }}?ver=1" target="_blank" rel="noopener">Ver PDF</a>
        </div></div>
        <div class="caja"><div class="cuerpo" style="display: grid; gap: 12px">
            <div><div class="k">Entrega</div><div class="v">{{ $o->fecha_entrega ? ucfirst($o->fecha_entrega->locale('es')->isoFormat('dddd D [de] MMMM')) : 'Por acordar' }}</div></div>
            @if($o->lugar_entrega)<div><div class="k">Lugar</div><div>{{ $o->lugar_entrega }}</div></div>@endif
            <div><div class="k">Factura</div><div class="v" style="color: {{ $facturas->isEmpty() ? 'var(--amber)' : 'var(--green-ink)' }}">{{ $facturas->isEmpty() ? 'Pendiente' : 'Recibida' }}</div></div>
            <div><div class="k">Pago</div><div>{{ $o->estado === 'pagada' ? 'Pagada' : 'Pendiente' }}</div></div>
        </div></div>
    </aside>
</div>
@endsection
