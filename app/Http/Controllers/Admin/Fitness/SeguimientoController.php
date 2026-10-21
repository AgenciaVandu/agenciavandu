<?php

namespace App\Http\Controllers\Admin\Fitness;

use App\Models\Cliente;
use App\Models\Paquete;
use App\Models\Tarea;
use App\Models\User;
use App\Support\Cuentas;
use App\Support\Fitness\Embudo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Ventas del gimnasio: interesados, clases de prueba, nuevos inscritos, renovaciones y vencidos.
 * Toma el lugar del embudo de Ventas; las etapas salen de los paquetes de cada persona.
 */
class SeguimientoController extends FitnessController
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $ver = $request->query('ver') === 'descartados' ? 'descartados' : 'tablero';
        $periodo = array_key_exists($request->query('periodo'), Embudo::PERIODOS) ? $request->query('periodo') : 'mes';
        $grupos = Embudo::tablero($q);

        return view('admin.fitness.seguimiento', [
            'q'          => $q,
            'ver'        => $ver,
            'periodo'    => $periodo,
            'grupos'     => $grupos,
            'resultados' => Embudo::resultados($periodo),
            'paquetes'   => $this->paquetes(),
            'equipo'     => User::where('activo', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Alguien preguntó por WhatsApp, redes o pasó al gimnasio */
    public function store(Request $request)
    {
        $d = $request->validate([
            'nombre'   => 'required|string|max:160',
            'telefono' => 'nullable|string|max:30',
            'email'    => ['nullable', 'email', 'max:190'],
            'origen'   => ['required', Rule::in(array_keys(Embudo::ORIGENES))],
            'interes'  => 'nullable|string|max:300',
        ], ['nombre.required' => 'Escribe su nombre.']);

        $d += ['telefono' => null, 'email' => null, 'interes' => null];
        $tel = preg_replace('/\D+/', '', (string) $d['telefono']);
        $ya = ($d['email'] ? Cliente::whereRaw('LOWER(email) = ?', [mb_strtolower($d['email'])])->first() : null)
            ?? (strlen($tel) >= 8 ? Cliente::whereNotNull('telefono')->get(['id', 'telefono'])->first(fn ($x) => substr(preg_replace('/\D+/', '', (string) $x->telefono), -10) === substr($tel, -10)) : null);
        if ($ya) {
            $ya = Cliente::find($ya->id);
            return redirect()->route('admin.clientes.show', [$ya, 'ver' => 'atleta'])->with('ok', "{$ya->nombre} ya estaba registrado con ese teléfono o correo.");
        }

        Cliente::create($d + ['contacto_at' => now()]);
        return back()->with('ok', "{$d['nombre']} quedó en Interesados. Escríbele para agendar su clase de prueba.");
    }

    public function descartar(Request $request, Cliente $atleta)
    {
        $d = $request->validate(['motivo' => ['required', Rule::in(Embudo::MOTIVOS)]], ['motivo.required' => 'Elige el motivo.']);
        $atleta->forceFill(['descartado_at' => now(), 'motivo_descarte' => $d['motivo']])->save();
        Tarea::where('cliente_id', $atleta->id)->abiertas()->whereNull('proyecto_id')->update(['estado' => 'terminada', 'terminada_at' => now()]);
        return back()->with('ok', "{$atleta->nombre} salió del seguimiento ({$d['motivo']}). Lo encuentras en Descartados.");
    }

    public function recuperar(Cliente $atleta)
    {
        $atleta->forceFill(['descartado_at' => null, 'motivo_descarte' => null, 'contacto_at' => now()])->save();
        return back()->with('ok', "{$atleta->nombre} volvió al seguimiento.");
    }

    /** Recordatorio para escribirle o llamarle (queda en Tareas de quien lo atiende) */
    public function tarea(Request $request, Cliente $atleta)
    {
        $d = $request->validate([
            'titulo'       => 'required|string|max:160',
            'fecha_limite' => 'required|date',
            'hora'         => 'nullable|date_format:H:i',
            'asignada_a'   => ['nullable', Rule::exists('users', 'id')->where('activo', true)->where('cuenta_id', Cuentas::id())],
        ], ['titulo.required' => 'Escribe qué hay que hacer.', 'fecha_limite.required' => 'Elige el día.']);
        $yo = $request->user();
        $t = Tarea::create($d + ['cliente_id' => $atleta->id, 'asignada_a' => $d['asignada_a'] ?? $yo->id, 'creada_por' => $yo->id, 'estado' => 'pendiente']);
        if ($t->asignada_a !== $yo->id) {
            \App\Support\Push\Notificar::aUsuarios([$t->asignada_a], 'tarea_asignada', "{$yo->primer_nombre} te asignó: {$t->titulo}", $atleta->nombre . ($t->cuando ? ' · ' . $t->cuando : ''), route('admin.tareas.show', $t), 'tarea-' . $t->id);
        }
        return back()->with('ok', 'Seguimiento agendado' . ($t->cuando ? ' para ' . mb_strtolower($t->cuando) : '') . '.');
    }

    /** Paquete que se propone en cada botón: la prueba o el primero de pago */
    private function paquetes(): array
    {
        $activos = Paquete::where('activo', true)->orderBy('precio')->get(['id', 'es_prueba', 'precio']);
        return [
            'prueba' => $activos->firstWhere('es_prueba', true)?->id,
            'pagado' => $activos->where('es_prueba', false)->first()?->id,
        ];
    }
}
