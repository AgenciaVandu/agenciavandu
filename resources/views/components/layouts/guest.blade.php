<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{$titulo ?? 'Diseño y desarrollo web a medida'}} - Agencia Vandu</title>
    <meta name="description" content="{{$metaDescription ?? 'sin meta'}}">
    @stack('estilos')
</head>

<body class="body">
    <div class="flotante">
        <div class="mensaje">Bienvenido a Vandú 😊</div>
        <div class="notificacion">
            <img src="{{asset('/img/bell-solid.svg')}}" class="imagen" alt="">
        </div>
        <a href="" class="btn-contacto">
            <img src="{{asset('/img/message-solid.svg')}}" width="40" alt="mensaje">
        </a>
    </div>
    <x-nav />
    <main>
        {{$slot}}
    </main>
    <x-footer />
</body>
@stack('js')

</html>