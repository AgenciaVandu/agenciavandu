@php
    /** @var \App\Models\Proyecto $p */
    $abiertas = $p->solicitudes->where('estado', 'abierta');
    $icoSubir = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 15V3m0 0L7 8m5-5 5 5"/><path d="M4 15v4a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-4"/></svg>';
    $recibidos = function ($lista) {
        if ($lista->isEmpty()) return '';
        return '<ul class="env-lista">' . $lista->map(fn ($a) => '<li><span class="ok">✓</span><span class="n">' . e($a->nombre) . '</span><span class="pz num">' . e($a->peso_texto) . '</span></li>')->join('') . '</ul>';
    };
@endphp
<section id="enviar-archivos" class="enviar" data-subir="{{ route('proyecto.subir', $p->token) }}" data-token="{{ csrf_token() }}">
    <h2>Archivos que necesitamos</h2>
    <p class="env-ayuda">Para avanzar necesitamos lo siguiente. Súbelo aquí mismo: aceptamos ZIP, video, audio, imágenes y editables (Illustrator, Photoshop, Figma…).</p>

    @foreach($abiertas as $s)
        <div class="env-caja pedida" data-solicitud="{{ $s->id }}">
            <div class="env-top">
                <div>
                    <b class="env-t">{{ $s->titulo }}</b>
                    @if($s->fecha_limite)<span class="env-fecha {{ $s->vencida ? 'tarde' : '' }}">Para el {{ $s->fecha_texto }}</span>@endif
                    @if($s->instrucciones)<p class="env-inst">{{ $s->instrucciones }}</p>@endif
                </div>
            </div>
            {!! $recibidos($s->archivos) !!}
            <label class="env-zona">
                <input type="file" multiple class="env-input">
                {!! $icoSubir !!} <span><b>Elige tus archivos</b> o arrástralos aquí</span>
            </label>
            <ul class="env-lista env-progreso"></ul>
        </div>
    @endforeach

    <div class="env-caja" data-solicitud="">
        <div class="env-top"><div>
            <b class="env-t">¿Algo más que quieras compartirnos?</b>
            <p class="env-inst">Logos, fotos, videos, audios, textos o editables. Llegan directo a nuestra carpeta del proyecto.</p>
        </div></div>
        {!! $recibidos($enviados) !!}
        <label class="env-zona">
            <input type="file" multiple class="env-input">
            {!! $icoSubir !!} <span><b>Elige tus archivos</b> o arrástralos aquí</span>
        </label>
        <ul class="env-lista env-progreso"></ul>
    </div>
    <p class="env-nota">Hasta {{ round(\App\Support\SubidaCliente::maxBytes() / 1073741824, 1) + 0 }} GB por archivo. Si es muy pesado, deja esta página abierta hasta que termine.</p>
</section>
