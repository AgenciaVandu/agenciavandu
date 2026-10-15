{{-- Campos de una razón social. Params: $r (ClienteRazon o null), $pre (prefijo para ids) --}}
@php $v = fn ($k) => old($k, $r?->{$k}); @endphp
<div class="row g-2">
    <div class="col-12">
        <label class="form-label" for="{{ $pre }}-nombre">Nombre de la empresa <span class="text-secondary fw-normal">(como la conoces)</span></label>
        <input name="nombre" id="{{ $pre }}-nombre" class="form-control form-control-sm" value="{{ $v('nombre') }}" maxlength="255" required placeholder="p. ej. Hotel X Playa">
    </div>
    <div class="col-12">
        <label class="form-label" for="{{ $pre }}-razon">Razón social</label>
        <input name="razon_social" id="{{ $pre }}-razon" class="form-control form-control-sm" value="{{ $v('razon_social') }}" maxlength="255">
    </div>
    <div class="col-7">
        <label class="form-label" for="{{ $pre }}-rfc">RFC</label>
        <input name="rfc" id="{{ $pre }}-rfc" class="form-control form-control-sm num text-uppercase" value="{{ $v('rfc') }}" maxlength="15">
    </div>
    <div class="col-5">
        <label class="form-label" for="{{ $pre }}-cp">C.P. fiscal</label>
        <input name="cp_fiscal" id="{{ $pre }}-cp" class="form-control form-control-sm num" value="{{ $v('cp_fiscal') }}" maxlength="5" inputmode="numeric">
    </div>
    <div class="col-12">
        <label class="form-label" for="{{ $pre }}-reg">Régimen fiscal</label>
        <select name="regimen_fiscal" id="{{ $pre }}-reg" class="form-select form-select-sm">
            <option value="">—</option>
            @foreach(config('vandu.sat.regimenes') as $k => $t)<option value="{{ $k }}" @selected($v('regimen_fiscal') == $k)>{{ $k }} · {{ $t }}</option>@endforeach
        </select>
    </div>
    <div class="col-12">
        <label class="form-label" for="{{ $pre }}-uso">Uso de CFDI</label>
        <select name="uso_cfdi" id="{{ $pre }}-uso" class="form-select form-select-sm">
            <option value="">—</option>
            @foreach(config('vandu.sat.usos_cfdi') as $k => $t)<option value="{{ $k }}" @selected($v('uso_cfdi') == $k)>{{ $k }} · {{ $t }}</option>@endforeach
        </select>
    </div>
    <div class="col-12">
        <label class="form-label" for="{{ $pre }}-mail">Correo para la factura <span class="text-secondary fw-normal">(opcional)</span></label>
        <input type="email" name="email_factura" id="{{ $pre }}-mail" class="form-control form-control-sm" value="{{ $v('email_factura') }}" maxlength="255">
    </div>
</div>
