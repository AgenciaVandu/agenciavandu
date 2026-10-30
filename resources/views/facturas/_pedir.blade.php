{{-- "Necesito factura": el cliente deja sus datos fiscales desde su enlace. Recibe $accion, $f (datos ya conocidos) y $pedida (fecha o null) --}}
@php
    $campos = ['rfc', 'razon_social', 'regimen_fiscal', 'cp_fiscal', 'uso_cfdi', 'email_factura'];
    $conError = collect($campos)->first(fn ($k) => $errors->has($k));
    $regimenes = config('vandu.sat.regimenes', []);
    $usos = config('vandu.sat.usos_cfdi', []);
@endphp
<style>
    .pf { background: #fff; border: 1px solid #E3E4E8; border-radius: 16px; padding: 18px; text-align: left; }
    .pf summary { cursor: pointer; font-weight: 700; font-size: 16px; list-style: none; display: flex; align-items: center; justify-content: space-between; gap: 10px; }
    .pf summary::-webkit-details-marker { display: none; }
    .pf summary::after { content: '+'; font-size: 22px; line-height: 1; color: #6B6880; }
    .pf[open] summary::after { content: '–'; }
    .pf .pf-sub { color: #6B6880; font-size: 14px; margin: 6px 0 0; }
    .pf .pf-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 12px; }
    .pf .pf-full { grid-column: 1 / -1; }
    .pf label { display: block; font-size: 14px; font-weight: 600; margin: 12px 0 6px; }
    .pf input, .pf select { width: 100%; font: inherit; font-size: 16px; padding: 11px 13px; border: 1px solid #E3E4E8; border-radius: 12px; background: #fff; color: inherit; }
    .pf input[aria-invalid="true"], .pf select[aria-invalid="true"] { border-color: #E5484D; }
    .pf .pf-err { background: #FDECEC; color: #B42318; border-radius: 12px; padding: 10px 12px; font-size: 14px; margin-top: 12px; }
    .pf .pf-btn { width: 100%; margin-top: 16px; padding: 13px 18px; border-radius: 12px; border: 0; background: #13161D; color: #fff; font: inherit; font-weight: 600; font-size: 16px; cursor: pointer; }
    .pf .pf-ok { margin-top: 10px; font-size: 14px; color: #047A4B; font-weight: 600; }
    @media (max-width: 480px) { .pf .pf-grid { grid-template-columns: 1fr; } }
</style>
<details class="pf" id="factura" @if($conError) open @endif>
    <summary>{{ $pedida ? 'Ya pediste tu factura' : '¿Necesitas factura?' }}</summary>
    <p class="pf-sub">
        @if($pedida)
            Recibimos tus datos el {{ $pedida->locale('es')->isoFormat('D [de] MMMM') }}. Te avisaremos por correo cuando esté lista. Si algo está mal, corrígelo y vuelve a enviar.
        @else
            Escribe los datos de tu constancia de situación fiscal y te la enviamos por correo.
        @endif
    </p>
    @if($conError)<div class="pf-err" role="alert">{{ $errors->first($conError) }}</div>@endif
    <form method="post" action="{{ $accion }}" class="pf-grid">
        @csrf
        <div>
            <label for="pf-rfc">RFC</label>
            <input id="pf-rfc" name="rfc" value="{{ old('rfc', $f['rfc'] ?? '') }}" required maxlength="13" autocomplete="off" autocapitalize="characters" style="text-transform: uppercase" @if($errors->has('rfc')) aria-invalid="true" @endif>
        </div>
        <div>
            <label for="pf-cp">Código postal fiscal</label>
            <input id="pf-cp" name="cp_fiscal" value="{{ old('cp_fiscal', $f['cp'] ?? '') }}" required maxlength="5" inputmode="numeric" autocomplete="postal-code" @if($errors->has('cp_fiscal')) aria-invalid="true" @endif>
        </div>
        <div class="pf-full">
            <label for="pf-razon">Nombre o razón social</label>
            <input id="pf-razon" name="razon_social" value="{{ old('razon_social', $f['razon_social'] ?? '') }}" required maxlength="200" placeholder="Como aparece en tu constancia, sin S.A. de C.V." @if($errors->has('razon_social')) aria-invalid="true" @endif>
        </div>
        <div class="pf-full">
            <label for="pf-regimen">Régimen fiscal</label>
            <select id="pf-regimen" name="regimen_fiscal" required @if($errors->has('regimen_fiscal')) aria-invalid="true" @endif>
                <option value="">Elige…</option>
                @foreach($regimenes as $k => $t)<option value="{{ $k }}" @selected(old('regimen_fiscal', $f['regimen'] ?? '') == $k)>{{ $k }} · {{ $t }}</option>@endforeach
            </select>
        </div>
        <div class="pf-full">
            <label for="pf-uso">Uso de la factura</label>
            <select id="pf-uso" name="uso_cfdi" required @if($errors->has('uso_cfdi')) aria-invalid="true" @endif>
                @foreach($usos as $k => $t)<option value="{{ $k }}" @selected(old('uso_cfdi', $f['uso'] ?? 'G03') == $k)>{{ $k }} · {{ $t }}</option>@endforeach
            </select>
        </div>
        <div class="pf-full">
            <label for="pf-email">Correo para recibirla</label>
            <input id="pf-email" type="email" name="email_factura" value="{{ old('email_factura', $f['email'] ?? '') }}" required maxlength="190" autocomplete="email" @if($errors->has('email_factura')) aria-invalid="true" @endif>
        </div>
        <div class="pf-full"><button class="pf-btn">{{ $pedida ? 'Actualizar mis datos' : 'Pedir mi factura' }}</button></div>
    </form>
</details>
