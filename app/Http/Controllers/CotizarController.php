<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Mail\CotizacionRecibida;
use Illuminate\Support\Facades\Mail;

class CotizarController extends Controller
{
    public function store(Request $request)
    {
        // 1. Validación (Si falla, Laravel enviará automáticamente un JSON con los errores)
        $request->validate([
            'name'     => 'required|max:100',
            'lastname' => 'required|max:100',
            'tel'    => 'required',
            'email'    => 'required|email',
            'service'  => 'required',
            'g-recaptcha-response' => 'required'
        ]);

        // 2. Verificar reCAPTCHA v3
        $response = \Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret'   => env('RECAPTCHA_SECRET_KEY'),
            'response' => $request->input('g-recaptcha-response'),
        ]);

        $res = $response->json();

        if (!$res['success'] || $res['score'] < 0.5) {
            return response()->json([
                'success' => false,
                'message' => 'Parece que eres un bot. Intenta de nuevo.'
            ], 422);
        }

        // 3. Envío de correo
        try {
            Mail::to('proyectos@agenciavandu.com')->send(new \App\Mail\CotizacionRecibida($request->all()));

            return response()->json([
                'success' => true,
                'message' => '¡Genial! Hemos recibido tu información. En breve nos contactamos contigo.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar el correo, pero recibimos tu información.'
            ], 500);
        }
    }
}
