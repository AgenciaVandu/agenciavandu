<?php

namespace App\Http\Controllers\Admin\Fitness;

use App\Models\Cliente;
use App\Support\Cuentas;
use App\Support\Fitness\Fotos;
use Illuminate\Http\Request;

/** Lo propio del atleta en su ficha: foto, acceso a su portal, credencial QR y si está activo */
class AtletasController extends FitnessController
{
    public const VISTAS = [
        'todos'      => 'Todos',
        'activos'    => 'Con paquete',
        'por_vencer' => 'Por renovar',
        'sin_paquete' => 'Sin paquete',
        'pendientes' => 'Pago pendiente',
        'baja'       => 'Dados de baja',
    ];

    /** Lista de atletas (la sección Clientes de un gimnasio) */
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $ver = array_key_exists($request->query('ver'), self::VISTAS) ? $request->query('ver') : 'todos';
        $hoy = \App\Models\Membresia::hoy();
        $pronto = now(config('vandu.zona_horaria'))->addDays(7)->toDateString();

        $vista = fn (string $v) => function ($w) use ($v, $hoy, $pronto) {
            match ($v) {
                'activos'     => $w->whereHas('membresias', fn ($m) => $m->vigentes()),
                'por_vencer'  => $w->whereHas('membresias', fn ($m) => $m->vigentes()->where(fn ($x) => $x->whereDate('vence_el', '<=', $pronto)->orWhereRaw('creditos IS NOT NULL AND usados + 1 >= creditos'))),
                'sin_paquete' => $w->where('activo', '!=', false)->whereDoesntHave('membresias', fn ($m) => $m->vigentes()),
                'pendientes'  => $w->whereHas('membresias', fn ($m) => $m->where('estado', 'activa')->whereNull('pagado_el')),
                'baja'        => $w->where('activo', false),
                default       => $w,
            };
        };

        $atletas = Cliente::query()
            ->with(['membresias' => fn ($m) => $m->where('estado', 'activa')->whereDate('vence_el', '>=', $hoy)])
            ->withCount(['reservaciones as asistencias_count' => fn ($r) => $r->where('estado', 'asistio')])
            ->withMax(['reservaciones as ultima_asistencia' => fn ($r) => $r->where('estado', 'asistio')], 'checkin_at')
            ->when($q, fn ($w) => $w->where(fn ($x) => $x->where('nombre', 'like', "%$q%")->orWhere('email', 'like', "%$q%")->orWhere('telefono', 'like', "%$q%")))
            ->where($vista($ver))
            ->orderByRaw('activo = 0')->orderBy('nombre')
            ->paginate(30)->withQueryString();

        $conteos = collect(self::VISTAS)->map(fn ($t, $k) => Cliente::where($vista($k))->count());
        return view('admin.fitness.atletas', compact('atletas', 'q', 'ver', 'conteos'));
    }

    public function foto(int $atleta)
    {
        return Fotos::responder(Cliente::findOrFail($atleta));
    }

    public function subirFoto(Request $request, Cliente $atleta)
    {
        $request->validate(['foto' => 'required|image|max:12288'], ['foto.image' => 'Sube una imagen (JPG, PNG o WebP).', 'foto.max' => 'La foto pesa demasiado (máx. 12 MB).']);
        try {
            Fotos::guardar($atleta, $request->file('foto'));
        } catch (\RuntimeException $e) {
            return back()->withErrors(['foto' => $e->getMessage()]);
        }
        return back()->with('ok', 'Foto guardada. Con ella lo reconocen al escanear su QR.');
    }

    public function quitarFoto(Cliente $atleta)
    {
        Fotos::quitar($atleta);
        return back()->with('ok', 'Foto quitada.');
    }

    /** Enlace para que cree (o cambie) su contraseña del portal; se manda por WhatsApp o se copia */
    public function acceso(Request $request, Cliente $atleta)
    {
        abort_unless($atleta->email, 422, 'Primero captura su correo: es su usuario para entrar.');
        $url = $atleta->nuevoAcceso();
        $negocio = Cuentas::actual()?->nombre;
        $texto = "Hola {$atleta->primer_nombre}, este es tu acceso a {$negocio}: reserva tus clases, ve tus créditos y tu credencial QR para entrar. Crea tu contraseña aquí (sirve 7 días):\n$url";
        return back()->with('ok', 'Enlace de acceso listo. Mándaselo por WhatsApp o cópialo.')->with('acceso', [
            'url' => $url, 'whatsapp' => $atleta->whatsapp ? 'https://wa.me/' . $atleta->whatsapp . '?text=' . rawurlencode($texto) : null,
        ]);
    }

    public function nuevoQr(Cliente $atleta)
    {
        $atleta->nuevoQr();
        return back()->with('ok', 'Se generó una credencial nueva. La anterior ya no sirve.');
    }

    public function activo(Request $request, Cliente $atleta)
    {
        $atleta->forceFill(['activo' => ! ($atleta->activo ?? true)])->save();
        return back()->with('ok', $atleta->activo ? "{$atleta->nombre} está activo otra vez." : "{$atleta->nombre} quedó dado de baja: no puede reservar ni entrar con su QR.");
    }

    /** Credencial para imprimir o mandar como imagen */
    public function credencial(Cliente $atleta)
    {
        return view('admin.fitness.credencial', ['a' => $atleta, 'cuenta' => Cuentas::actual()]);
    }
}
