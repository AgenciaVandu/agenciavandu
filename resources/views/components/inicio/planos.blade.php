{{-- Planos: lo que construimos, dibujado en líneas. Con el cursor (o solos, uno tras otro) se construyen. Títulos en config/inicio.php → planos. --}}
@php $I = \App\Support\Inicio::class; $planos = config('inicio.planos'); @endphp
<div class="vx-caja" id="trabajo">
    <figure class="vx-ventana" data-aparece>
        <div class="vx-ventana-barra">
            <span class="vx-puntos" aria-hidden="true"><i></i><i></i><i></i></span>
            <span class="vx-ventana-titulo">Lo que construimos</span>
            <span data-planos-contador>01 / 05</span>
        </div>
<div class="vx-planos" data-planos>

<div class="vx-plano" data-plano="0" data-cur="Construir" style="grid-row:span 2">
<svg viewBox="0 0 520 470" preserveAspectRatio="xMidYMid meet" aria-hidden="true">
<rect class="g" x="20" y="20" width="480" height="430" rx="8"/><line class="g" x1="20" y1="50" x2="500" y2="50"/>
<rect class="a" pathLength="1" x="20" y="20" width="480" height="430" rx="8"/>
<rect class="g" x="44" y="68" width="56" height="14" rx="2"/><rect class="tx" x="330" y="72" width="26" height="6"/><rect class="tx" x="366" y="72" width="26" height="6" style="transition-delay:0.08s"/><rect class="tx" x="402" y="72" width="26" height="6" style="transition-delay:0.16s"/><rect class="g" x="440" y="64" width="40" height="20" rx="4"/>
<rect class="tx" x="44" y="118" width="230" height="22" style="transition-delay:0.24s"/><rect class="tx" x="44" y="148" width="180" height="22" style="transition-delay:0.32s"/>
<rect class="tx" x="44" y="186" width="200" height="7" style="transition-delay:0.40s"/><rect class="tx" x="44" y="200" width="160" height="7" style="transition-delay:0.48s"/>
<rect class="g" x="44" y="226" width="118" height="34" rx="5"/><rect class="f" x="44" y="226" width="118" height="34" rx="5" style="transition-delay:1.12s"/>
<rect class="g" x="300" y="112" width="176" height="148" rx="4"/><line class="g" x1="300" y1="112" x2="476" y2="260"/><line class="g" x1="476" y1="112" x2="300" y2="260"/>
<rect class="a" pathLength="1" x="300" y="112" width="176" height="148" rx="4" style="transition-delay:0.24s"/><rect class="ft" x="300" y="112" width="176" height="148" rx="4" style="transition-delay:0.48s"/>
<rect class="g" x="44" y="292" width="128" height="134" rx="4"/><rect class="g" x="196" y="292" width="128" height="134" rx="4"/><rect class="g" x="348" y="292" width="128" height="134" rx="4"/>
<rect class="a" pathLength="1" x="44" y="292" width="128" height="134" rx="4" style="transition-delay:0.48s"/><rect class="a" pathLength="1" x="196" y="292" width="128" height="134" rx="4" style="transition-delay:0.64s"/><rect class="a" pathLength="1" x="348" y="292" width="128" height="134" rx="4" style="transition-delay:0.80s"/>
<rect class="tx" x="60" y="380" width="80" height="7" style="transition-delay:0.64s"/><rect class="tx" x="212" y="380" width="80" height="7" style="transition-delay:0.80s"/><rect class="tx" x="364" y="380" width="80" height="7" style="transition-delay:0.96s"/>
<g class="cur"><path d="M0 0 L0 22 L6 16 L11 26 L15 24 L10 15 L18 15 Z" fill="#F5F6F7" stroke="#0B0C0E" stroke-width="1.5"/></g>
</svg>
<div class="vx-plano-pie"><span><b>{{ $I::n2(0) }}</b>&nbsp; {{ $planos[0][0] }}</span><span class="vx-plano-estado"><i>plano</i><i>{{ $planos[0][1] }}</i></span></div>
</div>

<div class="vx-plano" data-plano="1" data-cur="Construir">
<svg viewBox="0 0 300 210" preserveAspectRatio="xMidYMid meet" aria-hidden="true">
<rect class="g" x="14" y="12" width="272" height="188" rx="6"/><line class="g" x1="14" y1="36" x2="286" y2="36"/>
<path class="g" d="M252 20 h4 l4 10 h14 l3 -7 h-19"/><circle class="f" cx="280" cy="19" r="6" style="transition-delay:0.96s"/><text class="ap" x="280" y="22" font-size="8" text-anchor="middle" fill="#0B0C0E" font-family="Geist-semibold, sans-serif" style="transition-delay:0.96s">1</text>
<rect class="g" x="28" y="48" width="74" height="66" rx="3"/><rect class="g" x="113" y="48" width="74" height="66" rx="3"/><rect class="g" x="198" y="48" width="74" height="66" rx="3"/>
<rect class="g" x="28" y="122" width="74" height="66" rx="3"/><rect class="g" x="113" y="122" width="74" height="66" rx="3"/><rect class="g" x="198" y="122" width="74" height="66" rx="3"/>
<rect class="a" pathLength="1" x="28" y="48" width="74" height="66" rx="3"/><rect class="a" pathLength="1" x="113" y="48" width="74" height="66" rx="3" style="transition-delay:0.08s"/><rect class="a" pathLength="1" x="198" y="48" width="74" height="66" rx="3" style="transition-delay:0.16s"/>
<rect class="a" pathLength="1" x="28" y="122" width="74" height="66" rx="3" style="transition-delay:0.24s"/><rect class="a" pathLength="1" x="113" y="122" width="74" height="66" rx="3" style="transition-delay:0.32s"/><rect class="a" pathLength="1" x="198" y="122" width="74" height="66" rx="3" style="transition-delay:0.40s"/>
<rect class="ft" x="113" y="48" width="74" height="66" rx="3" style="transition-delay:0.72s"/>
<rect class="tx" x="36" y="96" width="40" height="5"/><rect class="tx" x="121" y="96" width="40" height="5" style="transition-delay:0.08s"/><rect class="tx" x="206" y="96" width="40" height="5" style="transition-delay:0.16s"/><rect class="tx" x="36" y="170" width="40" height="5" style="transition-delay:0.24s"/><rect class="tx" x="121" y="170" width="40" height="5" style="transition-delay:0.32s"/><rect class="tx" x="206" y="170" width="40" height="5" style="transition-delay:0.40s"/>
</svg>
<div class="vx-plano-pie"><span><b>{{ $I::n2(1) }}</b>&nbsp; {{ $planos[1][0] }}</span><span class="vx-plano-estado"><i>plano</i><i>{{ $planos[1][1] }}</i></span></div>
</div>

<div class="vx-plano" data-plano="2" data-cur="Construir">
<svg viewBox="0 0 300 210" preserveAspectRatio="xMidYMid meet" aria-hidden="true">
<rect class="g" x="20" y="18" width="146" height="176" rx="6"/>
<rect class="g" x="34" y="40" width="118" height="22" rx="3"/><rect class="g" x="34" y="72" width="118" height="22" rx="3"/><rect class="g" x="34" y="104" width="118" height="44" rx="3"/>
<rect class="tx" x="42" y="49" width="60" height="5"/><rect class="tx" x="42" y="81" width="74" height="5" style="transition-delay:0.24s"/><rect class="tx" x="42" y="113" width="90" height="5" style="transition-delay:0.48s"/><rect class="tx" x="42" y="123" width="70" height="5" style="transition-delay:0.56s"/>
<rect class="g" x="34" y="160" width="118" height="22" rx="4"/><rect class="f" x="34" y="160" width="118" height="22" rx="4" style="transition-delay:0.72s"/>
<path class="g" d="M152 171 C190 171 186 108 222 108" stroke-dasharray="3 4"/><path class="a" pathLength="1" d="M152 171 C190 171 186 108 222 108" style="transition-delay:0.80s"/>
<rect class="g" x="222" y="52" width="58" height="112" rx="10"/><rect class="a" pathLength="1" x="222" y="52" width="58" height="112" rx="10" style="transition-delay:0.96s"/>
<rect class="f" x="230" y="94" width="40" height="18" rx="6" style="transition-delay:1.20s"/><path class="ap" d="M236 103 l4 4 l8 -8" fill="none" stroke="#0B0C0E" stroke-width="2" style="transition-delay:1.20s"/>
</svg>
<div class="vx-plano-pie"><span><b>{{ $I::n2(2) }}</b>&nbsp; {{ $planos[2][0] }}</span><span class="vx-plano-estado"><i>plano</i><i>{{ $planos[2][1] }}</i></span></div>
</div>

<div class="vx-plano" data-plano="3" data-cur="Construir">
<svg viewBox="0 0 300 210" preserveAspectRatio="xMidYMid meet" aria-hidden="true">
<rect class="g" x="14" y="12" width="272" height="188" rx="6"/><line class="g" x1="70" y1="12" x2="70" y2="200"/>
<rect class="tx" x="24" y="28" width="34" height="6"/><rect class="tx" x="24" y="44" width="28" height="5" style="transition-delay:0.08s"/><rect class="tx" x="24" y="56" width="32" height="5" style="transition-delay:0.16s"/><rect class="tx" x="24" y="68" width="26" height="5" style="transition-delay:0.24s"/>
<rect class="g" x="84" y="26" width="58" height="34" rx="3"/><rect class="g" x="150" y="26" width="58" height="34" rx="3"/><rect class="g" x="216" y="26" width="58" height="34" rx="3"/>
<rect class="tx" x="92" y="40" width="34" height="9" style="transition-delay:0.16s"/><rect class="tx" x="158" y="40" width="30" height="9" style="transition-delay:0.24s"/><rect class="tx" x="224" y="40" width="38" height="9" style="transition-delay:0.32s"/>
<rect class="barra" x="92" y="90" width="18" height="96"/><rect class="barra" x="122" y="120" width="18" height="66" style="transition-delay:0.08s"/><rect class="barra" x="152" y="104" width="18" height="82" style="transition-delay:0.16s"/><rect class="barra" x="182" y="78" width="18" height="108" style="transition-delay:0.24s"/><rect class="barra" x="212" y="112" width="18" height="74" style="transition-delay:0.32s"/><rect class="barra" x="242" y="72" width="18" height="114" style="transition-delay:0.40s"/>
<line class="g" x1="84" y1="186" x2="274" y2="186"/>
</svg>
<div class="vx-plano-pie"><span><b>{{ $I::n2(3) }}</b>&nbsp; {{ $planos[3][0] }}</span><span class="vx-plano-estado"><i>plano</i><i>{{ $planos[3][1] }}</i></span></div>
</div>

<div class="vx-plano" data-plano="4" data-cur="Construir">
<svg viewBox="0 0 300 210" preserveAspectRatio="xMidYMid meet" aria-hidden="true">
<rect class="g" x="14" y="12" width="272" height="188" rx="6"/><line class="g" x1="14" y1="36" x2="286" y2="36"/>
<rect class="g" x="40" y="52" width="100" height="62" rx="4"/><rect class="g" x="160" y="52" width="100" height="62" rx="4"/><rect class="g" x="40" y="124" width="100" height="62" rx="4"/><rect class="g" x="160" y="124" width="100" height="62" rx="4"/>
<rect class="ft" x="40" y="52" width="100" height="62" rx="4"/><rect class="ft" x="160" y="52" width="100" height="62" rx="4" style="transition-delay:0.16s"/><rect class="ft" x="40" y="124" width="100" height="62" rx="4" style="transition-delay:0.32s"/><rect class="ft" x="160" y="124" width="100" height="62" rx="4" style="transition-delay:0.48s"/>
<circle class="g gira" cx="90" cy="83" r="16"/><rect class="g gira" x="194" y="67" width="32" height="32" style="transition-delay:0.16s"/><path class="g gira" d="M90 139 l18 30 h-36 Z" style="transition-delay:0.32s"/><path class="g gira" d="M210 139 l5 10 l11 2 l-8 8 l2 11 l-10 -5 l-10 5 l2 -11 l-8 -8 l11 -2 Z" style="transition-delay:0.48s"/>
<circle class="a" pathLength="1" cx="90" cy="83" r="16"/><rect class="a" pathLength="1" x="194" y="67" width="32" height="32" style="transition-delay:0.16s"/>
</svg>
<div class="vx-plano-pie"><span><b>{{ $I::n2(4) }}</b>&nbsp; {{ $planos[4][0] }}</span><span class="vx-plano-estado"><i>plano</i><i>{{ $planos[4][1] }}</i></span></div>
</div>


</div>
        <figcaption class="vx-ventana-pie">
            <span>Diseñados y programados por nosotros, sin plantillas.</span>
            <span class="vx-verde vx-solo-escritorio">Pasa el cursor sobre un plano</span>
        </figcaption>
        <div class="vx-barra-avance"><span data-planos-avance></span></div>
    </figure>
</div>
