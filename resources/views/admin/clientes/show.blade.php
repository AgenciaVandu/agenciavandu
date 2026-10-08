@extends('admin.layout')
@section('titulo', $cliente->nombre)

@section('contenido')
<a href="{{ route('admin.clientes.index') }}" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left"></i> Clientes</a>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h3 mb-0">{{ $cliente->nombre }}</h1>
        <div class="text-muted">{{ $cliente->empresa }}</div>
        <div class="small mt-2 d-flex flex-wrap gap-3">
            @if($cliente->email)<a href="mailto:{{ $cliente->email }}" class="text-decoration-none"><i class="bi bi-envelope"></i> {{ $cliente->email }}</a>@endif
            @if($cliente->whatsapp)<a href="https://wa.me/{{ $cliente->whatsapp }}" target="_blank" class="text-decoration-none num"><i class="bi bi-whatsapp"></i> {{ $cliente->telefono }}</a>@endif
            @if($cliente->rfc)<span class="num">RFC {{ $cliente->rfc }}</span>@endif
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.clientes.edit', $cliente) }}" class="btn btn-outline-dark">Editar</a>
        <a href="{{ route('admin.presupuestos.create', ['cliente' => $cliente->id]) }}" class="btn btn-verde"><i class="bi bi-plus-lg"></i> Nueva cotización</a>
    </div>
</div>

@if($cliente->notas)
    <div class="alert alert-light border small" style="white-space: pre-line">{{ $cliente->notas }}</div>
@endif

<div class="card">
    <div class="card-header">Cotizaciones</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <tbody>
            @forelse($cliente->presupuestos as $p)
                <tr>
                    <td class="num fw-semibold"><a href="{{ route('admin.presupuestos.edit', $p) }}" class="text-decoration-none">{{ $p->folio }}</a></td>
                    <td class="small text-muted">{{ $p->fecha->format('d/m/Y') }}</td>
                    <td class="small">{{ \Illuminate\Support\Str::limit($p->conceptos->first()?->descripcion, 60) }}</td>
                    <td class="text-end num">{{ $p->monto($p->modo_iva === 'desglosado' ? $p->total : $p->subtotal) }}</td>
                    <td>@include('admin.presupuestos._vigencia', ['p' => $p])</td>
                    <td><span class="estado estado-{{ $p->estado }}">{{ \App\Models\Presupuesto::ESTADOS[$p->estado] }}</span></td>
                    <td class="text-end"><button type="button" class="btn btn-sm btn-outline-dark" data-copiar="{{ $p->url_publica }}"><i class="bi bi-link-45deg"></i> Enlace</button></td>
                </tr>
            @empty
                <tr><td class="text-center text-muted py-4">Sin cotizaciones todavía.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
