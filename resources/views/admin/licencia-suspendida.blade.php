@php
    $f = fn ($d) => $d ? $d->locale('es')->isoFormat('D [de] MMMM [de] YYYY') : '';
    $logo = $cuenta->logo();
    $l = $e->licencia;
    $monto = $l ? $l->precio : null;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Acceso suspendido · {{ $cuenta->nombre }}</title>
    <style>
        :root { --ink: #13161D; --gris: #5F6675; --linea: #E4E6EB; --fondo: #F5F6F8; --marca: #4F46E5; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px 16px; background: var(--fondo); color: var(--ink);
               font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; font-size: 16px; line-height: 1.5; }
        .caja { background: #fff; border: 1px solid var(--linea); border-radius: 20px; padding: 32px 28px; max-width: 520px; width: 100%; display: grid; gap: 16px; }
        .logo { max-height: 44px; max-width: 180px; }
        h1 { font-size: 22px; margin: 0; letter-spacing: -.01em; }
        p { margin: 0; color: var(--gris); }
        .pago { background: var(--fondo); border-radius: 14px; padding: 16px 18px; display: grid; gap: 6px; font-size: 15px; }
        .pago b { color: var(--ink); } .num { font-variant-numeric: tabular-nums; letter-spacing: .02em; }
        .acciones { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 18px; border-radius: 12px; font: inherit; font-weight: 600; text-decoration: none; cursor: pointer; border: 1px solid var(--linea); background: #fff; color: var(--ink); }
        .btn.prim { background: #128C7E; border-color: #128C7E; color: #fff; }
        form { margin: 0; }
    </style>
</head>
<body>
<main class="caja">
    @if($logo)<img src="{{ $logo }}" alt="{{ $cuenta->nombre }}" class="logo">@endif
    <h1>El acceso al panel está suspendido</h1>
    <p>El plan de <b style="color: var(--ink)">{{ $cuenta->nombre }}</b> venció el {{ $f($e->vence) }}. <b style="color: var(--ink)">Toda tu información está guardada y completa</b>, y tus clientes pueden seguir abriendo sus cotizaciones y pedidos.</p>
    <p>En cuanto se registre tu pago, tu equipo vuelve a entrar con sus mismos usuarios.</p>
    @if(! empty($pago['clabe']) || ! empty($pago['banco']))
        <div class="pago">
            @if($monto)<div>Renovación {{ $l->periodo === 'anual' ? 'anual' : 'mensual' }}: <b class="num">${{ number_format($monto, 2) }}</b></div>@endif
            @if(! empty($pago['banco']))<div>Banco: <b>{{ $pago['banco'] }}</b></div>@endif
            @if(! empty($pago['titular']))<div>A nombre de: <b>{{ $pago['titular'] }}</b></div>@endif
            @if(! empty($pago['clabe']))<div>CLABE: <b class="num">{{ $pago['clabe'] }}</b></div>@endif
            @if(! empty($pago['nota']))<div style="font-size: 14px">{{ $pago['nota'] }}</div>@endif
        </div>
    @endif
    <div class="acciones">
        @if(! empty($pago['whatsapp']))
            <a class="btn prim" href="https://wa.me/{{ preg_replace('/\D/', '', $pago['whatsapp']) }}?text={{ rawurlencode('Hola, quiero reactivar el panel de ' . $cuenta->nombre . '. Te mando mi comprobante.') }}" target="_blank" rel="noopener">Mandar comprobante por WhatsApp</a>
        @endif
        <form method="post" action="{{ route('logout') }}">@csrf<button class="btn">Cerrar sesión</button></form>
    </div>
</main>
</body>
</html>
