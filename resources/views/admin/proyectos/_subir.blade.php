{{-- Subida con barra de progreso. Params: $p (proyecto), $grupo, $etapaId (opcional), $texto, $accept (opcional), $grande (bool) --}}
<div x-data="subidor('{{ route('admin.proyectos.subir', $p) }}', '{{ $grupo }}', {{ $etapaId ?? 'null' }})"
     class="subir {{ ! empty($grande) ? 'subir-grande' : '' }}" :class="{ arrastrando: arrastrando }"
     @dragover.prevent="arrastrando = true" @dragleave.prevent="arrastrando = false" @drop.prevent="arrastrando = false; enviar($event.dataTransfer.files)">
    <label class="subir-zona" x-show="!subiendo">
        <input type="file" multiple class="visually-hidden" @change="enviar($event.target.files)" @if(! empty($accept)) accept="{{ $accept }}" @endif>
        <i class="bi bi-cloud-arrow-up"></i>
        <span><b>{{ $texto }}</b> @if(! empty($grande))<br><span class="secundario">o arrastra los archivos aquí @if(\App\Support\Dropbox\Dropbox::conectado())· se guardan en tu Dropbox, sin límite de tamaño @endif</span>@endif</span>
    </label>
    <div class="subir-progreso" x-show="subiendo" x-cloak role="status">
        <div class="d-flex justify-content-between small mb-1"><span x-text="'Subiendo ' + cuantos + (cuantos === 1 ? ' archivo' : ' archivos') + '…'"></span><span class="num" x-text="pct + '%'"></span></div>
        <div class="barra"><span :style="'width:' + pct + '%'"></span></div>
        <div class="secundario text-truncate mt-1" style="font-size:12px" x-show="detalle" x-text="detalle"></div>
    </div>
    <div class="text-danger small mt-2" x-show="error" x-text="error" x-cloak role="alert"></div>
</div>
