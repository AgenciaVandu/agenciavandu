<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Mail\CotizacionRecibida;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http; // <--- IMPORTANTE: Agregamos esta línea

class CotizarController extends Controller
{
    public function store(Request $request)
    {
        // 1. Validación (Correcta: usa 'phone')
        $request->validate([
            'name'     => 'required|max:100',
            'lastname' => 'required|max:100',
            'phone'    => 'required', 
            'email'    => 'required|email',
            'service'  => 'required',
            'g-recaptcha-response' => 'required'
        ], [
            'phone.required' => 'El teléfono es obligatorio.',
            'name.required'  => 'El nombre es obligatorio.',
            'email.required' => 'Tu correo es importante',
            'service.required' => 'Selecciona el servicio para que sepamos que necesitas',
        ]);

        // 2. Verificar reCAPTCHA v3 (Quitamos la barra \ inicial y usamos el Facade importado)
        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret'   => env('RECAPTCHA_SECRET_KEY'),
            'response' => $request->input('g-recaptcha-response'),
        ]);

        $res = $response->json();

        if (!$res['success'] || ($res['score'] ?? 0) < 0.5) {
            return response()->json([
                'success' => false,
                'message' => 'Parece que el sistema de seguridad te detectó como bot. Intenta de nuevo.'
            ], 422);
        }

        // 3. Envío de correo
        try {
            // Usamos $request->only para enviar solo lo que la plantilla espera
            $datos = $request->only(['name', 'lastname', 'phone', 'email', 'service']);
            
            Mail::to('proyectos@agenciavandu.com')->send(new CotizacionRecibida($datos));

            return response()->json([
                'success' => true,
                'message' => '¡Genial! Hemos recibido tu información. En breve nos contactamos contigo.'
            ]);
        } catch (\Exception $e) {
            // Logueamos el error para que puedas verlo en storage/logs/laravel.log
            \Log::error("Error enviando correo: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al enviar el correo. Por favor, contáctanos directamente a proyectos@agenciavandu.com'
            ], 500);
        }
    }
}