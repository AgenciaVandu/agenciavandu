<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show()
    {
        return view('admin.login');
    }

    public function login(Request $request)
    {
        $datos = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ], [
            'email.required'    => 'Escribe tu correo.',
            'email.email'       => 'Ese correo no es válido.',
            'password.required' => 'Escribe tu contraseña.',
        ]);

        // Máximo 5 intentos por minuto por correo + IP
        $llave = Str::lower($datos['email']) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($llave, 5)) {
            $seg = RateLimiter::availableIn($llave);
            throw ValidationException::withMessages([
                'email' => "Demasiados intentos. Vuelve a intentar en {$seg} segundos.",
            ]);
        }

        if (! Auth::attempt($datos, $request->boolean('recordar'))) {
            RateLimiter::hit($llave, 60);
            throw ValidationException::withMessages([
                'email' => 'El correo o la contraseña no coinciden.',
            ]);
        }

        RateLimiter::clear($llave);
        $request->session()->regenerate();

        return redirect()->intended(route('admin.resumen'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('ok', 'Cerraste sesión.');
    }
}
