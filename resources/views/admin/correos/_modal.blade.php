{{--
    Ventana para redactar y enviar un correo.
    Uso: @include('admin.correos._modal', ['ctxTipo' => 'presupuesto', 'ctxId' => $p->id])
    Ábrela desde cualquier botón con data-correo="clave_de_plantilla" (o data-correo="" para la primera).
--}}
@php
    $ctxCorreo = \App\Support\Correos::contexto($ctxTipo, $ctxId);
    $plantillasCorreo = \App\Support\Correos::plantillas($ctxCorreo);
    $hayPdf = (bool) $ctxCorreo['presupuesto'];
    $enlaceCorreo = \App\Support\Correos::enlace($ctxCorreo);
@endphp
<div x-data="correoVandu({{ Js::from(['plantillas' => $plantillasCorreo, 'previa' => route('admin.correos.vista-previa')]) }})"
     @abrir-correo.window="abrir($event.detail)" @keydown.escape.window="abierto && cerrar()">
    <div class="correo-velo" x-show="abierto" x-cloak x-transition.opacity @click.self="cerrar()">
        <form method="post" action="{{ route('admin.correos.enviar') }}" enctype="multipart/form-data" class="correo-ventana" x-ref="form" role="dialog" aria-modal="true" aria-labelledby="correo-titulo"
              @input.debounce.500ms="previsualizar()" @change="previsualizar()" @submit="revisarAdjuntos($event)">
            @csrf
            <input type="hidden" name="contexto_tipo" value="{{ $ctxTipo }}">
            <input type="hidden" name="contexto_id" value="{{ $ctxId }}">
            <input type="hidden" name="plantilla" :value="clave">

            <div class="correo-cabeza">
                <div>
                    <h2 id="correo-titulo">Enviar correo</h2>
                    <span class="secundario">{{ $ctxCorreo['cliente']?->empresa ?: $ctxCorreo['cliente']?->nombre }}@if($ctxCorreo['presupuesto']) · {{ $ctxCorreo['presupuesto']->folio }}@endif</span>
                </div>
                <button type="button" class="btn btn-fantasma btn-icono" @click="cerrar()" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
            </div>

            <div class="correo-cuerpo">
                <div class="correo-campos">
                    <div>
                        <span class="form-label d-block">Plantilla</span>
                        <div class="correo-plantillas" role="radiogroup" aria-label="Plantilla">
                            <template x-for="(pl, k) in plantillas" :key="k">
                                <button type="button" role="radio" :aria-checked="clave === k" :class="clave === k && 'activo'" @click="usar(k)">
                                    <i class="bi" :class="pl.icono"></i> <span x-text="pl.etiqueta"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-12">
                            <label class="form-label" for="correo-para">Para</label>
                            <div class="input-group">
                                <input id="correo-para" name="para" class="form-control" x-model="para" placeholder="cliente@empresa.com" required autocomplete="off">
                                <button type="button" class="btn btn-borde" x-show="!conCopia" @click="conCopia = true">CC</button>
                            </div>
                            <div class="secundario mt-1" style="font-size:12px" x-show="!para" x-cloak><i class="bi bi-exclamation-circle"></i> Este cliente no tiene correo guardado; escríbelo aquí.</div>
                        </div>
                        <div class="col-12" x-show="conCopia" x-cloak>
                            <label class="form-label" for="correo-cc">Con copia a</label>
                            <input id="correo-cc" name="cc" class="form-control" x-model="cc" placeholder="otra@empresa.com, una-mas@empresa.com">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="correo-asunto">Asunto</label>
                            <input id="correo-asunto" name="asunto" class="form-control" x-model="asunto" required maxlength="200">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="correo-encabezado">Encabezado <span class="text-secondary fw-normal">(el título grande del correo, opcional)</span></label>
                            <input id="correo-encabezado" name="titulo" class="form-control" x-model="titulo" maxlength="120">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="correo-mensaje">Mensaje</label>
                            <textarea id="correo-mensaje" name="cuerpo" class="form-control" rows="9" x-model="cuerpo" required></textarea>
                            <div class="secundario mt-1" style="font-size:12px">Deja una línea en blanco para separar párrafos. La firma se agrega sola.</div>
                        </div>
                    </div>

                    <div class="correo-opciones">
                        <label class="form-check"><input type="checkbox" class="form-check-input" name="incluir_resumen" value="1" x-model="resumen"> <span class="form-check-label">Recuadro de resumen</span></label>
                        @if($enlaceCorreo)
                            <label class="form-check d-flex align-items-center gap-2"><input type="checkbox" class="form-check-input mt-0" name="incluir_boton" value="1" x-model="conBoton"> <span class="form-check-label">Botón</span>
                                <input name="boton" class="form-control form-control-sm" style="width: 190px" x-model="boton" :disabled="!conBoton" aria-label="Texto del botón"></label>
                        @endif
                        <label class="form-check" x-show="plantillas[clave] && plantillas[clave].miniaturas" x-cloak><input type="checkbox" class="form-check-input" name="incluir_miniaturas" value="1" x-model="miniaturas"> <span class="form-check-label"><i class="bi bi-images"></i> Fotos de la galería en el correo</span></label>
                        <label class="form-check"><input type="checkbox" class="form-check-input" name="incluir_banco" value="1" x-model="banco"> <span class="form-check-label">Datos bancarios</span></label>
                        @if($ctxCorreo['presupuesto'] && \App\Support\Aceptacion::puedeResponder($ctxCorreo['presupuesto']))
                            <label class="form-check"><input type="checkbox" class="form-check-input" name="incluir_codigo" value="1" x-model="codigo"> <span class="form-check-label"><i class="bi bi-shield-lock"></i> Código para aceptar o pedir cambios en línea <span class="secundario">(vale 24 h)</span></span></label>
                        @endif
                        @if($hayPdf)
                            <label class="form-check"><input type="checkbox" class="form-check-input" name="adjuntar_pdf" value="1" x-model="pdf"> <span class="form-check-label"><i class="bi bi-paperclip"></i> Adjuntar PDF de {{ $ctxCorreo['presupuesto']->folio }}</span></label>
                        @endif
                    </div>

                    <div class="correo-adjuntos" :class="pideAdjuntos && !archivos.length && 'pide'">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <label class="btn btn-borde btn-sm mb-0"><i class="bi bi-paperclip me-1"></i> Adjuntar archivos
                                <input type="file" name="adjuntos[]" multiple accept=".pdf,.xml,.zip,.png,.jpg,.jpeg,.doc,.docx,.xls,.xlsx" class="d-none" x-ref="adjuntos" @change="elegirArchivos()">
                            </label>
                            <span class="secundario" style="font-size:12.5px" x-show="pideAdjuntos && !archivos.length">Adjunta el PDF y el XML de la factura</span>
                            <span class="secundario" style="font-size:12.5px" x-show="!pideAdjuntos && !archivos.length">PDF, XML, imágenes… hasta 20 MB en total</span>
                        </div>
                        <ul class="correo-archivos" x-show="archivos.length" x-cloak>
                            <template x-for="(a, i) in archivos" :key="a.nombre + i">
                                <li><i class="bi" :class="a.icono"></i> <span class="n" x-text="a.nombre"></span> <span class="secundario num" x-text="a.peso"></span>
                                    <button type="button" class="btn btn-fantasma btn-icono btn-sm" @click="quitarArchivo(i)" :aria-label="'Quitar ' + a.nombre"><i class="bi bi-x"></i></button></li>
                            </template>
                        </ul>
                        <div class="secundario mt-1" style="font-size:12px" x-show="pideAdjuntos && archivos.length && !(archivos.some(a => a.ext === 'pdf') && archivos.some(a => a.ext === 'xml'))" x-cloak>
                            <i class="bi bi-info-circle"></i> Normalmente la factura lleva el PDF <b>y</b> el XML.
                        </div>
                    </div>
                </div>

                <div class="correo-previa">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="form-label mb-0">Así lo verá tu cliente</span>
                        <span class="secundario" style="font-size:12px" x-show="cargando" x-cloak><span class="spinner-border spinner-border-sm"></span> Actualizando…</span>
                    </div>
                    <iframe x-ref="previa" title="Vista previa del correo" sandbox="allow-same-origin" loading="lazy"></iframe>
                </div>
            </div>

            <div class="correo-pie">
                <span class="secundario me-auto" style="font-size:12.5px"><i class="bi bi-reply"></i> Las respuestas llegan a {{ config('vandu.correo.responder_a') }}</span>
                <button type="button" class="btn btn-borde" @click="cerrar()">Cancelar</button>
                <button class="btn btn-primario px-4"><i class="bi bi-send me-1"></i> Enviar</button>
            </div>
        </form>
    </div>
</div>
