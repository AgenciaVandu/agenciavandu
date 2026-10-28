@extends('admin.layout')
@section('titulo', 'Inicio')
@include('admin.lealtad._estilos')

@php
    $u = auth()->user();
    $ejemplo = \App\Support\Lealtad\Ejemplo::hay();
    $sinNada = $campanas->isEmpty() && $kpi['inscritos'] === 0;
    $max = max(1, max($dias));
    $cambio = $kpi['visitasAnt'] ? (int) round(($kpi['visitasMes'] - $kpi['visitasAnt']) / $kpi['visitasAnt'] * 100) : null;
@endphp

@section('contenido')
<div class="page-head">
    <div><h1>{{ $cuenta->nombre }}</h1><p class="sub">Tu programa de lealtad: quién viene, quién regresa y qué premios se están ganando.</p></div>
    <div class="d-flex flex-wrap gap-2">
        @if($u->puede('lealtad'))<a href="{{ route('admin.lealtad.qr') }}" class="btn btn-borde"><i class="bi bi-printer me-1"></i> Cartel con QR</a>@endif
        @if($u->puede('canjes'))<a href="{{ route('admin.lealtad.escanear') }}" class="btn btn-primario"><i class="bi bi-qr-code-scan me-1"></i> Escanear</a>@endif
    </div>
</div>

@if($ejemplo && $u->puede('lealtad'))
    <div class="aviso" style="background: var(--marca-soft); color: var(--ink); border: 1px solid #DCD0FF; flex-wrap: wrap">
        <i class="bi bi-lightbulb"></i><span class="flex-grow-1">Estás viendo un programa de ejemplo: dos campañas y comensales con visitas inventadas. Quítalo cuando arranques el tuyo.</span>
        <form method="post" action="{{ route('admin.lealtad.ejemplos.quitar') }}" onsubmit="return confirm('¿Quitar las campañas, comensales y visitas de ejemplo?')">@csrf @method('delete')<button class="btn btn-borde btn-sm">Quitar ejemplos</button></form>
    </div>
@endif

@if($sinNada)
    <section class="panel mb-4"><div class="panel-body">
        <h2 class="h5 mb-3">Arranca tu programa en 3 pasos</h2>
        <ol class="mb-3" style="line-height: 1.9">
            <li><a href="{{ route('admin.lealtad.campanas.create') }}">Crea tu primera campaña</a>: vigencia, cuántas visitas y qué premios se desbloquean en el camino.</li>
            <li><a href="{{ route('admin.lealtad.qr') }}">Imprime tu cartel con QR</a> para mesas y caja: ahí tus clientes se unen al programa.</li>
            <li>En cada visita, <a href="{{ route('admin.lealtad.escanear') }}">escanea el QR del comensal</a> desde su celular.</li>
        </ol>
        @if($u->puede('lealtad'))
            <form method="post" action="{{ route('admin.lealtad.ejemplos') }}">@csrf<button class="btn btn-link btn-sm px-0 text-secondary">O carga un programa de ejemplo para ver cómo se ve todo</button></form>
        @endif
    </div></section>
@endif

<div class="panel lk-kpis">
    <div><div class="k"><i class="bi bi-people"></i> En tu programa</div><div class="v num">{{ number_format($kpi['inscritos']) }}</div><div class="s">{{ $kpi['nuevos'] }} se unieron este mes</div></div>
    <div><div class="k"><i class="bi bi-door-open"></i> Visitas este mes</div><div class="v num">{{ number_format($kpi['visitasMes']) }}</div>
        <div class="s">@if($cambio !== null){{ $cambio >= 0 ? '+' : '' }}{{ $cambio }}% contra el mes pasado @else {{ $kpi['visitasAnt'] }} el mes pasado @endif</div></div>
    <div><div class="k"><i class="bi bi-arrow-repeat"></i> Regresan</div><div class="v num">{{ $kpi['retorno'] !== null ? $kpi['retorno'] . '%' : '—' }}</div><div class="s">{{ $kpi['regresan'] }} vinieron 2 veces o más (60 días)</div></div>
    <div><div class="k"><i class="bi bi-gift"></i> Premios entregados</div><div class="v num">{{ $kpi['canjesMes'] }}</div><div class="s">este mes</div></div>
</div>

<div class="row g-4">
    <div class="col-lg-7 d-grid gap-4 align-content-start">
        @forelse($campanas as $x)
            @php $k = $x['campana']; @endphp
            <section class="panel">
                <div class="panel-head">
                    <div><h2>{{ $k->nombre }} <span class="lk-estado {{ $k->estado }} ms-1">{{ ['vigente' => 'Vigente', 'proxima' => 'Próxima', 'canje' => 'En canje', 'terminada' => 'Terminó', 'pausada' => 'En pausa'][$k->estado] }}</span></h2>
                        <span class="ayuda">{{ ucfirst($k->vigencia_texto) }} · meta {{ $k->meta_visitas }} visitas</span></div>
                    @if($u->puede('lealtad'))<a href="{{ route('admin.lealtad.campanas.edit', $k) }}" class="btn btn-fantasma btn-sm">Editar</a>@endif
                </div>
                <div class="panel-body">
                    <div class="d-flex flex-wrap gap-4 mb-2 small">
                        <span><b class="num">{{ $x['participan'] }}</b> <span class="secundario">participan</span></span>
                        <span><b class="num">{{ $x['visitas'] }}</b> <span class="secundario">visitas</span></span>
                        <span><b class="num">{{ $x['meta'] }}</b> <span class="secundario">llegaron a la meta</span></span>
                        <span><b class="num">{{ $x['canjes'] }}</b> <span class="secundario">premios entregados</span></span>
                    </div>
                    @foreach($x['premios'] as $p)
                        <div class="lk-premio"><span class="n num">{{ $p['premio']->visitas }}</span>
                            <div class="flex-grow-1"><b>{{ $p['premio']->nombre }}</b>@if($p['premio']->descripcion)<div class="secundario small">{{ $p['premio']->descripcion }}</div>@endif</div>
                            <span class="secundario small num">{{ $p['alcanzan'] }} {{ $p['alcanzan'] === 1 ? 'lo ganó' : 'lo ganaron' }}</span></div>
                    @endforeach
                </div>
            </section>
        @empty
            @unless($sinNada)
                <div class="panel"><div class="vacio"><div class="ico"><i class="bi bi-gift"></i></div><h3>Sin campañas activas</h3><p>Crea una campaña para que las visitas sumen premios.</p>
                    @if($u->puede('lealtad'))<a href="{{ route('admin.lealtad.campanas.create') }}" class="btn btn-primario">Nueva campaña</a>@endif</div></div>
            @endunless
        @endforelse
    </div>
    <div class="col-lg-5 d-grid gap-4 align-content-start">
        <section class="panel">
            <div class="panel-head"><h2>Visitas, últimos 30 días</h2></div>
            <div class="lk-barras" role="img" aria-label="Visitas por día">
                @foreach($dias as $f => $n)<span class="{{ $loop->last ? 'hoy' : '' }}" style="height: {{ round($n / $max * 100) }}%" title="{{ \Illuminate\Support\Carbon::parse($f)->locale('es')->isoFormat('ddd D MMM') }}: {{ $n }}"></span>@endforeach
            </div>
        </section>
        <section class="panel">
            <div class="panel-head"><h2>Últimas visitas</h2>@if($u->puede('comensales'))<a href="{{ route('admin.comensales') }}" class="btn btn-fantasma btn-sm">Comensales</a>@endif</div>
            <div class="lk-lista">
                @forelse($ultimas as $v)
                    <div class="d-flex align-items-center gap-2 px-3 py-2">
                        <span class="avatar" style="width: 32px; height: 32px; font-size: 12px">{{ $v->comensal?->iniciales }}</span>
                        <span class="flex-grow-1 text-truncate">{{ $v->comensal?->nombre }}</span>
                        <span class="secundario small">{{ $v->fecha->isToday() ? 'hoy' : $v->fecha->locale('es')->diffForHumans() }}</span>
                    </div>
                @empty
                    <div class="panel-body secundario small">Aún no hay visitas. Escanea el QR del comensal en caja.</div>
                @endforelse
            </div>
        </section>
    </div>
</div>
@endsection
