@php
    $le = $licenciaEstado ?? null;
    $yo = auth()->user();
    // Solo lectura lo ven todos (no pueden guardar); lo demás, quien administra el negocio o la plataforma
    $mostrar = $le && in_array($le->clave, ['por_vencer', 'gracia', 'lectura', 'mantenimiento'], true)
        && ($le->clave === 'lectura' || $yo?->plataforma || $yo?->esSuperAdmin());
    $pago = $mostrar ? \App\Support\Licencias\Estado::datosPago() : [];
@endphp
@if($mostrar)
    <div class="aviso lic-aviso lic-{{ $le->clave }}" role="{{ in_array($le->clave, ['gracia', 'lectura'], true) ? 'alert' : 'status' }}">
        <i class="bi {{ $le->clave === 'lectura' ? 'bi-lock' : 'bi-calendar-event' }} align-self-start mt-1"></i>
        <div>
            <b>{{ \App\Support\Licencias\Estado::frase($le) }}</b>
            @php
                $cuentaTxt = '';
                if (! empty($pago['clabe'])) {
                    $cuentaTxt = 'transferencia a la CLABE ' . $pago['clabe'] . (! empty($pago['banco']) ? ' (' . $pago['banco'] . ')' : '') . (! empty($pago['titular']) ? ' a nombre de ' . $pago['titular'] : '') . '.';
                }
            @endphp
            @if($cuentaTxt !== '' || ! empty($pago['whatsapp']))
                <div class="small mt-1">
                    Para renovar: {{ $cuentaTxt }}
                    @if(! empty($pago['whatsapp']))
                        <a href="https://wa.me/{{ preg_replace('/\D/', '', $pago['whatsapp']) }}?text={{ rawurlencode('Hola, quiero renovar el plan de ' . (\App\Support\Cuentas::actual()?->nombre ?? 'mi negocio') . '.') }}" target="_blank" rel="noopener" class="fw-semibold">Mandar comprobante por WhatsApp</a>
                    @endif
                </div>
            @endif
        </div>
    </div>
    <style>
        .lic-aviso { display: flex; gap: 10px; align-items: flex-start; }
        .lic-por_vencer, .lic-mantenimiento { background: var(--amber-soft); color: var(--amber); border: 1px solid #F5DDB0; }
        .lic-gracia, .lic-lectura { background: var(--red-soft); color: var(--red); border: 1px solid #F6CFCB; }
        .lic-aviso a { color: inherit; text-decoration: underline; }
    </style>
@endif
