@php
    $ordenesPr = \App\Models\OrdenCompra::where('proyecto_id', $p->id)->where('estado', '!=', 'cancelada')->with(['proveedor:id,empresa', 'conceptos'])->latest('id')->get();
    $costoPr = $ordenesPr->sum(fn ($o) => $o->subtotal_final);
@endphp
<section class="panel">
    <div class="panel-head"><h2>Proveedores</h2><a href="{{ route('admin.ordenes.create', ['proyecto' => $p->id]) }}" class="btn btn-fantasma btn-sm"><i class="bi bi-plus-lg me-1"></i> Orden</a></div>
    @if($ordenesPr->isEmpty())
        <p class="panel-body secundario m-0" style="font-size: 13.5px">Pide aquí al proveedor lo que necesites para este proyecto (impresión, bordado, fabricación…) y recibe su factura en la orden.</p>
    @else
        <ul class="list-unstyled m-0">
            @foreach($ordenesPr as $o)
                <li class="{{ $loop->first ? '' : 'border-top' }}">
                    <a href="{{ route('admin.ordenes.show', $o) }}" class="d-block px-3 py-2 text-decoration-none text-reset">
                        <div class="d-flex justify-content-between gap-2"><span class="principal text-truncate">{{ $o->proveedor->empresa }}</span><span class="num text-nowrap">{{ \App\Models\OrdenCompra::dinero($o->subtotal_final) }}@if($o->comprobada) <i class="bi bi-patch-check-fill text-success" title="Comprobado con factura"></i>@endif</span></div>
                        <div class="d-flex justify-content-between gap-2"><span class="secundario text-truncate">{{ $o->folio }} · {{ $o->titulo }}</span><span class="estado estado-{{ $o->estado }}">{{ $o->estado_texto }}</span></div>
                    </a>
                </li>
            @endforeach
        </ul>
        <div class="panel-body border-top d-flex justify-content-between secundario" style="font-size: 13.5px">
            <span>Costo con proveedores, antes de IVA</span><b class="num text-body">{{ \App\Models\OrdenCompra::dinero($costoPr) }}</b>
        </div>
    @endif
</section>
