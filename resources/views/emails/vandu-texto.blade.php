@if($titulo){!! $titulo !!}

@endif
@foreach($parrafos as $p){!! $p !!}

@endforeach
@if($resumen)
@foreach($resumen as $k => $v){!! $k !!}: {!! $v !!}
@endforeach

@endif
@if($miniaturas)Tu galería tiene {!! count($miniaturas) + $mas !!} archivos listos para ver y descargar.

@endif
@if($boton && $url){!! $boton !!}: {!! $url !!}

@endif
@if($banco)Datos para tu pago
@foreach($banco as $k => $v){!! $k !!}: {!! $v !!}
@endforeach

@endif
--
{!! config('vandu.emisor.nombre') !!}
Agencia Vandu · {!! config('vandu.emisor.telefono') !!} · {!! config('vandu.emisor.sitio') !!}
