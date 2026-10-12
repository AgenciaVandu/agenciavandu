<a href="{{ route('proveedor.orden', [$o->proveedor->portal_token, $o->id]) }}">
    <span class="n">{{ $o->titulo }}</span>
    <span class="s num">{{ $o->folio }} · {{ $o->fecha?->locale('es')->isoFormat('D MMM YYYY') }}@if($o->fecha_entrega) · <span class="{{ $o->retrasada ? 'tarde' : '' }}">{{ $o->entrega_texto }}</span>@endif @if($adj = $o->archivos->where('autor', 'agencia')->whereIn('tipo', \App\Models\OrdenCompra::TIPOS_AGENCIA)->count()) · <b>📌 {{ $adj }} {{ $adj === 1 ? 'archivo' : 'archivos' }} para descargar</b>@endif @if($nuevos) · <b style="color: var(--blue)">{{ $nuevos === 1 ? '1 comentario nuevo' : "$nuevos comentarios nuevos" }}</b>@endif</span>
    <span class="monto num">{{ \App\Models\OrdenCompra::dinero($o->total) }}</span>
    <span class="est">
        <span class="pill {{ $o->estado_clase }}">{{ $o->estado_texto }}</span>
        @unless(in_array($o->estado, ['cancelada'], true))<span class="ic">{{ $fact ? '✓ Factura recibida' : 'Falta factura' }}</span>@endunless
    </span>
</a>
