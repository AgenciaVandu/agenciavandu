{{-- Foto, video o carrusel dentro de la vista previa (usa el estado pvI del componente) --}}
<div class="pv-media" :style="'aspect-ratio:' + pvAspecto({{ ! empty($vertical) ? 'true' : 'false' }})"
     @touchstart.passive="pvToque = $event.changedTouches[0].clientX" @touchend.passive="pvDeslizar($event.changedTouches[0].clientX)">
    <template x-if="!pvMedios().length">
        <div class="pv-vacio"><i class="bi bi-image"></i><span>Sin foto o video todavía</span></div>
    </template>
    <template x-for="(m, k) in pvMedios()" :key="m.id || k">
        <div class="pv-slide" x-show="k === pvI">
            <template x-if="m.tipo === 'video'"><video :src="m.url" muted loop playsinline autoplay preload="metadata"></video></template>
            <template x-if="m.tipo !== 'video'"><img :src="m.url" :alt="m.nombre"></template>
        </div>
    </template>
    <span class="pv-cuenta" x-show="pvMedios().length > 1 && pvRed !== 'instagram'" x-text="(pvI + 1) + '/' + pvMedios().length"></span>
    <span class="pv-cuenta ig" x-show="pvMedios().length > 1 && pvRed === 'instagram' && !pvVertical()" x-text="(pvI + 1) + '/' + pvMedios().length"></span>
    <button type="button" class="pv-flecha izq" x-show="pvMedios().length > 1 && pvI > 0" @click="pvI--" aria-label="Anterior"><i class="bi bi-chevron-left"></i></button>
    <button type="button" class="pv-flecha der" x-show="pvMedios().length > 1 && pvI < pvMedios().length - 1" @click="pvI++" aria-label="Siguiente"><i class="bi bi-chevron-right"></i></button>
</div>
