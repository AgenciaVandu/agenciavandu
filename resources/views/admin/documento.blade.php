@extends('admin.layout')
@section('titulo', 'Diseño de cotización')

@php
    use App\Support\DisenoDocumento as DD;
    $emisor = config('vandu.emisor');
    $estado = [
        'plantilla' => old('plantilla', $d['plantilla']), 'color' => old('color', $d['color']), 'letra' => old('letra', $d['letra']),
        'papel' => old('papel', $d['papel']), 'membrete' => $membrete, 'quitar' => false,
        'en_todas' => (bool) old('en_todas', $d['en_todas']), 'con_encabezado' => (bool) old('con_encabezado', $d['con_encabezado']),
        'sup' => (float) old('sup', $d['sup']), 'inf' => (float) old('inf', $d['inf']), 'izq' => (float) old('izq', $d['izq']), 'der' => (float) old('der', $d['der']),
        'papeles' => DD::PAPELES, 'letras' => collect(DD::LETRAS)->map(fn ($l) => $l['web']),
        'logoOscuro' => $logoOscuro, 'logoClaro' => $logoClaro,
    ];
    $colores = ['#242424', '#13161d', '#1f3a5f', '#0f5e4b', '#7a1f2b', '#6e32b5', '#b4532a', '#00a86b'];
@endphp

@push('head')
<style>
    .doc-editor { display: grid; grid-template-columns: minmax(0, 1fr) 420px; gap: 28px; align-items: start; }
    .doc-editor .vista { position: sticky; top: 24px; }
    .bloque + .bloque { margin-top: 20px; }
    .bloque h2 { font-size: 15.5px; font-weight: 600; margin: 0 0 4px; }
    .bloque > p { color: var(--muted); font-size: 14px; margin: 0 0 14px; }
    .plantillas { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
    .plantillas label { margin: 0; cursor: pointer; }
    .plantillas input { position: absolute; opacity: 0; pointer-events: none; }
    .plantillas .mini { border: 1.5px solid var(--line); border-radius: 10px; padding: 8px; background: #fff; aspect-ratio: 3 / 4; display: flex; flex-direction: column; gap: 5px; }
    .plantillas input:checked + .mini { border-color: var(--ink); box-shadow: 0 0 0 2px var(--ink); }
    .plantillas input:focus-visible + .mini { outline: 2px solid var(--blue); outline-offset: 2px; }
    .plantillas .nom { display: block; text-align: center; font-size: 13.5px; font-weight: 500; margin-top: 6px; }
    .mini .h { height: 18%; border-radius: 4px; }
    .mini .l { height: 5px; background: #e6e8ec; border-radius: 3px; }
    .mini .l.c { width: 60%; }
    .mini .t { height: 8px; border-radius: 2px; margin-top: 4px; }
    .colores { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    .colores button { width: 32px; height: 32px; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 0 0 1px var(--line); }
    .colores button.activo { box-shadow: 0 0 0 2px var(--ink); }
    .colores input[type=color] { width: 44px; height: 34px; padding: 2px; border-radius: 8px; border: 1px solid var(--line); background: #fff; }
    .opciones-fila { display: flex; flex-wrap: wrap; gap: 8px; }
    .opciones-fila label { margin: 0; }
    .opciones-fila input { position: absolute; opacity: 0; pointer-events: none; }
    .opciones-fila span { display: inline-block; padding: 8px 14px; border: 1px solid var(--line); border-radius: 99px; font-size: 14px; cursor: pointer; background: var(--surface); }
    .opciones-fila input:checked + span { border-color: var(--ink); background: var(--ink); color: #fff; }
    .opciones-fila input:focus-visible + span { outline: 2px solid var(--blue); outline-offset: 2px; }
    .margenes { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
    .margenes label { font-size: 13px; color: var(--muted); }
    .subir { display: flex; gap: 16px; align-items: center; flex-wrap: wrap; }
    .subir .prev { width: 84px; aspect-ratio: 210 / 297; border: 1px dashed var(--line); border-radius: 6px; background: #fff center / cover no-repeat; display: grid; place-items: center; color: var(--faint); font-size: 22px; }

    /* Vista previa */
    .hoja { position: relative; width: 100%; background: #fff center / 100% 100% no-repeat; border-radius: 4px; box-shadow: 0 1px 2px rgba(16,24,40,.08), 0 14px 40px rgba(16,24,40,.14); overflow: hidden; }
    .hoja .area { position: absolute; display: flex; flex-direction: column; font-size: 6.4px; line-height: 1.35; color: #111; }
    .hoja .area.guia { outline: 1px dashed rgba(37, 99, 235, .55); }
    .hoja .hd { display: flex; justify-content: space-between; align-items: center; padding: 9px 11px; border-radius: 5px; }
    .hoja .hd img { height: 15px; width: auto; max-width: 80px; object-fit: contain; }
    .hoja .hd .nm { font-weight: 700; font-size: 9px; }
    .hoja .hd .ct { text-align: right; font-size: 5.4px; }
    .hoja .hd.centro { flex-direction: column; gap: 4px; text-align: center; }
    .hoja .hd.centro .ct { text-align: center; }
    .hoja .dt { display: flex; justify-content: space-between; margin: 9px 0 12px; }
    .hoja table { width: 100%; border-collapse: collapse; }
    .hoja th { text-align: left; padding: 3px 4px; font-weight: 700; }
    .hoja td { padding: 6px 4px; }
    .hoja .r { text-align: right; }
    .hoja .tt td { padding: 3px 4px; }
    .hoja .sec { margin-top: 10px; font-weight: 700; font-size: 7px; }
    .hoja .lin { height: 3px; background: #e6e8ec; border-radius: 2px; margin-top: 4px; }
    @media (max-width: 1199.98px) { .doc-editor { grid-template-columns: minmax(0, 1fr); } .doc-editor .vista { position: static; max-width: 440px; } }
    @media (max-width: 575.98px) { .plantillas { grid-template-columns: repeat(2, minmax(0, 1fr)); } .margenes { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>
@endpush

@section('contenido')
<div class="page-head">
    <div>
        <h1>Diseño de cotización</h1>
        <p class="sub">Elige un diseño listo o sube tu hoja membretada. Se aplica al PDF y a la página que abre tu cliente.</p>
    </div>
    <a href="{{ route('admin.negocio.documento.muestra') }}" target="_blank" rel="noopener" class="btn btn-borde" data-recargar><i class="bi bi-file-earmark-pdf me-1"></i> Ver PDF de muestra</a>
</div>

<form method="post" action="{{ route('admin.negocio.documento.guardar') }}" enctype="multipart/form-data" class="doc-editor" x-data="disenoDoc({{ Js::from($estado) }})">
    @csrf
    <div>
        <section class="panel panel-body bloque">
            <h2>Hoja membretada <span class="secundario fw-normal">(opcional)</span></h2>
            <p>Si ya tienes una hoja con tu logo, datos y diseño, súbela y la cotización se escribe encima. PNG o JPG del tamaño de la hoja; también PDF.</p>
            <div class="subir">
                <div class="prev" :style="membrete && !quitar ? `background-image:url(${membrete})` : ''"><i class="bi bi-file-earmark-image" x-show="!membrete || quitar"></i></div>
                <div class="d-grid gap-2">
                    <label class="btn btn-borde mb-0"><i class="bi bi-upload me-1"></i> <span x-text="membrete && !quitar ? 'Cambiar hoja' : 'Subir hoja membretada'"></span>
                        <input type="file" name="membrete" accept="image/png,image/jpeg,application/pdf" class="visually-hidden" @change="elegir"></label>
                    <template x-if="membrete">
                        <label class="small d-flex gap-2 align-items-center"><input type="checkbox" name="quitar_membrete" value="1" x-model="quitar"> Quitar la hoja membretada</label>
                    </template>
                </div>
            </div>
            @error('membrete')<div class="text-danger small mt-2">{{ $message }}</div>@enderror

            <div x-show="membrete && !quitar" x-cloak class="mt-3 pt-3" style="border-top: 1px solid var(--line)">
                <span class="form-label d-block">Espacio libre para la cotización <span class="secundario fw-normal">(en milímetros; la línea punteada de la vista previa)</span></span>
                <div class="margenes">
                    <div><label for="m-sup">Arriba</label><input id="m-sup" type="number" name="sup" min="5" max="120" step="1" class="form-control num" x-model.number="sup"></div>
                    <div><label for="m-inf">Abajo</label><input id="m-inf" type="number" name="inf" min="5" max="80" step="1" class="form-control num" x-model.number="inf"></div>
                    <div><label for="m-izq">Izquierda</label><input id="m-izq" type="number" name="izq" min="5" max="50" step="1" class="form-control num" x-model.number="izq"></div>
                    <div><label for="m-der">Derecha</label><input id="m-der" type="number" name="der" min="5" max="50" step="1" class="form-control num" x-model.number="der"></div>
                </div>
                <div class="d-grid gap-1 mt-3">
                    <label class="d-flex gap-2 align-items-center small m-0"><input type="hidden" name="en_todas" value="0"><input type="checkbox" class="form-check-input m-0" name="en_todas" value="1" x-model="en_todas"> Usar la hoja en todas las páginas (si no, solo en la primera)</label>
                    <label class="d-flex gap-2 align-items-center small m-0"><input type="hidden" name="con_encabezado" value="0"><input type="checkbox" class="form-check-input m-0" name="con_encabezado" value="1" x-model="con_encabezado"> Poner también el encabezado del diseño (si tu hoja no trae logo ni datos)</label>
                </div>
            </div>
            <template x-if="!membrete || quitar">
                <div><input type="hidden" name="sup" :value="sup"><input type="hidden" name="inf" :value="inf"><input type="hidden" name="izq" :value="izq"><input type="hidden" name="der" :value="der">
                    <input type="hidden" name="en_todas" :value="en_todas ? 1 : 0"><input type="hidden" name="con_encabezado" :value="con_encabezado ? 1 : 0"></div>
            </template>
        </section>

        <section class="panel panel-body bloque">
            <h2>Diseño</h2>
            <p x-text="membrete && !quitar ? 'Con hoja membretada, el diseño define la tabla, los totales y los títulos.' : '¿No tienes formato o no te gusta el tuyo? Elige uno de estos.'"></p>
            <div class="plantillas" role="radiogroup" aria-label="Diseño">
                @foreach(DD::PLANTILLAS as $k => $pl)
                    <label title="{{ $pl['ayuda'] }}">
                        <input type="radio" name="plantilla" value="{{ $k }}" x-model="plantilla">
                        <span class="mini">
                            @if($k === 'clasica')<span class="h" :style="`background:${color}`"></span>
                            @elseif($k === 'moderna')<span class="h" :style="`background:${color}; border-radius:0`"></span>
                            @elseif($k === 'minimal')<span class="h" :style="`border-bottom:2px solid ${color}; border-radius:0`"></span>
                            @else<span class="h" :style="`border-bottom:3px double ${color}; border-radius:0; display:grid; place-items:center`"><span class="l c" style="width:40%"></span></span>@endif
                            <span class="l"></span><span class="l c"></span>
                            <span class="t" :style="{{ Js::from($k) }} === 'moderna' ? `background:${color}` : ({{ Js::from($k) }} === 'clasica' ? 'background:#eceef1' : `border-bottom:1.5px solid ${color}`)"></span>
                            <span class="l"></span><span class="l"></span><span class="l c"></span>
                        </span>
                        <span class="nom">{{ $pl['texto'] }}</span>
                    </label>
                @endforeach
            </div>
        </section>

        <section class="panel panel-body bloque">
            <h2>Color</h2>
            <p>El de tu marca. Se usa en el encabezado, la tabla y los totales.</p>
            <div class="colores">
                @foreach($colores as $col)
                    <button type="button" style="background: {{ $col }}" :class="{ activo: color === '{{ $col }}' }" @click="color = '{{ $col }}'" aria-label="Color {{ $col }}"></button>
                @endforeach
                <input type="color" name="color" x-model="color" aria-label="Otro color">
                <span class="num secundario small" x-text="color.toUpperCase()"></span>
            </div>
        </section>

        <section class="panel panel-body bloque">
            <div class="row g-4">
                <div class="col-md-7">
                    <h2>Letra</h2>
                    <div class="opciones-fila mt-2">
                        @foreach(DD::LETRAS as $k => $l)
                            <label><input type="radio" name="letra" value="{{ $k }}" x-model="letra"><span style="font-family: {{ $l['web'] }}">{{ $l['texto'] }}</span></label>
                        @endforeach
                    </div>
                </div>
                <div class="col-md-5">
                    <h2>Papel</h2>
                    <div class="opciones-fila mt-2">
                        @foreach(DD::PAPELES as $k => [$nombre])
                            <label><input type="radio" name="papel" value="{{ $k }}" x-model="papel"><span>{{ $nombre }}</span></label>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <div class="d-flex justify-content-end gap-2 mt-4">
            <button class="btn btn-primario px-4">Guardar diseño</button>
        </div>
    </div>

    {{-- Vista previa --}}
    <aside class="vista" aria-label="Vista previa">
        <div class="secundario small mb-2">Vista previa</div>
        <div class="hoja" :style="hojaEstilo">
            <div class="area" :class="{ guia: usaMembrete }" :style="areaEstilo">
                <template x-if="encabezado">
                    <div class="hd" :class="{ centro: plantilla === 'elegante' }" :style="hdEstilo">
                        <img :src="oscuro ? logoOscuro : logoClaro" alt="" x-show="oscuro ? logoOscuro : logoClaro">
                        <span class="nm" x-show="!(oscuro ? logoOscuro : logoClaro)">{{ config('vandu.marca.nombre') }}</span>
                        <span class="ct"><b>{{ $emisor['nombre'] ?? '' }}</b><br>{{ $emisor['telefono'] ?? '' }}<br>{{ $emisor['email'] ?? '' }}</span>
                    </div>
                </template>
                <div class="dt"><span><b>Ana Pérez</b><br>Empresa del Cliente</span><span class="r"><b>16 de octubre de 2026</b><br>Cotización</span></div>
                <table>
                    <thead><tr><th :style="thEstilo">Concepto</th><th :style="thEstilo" class="r">Cant.</th><th :style="thEstilo" class="r">Importe</th></tr></thead>
                    <tbody>
                        <tr :style="filaEstilo"><td><b>Producto principal</b><br>Medidas y materiales</td><td class="r">2</td><td class="r">$6,980.00</td></tr>
                        <tr :style="filaEstilo"><td><b>Instalación</b><br>Dentro de la ciudad</td><td class="r">1</td><td class="r">$650.00</td></tr>
                        <tr class="tt"><td></td><td class="r" :style="totEstilo">Subtotal</td><td class="r" :style="totEstilo">$7,630.00</td></tr>
                        <tr class="tt"><td></td><td class="r" :style="totFinal"><b>Total</b></td><td class="r" :style="totFinal"><b>$8,850.80</b></td></tr>
                    </tbody>
                </table>
                <div class="sec" :style="tituloEstilo">Consideraciones</div>
                <div class="lin"></div><div class="lin" style="width: 80%"></div><div class="lin" style="width: 65%"></div>
            </div>
        </div>
        <p class="secundario small mt-3 mb-0" x-show="usaMembrete" x-cloak>La línea punteada es donde se escribe la cotización; ajústala para que no se encime con tu membrete.</p>
    </aside>
</form>
@endsection

@push('scripts')
<script>
function disenoDoc(d) {
    return {
        ...d,
        elegir(e) {
            const f = e.target.files[0];
            if (!f) return;
            this.quitar = false;
            // Un PDF no se puede previsualizar aquí: se ve al guardar
            this.membrete = f.type.startsWith('image/') ? URL.createObjectURL(f) : null;
        },
        get usaMembrete() { return !!this.membrete && !this.quitar; },
        get encabezado() { return !this.usaMembrete || this.con_encabezado; },
        get oscuro() { return ['clasica', 'moderna'].includes(this.plantilla) && this.tinta === '#ffffff'; },
        get tinta() {
            const n = parseInt(this.color.slice(1), 16), r = n >> 16, g = (n >> 8) & 255, b = n & 255;
            return (0.299 * r + 0.587 * g + 0.114 * b) > 160 ? '#13161D' : '#ffffff';
        },
        get suave() {
            if (this.color === '#242424') return '#f3f3f3';
            const n = parseInt(this.color.slice(1), 16), m = (v) => Math.round(v + (255 - v) * .88);
            return `rgb(${m(n >> 16)}, ${m((n >> 8) & 255)}, ${m(n & 255)})`;
        },
        get papelMm() { return this.papeles[this.papel]; },
        get hojaEstilo() {
            return `aspect-ratio: ${this.papelMm[1]} / ${this.papelMm[2]}; font-family: ${this.letras[this.letra]};` + (this.usaMembrete ? `background-image: url(${this.membrete})` : '');
        },
        get areaEstilo() {
            const [, w, h] = this.papelMm;
            const m = this.usaMembrete ? [this.sup, this.der, this.inf, this.izq] : [13, 21, 18, 18];
            return `top:${m[0] / h * 100}%; right:${m[1] / w * 100}%; bottom:${m[2] / h * 100}%; left:${m[3] / w * 100}%`;
        },
        get hdEstilo() {
            const c = this.color, t = this.oscuro ? '#fff' : '#13161D';
            return {
                clasica: `background:${c}; color:${this.tinta}`,
                moderna: `background:${c}; color:${this.tinta}; border-radius:0`,
                minimal: `border-bottom:2px solid ${c}; border-radius:0; padding-left:0; padding-right:0; color:${t}`,
                elegante: `border-bottom:3px double ${c}; border-radius:0; color:${t}`,
            }[this.plantilla];
        },
        get thEstilo() {
            const c = this.color;
            return {
                clasica: `background:${this.suave}`,
                moderna: `background:${c}; color:${this.tinta}`,
                minimal: `border-bottom:1px solid ${c}`,
                elegante: `border-top:.75px solid ${c}; border-bottom:.75px solid ${c}; text-transform:uppercase; letter-spacing:.06em; font-size:5.6px`,
            }[this.plantilla];
        },
        get filaEstilo() { return this.plantilla === 'minimal' ? 'border-bottom:.5px solid #e3e4e8' : ''; },
        get totEstilo() { return `background:${this.suave}`; },
        get totFinal() { return this.color === '#242424' ? `background:${this.suave}` : `background:${this.color}; color:${this.tinta}`; },
        get tituloEstilo() { return this.plantilla === 'elegante' ? `color:${this.color}; text-transform:uppercase; letter-spacing:.06em` : ''; },
    };
}
</script>
@endpush
