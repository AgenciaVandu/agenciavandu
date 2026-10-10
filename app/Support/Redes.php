<?php

namespace App\Support;

use App\Models\Cliente;
use App\Models\RedesCodigo;
use App\Models\RedesComentario;
use App\Models\RedesMedio;
use App\Models\RedesPerfil;
use App\Models\RedesPost;
use App\Support\Dropbox\Dropbox;
use App\Support\Dropbox\DropboxError;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Módulo de redes sociales: archivos de cada post, datos para las vistas previas y verificación del cliente.
 * Las fotos y videos se guardan en Dropbox (Vandu/Clientes/<Cliente>/Redes/<Año-Mes>/) cuando está conectado.
 */
class Redes
{
    public const INTENTOS = 5;

    /** Cuánto dura el código. Como el servicio es mensual, por defecto vale todo el mes que se revisa. */
    public const VIGENCIAS = ['24h' => '24 horas', '7d' => '7 días', 'mes' => 'Todo el mes'];

    public static function redes(): array { return config('vandu.redes.redes'); }

    public static function token(Cliente $c): string
    {
        if (! $c->redes_token) {
            $c->forceFill(['redes_token' => Str::random(32)])->saveQuietly();
        }
        return $c->redes_token;
    }

    public static function urlCliente(Cliente $c, ?string $mes = null): string
    {
        return route('redes.publico', array_filter(['token' => self::token($c), 'mes' => $mes]));
    }

    public static function perfiles(Cliente $c): array
    {
        $guardados = RedesPerfil::where('cliente_id', $c->id)->get()->keyBy('red');
        $nombre = $c->empresa ?: $c->nombre;
        $usuario = Str::of($nombre)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '')->limit(28, '')->value();
        $out = [];
        foreach (self::redes() as $red => $info) {
            $p = $guardados->get($red);
            $out[$red] = [
                'red'        => $red,
                'usuario'    => $p?->usuario ?: $usuario,
                'nombre'     => $p?->nombre ?: $nombre,
                'bio'        => (string) $p?->bio,
                'enlace'     => (string) $p?->enlace,
                'seguidores' => $p?->seguidores,
                'seguidos'   => $p?->seguidos,
                'avatar'     => $p?->avatar ? route('redes.avatar', [self::token($c), $red]) . '?v=' . $p->updated_at?->timestamp : null,
                'iniciales'  => collect(explode(' ', $nombre))->filter()->take(2)->map(fn ($x) => mb_strtoupper(mb_substr($x, 0, 1)))->join(''),
            ];
        }
        return $out;
    }

    /** Datos de un post para las vistas previas (los usa el panel y la página del cliente) */
    public static function datos(RedesPost $p, ?string $token = null): array
    {
        $tz = config('vandu.zona_horaria');
        $f = $p->fecha?->copy()->setTimezone($tz);
        return [
            'id'          => $p->id,
            'titulo'      => (string) $p->titulo,
            'redes'       => array_values($p->redes ?? []),
            'formato'     => $p->formato,
            'fecha'       => $f?->format('Y-m-d\TH:i'),
            'dia'         => $f?->format('Y-m-d'),
            'fecha_texto' => $f ? ucfirst($f->locale('es')->isoFormat('ddd D [de] MMM · H:mm')) : 'Sin fecha',
            'texto'       => (string) $p->texto,
            'estado'      => $p->estado,
            'estado_texto'=> $p->estado_texto,
            'aprobado'    => $p->aprobado_at ? ($p->aprobado_por . ' · ' . $p->aprobado_at->copy()->setTimezone($tz)->locale('es')->isoFormat('D MMM, H:mm')) : null,
            'medios'      => $p->medios->map(fn ($m) => self::medio($m, $token))->values()->all(),
            'comentarios' => $p->comentarios->map(fn ($c) => [
                'tipo' => $c->tipo, 'actor' => $c->actor, 'autor' => $c->autor, 'texto' => $c->texto,
                'fecha' => $c->created_at?->copy()->setTimezone($tz)->locale('es')->isoFormat('D MMM, H:mm'),
            ])->values()->all(),
        ];
    }

    public static function medio(RedesMedio $m, ?string $token = null): array
    {
        $url = $token ? route('redes.medio', [$token, $m]) : route('admin.redes.medio', $m);
        return [
            'id' => $m->id, 'tipo' => $m->tipo, 'nombre' => $m->nombre,
            'url' => $url, 'mini' => $url . '?v=mini',
            'ancho' => $m->ancho, 'alto' => $m->alto,
        ];
    }

    // ---------- Archivos ----------

    public static function carpeta(RedesPost $p): string
    {
        $c = $p->cliente;
        $mes = ($p->fecha?->copy()->setTimezone(config('vandu.zona_horaria')) ?? now(config('vandu.zona_horaria')))->format('Y-m');
        return Dropbox::raiz() . '/Clientes/' . Dropbox::nombreSeguro($c->empresa ?: $c->nombre) . '/Redes/' . $mes;
    }

    public static function guardar(RedesPost $p, UploadedFile $f, ?int $ancho = null, ?int $alto = null): RedesMedio
    {
        $mime = $f->getMimeType() ?: $f->getClientMimeType();
        $tipo = str_starts_with((string) $mime, 'video/') ? 'video' : 'imagen';
        if ($tipo === 'imagen' && ($dim = @getimagesize($f->getRealPath()))) {
            [$ancho, $alto] = [$dim[0], $dim[1]];
        }
        $nombre = ArchivosProyecto::nombreArchivo($f->getClientOriginalName());

        if (Dropbox::conectado()) {
            $meta = Dropbox::cliente()->subirArchivo($f->getRealPath(), self::carpeta($p) . '/' . $nombre);
            $origen = 'dropbox';
            $ruta = $meta['id'];
        } else {
            $origen = 'local';
            $ruta = $f->storeAs("redes/{$p->cliente_id}/{$p->id}", Str::random(6) . '-' . $nombre, 'local');
        }

        return $p->medios()->create([
            'orden' => (int) $p->medios()->max('orden') + 1, 'tipo' => $tipo, 'origen' => $origen, 'ruta' => $ruta,
            'nombre' => $nombre, 'ancho' => $ancho, 'alto' => $alto, 'bytes' => $f->getSize(),
        ]);
    }

    /** Usa un archivo que ya está en Dropbox (sin copiarlo) */
    public static function vincularDropbox(RedesPost $p, string $id): RedesMedio
    {
        $meta = Dropbox::cliente()->metadata($id);
        $mime = ArchivosProyecto::mimeDe($meta['name']);
        return $p->medios()->create([
            'orden' => (int) $p->medios()->max('orden') + 1,
            'tipo' => str_starts_with($mime, 'video/') ? 'video' : 'imagen',
            'origen' => 'dropbox', 'ruta' => $meta['id'], 'nombre' => $meta['name'], 'bytes' => $meta['size'] ?? null,
        ]);
    }

    public static function borrar(RedesMedio $m): void
    {
        // Lo de Dropbox se queda ahí (pudo venir de otra carpeta); lo local se borra
        if ($m->origen === 'local') Storage::disk('local')->delete($m->ruta);
        $m->delete();
    }

    /** Sirve el archivo: miniatura rápida o el original (Dropbox lo entrega directo) */
    public static function responder(RedesMedio $m, bool $mini)
    {
        if ($m->origen === 'local') {
            abort_unless(Storage::disk('local')->exists($m->ruta), 404);
            return response()->file(Storage::disk('local')->path($m->ruta), ['Cache-Control' => 'private, max-age=86400']);
        }
        try {
            if ($mini && $m->tipo === 'imagen') {
                $jpg = Cache::remember('redes.mini.' . md5($m->ruta), now()->addHours(12), fn () => base64_encode(Dropbox::cliente()->miniatura($m->ruta, 'w640h480')));
                return response(base64_decode($jpg), 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=86400']);
            }
            return redirect()->away(Dropbox::cliente()->enlaceTemporal($m->ruta));
        } catch (DropboxError $e) {
            abort(404);
        }
    }

    // ---------- Verificación del cliente ----------

    /** Fecha en que vence un código según la vigencia elegida (todo el mes = hasta el último día del mes revisado) */
    public static function expiraPara(string $vigencia, ?Carbon $mes = null): Carbon
    {
        $tz = config('vandu.zona_horaria');
        $fin = match ($vigencia) {
            '7d'  => now()->addDays(7),
            'mes' => ($mes ?? now($tz))->copy()->setTimezone($tz)->endOfMonth()->setTimezone('UTC'),
            default => now()->addHours(24),
        };
        return $fin->lt(now()->addHours(24)) ? now()->addHours(24) : $fin; // mínimo 24 horas
    }

    public static function generarCodigo(Cliente $c, string $canal, string $vigencia = '24h', ?Carbon $mes = null): array
    {
        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $r = RedesCodigo::create(['cliente_id' => $c->id, 'codigo_hash' => Hash::make($codigo), 'canal' => $canal, 'expira_at' => self::expiraPara($vigencia, $mes)]);
        return ['codigo' => $codigo, 'formateado' => Aceptacion::formato($codigo), 'expira' => $r->expira_at];
    }

    public static function codigoVigente(Cliente $c): ?RedesCodigo
    {
        return RedesCodigo::where('cliente_id', $c->id)->where('expira_at', '>', now())->where('intentos', '<', self::INTENTOS)->orderByDesc('expira_at')->first();
    }

    /** El código sirve varias veces mientras esté vigente (el cliente revisa post por post, en varios días) */
    public static function verificar(Cliente $c, string $codigo): RedesCodigo|string
    {
        $codigo = preg_replace('/\D+/', '', $codigo);
        $vigentes = RedesCodigo::where('cliente_id', $c->id)->where('expira_at', '>', now())->where('intentos', '<', self::INTENTOS)->latest('id')->get();
        if ($vigentes->isEmpty()) return 'No hay un código vigente. Pide uno nuevo.';
        foreach ($vigentes as $r) {
            if (strlen($codigo) === 6 && Hash::check($codigo, $r->codigo_hash)) return $r;
        }
        $vigentes->each->increment('intentos');
        $restan = self::INTENTOS - $vigentes->max('intentos');
        return $restan > 0 ? 'El código no es correcto. Te quedan ' . $restan . ($restan === 1 ? ' intento.' : ' intentos.') : 'Se superaron los intentos. Pide un código nuevo.';
    }

    /**
     * ¿Ya confirmó su identidad en este dispositivo? Se recuerda (cookie cifrada) mientras su código siga vigente,
     * para que pueda revisar en varios días sin volver a escribirlo.
     */
    public static function sesion(Cliente $c): ?array
    {
        $s = session('redes_ok.' . $c->id);
        if (! $s) {
            $s = json_decode((string) request()->cookie('vandu_redes_' . $c->id), true) ?: null;
        }
        return ($s && ! empty($s['hasta']) && Carbon::parse($s['hasta'])->isFuture()) ? $s : null;
    }

    public static function abrirSesion(Cliente $c, string $nombre, ?Carbon $hasta = null): void
    {
        $hasta ??= now()->addHours(24);
        $datos = ['nombre' => $nombre, 'hasta' => $hasta->toIso8601String()];
        session(['redes_ok.' . $c->id => $datos]);
        \Illuminate\Support\Facades\Cookie::queue('vandu_redes_' . $c->id, json_encode($datos), max(1, (int) now()->diffInMinutes($hasta)));
    }

    public static function comentar(RedesPost $p, string $tipo, string $actor, ?string $autor, ?string $texto): RedesComentario
    {
        return $p->comentarios()->create(['tipo' => $tipo, 'actor' => $actor, 'autor' => $autor, 'texto' => $texto]);
    }

    /** "2026-10" válido o el mes actual */
    public static function mes(?string $mes): Carbon
    {
        $tz = config('vandu.zona_horaria');
        return ($mes && preg_match('/^\d{4}-\d{2}$/', $mes)) ? Carbon::createFromFormat('Y-m-d', $mes . '-01', $tz)->startOfDay() : now($tz)->startOfMonth();
    }
}
