<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\CorreoVandu;
use App\Models\Cliente;
use App\Models\Cuenta;
use App\Models\Presupuesto;
use App\Models\Proyecto;
use App\Models\Rol;
use App\Models\User;
use App\Support\Cuentas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

/** La plataforma: los negocios que la usan, su giro y su estado. Solo para quien la administra. */
class PlataformaController extends Controller
{
    public function index()
    {
        $cuenta = fn (string $m) => $m::sinCuenta()->selectRaw('cuenta_id, count(*) as n')->groupBy('cuenta_id')->pluck('n', 'cuenta_id');
        return view('admin.plataforma.index', [
            'cuentas'   => Cuenta::orderByDesc('activa')->orderBy('id')->get(),
            'usuarios'  => $cuenta(User::class),
            'clientes'  => $cuenta(Cliente::class),
            'cotiz'     => $cuenta(Presupuesto::class),
            'proyectos' => $cuenta(Proyecto::class),
            'acceso'    => User::sinCuenta()->selectRaw('cuenta_id, max(ultimo_acceso_at) as u')->groupBy('cuenta_id')->pluck('u', 'cuenta_id'),
            'giros'     => config('vandu.giros'),
            'propios'   => \App\Models\Giro::all()->keyBy('clave'),
            'porGiro'   => Cuenta::selectRaw('giro, count(*) as n')->groupBy('giro')->pluck('n', 'giro'),
            'principal' => Cuentas::principalId(),
            'viendo'    => session('cuenta_vista'),
        ]);
    }

    /** Alta de un negocio: su cuenta, sus roles de fábrica según el giro y su dueño (invitado como super admin) */
    public function store(Request $request)
    {
        $d = $request->validate([
            'nombre'       => 'required|string|max:120',
            'giro'         => ['required', Rule::in(array_keys(config('vandu.giros')))],
            'dueno_nombre' => 'required|string|max:120',
            'dueno_email'  => ['required', 'email', 'max:190', Rule::unique('users', 'email')],
            'dueno_tel'    => 'nullable|string|max:30',
        ], [
            'nombre.required'       => 'Escribe el nombre del negocio.',
            'dueno_nombre.required' => 'Escribe el nombre de quien lo administra.',
            'dueno_email.required'  => 'Escribe su correo.',
            'dueno_email.unique'    => 'Ese correo ya tiene acceso a la plataforma.',
        ]);

        [$cuenta, $dueno, $url] = DB::transaction(fn () => self::crearCuenta($d + ['ejemplos' => $request->boolean('ejemplos', true)]));
        $correo = false;
        if ($request->boolean('enviar_correo', true)) {
            $correo = Cuentas::como($cuenta, function () use ($dueno, $url, $cuenta) {
                try {
                    Mail::to($dueno->email, $dueno->name)->send(new CorreoVandu(
                        asunto: "Tu panel de {$cuenta->nombre} está listo",
                        titulo: "Hola, {$dueno->primer_nombre}",
                        cuerpo: "Ya está listo el panel de {$cuenta->nombre}: clientes, cotizaciones, proyectos y tu equipo en un solo lugar.\n\nCrea tu contraseña con el botón (el enlace sirve 7 días). Después, en “Mi negocio”, sube tu logo y tus datos para que tus clientes los vean en cada cotización.",
                        boton: 'Crear mi contraseña',
                        url: $url,
                    ));
                    return true;
                } catch (Throwable $e) {
                    report($e);
                    return false;
                }
            });
        }
        $texto = "Hola {$dueno->primer_nombre}, ya está listo el panel de {$cuenta->nombre}. Crea tu contraseña aquí (sirve 7 días):\n$url";

        return redirect()->route('admin.plataforma')->with('ok', "Cuenta “{$cuenta->nombre}” creada." . ($correo ? " Le mandamos la invitación a {$dueno->email}." : ''))->with('enlace', [
            'cuenta' => $cuenta->nombre, 'url' => $url,
            'whatsapp' => 'https://wa.me/' . ($dueno->whatsapp ?? '') . '?text=' . rawurlencode($texto),
        ]);
    }

    /** @return array{0: Cuenta, 1: User, 2: string} */
    public static function crearCuenta(array $d): array
    {
        $cuenta = Cuenta::create(['nombre' => $d['nombre'], 'giro' => $d['giro'], 'activa' => true]);
        return Cuentas::como($cuenta, function () use ($cuenta, $d) {
            $super = Rol::create(['nombre' => 'Super admin', 'descripcion' => 'Puede hacer todo, incluido invitar personas y cambiar permisos.', 'todo' => true, 'orden' => 0]);
            foreach (config("vandu.giros.{$cuenta->giro}.roles", []) as $i => $r) {
                Rol::create($r + ['orden' => $i + 1]);
            }
            $dueno = User::create([
                'name' => $d['dueno_nombre'], 'email' => $d['dueno_email'], 'telefono' => $d['dueno_tel'] ?? null,
                'password' => Str::random(40), 'rol_id' => $super->id, 'activo' => true, 'puesto' => 'Director',
            ]);
            $cuenta->update(['ajustes' => ['emisor' => ['nombre' => $d['dueno_nombre'], 'email' => $d['dueno_email'], 'telefono' => $d['dueno_tel'] ?? '']]]);
            // Con inventario, arranca con productos de ejemplo para aprender a usarlo
            if (($d['ejemplos'] ?? false) && $cuenta->tiene('stock')) \App\Support\ProductosEjemplo::cargar();
            // Lote de autos: 10 unidades y 5 clientes con créditos (al corriente y morosos)
            if (($d['ejemplos'] ?? false) && $cuenta->tiene('autos')) \App\Support\AutosEjemplo::cargar();
            // Gimnasio: tipos de clase HYROX, paquetes, horario de la semana y atletas con reservaciones
            if (($d['ejemplos'] ?? false) && $cuenta->tiene('fitness')) \App\Support\Fitness\Ejemplo::cargar();
            return [$cuenta, $dueno, $dueno->nuevoEnlace()];
        });
    }

    public function update(Request $request, Cuenta $cuenta)
    {
        $request->merge(['dominio' => self::limpiarDominio($request->input('dominio'))]);
        $principal = self::limpiarDominio(parse_url((string) config('app.url'), PHP_URL_HOST));
        $d = $request->validate([
            'nombre'  => 'required|string|max:120',
            'giro'    => ['required', Rule::in(array_keys(config('vandu.giros')))],
            'notas'   => 'nullable|string|max:2000',
            'sucursales_max' => 'nullable|integer|min:1|max:50',
            'dominio' => ['nullable', 'max:190', 'regex:/^([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/', Rule::unique('cuentas', 'dominio')->ignore($cuenta->id),
                          Rule::notIn(array_filter([$principal, $principal ? 'www.' . $principal : null]))],
        ], [
            'dominio.regex'  => 'Escribe solo el dominio, por ejemplo app.sunegocio.mx',
            'dominio.unique' => 'Ese dominio ya lo usa otro negocio.',
            'dominio.not_in' => 'Ese es el dominio principal de la plataforma.',
        ]);
        if ($cuenta->id === Cuentas::principalId()) $d['dominio'] = null;
        // Sucursales contratadas (se cobran aparte)
        $a = $cuenta->ajustes ?? [];
        $a['sucursales_max'] = (int) ($d['sucursales_max'] ?? 1);
        $d['ajustes'] = $a;
        unset($d['sucursales_max']);
        // Suspender o activar se hace con su propio botón (estado)
        if ($request->has('activa')) $d['activa'] = $cuenta->id === Cuentas::principalId() ? true : $request->boolean('activa');
        $cuenta->update($d);
        \App\Http\Middleware\CuentaPorDominio::olvidar();
        Cuentas::olvidarDominios();
        return back()->with('ok', "Cuenta “{$cuenta->nombre}” guardada.");
    }

    /** Suspender (su equipo no entra y sus enlaces no abren) o volver a activar */
    public function estado(Request $request, Cuenta $cuenta)
    {
        abort_if($cuenta->id === Cuentas::principalId(), 422, 'La cuenta principal no se puede suspender.');
        $cuenta->update(['activa' => ! $cuenta->activa]);
        \App\Http\Middleware\CuentaPorDominio::olvidar();
        if (! $cuenta->activa && (int) $request->session()->get('cuenta_vista') === $cuenta->id) $request->session()->forget('cuenta_vista');
        return back()->with('ok', $cuenta->activa ? "“{$cuenta->nombre}” está activa otra vez." : "“{$cuenta->nombre}” quedó suspendida: su equipo no puede entrar y sus enlaces no abren. Sus datos se conservan.");
    }

    /** Eliminar un negocio con todo lo suyo. Se confirma escribiendo su nombre */
    public function destroy(Request $request, Cuenta $cuenta)
    {
        abort_if($cuenta->id === Cuentas::principalId() || $cuenta->id === $request->user()->cuenta_id, 422, 'Esa cuenta no se puede eliminar.');
        $request->validate(['confirmar' => ['required', 'string', function ($attr, $v, $fail) use ($cuenta) {
            if (mb_strtolower(trim($v)) !== mb_strtolower(trim($cuenta->nombre))) $fail('Escribe el nombre exacto del negocio para confirmar.');
        }]], ['confirmar.required' => 'Escribe el nombre del negocio para confirmar.']);
        $resumen = \App\Support\Plataforma\BorrarNegocio::resumen($cuenta);
        if ((int) $request->session()->get('cuenta_vista') === $cuenta->id) $request->session()->forget('cuenta_vista');
        \App\Support\Plataforma\BorrarNegocio::borrar($cuenta);
        Cuentas::activar($request->user()->cuenta_id);
        return redirect()->route('admin.plataforma')->with('ok', "Se eliminó “{$cuenta->nombre}” con todo lo suyo." . ($resumen['correos'] ? ' Ya puedes volver a dar de alta: ' . implode(', ', $resumen['correos']) . '.' : ''));
    }

    /** "https://App.Losos.mx/admin" → "app.losos.mx" */
    public static function limpiarDominio(?string $d): ?string
    {
        $d = mb_strtolower(trim((string) $d));
        $d = preg_replace('#^[a-z]+://#', '', $d);
        $d = trim(explode('/', $d)[0], '. ');
        $d = explode(':', $d)[0];
        return $d === '' ? null : $d;
    }

    /** Ver el panel de otro negocio (soporte); todo lo que hagas queda en esa cuenta */
    public function entrar(Request $request, Cuenta $cuenta)
    {
        if ($cuenta->id === $request->user()->cuenta_id) {
            $request->session()->forget('cuenta_vista');
        } else {
            $request->session()->put('cuenta_vista', $cuenta->id);
        }
        Cuentas::activar($cuenta);
        return redirect()->to(\App\Support\Permisos::inicio($request->user()))->with('ok', "Estás viendo el panel de {$cuenta->nombre}.");
    }

    public function salir(Request $request)
    {
        $request->session()->forget('cuenta_vista');
        return redirect()->route('admin.plataforma')->with('ok', 'Regresaste a tu cuenta.');
    }
}
