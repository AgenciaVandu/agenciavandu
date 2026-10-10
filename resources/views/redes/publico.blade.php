@php
    $nombre = $c->empresa ?: $c->nombre;
    $mesTxt = ucfirst($mes->locale('es')->isoFormat('MMMM YYYY'));
    $idx = $meses->search($mes->format('Y-m'));
    $ant = $idx !== false && $idx > 0 ? $meses[$idx - 1] : $mes->copy()->subMonthNoOverflow()->format('Y-m');
    $sig = $idx !== false && $idx < $meses->count() - 1 ? $meses[$idx + 1] : $mes->copy()->addMonthNoOverflow()->format('Y-m');
    $inicioCal = $mes->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
    $finCal = $mes->copy()->endOfMonth()->endOfWeek(\Carbon\Carbon::SUNDAY);
    $dias = [];
    for ($d = $inicioCal->copy(); $d->lte($finCal); $d->addDay()) $dias[] = ['k' => $d->format('Y-m-d'), 'n' => $d->day, 'fuera' => ! $d->isSameMonth($mes), 'nombre' => $d->locale('es')->isoFormat('dddd')];
    $wa = config('vandu.whatsapp');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Contenido de {{ $nombre }} · {{ $mesTxt }} | {{ config('vandu.marca.nombre') }}</title>
    <link rel="icon" href="/favi.svg" type="image/svg+xml">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
    <style>
        @font-face { font-family: 'Geist'; src: url('{{ route('vandu.fuente') }}') format('woff2'); font-weight: 100 900; font-display: swap; }
        :root { --ink: #13161D; --mist: #F3F4F6; --line: #E3E4E8; --muted: #5d6270; --green: #00F385; --green-ink: #047A4B; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Geist', system-ui, sans-serif; color: var(--ink); background: #F6F7F9; font-size: 15px; line-height: 1.5; }
        [x-cloak] { display: none !important; }
        button { font: inherit; }
        .top { background: var(--ink); color: #fff; }
        .top .in { max-width: 1100px; margin: 0 auto; padding: 14px 16px; display: flex; align-items: center; gap: 14px; justify-content: space-between; flex-wrap: wrap; }
        .top img, .top svg { height: 24px; width: auto; }
        .top .quien { font-size: 13.5px; color: #C9CDD5; }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 24px 16px 80px; }
        h1 { font-size: 26px; margin: 0; letter-spacing: -.01em; }
        .sub { color: var(--muted); margin: 4px 0 0; }
        .cab { display: flex; justify-content: space-between; gap: 16px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 18px; }
        .mesnav { display: flex; align-items: center; gap: 8px; }
        .mesnav a { width: 38px; height: 38px; border-radius: 10px; border: 1px solid var(--line); background: #fff; display: grid; place-items: center; color: var(--ink); text-decoration: none; }
        .mesnav b { min-width: 150px; text-align: center; font-size: 17px; }
        .resumen { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 14px 18px; margin-bottom: 18px; }
        .resumen .barra { flex: 1 1 200px; height: 10px; border-radius: 99px; background: var(--mist); overflow: hidden; }
        .resumen .barra span { display: block; height: 100%; background: #00C46A; border-radius: 99px; transition: width .3s; }
        .resumen .t { font-weight: 600; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 44px; padding: 0 18px; border-radius: 10px; font-weight: 600; text-decoration: none; cursor: pointer; border: 1.5px solid var(--ink); background: #fff; color: var(--ink); }
        .btn.prim { background: var(--ink); color: #fff; }
        .btn.ok { background: #00C46A; border-color: #00C46A; color: #fff; }
        .btn:disabled { opacity: .5; cursor: default; }
        .btn.sm { min-height: 36px; padding: 0 12px; font-size: 14px; }
        .tabs { display: inline-flex; background: #E9EBEE; border-radius: 12px; padding: 4px; gap: 2px; margin-bottom: 18px; flex-wrap: wrap; }
        .tabs button { border: 0; background: none; padding: 8px 14px; border-radius: 9px; color: #3f4450; cursor: pointer; display: inline-flex; gap: 6px; align-items: center; }
        .tabs button.on { background: #fff; color: var(--ink); font-weight: 600; box-shadow: 0 1px 2px rgba(0,0,0,.08); }
        .est { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 600; padding: 2px 9px; border-radius: 99px; color: #fff; }
        .perfil-wrap { display: grid; grid-template-columns: minmax(0, 420px) minmax(0, 1fr); gap: 28px; align-items: start; }
        .ayuda { color: var(--muted); font-size: 14.5px; }
        .ayuda b { color: var(--ink); }
        .cal { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 1px; background: var(--line); border: 1px solid var(--line); border-radius: 14px; overflow: hidden; }
        .cal .dn { background: var(--mist); font-size: 12px; color: var(--muted); padding: 8px 10px; }
        .cal .dia { background: #fff; min-height: 120px; padding: 6px; display: flex; flex-direction: column; gap: 4px; }
        .cal .dia.fuera { background: #FAFBFC; color: #B5BAC4; }
        .cal .num { font-size: 12.5px; font-weight: 600; }
        .cp { display: flex; gap: 6px; align-items: center; border: 0; background: var(--mist); border-radius: 8px; padding: 4px; text-align: left; cursor: pointer; border-left: 3px solid var(--c); width: 100%; color: var(--ink); }
        .cp .th { width: 36px; height: 36px; border-radius: 6px; overflow: hidden; background: #dfe2e7; flex: none; display: grid; place-items: center; color: #8A90A0; }
        .cp .th img, .cp .th video { width: 100%; height: 100%; object-fit: cover; }
        .cp .tx { font-size: 12px; min-width: 0; line-height: 1.25; }
        .cp .tx b { display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tarjetas { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 14px; }
        .tarjeta { background: #fff; border: 1px solid var(--line); border-radius: 14px; overflow: hidden; cursor: pointer; text-align: left; padding: 0; color: var(--ink); display: flex; flex-direction: column; }
        .tarjeta .img { aspect-ratio: 4 / 5; background: var(--mist); position: relative; overflow: hidden; }
        .tarjeta .img img, .tarjeta .img video { width: 100%; height: 100%; object-fit: cover; }
        .tarjeta .img .vacia { position: absolute; inset: 0; display: grid; place-items: center; color: #9AA0AC; font-size: 28px; }
        .tarjeta .img .est { position: absolute; left: 10px; top: 10px; }
        .tarjeta .img .coment { position: absolute; right: 10px; top: 10px; background: rgba(0,0,0,.6); color: #fff; font-size: 12px; border-radius: 99px; padding: 2px 8px; }
        .tarjeta .info { padding: 12px 14px; }
        .tarjeta .info b { display: block; }
        .tarjeta .info span { font-size: 13px; color: var(--muted); }
        .tarjeta .info .redes i { margin-right: 4px; }
        .vacio { text-align: center; padding: 50px 20px; color: var(--muted); background: #fff; border: 1px solid var(--line); border-radius: 14px; }
        .vacio i { font-size: 34px; display: block; margin-bottom: 8px; }

        /* Detalle del post */
        dialog.modal { border: 0; padding: 0; border-radius: 18px; width: min(980px, calc(100vw - 24px)); max-height: calc(100dvh - 24px); background: #F6F7F9; color: var(--ink); box-shadow: 0 24px 60px rgba(0,0,0,.3); }
        dialog.modal::backdrop { background: rgba(15,18,25,.6); }
        .mod { display: grid; grid-template-columns: minmax(0, 1fr) 360px; min-height: 0; }
        .mod .mod-izq { padding: 22px; overflow: auto; max-height: calc(100dvh - 24px); }
        .mod .mod-der { background: #fff; border-left: 1px solid var(--line); padding: 20px; display: flex; flex-direction: column; gap: 14px; overflow: auto; max-height: calc(100dvh - 24px); }
        .mod .cerrar { position: absolute; top: 10px; right: 10px; width: 36px; height: 36px; border-radius: 50%; border: 0; background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,.15); cursor: pointer; z-index: 5; }
        .mod h2 { font-size: 18px; margin: 0; }
        .mod .meta { font-size: 13.5px; color: var(--muted); }
        .hilo { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
        .hilo li { background: var(--mist); border-radius: 12px; padding: 9px 12px; font-size: 14px; }
        .hilo li.agencia { background: #EEF3FF; }
        .hilo li.cambios { background: #FFF4E5; }
        .hilo li.aprobado { background: #E3FBEF; }
        .hilo li.estado, .hilo li.revision { background: none; padding: 0 4px; font-size: 12.5px; color: var(--muted); }
        .hilo .q { font-size: 12px; color: var(--muted); }
        .hilo .t { white-space: pre-line; }
        textarea, input[type=text] { width: 100%; font: inherit; border: 1.5px solid var(--line); border-radius: 10px; padding: 10px 12px; background: #fff; }
        textarea:focus, input:focus { outline: none; border-color: var(--ink); }
        .codigo { font-size: 24px; letter-spacing: .3em; text-align: center; font-weight: 600; font-variant-numeric: tabular-nums; }
        .err { color: #B4232A; font-size: 14px; font-weight: 500; }
        .okmsg { color: var(--green-ink); font-size: 14px; font-weight: 500; }
        .verif { border: 1.5px solid var(--ink); border-radius: 14px; padding: 14px; display: grid; gap: 10px; background: #fff; }
        .verif label { font-weight: 600; font-size: 14px; }
        .link { border: 0; background: none; padding: 0; text-decoration: underline; font-weight: 600; cursor: pointer; color: var(--ink); }
        .acciones { display: flex; gap: 8px; flex-wrap: wrap; }
        .acciones .btn { flex: 1 1 140px; }
        .aprobado-box { background: #E3FBEF; color: var(--green-ink); border-radius: 12px; padding: 10px 12px; font-weight: 500; font-size: 14px; }
        @media (max-width: 860px) {
            .perfil-wrap { grid-template-columns: minmax(0, 1fr); }
            .mod { grid-template-columns: minmax(0, 1fr); }
            .mod .mod-izq, .mod .mod-der { max-height: none; }
            .mod .mod-der { border-left: 0; border-top: 1px solid var(--line); }
            dialog.modal { overflow: auto; }
            .cal { display: block; background: none; border: 0; }
            .cal .dn, .cal .dia.fuera, .cal .dia.vacia { display: none; }
            .cal .dia { min-height: 0; border: 1px solid var(--line); border-radius: 12px; margin-bottom: 8px; padding: 10px; }
            .mesnav b { min-width: 0; }
        }
    </style>
    @include('redes._previa-recursos', ['parte' => 'estilos'])
</head>
<body x-data="contenido({{ Js::from([
        'posts' => $posts, 'feed' => $feed, 'perfiles' => $perfiles, 'redes' => config('vandu.redes.redes'), 'estados' => \App\Models\RedesPost::ESTADOS,
        'formatos' => config('vandu.redes.formatos'), 'sesion' => $sesion, 'mes' => $mes->format('Y-m'), 'dias' => $dias,
        'urls' => ['verificar' => route('redes.verificar', $token), 'codigo' => route('redes.codigo', $token), 'responder' => url('/redes/' . $token . '/posts'), 'todo' => route('redes.aprobar-todo', $token)],
        'nombreCliente' => $c->nombre, 'hayCorreo' => (bool) $c->email,
    ]) }})" @keydown.escape.window="cerrar()">

<header class="top"><div class="in">
    <x-logo-vandu width="90" height="28" />
    <span class="quien">Contenido para <b style="color:#fff">{{ $nombre }}</b></span>
</div></header>

<main class="wrap">
    <div class="cab">
        <div>
            <h1>Tu contenido de {{ mb_strtolower($mes->locale('es')->isoFormat('MMMM')) }}</h1>
            <p class="sub">Revisa cómo se verá cada publicación, déjanos comentarios y apruébalas.</p>
        </div>
        <div class="mesnav">
            <a href="{{ route('redes.publico', [$token, 'mes' => $ant]) }}" aria-label="Mes anterior"><i class="bi bi-chevron-left"></i></a>
            <b>{{ $mesTxt }}</b>
            <a href="{{ route('redes.publico', [$token, 'mes' => $sig]) }}" aria-label="Mes siguiente"><i class="bi bi-chevron-right"></i></a>
        </div>
    </div>

    <div class="resumen" x-show="posts.length" x-cloak>
        <span class="t" x-text="aprobados() + ' de ' + posts.length + ' aprobados'"></span>
        <span class="barra"><span :style="'width:' + (posts.length ? aprobados() / posts.length * 100 : 0) + '%'"></span></span>
        <span class="ayuda" x-show="pendientes()" x-text="pendientes() + (pendientes() === 1 ? ' esperando tu revisión' : ' esperando tu revisión')"></span>
        <button type="button" class="btn ok sm" x-show="pendientes()" @click="aprobarTodo()"><i class="bi bi-check2-all"></i> Aprobar todo lo pendiente</button>
    </div>

    <nav class="tabs" role="tablist">
        <button type="button" :class="vista === 'perfil' && 'on'" @click="vista = 'perfil'"><i class="bi bi-instagram"></i> Así se verá tu perfil</button>
        <button type="button" :class="vista === 'calendario' && 'on'" @click="vista = 'calendario'"><i class="bi bi-calendar3"></i> Calendario</button>
        <button type="button" :class="vista === 'todas' && 'on'" @click="vista = 'todas'"><i class="bi bi-grid"></i> Publicaciones</button>
    </nav>

    {{-- Perfil de Instagram con el feed --}}
    <section x-show="vista === 'perfil'">
        <div class="perfil-wrap">
            <div class="ig-perfil">
                <div class="usuario" x-text="perfiles.instagram.usuario"></div>
                <div class="datos">
                    <span class="pv-av aro"><template x-if="perfiles.instagram.avatar"><img :src="perfiles.instagram.avatar" alt=""></template><span x-show="!perfiles.instagram.avatar" x-text="perfiles.instagram.iniciales"></span></span>
                    <div class="cifras">
                        <div><b x-text="feed.length"></b>publicaciones</div>
                        <div><b x-text="perfiles.instagram.seguidores ? Number(perfiles.instagram.seguidores).toLocaleString('es-MX') : '—'"></b>seguidores</div>
                        <div><b x-text="perfiles.instagram.seguidos ? Number(perfiles.instagram.seguidos).toLocaleString('es-MX') : '—'"></b>seguidos</div>
                    </div>
                </div>
                <div class="bio"><b x-text="perfiles.instagram.nombre"></b><span x-text="perfiles.instagram.bio"></span><template x-if="perfiles.instagram.enlace"><span><br><a href="#" x-text="perfiles.instagram.enlace"></a></span></template></div>
                <div class="tabs-ig tabs" style="display:flex;justify-content:space-around;margin:0;border-radius:0;background:none;border-top:1px solid #efefef;padding:8px 0;font-size:20px;color:#8e8e8e"><i class="bi bi-grid-3x3" style="color:#0f1419"></i><i class="bi bi-play-btn"></i><i class="bi bi-person-square"></i></div>
                <div class="ig-grid">
                    <template x-for="p in feed" :key="p.id">
                        <button type="button" class="ig-cel" @click="abrir(p.id)" :title="p.fecha_texto">
                            <template x-if="p.medios.length && p.medios[0].tipo === 'imagen'"><img :src="p.medios[0].mini" alt="" loading="lazy"></template>
                            <template x-if="p.medios.length && p.medios[0].tipo === 'video'"><video :src="p.medios[0].url + '#t=0.5'" muted preload="metadata"></video></template>
                            <template x-if="!p.medios.length"><span class="vacia"><i class="bi bi-image"></i></span></template>
                            <i class="bi ico" :class="{ 'bi-collection-fill': p.formato === 'carrusel', 'bi-play-btn-fill': p.formato === 'reel' }" x-show="p.formato === 'carrusel' || p.formato === 'reel'"></i>
                            <span class="est" x-show="p.estado !== 'publicado'" :style="'background:' + estados[p.estado].color" x-text="estados[p.estado].texto"></span>
                        </button>
                    </template>
                </div>
                <div x-show="!feed.length" style="padding:30px;text-align:center;color:#8e8e8e">Todavía no hay publicaciones para Instagram.</div>
            </div>
            <div class="ayuda">
                <h2 style="font-size:18px;color:var(--ink);margin-top:0">Así se verá tu perfil</h2>
                <p>Esta es una vista de cómo quedará tu feed de Instagram con lo ya publicado y lo que viene. <b>Toca cualquier publicación</b> para verla como en Instagram, Facebook, TikTok o LinkedIn, dejarnos comentarios o aprobarla.</p>
                <p>Las que dicen <b>En revisión</b> esperan tu visto bueno.</p>
            </div>
        </div>
    </section>

    {{-- Calendario del mes --}}
    <section x-show="vista === 'calendario'" x-cloak>
        <div class="cal">
            <template x-for="dn in ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom']" :key="dn"><div class="dn" x-text="dn"></div></template>
            <template x-for="d in dias" :key="d.k">
                <div class="dia" :class="{ fuera: d.fuera, vacia: !delDia(d.k).length }">
                    <div class="num"><span x-text="d.n"></span><span class="ayuda" style="font-weight:400" x-text="' · ' + d.nombre" x-show="window.innerWidth <= 860"></span></div>
                    <template x-for="p in delDia(d.k)" :key="p.id">
                        <button type="button" class="cp" :style="'--c:' + estados[p.estado].color" @click="abrir(p.id)">
                            <span class="th"><template x-if="p.medios.length && p.medios[0].tipo === 'imagen'"><img :src="p.medios[0].mini" alt="" loading="lazy"></template><template x-if="p.medios.length && p.medios[0].tipo === 'video'"><i class="bi bi-play-btn"></i></template><template x-if="!p.medios.length"><i class="bi bi-image"></i></template></span>
                            <span class="tx"><b x-text="p.titulo || formatos[p.formato].nombre"></b><span x-text="p.fecha.slice(11) + ' · ' + estados[p.estado].texto"></span></span>
                        </button>
                    </template>
                </div>
            </template>
        </div>
    </section>

    {{-- Todas las publicaciones del mes --}}
    <section x-show="vista === 'todas'" x-cloak>
        <div class="tarjetas" x-show="posts.length">
            <template x-for="p in posts" :key="p.id">
                <button type="button" class="tarjeta" @click="abrir(p.id)">
                    <div class="img">
                        <template x-if="p.medios.length && p.medios[0].tipo === 'imagen'"><img :src="p.medios[0].mini" alt="" loading="lazy"></template>
                        <template x-if="p.medios.length && p.medios[0].tipo === 'video'"><video :src="p.medios[0].url + '#t=0.5'" muted preload="metadata"></video></template>
                        <template x-if="!p.medios.length"><span class="vacia"><i class="bi bi-image"></i></span></template>
                        <span class="est" :style="'background:' + estados[p.estado].color" x-text="estados[p.estado].texto"></span>
                        <span class="coment" x-show="p.comentarios.filter(c => c.texto && ['comentario','cambios'].includes(c.tipo)).length"><i class="bi bi-chat-dots"></i> <span x-text="p.comentarios.filter(c => c.texto && ['comentario','cambios'].includes(c.tipo)).length"></span></span>
                    </div>
                    <div class="info"><b x-text="p.titulo || formatos[p.formato].nombre"></b>
                        <span x-text="p.fecha_texto"></span><br>
                        <span class="redes"><template x-for="r in p.redes" :key="r"><i class="bi" :class="redes[r].icono"></i></template><span x-text="formatos[p.formato].nombre"></span></span></div>
                </button>
            </template>
        </div>
    </section>

    <div class="vacio" x-show="!posts.length && vista !== 'perfil'" x-cloak><i class="bi bi-calendar3"></i>No hay publicaciones para revisar en {{ mb_strtolower($mesTxt) }}.</div>
</main>

{{-- Detalle: vista previa por red, comentarios y aprobación --}}
<dialog class="modal" x-ref="modal" @close="post = null" @click.self="cerrar()">
    <template x-if="post">
        <div class="mod" style="position:relative">
            <button type="button" class="cerrar" @click="cerrar()" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
            <div class="mod-izq">@include('redes._vista-previa')</div>
            <div class="mod-der">
                <div>
                    <span class="est" :style="'background:' + estados[post.estado].color" x-text="estados[post.estado].texto"></span>
                    <h2 class="mt-2" style="margin-top:8px" x-text="post.titulo || formatos[post.formato].nombre"></h2>
                    <div class="meta"><span x-text="post.fecha_texto"></span> · <template x-for="r in post.redes" :key="r"><i class="bi" :class="redes[r].icono" style="margin-right:4px"></i></template></div>
                </div>

                <div class="aprobado-box" x-show="post.estado === 'aprobado' || post.estado === 'publicado'"><i class="bi bi-check-circle-fill"></i> <span x-text="post.aprobado ? 'Aprobado por ' + post.aprobado : estados[post.estado].texto"></span></div>

                <ul class="hilo" x-show="post.comentarios.filter(c => c.texto).length">
                    <template x-for="(cm, i) in post.comentarios.filter(c => c.texto)" :key="i">
                        <li :class="[cm.actor, cm.tipo]">
                            <div class="q"><b x-text="cm.actor === 'agencia' ? {{ Js::from(config('vandu.marca.nombre')) }} : (cm.autor || 'Tú')"></b> · <span x-text="cm.fecha"></span></div>
                            <div class="t" x-text="cm.texto"></div>
                        </li>
                    </template>
                </ul>

                {{-- Verificación (una vez) --}}
                <template x-if="!sesion">
                    <div class="verif">
                        <div><b>Para aprobar o comentar, confirma que eres tú</b><div class="ayuda" style="font-size:13.5px">Usa el código que te enviamos por correo o WhatsApp. Solo lo escribes una vez en este dispositivo.</div></div>
                        <div><label for="v-nombre">Tu nombre</label><input type="text" id="v-nombre" x-model="vNombre" autocomplete="name"></div>
                        <div><label for="v-codigo">Código de verificación</label><input type="text" id="v-codigo" class="codigo" x-model="vCodigo" @input="vCodigo = formatoCodigo(vCodigo)" inputmode="numeric" autocomplete="one-time-code" placeholder="000 000" maxlength="7"></div>
                        <div class="err" x-show="error" x-text="error"></div>
                        <div class="okmsg" x-show="okmsg" x-text="okmsg"></div>
                        <button type="button" class="btn prim" @click="verificar()" :disabled="ocupado">Continuar</button>
                        <div class="ayuda" style="font-size:13px">¿No lo tienes?
                            <template x-if="cfg.hayCorreo"><button type="button" class="link" @click="pedirCodigo()">Enviarme uno por correo</button></template>
                            <template x-if="!cfg.hayCorreo"><a href="https://wa.me/{{ $wa }}?text={{ rawurlencode('Hola, ¿me compartes el código para revisar mi contenido?') }}" target="_blank" rel="noopener" class="link">Pídelo por WhatsApp</a></template>
                        </div>
                    </div>
                </template>

                <template x-if="sesion">
                    <div style="display:grid;gap:10px">
                        <div class="ayuda" style="font-size:13px">Respondiendo como <b x-text="sesion.nombre"></b></div>
                        <textarea rows="3" x-model="texto" :placeholder="post.estado === 'aprobado' || post.estado === 'publicado' ? 'Deja un comentario…' : '¿Algo que cambiar? Escríbelo aquí…'"></textarea>
                        <div class="err" x-show="error" x-text="error"></div>
                        <div class="acciones">
                            <template x-if="post.estado === 'revision' || post.estado === 'cambios'">
                                <button type="button" class="btn ok" @click="responder('aprobar')" :disabled="ocupado"><i class="bi bi-check2"></i> Aprobar</button>
                            </template>
                            <template x-if="post.estado === 'revision' || post.estado === 'cambios'">
                                <button type="button" class="btn" @click="responder('cambios')" :disabled="ocupado || !texto.trim()"><i class="bi bi-pencil"></i> Pedir cambio</button>
                            </template>
                            <template x-if="post.estado === 'aprobado' || post.estado === 'publicado'">
                                <button type="button" class="btn" @click="responder('comentario')" :disabled="ocupado || !texto.trim()"><i class="bi bi-chat"></i> Comentar</button>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </template>
</dialog>

@include('redes._previa-recursos', ['parte' => 'script'])
<script>
window.contenido = (cfg) => ({
    ...window.redesVista(cfg.redes),
    cfg, posts: cfg.posts, feed: cfg.feed, perfiles: cfg.perfiles, redes: cfg.redes, estados: cfg.estados, formatos: cfg.formatos, dias: cfg.dias,
    sesion: cfg.sesion, vista: 'perfil', post: null, texto: '', error: '', okmsg: '', ocupado: false,
    vNombre: cfg.nombreCliente || '', vCodigo: '',
    init() {
        const id = new URLSearchParams(location.search).get('post');
        if (id) this.$nextTick(() => this.abrir(+id));
        if (!this.feed.length && this.posts.length) this.vista = 'todas';
    },
    todos() { const m = new Map(); [...this.feed, ...this.posts].forEach((p) => m.set(p.id, p)); return m; },
    delDia(k) { return this.posts.filter((p) => p.dia === k); },
    aprobados() { return this.posts.filter((p) => p.estado === 'aprobado' || p.estado === 'publicado').length; },
    pendientes() { return this.posts.filter((p) => p.estado === 'revision').length; },
    abrir(id) {
        const p = this.todos().get(id); if (!p) return;
        this.post = p; this.pvI = 0; this.pvRed = null; this.pvAbierto = false; this.texto = ''; this.error = ''; this.okmsg = '';
        this.$nextTick(() => this.$refs.modal.showModal());
    },
    cerrar() { if (this.$refs.modal.open) this.$refs.modal.close(); this.post = null; },
    actualizar(nuevo) {
        const pon = (lista) => lista.forEach((p, i) => { if (p.id === nuevo.id) lista[i] = nuevo; });
        pon(this.posts); pon(this.feed); this.post = nuevo;
    },
    formatoCodigo(v) { v = v.replace(/\D/g, '').slice(0, 6); return v.length > 3 ? v.slice(0, 3) + ' ' + v.slice(3) : v; },
    async enviar(url, datos) {
        const r = await fetch(url, { method: 'POST', credentials: 'same-origin', body: JSON.stringify(datos),
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content } });
        const d = await r.json().catch(() => ({}));
        if (r.status === 401 && d.verificar) { this.sesion = null; }
        if (!r.ok) throw new Error(d.mensaje || (d.errors ? Object.values(d.errors)[0][0] : 'No se pudo enviar. Intenta de nuevo.'));
        return d;
    },
    async verificar() {
        this.error = ''; this.ocupado = true;
        try { const d = await this.enviar(cfg.urls.verificar, { nombre: this.vNombre, codigo: this.vCodigo }); this.sesion = { nombre: d.nombre }; this.okmsg = ''; }
        catch (e) { this.error = e.message; }
        this.ocupado = false;
    },
    async pedirCodigo() {
        this.error = ''; this.okmsg = '';
        try { const d = await this.enviar(cfg.urls.codigo, {}); this.okmsg = d.mensaje; } catch (e) { this.error = e.message; }
    },
    async responder(accion) {
        this.error = ''; this.ocupado = true;
        try { const d = await this.enviar(cfg.urls.responder + '/' + this.post.id, { accion, texto: this.texto }); this.actualizar(d.post); this.texto = ''; }
        catch (e) { this.error = e.message; }
        this.ocupado = false;
    },
    async aprobarTodo() {
        if (!this.sesion) { const p = this.posts.find((x) => x.estado === 'revision'); if (p) this.abrir(p.id); return; }
        if (!confirm('¿Aprobar las ' + this.pendientes() + ' publicaciones que esperan tu revisión?')) return;
        try { await this.enviar(cfg.urls.todo, { mes: cfg.mes }); location.reload(); } catch (e) { alert(e.message); }
    },
});
</script>
</body>
</html>
