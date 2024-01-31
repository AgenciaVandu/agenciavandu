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
    <x-nav/>
    <main>
    {{$slot}}
    </main>
</body>
@stack('js')
</html>