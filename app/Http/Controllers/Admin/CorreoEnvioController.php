<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\CorreoNegocio;
use App\Support\Cuentas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;

/** Conectar el correo del negocio: se prueba antes de usarse, así nunca se queda sin poder mandar correos */
class CorreoEnvioController extends Controller
{
    public function show()
    {
        return view('admin.correo-envio', ['s' => CorreoNegocio::de(), 'proveedores' => CorreoNegocio::PROVEEDORES]);
    }

    public function guardar(Request $request)
    {
        $c = Cuentas::actual();
        $antes = (array) ($c->ajuste('smtp') ?? []);
        $d = $request->validate([
            'proveedor' => ['required', Rule::in(array_keys(CorreoNegocio::PROVEEDORES))],
            'desde'     => 'required|email|max:190',
            'usuario'   => 'required|string|max:190',
            'clave'     => [empty($antes['clave']) ? 'required' : 'nullable', 'string', 'max:255'],
            'host'      => ['required', 'string', 'max:190', 'regex:/^[a-z0-9.-]+$/i'],
            'puerto'    => 'required|integer|in:25,465,587,2525',
            'cifrado'   => 'required|in:tls,ssl,ninguno',
        ], [
            'desde.required' => 'Escribe el correo desde el que se enviará.', 'desde.email' => 'Revisa el correo.',
            'usuario.required' => 'Escribe el usuario (casi siempre es el mismo correo).',
            'clave.required' => 'Escribe la contraseña del correo.', 'host.required' => 'Escribe el servidor de salida (SMTP).',
            'host.regex' => 'El servidor se escribe sin http:// ni espacios, por ejemplo mail.tunegocio.com', 'puerto.in' => 'Los puertos de envío son 465, 587, 25 o 2525.',
        ]);
        $clave = filled($d['clave'] ?? null) ? $d['clave'] : Crypt::decryptString($antes['clave']);
        $s = ['proveedor' => $d['proveedor'], 'desde' => mb_strtolower(trim($d['desde'])), 'usuario' => trim($d['usuario']),
              'host' => mb_strtolower(trim($d['host'])), 'puerto' => (int) $d['puerto'], 'cifrado' => $d['cifrado']];

        // Se prueba antes de usarlo: si falla, se guarda pero no se activa
        $error = CorreoNegocio::probar($s, $clave, $request->user()->email);
        $a = $c->ajustes ?? [];
        $a['smtp'] = $s + ['clave' => Crypt::encryptString($clave), 'activo' => $error === null, 'error' => $error,
                           'verificado' => $error === null ? now()->toIso8601String() : ($antes['verificado'] ?? null)];
        $c->update(['ajustes' => $a]);
        Cuentas::activar($c->fresh());

        return $error
            ? back()->withInput($request->except('clave'))->with('smtp_error', $error)
            : redirect()->route('admin.negocio.correo')->with('ok', "Listo: te mandamos un correo de prueba a {$request->user()->email}. Desde ahora tus correos salen desde {$s['desde']}.");
    }

    public function desactivar()
    {
        $c = Cuentas::actual();
        $a = $c->ajustes ?? [];
        unset($a['smtp']);
        $c->update(['ajustes' => $a]);
        Cuentas::activar($c->fresh());
        return redirect()->route('admin.negocio.correo')->with('ok', 'Tu correo se desconectó. Los correos vuelven a salir desde el correo de la plataforma, con tu nombre.');
    }
}
