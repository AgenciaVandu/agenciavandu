<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vandú Admin | Prospectos</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <style>
        body { 
            font-family: 'Montserrat', sans-serif; 
            background-color: #ffffff; /* Fondo blanco puro estilo Vandú */
            color: #1a1a1a;
        }
        .vandu-navbar {
            background-color: #000000;
            padding: 20px 0;
            margin-bottom: 50px;
        }
        .vandu-title {
            font-weight: 700;
            letter-spacing: -1px;
            text-transform: uppercase;
        }
        .card-vandu {
            border: 1px solid #eeeeee;
            border-radius: 0; /* Menos redondeado, más minimalista */
            box-shadow: 10px 10px 0px #f0f0f0; /* Sombra sólida tipo diseño moderno */
        }
        .table thead {
            background-color: #f8f9fa;
        }
        .table thead th {
            border-bottom: 2px solid #000;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 1px;
            color: #888;
        }
        .badge-vandu {
            border-radius: 0;
            padding: 6px 12px;
            font-weight: 400;
            background-color: #000;
            color: #fff;
        }
        .btn-vandu-outline {
            border: 2px solid #000;
            border-radius: 0;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 12px;
            transition: 0.3s;
        }
        .btn-vandu-outline:hover {
            background-color: #000;
            color: #fff;
        }
        .text-vandu-blue { color: #0047FF; } /* El azul eléctrico de la agencia */
    </style>
</head>
<body>

<nav class="vandu-navbar">
    <div class="container">
        <span class="text-white vandu-title h4">VANDÚ <span class="text-vandu-blue">ADMIN</span></span>
    </div>
</nav>

<div class="container">
    <div class="row align-items-end mb-4">
        <div class="col-md-8">
            <h1 class="vandu-title">Prospectos <span class="text-vandu-blue">Recientes</span></h1>
            <p class="text-muted">Gestión de leads y solicitudes de cotización.</p>
        </div>
        <div class="col-md-4 text-right">
            <a href="/" class="btn btn-vandu-outline">Ir al sitio web</a>
        </div>
    </div>

    <div class="card card-vandu">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Email / Teléfono</th>
                            <th>Servicio</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cotizaciones as $c)
                        <tr>
                            <td class="align-middle">
                                <span class="small font-weight-bold">{{ $c->created_at->format('d.m.Y') }}</span>
                            </td>
                            <td class="align-middle text-uppercase">
                                <strong>{{ $c->name }} {{ $c->lastname }}</strong>
                            </td>
                            <td class="align-middle">
                                <div class="small">{{ $c->email }}</div>
                                <div class="font-weight-bold">{{ $c->phone }}</div>
                            </td>
                            <td class="align-middle">
                                <span class="badge badge-vandu">{{ $c->service }}</span>
                            </td>
                            <td class="align-middle text-center">
                                <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $c->phone) }}" target="_blank" class="btn btn-sm btn-dark">
                                    <i class="fab fa-whatsapp"></i>
                                </a>
                                <a href="mailto:{{ $c->email }}" class="btn btn-sm btn-outline-dark">
                                    <i class="fas fa-envelope"></i>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">No hay registros nuevos.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</body>
</html>