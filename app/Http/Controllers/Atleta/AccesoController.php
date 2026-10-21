<?php

namespace App\Http\Controllers\Atleta;

use App\Http\Controllers\Controller;
use App\Mail\CorreoVandu;
use App\Models\Cliente;
use App\Models\Cuenta;
use App\Support\Cuentas;
use App\Support\Fitness\Portal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Entrar al portal del atleta (correo y contraseña), crear la contraseña con el enlace que manda el gimnasio
 * y pedir uno nuevo si la olvidó. Es un acceso aparte del panel: guard "atleta".
 */
class AccesoController extends Controller
{
    /** El gimnasio: por su dominio propio o por su nombre corto en la URL */
    private function cuenta(Request $request, ?string $negocio): Cuenta
    {
        $c = $request->attributes->get('cuenta_dominio') ?? ($negocio ? Cuenta::where('slug', $negocio)->first() : null);
        abort_unless($c && $c->activa && $c->tiene('fitness'), 404);
        Cuentas::activar($c);
        return $c;
    }

    public function entrar(Request $request, ?string $negocio = null)
    {
        $c = $this->cuenta($request, $negocio);
        if (Auth::guard('atleta')->check() && Auth::guard('atleta')->user()->cuenta_id === $c->id) return redirect()->route('atleta.inicio');
        return response()->view('atleta.entrar', ['cuenta' => $c, 'negocio' => $negocio])->header('X-Robots-Tag', 'noindex');
    }

    public function login(Request $request, ?string $negocio = null)
    {
        $c = $this->cuenta($request, $negocio);
        $d = $request->validate(['email' => 'required|email', 'password' => 'required|string'], ['email.required' => 'Escribe tu correo.', 'password.required' => 'Escribe tu contraseña.']);
        $clave = 'atleta-login|' . $c->id . '|' . mb_strtolower($d['email']) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($clave, 6)) {
            throw ValidationException::withMessages(['email' => 'Demasiados intentos. Espera ' . ceil(RateLimiter::availableIn($clave) / 60) . ' min.']);
        }
        $ok = Auth::guard('atleta')->attempt(['email' => mb_strtolower(trim($d['email'])), 'password' => $d['password'], 'cuenta_id' => $c->id], true);
        if (! $ok) {
            RateLimiter::hit($clave, 600);
            throw ValidationException::withMessages(['email' => 'El correo o la contraseña no coinciden.']);
        }
        $a = Auth::guard('atleta')->user();
        if ($a->activo === false) {
            Auth::guard('atleta')->logout();
            throw ValidationException::withMessages(['email' => 'Tu acceso está desactivado. Pregunta en recepción.']);
        }
        RateLimiter::clear($clave);
        $request->session()->regenerate();
        $request->session()->put('atleta_cuenta', $c->id);
        return redirect()->intended(route('atleta.inicio'));
    }

    public function salir(Request $request)
    {
        $id = Auth::guard('atleta')->user()?->cuenta_id ?? $request->session()->get('atleta_cuenta');
        Auth::guard('atleta')->logout();
        $request->session()->regenerateToken();
        $c = $id ? Cuenta::find($id) : null;
        return redirect()->to($c ? Portal::url($c) : url('/'));
    }

    /** Enlace del gimnasio para crear (o cambiar) la contraseña */
    public function acceso(string $token)
    {
        $a = Cliente::porAcceso($token);
        if ($a) Cuentas::activar($a->cuenta_id);
        return response()->view('atleta.acceso', ['a' => $a, 'token' => $token, 'cuenta' => $a ? Cuentas::actual() : null], $a ? 200 : 410)->header('Referrer-Policy', 'no-referrer');
    }

    public function guardarAcceso(Request $request, string $token)
    {
        $a = Cliente::porAcceso($token);
        abort_unless($a, 410);
        Cuentas::activar($a->cuenta_id);
        $d = $request->validate(['password' => ['required', 'confirmed', Password::min(8)]], [
            'password.required' => 'Escribe una contraseña.', 'password.confirmed' => 'Las contraseñas no coinciden.', 'password.min' => 'Usa al menos 8 caracteres.',
        ]);
        $a->forceFill(['password' => $d['password'], 'acceso_hash' => null, 'acceso_expira' => null, 'ultimo_acceso_at' => now()])->save();
        Auth::guard('atleta')->login($a, true);
        $request->session()->regenerate();
        $request->session()->put('atleta_cuenta', $a->cuenta_id);
        return redirect()->route('atleta.inicio')->with('ok', "¡Listo, {$a->primer_nombre}! Ya puedes reservar tus clases.");
    }

    /** Olvidé mi contraseña: si el correo existe en ese gimnasio, le mandamos un enlace (sin decir si existe) */
    public function olvide(Request $request, ?string $negocio = null)
    {
        $c = $this->cuenta($request, $negocio);
        if ($request->isMethod('get')) return view('atleta.olvide', ['cuenta' => $c, 'negocio' => $negocio]);

        $d = $request->validate(['email' => 'required|email']);
        $clave = 'atleta-olvide|' . $request->ip();
        if (! RateLimiter::tooManyAttempts($clave, 5)) {
            RateLimiter::hit($clave, 3600);
            $a = Cliente::where('email', mb_strtolower(trim($d['email'])))->where('activo', '!=', false)->first();
            if ($a) {
                try {
                    Mail::to($a->email, $a->nombre)->send(new CorreoVandu(
                        asunto: 'Tu acceso a ' . $c->nombre,
                        titulo: "Hola, {$a->primer_nombre}",
                        cuerpo: "Pediste crear una contraseña nueva para reservar tus clases en {$c->nombre}. El enlace sirve 7 días. Si no fuiste tú, ignora este correo.",
                        boton: 'Crear mi contraseña',
                        url: $a->nuevoAcceso(),
                    ));
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }
        return back()->with('ok', 'Si tu correo está registrado, te llegará un enlace para crear tu contraseña. Si no llega, pídelo en recepción.');
    }
}
