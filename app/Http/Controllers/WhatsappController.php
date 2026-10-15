<?php

namespace App\Http\Controllers;

use App\Mail\CorreoVandu;
use App\Models\Prospecto;
use App\Support\Push\Notificar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Botón "Cotiza por WhatsApp" del sitio: antes de abrir WhatsApp se piden unos datos,
 * se guarda el prospecto en Ventas, se avisa al equipo (push y correo) y se devuelve
 * el enlace de WhatsApp con el mensaje ya escrito.
 */
class WhatsappController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $d = $request->validate([
            'nombre'   => 'required|string|max:100',
            'telefono' => 'required|string|max:30',
            'empresa'  => 'nullable|string|max:120',
            'email'    => 'nullable|email|max:160',
            'servicio' => 'required|string|max:80',
            'mensaje'  => 'nullable|string|max:1000',
            'pagina'   => 'nullable|string|max:255',
        ], [
            'nombre.required'   => 'Escribe tu nombre.',
            'telefono.required' => 'Escribe tu WhatsApp para poder responderte.',
            'servicio.required' => 'Elige qué necesitas.',
            'email.email'       => 'Revisa tu correo, parece que le falta algo.',
        ]);

        $texto = self::mensaje($d);
        $enlace = 'https://wa.me/' . preg_replace('/\D/', '', (string) config('vandu.whatsapp')) . '?text=' . rawurlencode($texto);

        // Si reCAPTCHA dice que es un bot, no se guarda nada, pero el enlace de WhatsApp sí se entrega
        if (! $this->esPersona($request)) {
            return response()->json(['success' => true, 'whatsapp' => $enlace]);
        }

        $prospecto = null;
        try {
            $prospecto = Prospecto::desdeFormulario([
                'name'    => $d['nombre'],
                'phone'   => $d['telefono'],
                'email'   => $d['email'] ?? null,
                'company' => $d['empresa'] ?? null,
                'service' => $d['servicio'],
                'message' => $texto,
                'page'    => $d['pagina'] ?? null,
            ], 'whatsapp');

            Notificar::evento('mensaje_sitio', 'Nuevo prospecto por WhatsApp',
                $d['nombre'] . ' · ' . $d['servicio'] . ' · ' . $d['telefono'],
                route('admin.ventas.show', $prospecto), 'prospecto-' . $prospecto->id);
        } catch (\Throwable $e) {
            Log::error('No se pudo guardar el prospecto de WhatsApp: ' . $e->getMessage());
        }

        try {
            Mail::to(config('vandu.correo.avisos'))->send($this->correo($d, $texto, $prospecto));
        } catch (\Throwable $e) {
            Log::error('No se pudo enviar la copia del WhatsApp por correo: ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'whatsapp' => $enlace]);
    }

    /** El mensaje que el cliente envía por WhatsApp, escrito en primera persona */
    public static function mensaje(array $d): string
    {
        $lineas = ['Hola, soy ' . trim($d['nombre']) . (! empty($d['empresa']) ? ' de ' . trim($d['empresa']) : '') . '.'];
        $lineas[] = 'Me interesa: ' . trim($d['servicio']) . '.';
        if (! empty($d['mensaje'])) {
            $lineas[] = '';
            $lineas[] = trim($d['mensaje']);
        }
        if (! empty($d['email'])) {
            $lineas[] = '';
            $lineas[] = 'Mi correo: ' . trim($d['email']);
        }
        $lineas[] = '';
        $lineas[] = '(Te escribo desde agenciavandu.com)';

        return implode("\n", $lineas);
    }

    private function correo(array $d, string $texto, ?Prospecto $p): CorreoVandu
    {
        $resumen = array_filter([
            'Nombre'   => $d['nombre'],
            'Empresa'  => $d['empresa'] ?? null,
            'WhatsApp' => $d['telefono'],
            'Correo'   => $d['email'] ?? null,
            'Desde'    => empty($d['pagina']) ? null : ($d['pagina'] === '/' ? 'Página de inicio' : 'agenciavandu.com' . $d['pagina']),
            'Le interesa' => $d['servicio'],
        ]);

        $cuerpo = "{$d['nombre']} llenó el formulario del botón de WhatsApp en agenciavandu.com y se abrió WhatsApp con este mensaje:\n\n"
            . '“' . preg_replace("/\n\s*\n/", "\n", $texto) . '”'
            . "\n\nSi en unos minutos no te llega el mensaje, escríbele tú: ya quedó registrado como prospecto en Ventas.";

        return new CorreoVandu(
            asunto: 'Nuevo prospecto por WhatsApp: ' . $d['nombre'] . ' · ' . $d['servicio'],
            titulo: 'Nuevo prospecto por WhatsApp',
            cuerpo: $cuerpo,
            boton: $p ? 'Abrir en el portal' : null,
            url: $p ? route('admin.ventas.show', $p) : null,
            resumen: $resumen,
        );
    }

    /** reCAPTCHA v3: si no hay llave configurada (local o pruebas), se deja pasar */
    private function esPersona(Request $request): bool
    {
        $secreto = env('RECAPTCHA_SECRET_KEY');
        if (! $secreto) {
            return true;
        }
        try {
            $r = Http::asForm()->timeout(5)->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret'   => $secreto,
                'response' => (string) $request->input('g-recaptcha-response'),
            ])->json();
            return ($r['success'] ?? false) && ($r['score'] ?? 0) >= 0.5;
        } catch (\Throwable $e) {
            Log::warning('reCAPTCHA no respondió en el botón de WhatsApp: ' . $e->getMessage());
            return true; // si Google no responde, mejor no perder al prospecto
        }
    }
}
