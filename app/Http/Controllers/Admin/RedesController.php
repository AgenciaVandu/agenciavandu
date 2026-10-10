<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\CorreoVandu;
use App\Models\Cliente;
use App\Models\RedesMedio;
use App\Models\RedesPerfil;
use App\Models\RedesPost;
use App\Support\Aceptacion;
use App\Support\Correos;
use App\Support\Dropbox\Dropbox;
use App\Support\Dropbox\DropboxError;
use App\Support\Redes;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Redes sociales: calendario de contenido por cliente, editor con vista previa y envío a revisión */
class RedesController extends Controller
{
    public function index()
    {
        $tz = config('vandu.zona_horaria');
        $ini = now($tz)->startOfMonth()->setTimezone('UTC');
        $fin = now($tz)->endOfMonth()->setTimezone('UTC');

        $clientes = Cliente::whereIn('id', RedesPost::select('cliente_id')->distinct())->orWhereNotNull('redes_token')
            ->orderBy('empresa')->orderBy('nombre')->get()
            ->map(function ($c) use ($ini, $fin) {
                $mes = RedesPost::where('cliente_id', $c->id)->whereBetween('fecha', [$ini, $fin])->get();
                $c->resumen = collect(RedesPost::ESTADOS)->map(fn ($e, $k) => $mes->where('estado', $k)->count())->all();
                $c->total_mes = $mes->count();
                $c->proximo = RedesPost::where('cliente_id', $c->id)->where('fecha', '>=', now())->orderBy('fecha')->first();
                $c->comentarios_nuevos = RedesPost::where('cliente_id', $c->id)->where('estado', 'cambios')->count();
                return $c;
            });

        return view('admin.redes.index', [
            'clientes' => $clientes,
            'disponibles' => Cliente::whereNotIn('id', $clientes->pluck('id'))->orderBy('empresa')->orderBy('nombre')->get(['id', 'nombre', 'empresa']),
        ]);
    }

    public function activar(Request $request)
    {
        $c = Cliente::findOrFail($request->validate(['cliente_id' => 'required|exists:clientes,id'])['cliente_id']);
        Redes::token($c);
        return redirect()->route('admin.redes.cliente', $c)->with('ok', 'Listo. Configura cómo se ven sus perfiles y empieza a planear su contenido.');
    }

    /**
     * Quitar al cliente del servicio de redes: se borran su calendario, posts, comentarios, perfiles y códigos,
     * y su enlace deja de funcionar. El cliente sigue en el panel y lo que esté en Dropbox se queda ahí.
     */
    public function quitar(Cliente $cliente)
    {
        $posts = RedesPost::with('medios')->where('cliente_id', $cliente->id)->get();
        $n = $posts->count();
        foreach ($posts as $p) {
            $p->medios->each(fn ($m) => Redes::borrar($m));
            $p->delete();
        }
        RedesPerfil::where('cliente_id', $cliente->id)->get()->each(function ($pf) {
            if ($pf->avatar_origen === 'local' && $pf->avatar) Storage::disk('local')->delete($pf->avatar);
            $pf->delete();
        });
        \App\Models\RedesCodigo::where('cliente_id', $cliente->id)->delete();
        Storage::disk('local')->deleteDirectory("redes/{$cliente->id}");
        $cliente->forceFill(['redes_token' => null])->saveQuietly();

        return redirect()->route('admin.redes')->with('ok', ($cliente->empresa ?: $cliente->nombre) . " ya no está en el servicio de redes ($n " . ($n === 1 ? 'post borrado' : 'posts borrados') . ').');
    }

    public function cliente(Request $request, Cliente $cliente)
    {
        $mes = Redes::mes($request->query('mes'));
        $vista = in_array($request->query('vista'), ['calendario', 'feed', 'lista'], true) ? $request->query('vista') : 'calendario';
        $ini = $mes->copy()->setTimezone('UTC');
        $fin = $mes->copy()->endOfMonth()->setTimezone('UTC');

        $posts = RedesPost::with(['medios', 'comentarios'])->where('cliente_id', $cliente->id)->whereBetween('fecha', [$ini, $fin])->orderBy('fecha')->get();
        $sinFecha = RedesPost::with(['medios', 'comentarios'])->where('cliente_id', $cliente->id)->whereNull('fecha')->latest('id')->get();
        // Feed: lo de Instagram de todos los meses (como se ve el perfil), el más nuevo primero
        $feed = RedesPost::with('medios')->where('cliente_id', $cliente->id)->whereNotNull('fecha')
            ->get()->filter(fn ($p) => in_array('instagram', $p->redes ?? [], true) && $p->formato !== 'historia')->sortByDesc('fecha')->take(60)->values();

        return view('admin.redes.cliente', [
            'cliente'  => $cliente,
            'mes'      => $mes,
            'vista'    => $vista,
            'posts'    => $posts,
            'sinFecha' => $sinFecha,
            'feed'     => $feed,
            'perfiles' => Redes::perfiles($cliente),
            'url'      => Redes::urlCliente($cliente, $mes->format('Y-m')),
            'conteo'   => collect(RedesPost::ESTADOS)->map(fn ($e, $k) => $posts->where('estado', $k)->count()),
        ]);
    }

    public function perfiles(Request $request, Cliente $cliente)
    {
        $redes = array_keys(Redes::redes());
        $request->validate([
            'perfiles'                => 'array',
            'perfiles.*.usuario'      => 'nullable|string|max:80',
            'perfiles.*.nombre'       => 'nullable|string|max:120',
            'perfiles.*.bio'          => 'nullable|string|max:500',
            'perfiles.*.enlace'       => 'nullable|string|max:255',
            'perfiles.*.seguidores'   => 'nullable|integer|min:0',
            'perfiles.*.seguidos'     => 'nullable|integer|min:0',
            'avatar'                  => 'nullable|image|max:8192',
        ]);
        $avatar = null;
        if ($f = $request->file('avatar')) {
            if (Dropbox::conectado()) {
                $carpeta = Dropbox::raiz() . '/Clientes/' . Dropbox::nombreSeguro($cliente->empresa ?: $cliente->nombre) . '/Redes';
                $avatar = ['avatar' => Dropbox::cliente()->subirArchivo($f->getRealPath(), $carpeta . '/Foto de perfil.' . $f->getClientOriginalExtension())['id'], 'avatar_origen' => 'dropbox'];
            } else {
                $avatar = ['avatar' => $f->store("redes/{$cliente->id}/perfil", 'local'), 'avatar_origen' => 'local'];
            }
        }
        foreach ($redes as $red) {
            $d = $request->input("perfiles.$red", []);
            $d['usuario'] = isset($d['usuario']) ? ltrim(trim($d['usuario']), '@') : null;
            RedesPerfil::updateOrCreate(['cliente_id' => $cliente->id, 'red' => $red], array_merge(
                array_intersect_key($d, array_flip(['usuario', 'nombre', 'bio', 'enlace', 'seguidores', 'seguidos'])),
                $avatar ?? [],
            ));
        }
        return back()->with('ok', 'Perfiles guardados.');
    }

    public function crear(Request $request, Cliente $cliente)
    {
        $d = $request->validate(['fecha' => 'nullable|date', 'redes' => 'nullable|array']);
        $fecha = ! empty($d['fecha'])
            ? Carbon::parse($d['fecha'] . (strlen($d['fecha']) <= 10 ? ' 10:00' : ''), config('vandu.zona_horaria'))->setTimezone('UTC')
            : null;
        $p = RedesPost::create([
            'cliente_id' => $cliente->id,
            'redes'      => array_values(array_intersect($d['redes'] ?? ['instagram', 'facebook'], array_keys(Redes::redes()))) ?: ['instagram'],
            'formato'    => 'post',
            'fecha'      => $fecha,
            'estado'     => 'borrador',
        ]);
        Redes::token($cliente);
        return redirect()->route('admin.redes.post', $p);
    }

    public function post(RedesPost $post)
    {
        $post->load(['medios', 'comentarios', 'cliente']);
        return view('admin.redes.editor', [
            'post'     => $post,
            'cliente'  => $post->cliente,
            'datos'    => Redes::datos($post),
            'perfiles' => Redes::perfiles($post->cliente),
        ]);
    }

    public function guardar(Request $request, RedesPost $post)
    {
        $d = $request->validate([
            'titulo'  => 'nullable|string|max:120',
            'redes'   => 'required|array|min:1',
            'redes.*' => [Rule::in(array_keys(Redes::redes()))],
            'formato' => ['required', Rule::in(array_keys(config('vandu.redes.formatos')))],
            'fecha'   => 'nullable|date',
            'texto'   => 'nullable|string|max:10000',
            'estado'  => ['required', Rule::in(array_keys(RedesPost::ESTADOS))],
        ], ['redes.required' => 'Elige al menos una red.']);

        $antes = $post->estado;
        $post->fill([
            'titulo'  => $d['titulo'] ?? null,
            'redes'   => array_values(array_unique($d['redes'])),
            'formato' => $d['formato'],
            'fecha'   => ! empty($d['fecha']) ? Carbon::parse($d['fecha'], config('vandu.zona_horaria'))->setTimezone('UTC') : null,
            'texto'   => $d['texto'] ?? null,
            'estado'  => $d['estado'],
        ]);
        if ($d['estado'] === 'aprobado' && $antes !== 'aprobado' && ! $post->aprobado_at) {
            $post->aprobado_at = now();
            $post->aprobado_por = 'Agencia (' . ($request->user()?->name ?? 'manual') . ')';
        }
        $post->save();
        if ($antes !== $post->estado) {
            Redes::comentar($post, 'estado', 'agencia', $request->user()?->name, 'Estado: ' . $post->estado_texto);
        }

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'post' => Redes::datos($post->fresh(['medios', 'comentarios']))])
            : back()->with('ok', 'Post guardado.');
    }

    public function borrar(RedesPost $post)
    {
        $cliente = $post->cliente;
        $mes = $post->fecha_local?->format('Y-m');
        $post->medios->each(fn ($m) => Redes::borrar($m));
        $post->delete();
        return redirect()->route('admin.redes.cliente', array_filter([$cliente, 'mes' => $mes]))->with('ok', 'Post eliminado.');
    }

    public function duplicar(RedesPost $post)
    {
        $copia = $post->replicate(['estado', 'aprobado_at', 'aprobado_por']);
        $copia->estado = 'borrador';
        $copia->titulo = trim(($post->titulo ?: 'Post') . ' (copia)');
        $copia->save();
        foreach ($post->medios as $m) {
            if ($m->origen === 'local' && Storage::disk('local')->exists($m->ruta)) {
                $nueva = "redes/{$copia->cliente_id}/{$copia->id}/" . basename($m->ruta);
                Storage::disk('local')->copy($m->ruta, $nueva);
                $copia->medios()->create(array_merge($m->only(['orden', 'tipo', 'origen', 'nombre', 'ancho', 'alto', 'bytes']), ['ruta' => $nueva]));
            } else {
                $copia->medios()->create($m->only(['orden', 'tipo', 'origen', 'ruta', 'nombre', 'ancho', 'alto', 'bytes']));
            }
        }
        return redirect()->route('admin.redes.post', $copia)->with('ok', 'Copia creada como borrador.');
    }

    public function subir(Request $request, RedesPost $post)
    {
        $max = config('vandu.redes.max_mb') * 1024;
        $request->validate([
            'archivos'   => 'required|array|max:20',
            'archivos.*' => "file|max:$max|mimetypes:image/jpeg,image/png,image/webp,image/gif,image/heic,video/mp4,video/quicktime,video/webm",
            'ancho.*'    => 'nullable|integer', 'alto.*' => 'nullable|integer',
        ], ['archivos.*.mimetypes' => 'Solo fotos (JPG, PNG, WEBP) o videos (MP4, MOV).', 'archivos.*.max' => 'Cada archivo puede pesar hasta ' . config('vandu.redes.max_mb') . ' MB.']);

        try {
            foreach ($request->file('archivos') as $i => $f) {
                Redes::guardar($post->load('cliente'), $f, $request->integer("ancho.$i") ?: null, $request->integer("alto.$i") ?: null);
            }
        } catch (DropboxError $e) {
            return response()->json(['ok' => false, 'mensaje' => 'Dropbox: ' . $e->getMessage()], 502);
        }
        return response()->json(['ok' => true, 'medios' => $post->fresh('medios')->medios->map(fn ($m) => Redes::medio($m))->values()]);
    }

    public function dropbox(Request $request, RedesPost $post)
    {
        $d = $request->validate(['ids' => 'required|array|min:1|max:20', 'ids.*' => 'string|starts_with:id:']);
        try {
            foreach ($d['ids'] as $id) Redes::vincularDropbox($post, $id);
        } catch (DropboxError $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 502);
        }
        return response()->json(['ok' => true, 'medios' => $post->fresh('medios')->medios->map(fn ($m) => Redes::medio($m))->values()]);
    }

    public function ordenar(Request $request, RedesPost $post)
    {
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids'];
        foreach ($ids as $i => $id) RedesMedio::where('post_id', $post->id)->whereKey($id)->update(['orden' => $i]);
        return response()->json(['ok' => true]);
    }

    public function quitarMedio(RedesMedio $medio)
    {
        $post = $medio->post;
        Redes::borrar($medio);
        return response()->json(['ok' => true, 'medios' => $post->fresh('medios')->medios->map(fn ($m) => Redes::medio($m))->values()]);
    }

    public function medio(Request $request, RedesMedio $medio)
    {
        return Redes::responder($medio, $request->query('v') === 'mini');
    }

    public function comentar(Request $request, RedesPost $post)
    {
        $t = $request->validate(['texto' => 'required|string|max:3000'])['texto'];
        Redes::comentar($post, 'comentario', 'agencia', $request->user()?->name, trim($t));
        return response()->json(['ok' => true, 'post' => Redes::datos($post->fresh(['medios', 'comentarios']))]);
    }

    /** Arrastrar en el feed: intercambia la fecha de dos posts para cambiar su lugar en la cuadrícula */
    public function mover(Request $request, Cliente $cliente)
    {
        $d = $request->validate(['a' => 'required|integer', 'b' => 'required|integer']);
        $a = RedesPost::where('cliente_id', $cliente->id)->findOrFail($d['a']);
        $b = RedesPost::where('cliente_id', $cliente->id)->findOrFail($d['b']);
        if ($a->estado === 'publicado' || $b->estado === 'publicado') {
            return response()->json(['ok' => false, 'mensaje' => 'Los posts ya publicados no se pueden mover.'], 422);
        }
        [$fa, $fb] = [$a->fecha, $b->fecha];
        $a->update(['fecha' => $fb]);
        $b->update(['fecha' => $fa]);
        return response()->json(['ok' => true]);
    }

    /** Manda el mes a revisión: los borradores pasan a "En revisión" y el cliente recibe su enlace con código */
    public function revision(Request $request, Cliente $cliente)
    {
        $d = $request->validate(['mes' => 'required|date_format:Y-m', 'canal' => 'required|in:correo,whatsapp,enlace']);
        $mes = Redes::mes($d['mes']);
        $posts = RedesPost::where('cliente_id', $cliente->id)
            ->whereBetween('fecha', [$mes->copy()->setTimezone('UTC'), $mes->copy()->endOfMonth()->setTimezone('UTC')])
            ->whereIn('estado', ['borrador', 'cambios'])->get();
        foreach ($posts as $p) {
            $p->update(['estado' => 'revision']);
            Redes::comentar($p, 'revision', 'agencia', $request->user()?->name, 'Enviado a revisión');
        }
        $pendientes = RedesPost::where('cliente_id', $cliente->id)->where('estado', 'revision')
            ->whereBetween('fecha', [$mes->copy()->setTimezone('UTC'), $mes->copy()->endOfMonth()->setTimezone('UTC')])->count();

        $url = Redes::urlCliente($cliente, $d['mes']);
        $c = Redes::generarCodigo($cliente, $d['canal']);
        $vence = Aceptacion::vigenciaTexto($c['expira']);
        $mesTexto = $mes->locale('es')->isoFormat('MMMM');
        $nombre = Correos::primerNombre($cliente->nombre);

        if ($d['canal'] === 'whatsapp') {
            abort_unless($cliente->whatsapp, 422, 'El cliente no tiene WhatsApp registrado.');
            $msg = "Hola {$nombre}, ya está listo tu contenido de {$mesTexto} para revisión ({$pendientes} publicaciones): {$url}\n\nAhí puedes ver cómo queda en cada red, aprobar o dejarnos comentarios. Tu código de verificación: *{$c['formateado']}* (válido hasta el {$vence}).";
            return redirect()->away('https://wa.me/' . $cliente->whatsapp . '?text=' . rawurlencode($msg));
        }
        if ($d['canal'] === 'correo') {
            abort_unless($cliente->email, 422, 'El cliente no tiene correo registrado.');
            try {
                Mail::to($cliente->email)->send(new CorreoVandu(
                    asunto: "Tu contenido de {$mesTexto} está listo para revisión",
                    titulo: 'Tu contenido está listo',
                    cuerpo: "Hola {$nombre},\n\nPreparamos {$pendientes} publicaciones para {$mesTexto}. En el enlace puedes ver cómo se verá cada una en Instagram, Facebook, TikTok o LinkedIn, aprobarlas o dejarnos tus comentarios en cada post.\n\n¡Gracias!",
                    boton: 'Revisar mi contenido', url: $url,
                    codigo: ['formateado' => $c['formateado'], 'vigencia' => $vence],
                ));
            } catch (\Throwable $e) {
                report($e);
                return back()->withErrors(['correo' => 'No se pudo enviar el correo: ' . Str::limit($e->getMessage(), 140)]);
            }
            return back()->with('ok', "Contenido de {$mesTexto} enviado a revisión a {$cliente->email}.");
        }
        return back()->with('ok', "{$posts->count()} posts pasaron a revisión. Código para el cliente: {$c['formateado']} (vale 24 h).")
            ->with('codigo_redes', $c['formateado']);
    }
}
