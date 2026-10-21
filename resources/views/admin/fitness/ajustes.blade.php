@extends('admin.layout')
@section('titulo', 'Reglas del gimnasio')

@section('contenido')
<div style="max-width: 820px">
    <div class="page-head"><div><h1>Reglas del gimnasio</h1><p class="sub">Cómo se reserva, se cancela y se entra a clase. Aplican al panel y al portal de tus atletas.</p></div></div>

    <form method="post" action="{{ route('admin.fitness-ajustes.guardar') }}" class="d-grid gap-3">
        @csrf
        @php $campo = function ($k, $label, $unidad, $ayuda = null, $max = 999) use ($a) { return compact('k', 'label', 'unidad', 'ayuda', 'max'); }; @endphp
        @foreach([
            'Reservaciones' => [
                $campo('dias_adelante', 'Se puede reservar con', 'días de anticipación', 'Hasta cuántos días hacia adelante ve y reserva el atleta.', 90),
                $campo('reservar_hasta', 'Cierran', 'minutos antes', '0 = hasta que empieza la clase.', 1440),
                $campo('horas_cancelar', 'Cancelar sin perder la clase', 'horas antes', 'Si cancela con menos tiempo, el crédito no se le regresa.', 168),
                $campo('max_por_dia', 'Clases al día por atleta', 'máximo', '0 = sin límite. Cada paquete puede tener el suyo.', 10),
            ],
            'Entrada con QR' => [
                $campo('checkin_antes', 'El QR sirve desde', 'minutos antes', null, 240),
                $campo('checkin_despues', 'Y hasta', 'minutos después de empezar', null, 240),
            ],
            'Avisos al atleta (notificación en su celular)' => [
                $campo('aviso_clase', 'Recordatorio de su clase', 'minutos antes', '0 = no avisar.', 1440),
                $campo('aviso_vence', 'Aviso de que su paquete vence', 'días antes', 'También le avisamos cuando le queda una sola clase. 0 = no avisar.', 30),
            ],
        ] as $titulo => $campos)
            <section class="panel panel-body">
                <h2 class="fs-6 fw-semibold mb-3">{{ $titulo }}</h2>
                <div class="row g-3">
                    @foreach($campos as $c)
                        <div class="col-md-6"><label class="form-label" for="aj-{{ $c['k'] }}">{{ $c['label'] }}</label>
                            <div class="input-group"><input id="aj-{{ $c['k'] }}" name="{{ $c['k'] }}" type="number" min="0" max="{{ $c['max'] }}" class="form-control num" value="{{ old($c['k'], $a[$c['k']]) }}" required><span class="input-group-text">{{ $c['unidad'] }}</span></div>
                            @if($c['ayuda'])<div class="secundario small mt-1">{{ $c['ayuda'] }}</div>@endif</div>
                    @endforeach
                </div>
                @if($loop->first)
                    <label class="form-check mt-3"><input type="checkbox" name="atleta_reserva" value="1" class="form-check-input" @checked(old('atleta_reserva', $a['atleta_reserva']))>
                        <span class="form-check-label">Los atletas pueden reservar y cancelar desde su portal</span></label>
                @endif
            </section>
        @endforeach
        <div class="d-flex justify-content-end"><button class="btn btn-primario px-4">Guardar reglas</button></div>
    </form>
</div>
@endsection
