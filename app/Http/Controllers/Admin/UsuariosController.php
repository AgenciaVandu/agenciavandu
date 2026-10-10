<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\CorreoVandu;
use App\Models\Rol;
use App\Models\Tarea;
use App\Models\User;
use App\Support\Permisos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Equipo: invitar personas, darles rol y puesto, y armar los roles con sus permisos */
class UsuariosController extends Controller
{
    public function index(Request $request)
    {
        $usuarios = User::with('rol')->withCount(['tareas as abiertas_count' => fn ($q) => $q->abiertas()])
            ->orderByDesc('activo')->orderBy('name')->get();

        return view('admin.usuarios.index', [
            'usuarios'  => $usuarios,
            'roles'     => Rol::withCount('usuarios')->orderBy('orden')->orderBy('id')->get(),
            'secciones' => Permisos::SECCIONES,
            'tab'       => $request->query('tab') === 'roles' ? 'roles' : 'personas',
        ]);
    }

    public function store(Request $request)
    {
        $d = $this->validar($request);
        $u = User::create($d + ['password' => Str::random(40), 'activo' => true]);
        return $this->conEnlace($request, $u, 'Invitamos a ' . $u->primer_nombre . '.');
    }

    public function edit(User $usuario)
    {
        return view('admin.usuarios.editar', [
            'u'       => $usuario->load('rol'),
            'roles'   => Rol::orderBy('orden')->orderBy('id')->get(),
            'tareas'  => Tarea::with('cliente', 'proyecto.cliente')->where('asignada_a', $usuario->id)->abiertas()->orderByRaw('fecha_limite is null')->orderBy('fecha_limite')->take(8)->get(),
            'yo'      => auth()->id() === $usuario->id,
        ]);
    }

    public function update(Request $request, User $usuario)
    {
        $d = $this->validar($request, $usuario);
        $d['activo'] = $request->boolean('activo');
        $yo = $request->user()->id === $usuario->id;
        if ($yo && ! $d['activo']) throw ValidationException::withMessages(['activo' => 'No puedes desactivar tu propia cuenta.']);
        $nuevoRol = Rol::find($d['rol_id']);
        if ($yo && $usuario->esSuperAdmin() && ! $nuevoRol?->todo) throw ValidationException::withMessages(['rol_id' => 'No puedes quitarte a ti mismo el rol de super admin.']);
        if ($usuario->esSuperAdmin() && (! $nuevoRol?->todo || ! $d['activo']) && $this->superAdmins() <= 1) {
            throw ValidationException::withMessages(['rol_id' => 'Debe quedar al menos un super admin activo.']);
        }
        $usuario->update($d);
        return redirect()->route('admin.usuarios.edit', $usuario)->with('ok', 'Cambios guardados.');
    }

    /** Nuevo enlace para crear contraseña (invitación o "olvidé mi contraseña") */
    public function enlace(Request $request, User $usuario)
    {
        abort_unless($usuario->activo, 422, 'La cuenta está desactivada.');
        return $this->conEnlace($request, $usuario, $usuario->pendiente ? 'Nueva invitación lista.' : 'Enlace para nueva contraseña listo.');
    }

    public function destroy(Request $request, User $usuario)
    {
        if ($request->user()->id === $usuario->id) return back()->withErrors(['usuario' => 'No puedes eliminar tu propia cuenta.']);
        if ($usuario->esSuperAdmin() && $this->superAdmins() <= 1) return back()->withErrors(['usuario' => 'Debe quedar al menos un super admin activo.']);
        $n = $usuario->name;
        $usuario->delete(); // sus tareas quedan sin asignar
        return redirect()->route('admin.usuarios')->with('ok', "Se eliminó a $n. Sus tareas quedaron sin asignar.");
    }

    /* ---------------- Roles ---------------- */

    public function rolStore()
    {
        $r = Rol::create(['nombre' => 'Rol nuevo', 'permisos' => [], 'orden' => (int) Rol::max('orden') + 1]);
        return redirect()->to(route('admin.usuarios', ['tab' => 'roles']) . '#rol-' . $r->id)->with('ok', 'Rol creado. Ponle nombre y elige qué puede ver.');
    }

    public function rolUpdate(Request $request, Rol $rol)
    {
        $d = $request->validate([
            'nombre'      => 'required|string|max:60',
            'descripcion' => 'nullable|string|max:200',
            'permisos'    => 'array',
            'permisos.*'  => [Rule::in(array_keys(Permisos::SECCIONES))],
        ], ['nombre.required' => 'Ponle nombre al rol.']);
        // El super admin siempre lo puede todo
        $rol->update(['nombre' => $d['nombre'], 'descripcion' => $d['descripcion'] ?? null] + ($rol->todo ? [] : ['permisos' => array_values($d['permisos'] ?? [])]));
        return redirect()->to(route('admin.usuarios', ['tab' => 'roles']) . '#rol-' . $rol->id)->with('ok', "Rol “{$rol->nombre}” guardado.");
    }

    public function rolDestroy(Rol $rol)
    {
        if ($rol->todo) return back()->withErrors(['rol' => 'El rol de super admin no se puede eliminar.']);
        if ($n = $rol->usuarios()->count()) return back()->withErrors(['rol' => "No se puede eliminar: $n " . ($n === 1 ? 'persona lo tiene' : 'personas lo tienen') . '. Cámbiales el rol primero.']);
        $rol->delete();
        return redirect()->route('admin.usuarios', ['tab' => 'roles'])->with('ok', "Se eliminó el rol “{$rol->nombre}”.");
    }

    /* ---------------- Apoyo ---------------- */

    private function validar(Request $request, ?User $u = null): array
    {
        return $request->validate([
            'name'     => 'required|string|max:120',
            'email'    => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($u?->id)],
            'puesto'   => 'nullable|string|max:80',
            'telefono' => 'nullable|string|max:30',
            'rol_id'   => 'required|exists:roles,id',
        ], [
            'name.required'  => 'Escribe su nombre.',
            'email.required' => 'Escribe su correo.',
            'email.unique'   => 'Ya hay alguien con ese correo.',
            'rol_id.required'=> 'Elige un rol.',
        ]);
    }

    private function superAdmins(): int
    {
        return User::where('activo', true)->whereHas('rol', fn ($q) => $q->where('todo', true))->count();
    }

    /** Genera el enlace, lo manda por correo si se pidió y lo deja a la mano para copiar o mandar por WhatsApp */
    private function conEnlace(Request $request, User $u, string $ok)
    {
        $url = $u->nuevoEnlace();
        $nuevo = $u->pendiente;
        $correo = false;
        if ($request->boolean('enviar_correo', true)) {
            try {
                Mail::to($u->email, $u->name)->send(new CorreoVandu(
                    asunto: $nuevo ? 'Te invitaron al panel de Agencia Vandu' : 'Crea una nueva contraseña para el panel',
                    titulo: $nuevo ? "Hola, {$u->primer_nombre}" : 'Nueva contraseña',
                    cuerpo: ($nuevo
                        ? "Ya tienes acceso al panel de Agencia Vandu" . ($u->puesto ? " como {$u->puesto}" : '') . ". Ahí verás tus tareas y podrás subir tu trabajo.\n\nCrea tu contraseña con el botón. El enlace sirve 7 días."
                        : "Usa el botón para crear una nueva contraseña. El enlace sirve 7 días y deja de funcionar al usarlo."),
                    boton: $nuevo ? 'Crear mi contraseña' : 'Crear nueva contraseña',
                    url: $url,
                ));
                $correo = true;
            } catch (Throwable $e) {
                report($e);
            }
        }
        $texto = ($nuevo ? "Hola {$u->primer_nombre}, ya tienes acceso al panel de Agencia Vandu. Crea tu contraseña aquí (sirve 7 días):" : "Hola {$u->primer_nombre}, aquí puedes crear tu nueva contraseña del panel de Agencia Vandu (sirve 7 días):") . "\n$url";

        return redirect()->route('admin.usuarios.edit', $u)->with('ok', $ok . ($correo ? " Le enviamos el enlace a {$u->email}." : ''))->with('enlace', [
            'url'      => $url,
            'whatsapp' => 'https://wa.me/' . ($u->whatsapp ?? '') . '?text=' . rawurlencode($texto),
            'correo'   => $correo,
        ]);
    }
}
