<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>¡Nuevo Mail!</title>
</head>
<body>
    <h1>Nuevo correo</h1>
    <p>Este es el primer correo</p>
    <p><strong>Nombre:</strong>
        {{$dataform ['name']}}
    </p>
    <p><strong>Apellido:</strong>
        {{$dataform ['lastname']}}
    </p>
    <p><strong>Teléfono:</strong>
        {{$dataform ['phone']}}
    </p>
    <p><strong>email:</strong>
        {{$dataform ['email']}}
    </p>
    <p><strong>Servicio:</strong>
        {{$dataform ['web', 'photo', 'digital', 'impresos']}}
    </p>
    <p><strong>Presupuesto:</strong>
        {{$dataform ['30k', '50k', '100k']}}
    </p>
</body>
</html>