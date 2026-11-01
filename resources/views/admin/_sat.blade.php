{{-- Campo con búsqueda en el catálogo del SAT. Uso: @include('admin._sat', ['tipo' => 'productos'|'unidades', 'name' => 'clave_sat', 'valor' => '…', 'texto' => '…', 'nameTexto' => 'clave_sat_texto', 'id' => '…']) --}}
@include('admin._sat-js')
<div class="sat" x-data="satCampo({{ Js::from(['tipo' => $tipo, 'valor' => $valor ?? '', 'texto' => $texto ?? '']) }})" @click.outside="abierto = false">
    <input type="text" class="form-control {{ $clase ?? '' }}" @if(! empty($id)) id="{{ $id }}" @endif x-model="q" @input.debounce.300ms="buscar()" @focus="buscar()" @keydown="tecla($event)"
           autocomplete="off" placeholder="{{ $tipo === 'unidades' ? 'Escribe: pieza, servicio, kilo… o la clave (H87)' : 'Escribe: asesoría, playera, renta… o la clave de 8 números' }}"
           aria-label="{{ $tipo === 'unidades' ? 'Clave de unidad SAT' : 'Clave de producto o servicio SAT' }}">
    <input type="hidden" name="{{ $name }}" :value="valor">
    @if(! empty($nameTexto))<input type="hidden" name="{{ $nameTexto }}" :value="texto">@endif
    <div class="sat-lista" x-show="abierto && (opciones.length || ! buscando)" x-cloak>
        <template x-for="(o, n) in opciones" :key="o.clave"><button type="button" :class="n === i && 'on'" @mousedown.prevent="elegir(o)"><span class="k" x-text="o.clave"></span><span x-text="o.texto"></span></button></template>
        <div class="sat-vacio" x-show="! opciones.length && ! buscando">Sin resultados. Prueba con otra palabra o escribe la clave completa.</div>
    </div>
</div>
