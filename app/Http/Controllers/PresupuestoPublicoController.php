<?php

namespace App\Http\Controllers;

use App\Models\Presupuesto;
use App\Support\PresupuestoPdf;
use App\Support\Push\Notificar;
use App\Support\Aceptacion;
use App\Mail\CorreoVandu;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

/** Vista que recibe el cliente: /cotizacion/{token} */
class PresupuestoPublicoController extends Controller
{
    public function show(Request $request, string $token)
    {
        $p = Presupuesto::where('token', $token)->with(['conceptos', 'proyecto.etapas', 'proyecto.pagos', 'cliente', 'eventos' => fn ($q) => $q->where('visible_cliente', true)])->firstOrFail();

        // ?vista_previa=1 lo usa el panel para no contar tus propias visitas
        if (! $request->boolean('vista_previa')) {
            $avisar = ! auth()->check() && Notificar::visitaNueva($p->ultima_vista_at);
            $p->timestamps = false;
            $p->increment('vistas', 1, ['ultima_vista_at' => now()]);
            if ($avisar) {
                $quien = $p->cliente_empresa ?: $p->cliente_nombre;
                $veces = $p->vistas === 1 ? 'La abrió por primera vez' : "Ya la abrió {$p->vistas} veces";
                Notificar::evento('cotizacion_abierta', "$quien abrió su cotización",
                    "{$p->folio} · " . $p->monto($p->modo_iva === 'desglosado' ? $p->total : $p->subtotal) . " · $veces",
                    route('admin.presupuestos.edit', $p), 'cotizacion-' . $p->id);
            }
        }

        return response()
            ->view('presupuestos.publico', ['p' => $p])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function descargar(string $token)
    {
        $p = Presupuesto::where('token', $token)->firstOrFail();

        if (! $p->vigente && ! in_array($p->estado, ['aceptada', 'negociacion'], true)) {
            return redirect()->route('presupuesto.publico', $token);
        }

        if (! auth()->check()) {
            $quien = $p->cliente_empresa ?: $p->cliente_nombre;
            Notificar::evento('cotizacion_descargada', "$quien descargó su cotización",
                "{$p->folio} en PDF · buen momento para darle seguimiento", route('admin.presupuestos.edit', $p), 'descarga-' . $p->id);
        }

        return PresupuestoPdf::descargar($p);
    }

    /** El cliente acepta o pide cambios, verificando su identidad con el código de 24 horas */
    public function responder(Request $request, string $token)
    {
        $p = Presupuesto::where('token', $token)->with(['conceptos', 'cliente'])->firstOrFail();

        $d = $request->validate([
            'accion'  => 'required|in:aceptar,cambios',
            'nombre'  => 'required|string|max:120',
            'mensaje' => 'required_if:accion,cambios|nullable|string|min:5|max:3000',
            'codigo'  => 'required|string|max:12',
        ], [
            'nombre.required'     => 'Escribe tu nombre.',
            'mensaje.required_if' => 'Cuéntanos qué cambios necesitas.',
            'mensaje.min'         => 'Cuéntanos un poco más sobre los cambios.',
            'codigo.required'     => 'Escribe tu código de verificación.',
        ]);

        if (! Aceptacion::puedeResponder($p)) {
            $motivo = $p->estado === 'aceptada'
                ? 'Esta cotización ya fue aceptada y no se puede modificar desde aquí. Si necesitas un ajuste, escríbenos.'
                : 'Esta cotización ya no se puede responder en línea. Escríbenos y te ayudamos.';
            return $this->respuesta($request, $token, false, $motivo, 409);
        }

        $ok = Aceptacion::verificar($p, $d['codigo']);
        if ($ok !== true) {
            return $this->respuesta($request, $token, false, $ok, 422, 'codigo');
        }

        $nombre = trim($d['nombre']);
        $quien = $p->cliente_empresa ?: $p->cliente_nombre;
        if ($d['accion'] === 'aceptar') {
            $p->estado = 'aceptada';
            $p->aceptada_el = now(config('vandu.zona_horaria'))->toDateString();
            $p->save();
            Aceptacion::registrar($p, 'aceptada', 'cliente', $nombre, 'Aceptada en línea con código de verificación', Aceptacion::foto($p));
            Notificar::evento('cotizacion_aceptada', "$quien aceptó su cotización",
                "{$p->folio} · " . $p->monto($p->modo_iva === 'desglosado' ? $p->total : $p->subtotal) . " · ya puedes crear el proyecto",
                route('admin.proyectos.create', $p), 'cotizacion-' . $p->id);
            $this->avisarAgencia($p, "$nombre aceptó la cotización {$p->folio}", "Ya puedes convertirla en proyecto desde el panel.", route('admin.proyectos.create', $p), 'Crear proyecto');
            return $this->respuesta($request, $token, true, '¡Gracias! Recibimos tu aceptación. Te contactaremos para arrancar.');
        }

        $p->estado = 'negociacion';
        $p->save();
        Aceptacion::registrar($p, 'cambios', 'cliente', $nombre, trim($d['mensaje']));
        Notificar::evento('cambios_solicitados', "$quien pidió cambios a su cotización",
            "{$p->folio} · " . \Illuminate\Support\Str::limit(trim($d['mensaje']), 90),
            route('admin.presupuestos.edit', $p), 'cotizacion-' . $p->id);
        $this->avisarAgencia($p, "$nombre pidió cambios a la cotización {$p->folio}", "Lo que necesita:\n\n" . trim($d['mensaje']), route('admin.presupuestos.edit', $p), 'Ver cotización');
        return $this->respuesta($request, $token, true, 'Recibimos tus comentarios. Te enviaremos la cotización actualizada muy pronto.');
    }

    /** El cliente pide que le mandemos un código a su correo registrado */
    public function pedirCodigo(Request $request, string $token)
    {
        $p = Presupuesto::where('token', $token)->with('cliente')->firstOrFail();
        if (! Aceptacion::puedeResponder($p)) {
            return $this->respuesta($request, $token, false, 'Esta cotización ya no se puede responder en línea.', 409);
        }
        $email = $p->cliente?->email;
        if (! $email) {
            return $this->respuesta($request, $token, false, 'No tenemos un correo registrado. Pídenos tu código por WhatsApp.', 422);
        }
        if ($p->codigos()->where('canal', 'cliente')->where('created_at', '>', now()->subHour())->count() >= 3) {
            return $this->respuesta($request, $token, false, 'Ya enviamos varios códigos en la última hora. Revisa tu correo (también la carpeta de spam).', 429);
        }

        $c = Aceptacion::generarCodigo($p, 'cliente');
        try {
            Mail::to($email)->send(new CorreoVandu(
                asunto: "Tu código de verificación: {$c['formateado']}",
                titulo: 'Tu código de verificación',
                cuerpo: 'Hola ' . \App\Support\Correos::primerNombre($p->cliente_nombre) . ",\n\nUsa este código para aceptar o pedir cambios a la cotización {$p->folio}. Es válido hasta el " . Aceptacion::vigenciaTexto($c['expira']) . ".\n\nSi no lo pediste tú, ignora este mensaje.",
                boton: 'Volver a mi cotización', url: $p->url_publica,
                resumen: ['Código' => $c['formateado'], 'Cotización' => $p->folio, 'Vigencia' => '24 horas'],
            ));
        } catch (\Throwable $e) {
            Log::error('No se pudo enviar el código: ' . $e->getMessage());
            return $this->respuesta($request, $token, false, 'No pudimos enviar el correo. Pídenos tu código por WhatsApp.', 500);
        }
        Aceptacion::registrar($p, 'codigo', 'sistema', null, 'Enviado por correo a ' . Aceptacion::correoOculto($email) . ' (a petición del cliente)', ['canal' => 'correo'], false);

        return $this->respuesta($request, $token, true, 'Te enviamos un código a ' . Aceptacion::correoOculto($email) . '. Vigente 24 horas.');
    }

    private function avisarAgencia(Presupuesto $p, string $asunto, string $cuerpo, string $url, string $boton): void
    {
        try {
            Mail::to(config('vandu.correo.responder_a'))->send(new CorreoVandu(
                asunto: $asunto, titulo: $asunto, cuerpo: $cuerpo, boton: $boton, url: $url,
                resumen: ['Cliente' => $p->cliente_empresa ?: $p->cliente_nombre, 'Folio' => $p->folio, 'Importe' => $p->monto($p->modo_iva === 'desglosado' ? $p->total : $p->subtotal)],
            ));
        } catch (\Throwable $e) {
            Log::warning('Aviso a la agencia: ' . $e->getMessage());
        }
    }

    private function respuesta(Request $request, string $token, bool $ok, string $mensaje, int $status = 200, ?string $campo = null)
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => $ok, 'mensaje' => $mensaje, 'campo' => $campo], $ok ? 200 : $status);
        }
        return redirect()->route('presupuesto.publico', $token)->with($ok ? 'respuesta_ok' : 'respuesta_error', $mensaje);
    }
}
