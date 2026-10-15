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
        $p = Presupuesto::where('token', $token)->with(['conceptos', 'proyecto.etapas', 'proyecto.pagos', 'cliente', 'prospecto', 'facturas', 'complementos.pago', 'eventos' => fn ($q) => $q->where('visible_cliente', true)])->firstOrFail();

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

    /** El cliente descarga su factura (PDF o XML) */
    public function factura(string $token, int $factura)
    {
        $p = Presupuesto::where('token', $token)->firstOrFail();
        $f = $p->archivosFiscales()->findOrFail($factura);

        if (! auth()->check()) {
            Notificar::evento('cotizacion_descargada', ($p->cliente_empresa ?: $p->cliente_nombre) . ' descargó su ' . ($f->es_complemento ? 'complemento de pago' : 'factura'),
                "{$p->folio} · {$f->nombre}", route('admin.presupuestos.edit', $p), 'factura-' . $p->id);
        }

        return $f->respuesta();
    }

    /** El cliente acepta o pide cambios desde su enlace (con su nombre y su confirmación) */
    public function responder(Request $request, string $token)
    {
        $p = Presupuesto::where('token', $token)->with(['conceptos', 'cliente'])->firstOrFail();

        $d = $request->validate([
            'accion'  => 'required|in:aceptar,cambios',
            'nombre'  => 'required|string|max:120',
            'mensaje' => 'required_if:accion,cambios|nullable|string|min:5|max:3000',
        ], [
            'nombre.required'     => 'Escribe tu nombre.',
            'mensaje.required_if' => 'Cuéntanos qué cambios necesitas.',
            'mensaje.min'         => 'Cuéntanos un poco más sobre los cambios.',
        ]);

        if (! Aceptacion::puedeResponder($p)) {
            $motivo = $p->estado === 'aceptada'
                ? 'Esta cotización ya fue aceptada y no se puede modificar desde aquí. Si necesitas un ajuste, escríbenos.'
                : 'Esta cotización ya no se puede responder en línea. Escríbenos y te ayudamos.';
            return $this->respuesta($request, $token, false, $motivo, 409);
        }

        $nombre = trim($d['nombre']);
        $quien = $p->cliente_empresa ?: $p->cliente_nombre;
        if ($d['accion'] === 'aceptar') {
            $p->estado = 'aceptada';
            $p->aceptada_el = now(config('vandu.zona_horaria'))->toDateString();
            $p->save();
            Aceptacion::registrar($p, 'aceptada', 'cliente', $nombre, 'Aceptada en línea desde su enlace', Aceptacion::foto($p));
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
