<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/** Cada quien cambia su nombre, teléfono y contraseña */
class CuentaController extends Controller
{
    public function show(Request $request)
    {
        return view('admin.cuenta', ['u' => $request->user()->load('rol')]);
    }

    public function update(Request $request)
    {
        $u = $request->user();
        $d = $request->validate([
            'name'     => 'required|string|max:120',
            'email'    => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($u->id)],
            'telefono' => 'nullable|string|max:30',
            'actual'   => 'nullable|required_with:password|string',
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ], [
            'actual.required_with' => 'Escribe tu contraseña actual para cambiarla.',
            'password.confirmed'   => 'Las contraseñas nuevas no coinciden.',
            'password.min'         => 'Usa al menos 8 caracteres.',
            'email.unique'         => 'Ya hay alguien con ese correo.',
        ]);
        if (! empty($d['password']) && ! Hash::check($d['actual'], $u->password)) {
            throw ValidationException::withMessages(['actual' => 'Tu contraseña actual no coincide.']);
        }
        $u->fill(['name' => $d['name'], 'email' => $d['email'], 'telefono' => $d['telefono'] ?? null]);
        if (! empty($d['password'])) $u->password = $d['password'];
        $u->save();
        return redirect()->route('admin.cuenta')->with('ok', ! empty($d['password']) ? 'Datos y contraseña actualizados.' : 'Datos actualizados.');
    }
}
