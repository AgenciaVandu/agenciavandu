{{--
    Vista previa de un post "como se ve" en Instagram, Facebook, TikTok y LinkedIn.
    Se usa dentro de un componente Alpine que mezcla window.redesVista() y tiene `post` y `perfiles`.
    Es una simulación del diseño de cada red para revisar y aprobar; no son capturas de las apps.
--}}
<div class="pv" x-effect="pvAjustar()">
    <div class="pv-redes" role="tablist" aria-label="Ver en" x-show="pvRedes().length > 1">
        <template x-for="r in pvRedes()" :key="r">
            <button type="button" role="tab" :aria-selected="pvRed === r" :class="pvRed === r && 'activo'" @click="pvRed = r; pvI = 0">
                <i class="bi" :class="pvInfo(r).icono"></i> <span x-text="pvInfo(r).nombre"></span>
            </button>
        </template>
    </div>

    <div class="pv-tel" :class="'pv-' + pvRed + ' pv-f-' + pvFormato()">
        {{-- Medio (foto, video o carrusel) reutilizable --}}
        <template x-if="false"><div></div></template>

        {{-- ================= INSTAGRAM / FACEBOOK: publicación o carrusel ================= --}}
        <template x-if="(pvRed === 'instagram' || pvRed === 'facebook') && !pvVertical()">
            <div>
                <div class="pv-cab">
                    <span class="pv-av" :class="pvRed === 'instagram' && 'aro'"><template x-if="pvPerfil().avatar"><img :src="pvPerfil().avatar" alt=""></template><span x-show="!pvPerfil().avatar" x-text="pvPerfil().iniciales"></span></span>
                    <div class="pv-quien">
                        <b x-text="pvRed === 'instagram' ? pvPerfil().usuario : pvPerfil().nombre"></b>
                        <small x-show="pvRed === 'facebook'"><span x-text="pvCuando()"></span> · <i class="bi bi-globe-americas"></i></small>
                    </div>
                    <i class="bi bi-three-dots pv-mas"></i>
                </div>
                <template x-if="pvRed === 'facebook'">
                    <div class="pv-txt fb"><span x-html="pvTexto(220)"></span><button type="button" class="pv-vermas" x-show="pvLargo(220)" @click="pvAbierto = !pvAbierto" x-text="pvAbierto ? 'Ver menos' : 'Ver más'"></button></div>
                </template>
                @include('redes._medio')
                <template x-if="pvRed === 'instagram'">
                    <div>
                        <div class="pv-acc ig"><i class="bi bi-heart"></i><i class="bi bi-chat"></i><i class="bi bi-send"></i><span class="pv-puntos" x-show="pvMedios().length > 1"><template x-for="(m, k) in pvMedios()" :key="k"><span :class="k === pvI && 'on'"></span></template></span><i class="bi bi-bookmark ms-auto"></i></div>
                        <div class="pv-txt"><b x-text="pvPerfil().usuario"></b> <span x-html="pvTexto(125)"></span><button type="button" class="pv-vermas gris" x-show="pvLargo(125)" @click="pvAbierto = !pvAbierto" x-text="pvAbierto ? 'menos' : 'más'"></button></div>
                        <div class="pv-fecha" x-text="pvCuando(true)"></div>
                    </div>
                </template>
                <template x-if="pvRed === 'facebook'">
                    <div class="pv-acc fb"><span><i class="bi bi-hand-thumbs-up"></i> Me gusta</span><span><i class="bi bi-chat"></i> Comentar</span><span><i class="bi bi-share"></i> Compartir</span></div>
                </template>
            </div>
        </template>

        {{-- ================= Vertical: reel, historia y TikTok ================= --}}
        <template x-if="pvVertical()">
            <div class="pv-vert">
                @include('redes._medio', ['vertical' => true])
                <div class="pv-sombra"></div>
                <template x-if="pvFormato() === 'historia'">
                    <div class="pv-hist">
                        <div class="pv-barras"><template x-for="(m, k) in pvMedios().length ? pvMedios() : [1]" :key="k"><span :class="k <= pvI && 'on'"></span></template></div>
                        <div class="pv-hist-cab"><span class="pv-av mini"><template x-if="pvPerfil().avatar"><img :src="pvPerfil().avatar" alt=""></template><span x-show="!pvPerfil().avatar" x-text="pvPerfil().iniciales"></span></span><b x-text="pvRed === 'instagram' ? pvPerfil().usuario : pvPerfil().nombre"></b><small>2 h</small></div>
                        <div class="pv-hist-pie"><span>Enviar mensaje</span><i class="bi bi-heart"></i><i class="bi bi-send"></i></div>
                    </div>
                </template>
                <template x-if="pvFormato() !== 'historia'">
                    <div>
                        <div class="pv-top" x-show="pvRed === 'tiktok'"><span>Siguiendo</span><b>Para ti</b></div>
                        <div class="pv-top izq" x-show="pvRed !== 'tiktok'"><b>Reels</b></div>
                        <div class="pv-lado">
                            <span class="pv-av mini" x-show="pvRed === 'tiktok'"><template x-if="pvPerfil().avatar"><img :src="pvPerfil().avatar" alt=""></template><span x-show="!pvPerfil().avatar" x-text="pvPerfil().iniciales"></span></span>
                            <span><i class="bi bi-heart-fill"></i><small>Me gusta</small></span>
                            <span><i class="bi bi-chat-fill" :class="pvRed !== 'tiktok' && 'bi-chat'"></i><small>Comentar</small></span>
                            <span x-show="pvRed === 'tiktok'"><i class="bi bi-bookmark-fill"></i><small>Guardar</small></span>
                            <span><i class="bi" :class="pvRed === 'tiktok' ? 'bi-reply-fill' : 'bi-send'"></i><small>Compartir</small></span>
                        </div>
                        <div class="pv-abajo">
                            <div class="pv-quien-v"><span class="pv-av mini" x-show="pvRed !== 'tiktok'"><template x-if="pvPerfil().avatar"><img :src="pvPerfil().avatar" alt=""></template><span x-show="!pvPerfil().avatar" x-text="pvPerfil().iniciales"></span></span>
                                <b x-text="(pvRed === 'tiktok' ? '@' : '') + (pvRed === 'facebook' ? pvPerfil().nombre : pvPerfil().usuario)"></b>
                                <span class="pv-seguir" x-show="pvRed !== 'tiktok'">Seguir</span></div>
                            <div class="pv-cap"><span x-html="pvTexto(70)"></span><button type="button" class="pv-vermas blanco" x-show="pvLargo(70)" @click="pvAbierto = !pvAbierto" x-text="pvAbierto ? 'menos' : 'más'"></button></div>
                            <div class="pv-audio"><i class="bi bi-music-note-beamed"></i> <span x-text="(pvRed === 'tiktok' ? 'sonido original - ' : 'Audio original · ') + pvPerfil().usuario"></span></div>
                        </div>
                    </div>
                </template>
            </div>
        </template>

        {{-- ================= LINKEDIN ================= --}}
        <template x-if="pvRed === 'linkedin' && !pvVertical()">
            <div>
                <div class="pv-cab li">
                    <span class="pv-av cuadro"><template x-if="pvPerfil().avatar"><img :src="pvPerfil().avatar" alt=""></template><span x-show="!pvPerfil().avatar" x-text="pvPerfil().iniciales"></span></span>
                    <div class="pv-quien">
                        <b x-text="pvPerfil().nombre"></b>
                        <small x-text="(pvPerfil().seguidores ? Number(pvPerfil().seguidores).toLocaleString('es-MX') + ' seguidores' : 'Página de empresa')"></small>
                        <small><span x-text="pvCuando()"></span> · <i class="bi bi-globe-americas"></i></small>
                    </div>
                    <span class="pv-li-seguir">+ Seguir</span>
                </div>
                <div class="pv-txt li"><span x-html="pvTexto(210)"></span><button type="button" class="pv-vermas gris" x-show="pvLargo(210)" @click="pvAbierto = !pvAbierto" x-text="pvAbierto ? '…menos' : '…más'"></button></div>
                @include('redes._medio')
                <div class="pv-acc li"><span><i class="bi bi-hand-thumbs-up"></i> Recomendar</span><span><i class="bi bi-chat-text"></i> Comentar</span><span><i class="bi bi-arrow-repeat"></i> Compartir</span><span><i class="bi bi-send-fill"></i> Enviar</span></div>
            </div>
        </template>
    </div>
    <p class="pv-nota">Vista previa aproximada de cómo se verá en <span x-text="pvInfo(pvRed).nombre"></span>.</p>
</div>
