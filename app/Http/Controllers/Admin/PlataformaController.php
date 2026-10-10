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

        [$cuenta, $dueno, $url] = DB::transaction(fn () => self::crearCuenta($d));
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
            return [$cuenta, $dueno, $dueno->nuevoEnlace()];
        });
    }

    public function update(Request $request, Cuenta $cuenta)
    {
        $d = $request->validate([
            'nombre' => 'required|string|max:120',
            'giro'   => ['required', Rule::in(array_keys(config('vandu.giros')))],
            'notas'  => 'nullable|string|max:2000',
        ]);
        $d['activa'] = $cuenta->id === Cuentas::principalId() ? true : $request->boolean('activa');
        $cuenta->update($d);
        return back()->with('ok', "Cuenta “{$cuenta->nombre}” guardada.");
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
