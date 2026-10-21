<?php

namespace App\Http\Controllers\Lealtad;

use App\Http\Controllers\Controller;
use App\Mail\CorreoVandu;
use App\Models\Comensal;
use App\Models\Cuenta;
use App\Support\Lealtad\Programa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/** Cuenta del comensal en la plataforma: registrarse (y unirse al restaurante desde el que llegó), entrar, recuperar acceso */
class AccesoController extends Controller
{
    /** Restaurante desde el que llegó (?r=slug), para regresarlo ahí y unirlo */
    private function restaurante(Request $request): ?Cuenta
    {
        $slug = (string) ($request->input('r') ?? $request->query('r'));
        if ($slug === '') return null;
        $c = Cuenta::where('slug', $slug)->first();
        return $c && $c->activa && $c->tiene('lealtad') ? $c : null;
    }

    public function entrar(Request $request)
    {
        if (Auth::guard('comensal')->check()) return redirect()->route('lealtad.inicio');
        return view('lealtad.entrar', ['restaurante' => $this->restaurante($request)]);
    }

    public function login(Request $request)
    {
        $d = $request->validate(['email' => 'required|email', 'password' => 'required|string'], ['email.required' => 'Escribe tu correo.', 'password.required' => 'Escribe tu contraseña.']);
        $clave = 'comensal-login|' . mb_strtolower($d['email']) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($clave, 6)) {
            throw ValidationException::withMessages(['email' => 'Demasiados intentos. Espera ' . ceil(RateLimiter::availableIn($clave) / 60) . ' min.']);
        }
        if (! Auth::guard('comensal')->attempt(['email' => mb_strtolower(trim($d['email'])), 'password' => $d['password']], true)) {
            RateLimiter::hit($clave, 600);
            throw ValidationException::withMessages(['email' => 'El correo o la contraseña no coinciden.']);
        }
        RateLimiter::clear($clave);
        $request->session()->regenerate();
        $r = $this->restaurante($request);
        return $r ? redirect()->route('lealtad.restaurante', $r->slug) : redirect()->intended(route('lealtad.inicio'));
    }

    public function registro(Request $request)
    {
        if (Auth::guard('comensal')->check()) return redirect()->route('lealtad.inicio');
        return view('lealtad.registro', ['restaurante' => $this->restaurante($request)]);
    }

    public function registrar(Request $request)
    {
        $r = $this->restaurante($request);
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $d = $request->validate([
            'nombre'    => 'required|string|max:120',
            'telefono'  => 'required|string|max:30',
            'email'     => ['required', 'email', 'max:190', Rule::unique('comensales', 'email')],
            'password'  => ['required', 'confirmed', Password::min(8)],
            'privacidad' => 'accepted',
            'compartir' => $r ? 'accepted' : 'nullable',
        ], [
            'nombre.required' => 'Escribe tu nombre.', 'telefono.required' => 'Escribe tu teléfono.',
            'email.unique' => 'Ese correo ya tiene cuenta. Entra con tu contraseña.',
            'password.confirmed' => 'Las contraseñas no coinciden.', 'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'privacidad.accepted' => 'Acepta el aviso de privacidad para crear tu cuenta.',
            'compartir.accepted' => 'Para unirte necesitas aceptar compartir tus datos con el restaurante.',
        ]);
        $p = Comensal::create(['nombre' => trim($d['nombre']), 'telefono' => trim($d['telefono']), 'email' => $d['email'], 'password' => $d['password']]);
        Auth::guard('comensal')->login($p, true);
        $request->session()->regenerate();
        if ($r) {
            Programa::unir($r, $p);
            return redirect()->route('lealtad.restaurante', $r->slug)->with('ok', "¡Listo, {$p->primer_nombre}! Ya estás en el programa de {$r->nombre}. Muestra tu QR en cada visita.");
        }
        return redirect()->route('lealtad.inicio')->with('ok', "¡Bienvenido, {$p->primer_nombre}! Únete a tus restaurantes desde el QR que tienen en mesa o caja.");
    }

    public function salir(Request $request)
    {
        Auth::guard('comensal')->logout();
        $request->session()->regenerateToken();
        return redirect()->route('lealtad.entrar');
    }

    public function olvide(Request $request)
    {
        if ($request->isMethod('get')) return view('lealtad.olvide');
        $d = $request->validate(['email' => 'required|email']);
        $clave = 'comensal-olvide|' . $request->ip();
        if (! RateLimiter::tooManyAttempts($clave, 5)) {
            RateLimiter::hit($clave, 3600);
            $p = Comensal::where('email', mb_strtolower(trim($d['email'])))->first();
            if ($p) {
                try {
                    Mail::to($p->email, $p->nombre)->send(new CorreoVandu(
                        asunto: 'Tu acceso a ' . config('vandu.plataforma.nombre'),
                        titulo: "Hola, {$p->primer_nombre}",
                        cuerpo: 'Pediste crear una contraseña nueva para tu programa de lealtad. El enlace sirve 7 días. Si no fuiste tú, ignora este correo.',
                        boton: 'Crear mi contraseña',
                        url: $p->nuevoAcceso(),
                    ));
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }
        return back()->with('ok', 'Si tu correo está registrado, te llegará un enlace para crear tu contraseña.');
    }

    public function acceso(string $token)
    {
        return response()->view('lealtad.acceso', ['p' => Comensal::porAcceso($token), 'token' => $token])->header('Referrer-Policy', 'no-referrer');
    }

    public function guardarAcceso(Request $request, string $token)
    {
        $p = Comensal::porAcceso($token);
        abort_unless($p, 410);
        $d = $request->validate(['password' => ['required', 'confirmed', Password::min(8)]], ['password.confirmed' => 'Las contraseñas no coinciden.']);
        $p->forceFill(['password' => $d['password'], 'acceso_hash' => null, 'acceso_expira' => null])->save();
        Auth::guard('comensal')->login($p, true);
        $request->session()->regenerate();
        return redirect()->route('lealtad.inicio')->with('ok', 'Contraseña guardada.');
    }
}
