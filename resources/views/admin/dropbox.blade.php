@extends('admin.layout')
@section('titulo', 'Dropbox')

@section('contenido')
<div class="page-head">
    <div>
        <h1>Dropbox</h1>
        <p class="sub">Aquí se guardan las fotos, videos, documentos y constancias que subes al panel.</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <section class="panel">
            <div class="panel-head"><h2>Conexión</h2>
                @if($conectado)<span class="estado estado-aceptada">Conectado</span>@else<span class="estado estado-rechazada">Sin conectar</span>@endif
            </div>
            <div class="panel-body">
                @if($simulado)
                    <div class="aviso aviso-ok mb-3"><i class="bi bi-cone-striped"></i> Modo de prueba: Dropbox simulado en una carpeta local.</div>
                @endif

                @if(! $configurado)
                    <p class="mt-0">Falta poner las llaves de tu app de Dropbox en el <code>.env</code> del servidor:</p>
<pre class="p-3 rounded" style="background:var(--sunken); font-size:13px">DROPBOX_APP_KEY=tu_app_key
DROPBOX_APP_SECRET=tu_app_secret</pre>
                    <p class="secundario mb-0">Después corre <code>php artisan optimize:clear</code> y regresa a esta página.</p>
                @elseif(! $conectado)
                    <p class="mt-0">Conecta tu cuenta para que todo lo nuevo se guarde en tu Dropbox, dentro de <b>{{ $raiz }}</b>.</p>
                    <a href="{{ route('admin.dropbox.conectar') }}" class="btn btn-primario"><i class="bi bi-dropbox me-1"></i> Conectar Dropbox</a>
                @else
                    <dl class="mb-3">
                        <dt class="secundario fw-normal" style="font-size:13px">Cuenta</dt>
                        <dd>{{ $integracion?->cuenta ?: ($simulado ? 'Simulada' : '—') }}</dd>
                        <dt class="secundario fw-normal" style="font-size:13px">Carpeta</dt>
                        <dd class="num">{{ $raiz }}</dd>
                    </dl>
                    @unless($simulado)
                        <a href="{{ route('admin.dropbox.abrir') }}" target="_blank" rel="noopener" class="btn btn-borde"><i class="bi bi-box-arrow-up-right me-1"></i> Abrir carpeta en Dropbox</a>
                        <form method="post" action="{{ route('admin.dropbox.desconectar') }}" class="d-inline" onsubmit="return confirm('¿Desconectar Dropbox? Tus archivos se quedan en Dropbox, pero el panel ya no podrá mostrarlos hasta que lo vuelvas a conectar.')">@csrf
                            <button class="btn btn-fantasma text-danger"><i class="bi bi-plug me-1"></i> Desconectar</button>
                        </form>
                    @endunless
                @endif
            </div>
        </section>
    </div>

    <div class="col-lg-5 d-grid gap-4 align-content-start">
        <section class="panel">
            <div class="panel-head"><h2>Archivos</h2></div>
            <div class="panel-body">
                <div class="d-flex justify-content-between"><span>En Dropbox</span><b class="num">{{ $enDropbox }}</b></div>
                <div class="d-flex justify-content-between mt-1"><span>Todavía en el servidor</span><b class="num">{{ $locales }}</b></div>
                @if($conectado && $locales)
                    <p class="secundario mt-3 mb-1" style="font-size:13.5px">Para pasar a Dropbox lo que ya estaba en el servidor, corre una vez en la terminal del servidor:</p>
                    <pre class="p-2 rounded mb-0" style="background:var(--sunken); font-size:13px">php artisan vandu:dropbox-migrar</pre>
                @endif
            </div>
        </section>
        <section class="panel">
            <div class="panel-head"><h2>Cómo se organiza</h2></div>
            <div class="panel-body" style="font-size:14px">
<pre class="mb-2" style="font-size:12.5px; white-space:pre-wrap">{{ $raiz }}/
  Proyectos/
    Cliente - Proyecto · CT-0001/
      Galería/        ← lo que ve tu cliente
      No publicado/   ← oculto para el cliente
      Documentos/1. Etapa/
  Clientes/
    Cliente/Constancias/</pre>
                <p class="secundario mb-0">Puedes exportar directo a la carpeta <b>Galería</b> de un proyecto y darle <b>Sincronizar</b> en el panel. En el servidor solo quedan vistas previas ligeras para que todo cargue rápido.</p>
            </div>
        </section>
    </div>
</div>
@endsection
