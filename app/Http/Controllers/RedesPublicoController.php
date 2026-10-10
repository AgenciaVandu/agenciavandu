<?php

namespace App\Http\Controllers;

use App\Mail\CorreoVandu;
use App\Models\Cliente;
use App\Models\RedesMedio;
use App\Models\RedesPerfil;
use App\Models\RedesPost;
use App\Support\Aceptacion;
use App\Support\Correos;
use App\Support\Dropbox\Dropbox;
use App\Support\Push\Notificar;
use App\Support\Redes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Página del cliente para revisar su contenido: perfil, feed, calendario, comentarios y aprobación */
class RedesPublicoController extends Controller
{
    private function cliente(string $token): Cliente
    {
        return Cliente::where('redes_token', $token)->firstOrFail();
    }

    public function show(Request $request, string $token)
    {
        $c = $this->cliente($token);
        $mes = Redes::mes($request->query('mes'));
        $ini = $mes->copy()->setTimezone('UTC');
        $fin = $mes->copy()->endOfMonth()->setTimezone('UTC');

        // El cliente no ve borradores: solo lo que ya se le mandó a revisar
        $visibles = fn ($q) => $q->where('cliente_id', $c->id)->where('estado', '!=', 'borrador');
        $posts = RedesPost::with(['medios', 'comentarios'])->where($visibles)->whereBetween('fecha', [$ini, $fin])->orderBy('fecha')->get();
        $feed = RedesPost::with('medios')->where($visibles)->whereNotNull('fecha')->get()
            ->filter(fn ($p) => in_array('instagram', $p->redes ?? [], true) && $p->formato !== 'historia')->sortByDesc('fecha')->take(60)->values();
        $meses = RedesPost::where($visibles)->whereNotNull('fecha')->get(['fecha'])
            ->map(fn ($p) => $p->fecha->copy()->setTimezone(config('vandu.zona_horaria'))->format('Y-m'))->unique()->sort()->values();

        return response()->view('redes.publico', [
            'c'        => $c,
            'token'    => $token,
            'mes'      => $mes,
            'meses'    => $meses,
            'posts'    => $posts->map(fn ($p) => Redes::datos($p, $token))->values(),
            'feed'     => $feed->map(fn ($p) => Redes::datos($p, $token))->values(),
            'perfiles' => Redes::perfiles($c),
            'sesion'   => Redes::sesion($c),
        ])->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function medio(Request $request, string $token, RedesMedio $medio)
    {
        $c = $this->cliente($token);
        abort_unless($medio->post && $medio->post->cliente_id === $c->id && $medio->post->estado !== 'borrador', 404);
        return Redes::responder($medio, $request->query('v') === 'mini');
    }

    public function avatar(string $token, string $red)
    {
        $c = $this->cliente($token);
        $p = RedesPerfil::where('cliente_id', $c->id)->where('red', $red)->whereNotNull('avatar')->firstOrFail();
        if ($p->avatar_origen === 'local') {
            return response()->file(Storage::disk('local')->path($p->avatar), ['Cache-Control' => 'public, max-age=86400']);
        }
        return redirect()->away(Dropbox::cliente()->enlaceTemporal($p->avatar));
    }

    /** Verifica el código una vez; después puede aprobar y comentar durante unas horas sin volver a escribirlo */
    public function verificar(Request $request, string $token)
    {
        $c = $this->cliente($token);
        $d = $request->validate(['codigo' => 'required|string|max:12', 'nombre' => 'required|string|max:120'], [
            'codigo.required' => 'Escribe tu código de verificación.', 'nombre.required' => 'Escribe tu nombre.',
        ]);
        $ok = Redes::verificar($c, $d['codigo']);
        if (is_string($ok)) return response()->json(['ok' => false, 'mensaje' => $ok, 'campo' => 'codigo'], 422);
        Redes::abrirSesion($c, trim($d['nombre']), $ok->expira_at);
        return response()->json(['ok' => true, 'nombre' => trim($d['nombre'])]);
    }

    public function codigo(string $token)
    {
        $c = $this->cliente($token);
        if (! $c->email) return response()->json(['ok' => false, 'mensaje' => 'No tenemos un correo registrado. Pídenos tu código por WhatsApp.'], 422);
        $k = Redes::generarCodigo($c, 'cliente', '7d');
        try {
            Mail::to($c->email)->send(new CorreoVandu(
                asunto: "Tu código de verificación: {$k['formateado']}",
                titulo: 'Tu código de verificación',
                cuerpo: 'Hola ' . Correos::primerNombre($c->nombre) . ",\n\nUsa este código para aprobar o comentar tu contenido en redes. Si no lo pediste tú, ignora este mensaje.",
                boton: 'Volver a mi contenido', url: Redes::urlCliente($c),
                codigo: ['formateado' => $k['formateado'], 'vigencia' => Aceptacion::vigenciaTexto($k['expira'])],
            ));
        } catch (\Throwable $e) {
            Log::error('Código de redes: ' . $e->getMessage());
            return response()->json(['ok' => false, 'mensaje' => 'No pudimos enviar el correo. Pídenos tu código por WhatsApp.'], 500);
        }
        return response()->json(['ok' => true, 'mensaje' => 'Te enviamos un código a ' . Aceptacion::correoOculto($c->email) . '.']);
    }

    public function responder(Request $request, string $token, RedesPost $post)
    {
        $c = $this->cliente($token);
        abort_unless($post->cliente_id === $c->id && $post->estado !== 'borrador', 404);
        $s = Redes::sesion($c);
        if (! $s) return response()->json(['ok' => false, 'mensaje' => 'Confirma tu identidad con tu código para continuar.', 'verificar' => true], 401);

        $d = $request->validate([
            'accion' => 'required|in:aprobar,cambios,comentario',
            'texto'  => 'required_unless:accion,aprobar|nullable|string|max:3000',
        ], ['texto.required_unless' => 'Escribe tu comentario.']);

        if ($d['accion'] !== 'comentario' && in_array($post->estado, ['aprobado', 'publicado'], true)) {
            return response()->json(['ok' => false, 'mensaje' => 'Este post ya está ' . mb_strtolower($post->estado_texto) . '. Puedes dejar un comentario.'], 409);
        }

        $quien = $c->empresa ?: $c->nombre;
        if ($d['accion'] === 'aprobar') {
            $post->update(['estado' => 'aprobado', 'aprobado_at' => now(), 'aprobado_por' => $s['nombre']]);
            Redes::comentar($post, 'aprobado', 'cliente', $s['nombre'], $d['texto'] ?? null);
            Notificar::evento('redes_aprobado', "$quien aprobó un post", ($post->titulo ?: Str::limit((string) $post->texto, 60)) ?: 'Post del ' . $post->fecha_local?->format('d/m'), route('admin.redes.post', $post), 'redes-' . $c->id);
        } else {
            if ($d['accion'] === 'cambios') $post->update(['estado' => 'cambios']);
            Redes::comentar($post, $d['accion'], 'cliente', $s['nombre'], trim($d['texto']));
            Notificar::evento('redes_cambios', $d['accion'] === 'cambios' ? "$quien pidió un cambio" : "$quien comentó un post", Str::limit(trim($d['texto']), 100), route('admin.redes.post', $post), 'redes-' . $c->id);
        }

        return response()->json(['ok' => true, 'post' => Redes::datos($post->fresh(['medios', 'comentarios']), $token)]);
    }

    public function aprobarTodo(Request $request, string $token)
    {
        $c = $this->cliente($token);
        $s = Redes::sesion($c);
        if (! $s) return response()->json(['ok' => false, 'mensaje' => 'Confirma tu identidad con tu código para continuar.', 'verificar' => true], 401);
        $mes = Redes::mes($request->input('mes'));
        $posts = RedesPost::where('cliente_id', $c->id)->where('estado', 'revision')
            ->whereBetween('fecha', [$mes->copy()->setTimezone('UTC'), $mes->copy()->endOfMonth()->setTimezone('UTC')])->get();
        foreach ($posts as $p) {
            $p->update(['estado' => 'aprobado', 'aprobado_at' => now(), 'aprobado_por' => $s['nombre']]);
            Redes::comentar($p, 'aprobado', 'cliente', $s['nombre'], 'Aprobado junto con todo el mes');
        }
        if ($posts->count()) {
            Notificar::evento('redes_aprobado', ($c->empresa ?: $c->nombre) . ' aprobó su contenido', $posts->count() . ' posts de ' . $mes->locale('es')->isoFormat('MMMM') . ' aprobados', route('admin.redes.cliente', [$c, 'mes' => $mes->format('Y-m')]), 'redes-' . $c->id);
        }
        return response()->json(['ok' => true, 'aprobados' => $posts->count()]);
    }
}
