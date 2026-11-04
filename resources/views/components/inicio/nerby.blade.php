{{-- Nerby: somos aliados. Pestañas con sus funciones y una demo de lectura de tickets con IA (config/inicio.php → nerby). --}}
@php $I = \App\Support\Inicio::class; @endphp
<section class="vx-nerby" id="nerby">
    <div class="vx-caja">
        <div class="vx-nerby-sello" data-aparece>
            <img src="{{ asset('img/logo-vandu-blanco.svg') }}" alt="Agencia Vandu" height="22">
            <span class="vx-por" aria-hidden="true">×</span>
            <img src="{{ asset('img/inicio/nerby-logo-blanco.svg') }}" alt="Nerby" height="26">
            <span class="vx-nerby-aliados">Somos aliados de Nerby</span>
        </div>
        <div class="vx-encabezado">
            <h2 data-descifra>Que tu negocio trabaje para ti<span class="vx-morado">.</span></h2>
            <p class="vx-intro" data-aparece>Nerby junta ventas, cotizaciones, facturación y WhatsApp en un solo sistema, con inteligencia artificial que hace el trabajo pesado. En Vandu lo instalamos, lo configuramos y lo adaptamos a tu giro.</p>
        </div>
        <div class="vx-nerby-grid" data-nerby>
            <div>
                <div class="vx-nerby-tabs" role="tablist" aria-label="Funciones de Nerby">
                    @foreach (config('inicio.nerby.funciones') as $k => $f)
                        <button type="button" role="tab" id="vx-ntab-{{ $k }}" aria-controls="vx-npanel-{{ $k }}" aria-selected="{{ $k === 0 ? 'true' : 'false' }}" @class(['vx-ntab', 'vx-on' => $k === 0]) data-ntab="{{ $k }}" data-ventana="{{ $f['ventana'] }}">
                            <span class="vx-ntab-n">{{ $I::n2($k) }}</span>
                            <span class="vx-ntab-t">{{ $f['titulo'] }} @if ($f['ia'])<span class="vx-ia">IA</span>@endif</span>
                            <span></span>
                            <span class="vx-ntab-d">{{ $f['texto'] }}</span>
                        </button>
                    @endforeach
                </div>
                <div class="vx-nerby-cta">
                    <a href="{{ $I::whatsapp() }}" data-servicio="Otro" data-mensaje="Quiero una demo de Nerby." class="vx-nbtn">Pide tu demo de Nerby <span aria-hidden="true">→</span></a>
                    <img src="{{ asset('img/inicio/nerby-mascota.webp') }}" alt="" width="64" height="75" loading="lazy">
                </div>
            </div>

            <figure class="vx-nerby-ventana" data-aparece>
                <div class="vx-nerby-barra">
                    <img src="{{ asset('img/inicio/nerby-logo-blanco.svg') }}" alt="" height="16"><span data-nerby-ventana>Gastos</span><span class="vx-nerby-ej">Ejemplo</span>
                </div>

                <div class="vx-npanel" id="vx-npanel-0" role="tabpanel" aria-labelledby="vx-ntab-0" data-npanel="0">
                    <div class="vx-ticket-grid" data-ticket>
                        <div class="vx-ticket" aria-label="Ticket de ejemplo de Ferretería Itzimná por $791.00">
                            <p class="vx-ticket-c"><b>FERRETERÍA ITZIMNÁ</b></p>
                            <p class="vx-ticket-c vx-ticket-gris">Mérida, Yuc.</p>
                            <p><span>28/10/2026</span><span>14:32</span></p>
                            <p class="vx-ticket-sep"><span>2 CINTA MASKING</span><span>90.00</span></p>
                            <p><span>1 BROCHA 3"</span><span>89.00</span></p>
                            <p><span>1 PINTURA 4 L</span><span>612.00</span></p>
                            <p class="vx-ticket-sep"><span>SUBTOTAL</span><span>681.90</span></p>
                            <p><span>IVA 16%</span><span>109.10</span></p>
                            <p><b>TOTAL</b><b>791.00</b></p>
                            <p class="vx-ticket-borroso">PAGO: T•••••</p>
                            <span class="vx-escaneo" aria-hidden="true"></span>
                        </div>
                        <div>
                            <p class="vx-nerby-gris">Nuevo gasto · leído de la foto</p>
                            @foreach (config('inicio.nerby.ticket') as $k => [$etiqueta, $valor])
                                <div class="vx-campo"><span>{{ $etiqueta }}</span><span class="vx-campo-val" data-campo="{{ $k }}" data-valor="{{ $valor }}"></span></div>
                            @endforeach
                            <p class="vx-aviso" data-ticket-aviso aria-live="polite"></p>
                            <div class="vx-ticket-botones">
                                <button type="button" class="vx-nbtn" data-ticket-leer>Leer ticket con IA</button>
                                <button type="button" class="vx-nbtn vx-nbtn-linea" data-ticket-ok hidden>Confirmar gasto</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="vx-npanel" id="vx-npanel-1" role="tabpanel" aria-labelledby="vx-ntab-1" data-npanel="1" hidden>
                    <img src="{{ asset('img/inicio/nerby-facturas.webp') }}" alt="Pantalla de facturación de Nerby" loading="lazy" width="1400" height="875">
                </div>
                <div class="vx-npanel vx-npanel-movil" id="vx-npanel-2" role="tabpanel" aria-labelledby="vx-ntab-2" data-npanel="2" hidden>
                    <img src="{{ asset('img/inicio/nerby-movil.webp') }}" alt="Nerby en el celular" loading="lazy" width="600" height="1319">
                </div>
            </figure>
        </div>
    </div>
</section>
