<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Mail\CotizacionRecibida;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http; // <--- ESTA LÍNEA ES LA QUE FALTA

class CotizarController extends Controller
{
    public function store(Request $request)
    {
        // 1. Validación
        $request->validate([
            'name'     => 'required|max:100',
            'lastname' => 'required|max:100',
            'phone'    => 'required', 
            'email'    => 'required|email',
            'service'  => 'required',
            'g-recaptcha-response' => 'required'
        ]);

        // 2. Verificar reCAPTCHA v3 (Usando el Facade importado)
        try {
            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret'   => env('RECAPTCHA_SECRET_KEY'),
                'response' => $request->input('g-recaptcha-response'),
            ]);

            $res = $response->json();

            if (!$res['success'] || ($res['score'] ?? 0) < 0.5) {
                return response()->json(['success' => false, 'message' => 'Fallo de seguridad (Bot).'], 422);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error validando reCAPTCHA.'], 500);
        }

        // 3. Envío de correo
        try {
            // Pasamos solo los datos limpios
            $datos = $request->only(['name', 'lastname', 'phone', 'email', 'service']);
            
            Mail::to('proyectos@agenciavandu.com')->send(new CotizacionRecibida($datos));

            return response()->json([
                'success' => true,
                'message' => '¡Genial! Hemos recibido tu información.'
            ]);
        } catch (\Exception $e) {
            // Log para que revises en storage/logs/laravel.log
            \Log::error("Error Mail: " . $e->getMessage());

            return response()->json([
                'success' => false, 
                'message' => 'El servidor de correo falló, pero tu mensaje se intentó enviar.'
            ], 500);
        }
    }
}