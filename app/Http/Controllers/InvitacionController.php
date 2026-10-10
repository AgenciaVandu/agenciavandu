<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Permisos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

/** Enlace de invitación (o de nueva contraseña) que manda la agencia a alguien del equipo */
class InvitacionController extends Controller
{
    public function show(string $token)
    {
        $u = User::porInvitacion($token);
        return response()->view('admin.invitacion', ['u' => $u, 'token' => $token], $u ? 200 : 410)->header('Referrer-Policy', 'no-referrer');
    }

    public function guardar(Request $request, string $token)
    {
        $u = User::porInvitacion($token);
        abort_unless($u, 410);
        $d = $request->validate([
            'name'     => 'required|string|max:120',
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'password.required'  => 'Escribe una contraseña.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.min'       => 'Usa al menos 8 caracteres.',
        ]);
        $u->forceFill([
            'name' => $d['name'], 'password' => $d['password'],
            'invitacion_hash' => null, 'invitacion_expira' => null, 'ultimo_acceso_at' => now(),
        ])->save();

        Auth::logout();
        Auth::login($u, true);
        $request->session()->regenerate();

        return redirect()->to(Permisos::inicio($u))->with('ok', "¡Listo, {$u->primer_nombre}! Ya estás dentro del panel.");
    }
}
