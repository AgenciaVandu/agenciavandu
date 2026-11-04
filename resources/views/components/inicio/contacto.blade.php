{{-- Contacto paso a paso: qué necesitas, nombre del negocio y para cuándo. Al final abre el formulario de WhatsApp con todo escrito (config/inicio.php → contacto). --}}
@php $I = \App\Support\Inicio::class; @endphp
<section class="vx-contacto" id="contacto">
    <div class="vx-caja vx-contacto-grid">
        <div>
            <h2 data-descifra>¿Platicamos?</h2>
            <p class="vx-contacto-txt" data-aparece>Cuéntanos qué necesitas y te decimos cuál es la mejor opción para tu negocio y tu presupuesto.</p>
        </div>
        <div class="vx-conv" data-conv data-aparece>
            <div class="vx-conv-pasos" aria-hidden="true"><i></i><i></i><i></i></div>

            <div class="vx-conv-paso" data-paso="0">
                <p class="vx-conv-n">1 de 3</p>
                <p class="vx-conv-p" id="vx-p1">¿Qué necesitas?</p>
                <div class="vx-ops" role="group" aria-labelledby="vx-p1">
                    @foreach (config('inicio.contacto.necesidades') as [$texto, $servicio])
                        <button type="button" class="vx-op" data-necesidad="{{ $texto }}" data-servicio="{{ $servicio }}" aria-pressed="false">{{ $texto }}</button>
                    @endforeach
                </div>
            </div>
            <div class="vx-conv-paso" data-paso="1" hidden>
                <p class="vx-conv-n">2 de 3</p>
                <label class="vx-conv-p" for="vx-negocio">¿Cómo se llama tu negocio?</label>
                <input id="vx-negocio" type="text" maxlength="120" autocomplete="organization" placeholder="Ej. Panadería La Espiga" data-negocio>
            </div>
            <div class="vx-conv-paso" data-paso="2" hidden>
                <p class="vx-conv-n">3 de 3</p>
                <p class="vx-conv-p" id="vx-p3">¿Para cuándo lo necesitas?</p>
                <div class="vx-ops" role="group" aria-labelledby="vx-p3">
                    @foreach (config('inicio.contacto.cuando') as $texto)
                        <button type="button" class="vx-op" data-cuando="{{ $texto }}" aria-pressed="false">{{ $texto }}</button>
                    @endforeach
                </div>
            </div>
            <div class="vx-conv-paso" data-paso="3" hidden>
                <p class="vx-conv-n">Tu mensaje, listo para enviar</p>
                <p class="vx-burbuja" data-conv-mensaje aria-live="polite"></p>
                <div class="vx-conv-botones">
                    <a class="vx-sig" href="{{ $I::whatsapp() }}" data-conv-enviar>Enviar por WhatsApp →</a>
                    <button type="button" class="vx-sig vx-sig-linea" data-llamar>Prefiero que me llamen</button>
                </div>
            </div>
            <div class="vx-conv-paso vx-conv-form" data-paso="4" hidden>
                <p class="vx-conv-p">Déjanos tus datos y te llamamos.</p>
                <x-form />
            </div>

            <div class="vx-conv-nav">
                <button type="button" class="vx-atras" data-conv-atras hidden>← Atrás</button>
                <button type="button" class="vx-sig" data-conv-sig disabled>Siguiente →</button>
            </div>
            <div class="vx-correo">
                <span>O escribe a</span>
                <input type="text" class="correo" value="{{ config('inicio.contacto.correo') }}" readonly aria-label="Correo de contacto">
                <button type="button" aria-label="Copiar correo"><img src="{{ asset('img/copy.svg') }}" class="copiado" width="14" alt=""></button>
                <span id="message" aria-live="polite"></span>
            </div>
        </div>
    </div>
</section>
