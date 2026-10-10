<?php

namespace App\Support\Dropbox;

use App\Models\Integracion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Cliente mínimo de la API de Dropbox (v2) con tokens de actualización.
 * Toda la app pasa por aquí: subir, mover, borrar, listar, enlaces temporales y miniaturas.
 * Con VANDU_DROPBOX_SIMULADO=true se usa DropboxSimulado (carpeta local) para pruebas.
 */
class Dropbox
{
    public const API = 'https://api.dropboxapi.com/2/';
    public const CONTENIDO = 'https://content.dropboxapi.com/2/';
    public const CHUNK = 8 * 1024 * 1024;
    public const PERMISOS = ['account_info.read', 'files.metadata.read', 'files.metadata.write', 'files.content.read', 'files.content.write', 'sharing.read', 'sharing.write'];

    private static ?Dropbox $instancia = null;

    public static function cliente(): static
    {
        return self::$instancia ??= (config('vandu.dropbox.simulado') ? new DropboxSimulado() : new self());
    }

    /** Para pruebas */
    public static function usar(?Dropbox $d): void
    {
        self::$instancia = $d;
    }

    public static function configurado(): bool
    {
        return (bool) config('vandu.dropbox.simulado') || (config('vandu.dropbox.app_key') && config('vandu.dropbox.app_secret'));
    }

    public static function conectado(): bool
    {
        return self::configurado() && (config('vandu.dropbox.simulado') || Integracion::de('dropbox') !== null);
    }

    /* ---------------- Conexión ---------------- */

    public static function urlAutorizar(string $estado): string
    {
        return 'https://www.dropbox.com/oauth2/authorize?' . http_build_query([
            'client_id'         => config('vandu.dropbox.app_key'),
            'response_type'     => 'code',
            'token_access_type' => 'offline',
            // Pide todos los permisos que usa el panel (si la app no los tiene activos, Dropbox lo avisa al conectar)
            'scope'             => implode(' ', self::PERMISOS),
            'redirect_uri'      => route('admin.dropbox.conectar'),
            'state'             => $estado,
        ]);
    }

    /** Cambia el código de autorización por tokens y guarda la conexión */
    public function conectar(string $codigo): Integracion
    {
        $r = Http::asForm()->timeout(20)->post('https://api.dropboxapi.com/oauth2/token', [
            'code'          => $codigo,
            'grant_type'    => 'authorization_code',
            'client_id'     => config('vandu.dropbox.app_key'),
            'client_secret' => config('vandu.dropbox.app_secret'),
            'redirect_uri'  => route('admin.dropbox.conectar'),
        ]);
        if (! $r->successful()) {
            throw new DropboxError('Dropbox no aceptó la conexión: ' . ($r->json('error_description') ?? $r->body()));
        }
        $datos = [
            'refresh_token' => $r->json('refresh_token'),
            'access_token'  => $r->json('access_token'),
            'expira'        => now()->addSeconds((int) $r->json('expires_in', 14400) - 120)->timestamp,
            'account_id'    => $r->json('account_id'),
        ];
        $int = Integracion::updateOrCreate(['proveedor' => 'dropbox'], ['datos' => $datos]);
        Cache::forget(\App\Support\Cuentas::clave('dropbox.token'));
        $cuenta = $this->rpc('users/get_current_account', null);
        $int->update(['cuenta' => trim(($cuenta['name']['display_name'] ?? '') . ' · ' . ($cuenta['email'] ?? ''), ' ·')]);
        return $int;
    }

    public function desconectar(): void
    {
        try { $this->rpc('auth/token/revoke', null); } catch (\Throwable) { /* ya no importa */ }
        Integracion::where('proveedor', 'dropbox')->delete();
        Cache::forget(\App\Support\Cuentas::clave('dropbox.token'));
    }

    /** Token de acceso vigente (se renueva solo) */
    public function token(): string
    {
        $int = Integracion::de('dropbox');
        if (! $int) {
            throw new DropboxError('Dropbox no está conectado. Conéctalo en el panel, sección Dropbox.');
        }
        $d = $int->datos;
        if (! empty($d['access_token']) && ($d['expira'] ?? 0) > time()) {
            return $d['access_token'];
        }
        $r = Http::asForm()->timeout(20)->post('https://api.dropboxapi.com/oauth2/token', [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $d['refresh_token'] ?? '',
            'client_id'     => config('vandu.dropbox.app_key'),
            'client_secret' => config('vandu.dropbox.app_secret'),
        ]);
        if (! $r->successful()) {
            throw new DropboxError('No se pudo renovar el acceso a Dropbox. Vuelve a conectarlo. (' . ($r->json('error_description') ?? $r->status()) . ')');
        }
        $d['access_token'] = $r->json('access_token');
        $d['expira'] = now()->addSeconds((int) $r->json('expires_in', 14400) - 120)->timestamp;
        $int->update(['datos' => $d]);
        return $d['access_token'];
    }

    /** Token y vigencia para que el navegador suba directo a Dropbox */
    public function tokenNavegador(): array
    {
        $token = $this->token();
        return ['token' => $token, 'expira' => Integracion::de('dropbox')->datos['expira'] ?? null, 'api' => self::CONTENIDO];
    }

    /* ---------------- Llamadas ---------------- */

    public function rpc(string $endpoint, ?array $args): array
    {
        // Reintenta solo fallas de red, saturación (429) o errores del lado de Dropbox (5xx)
        $req = Http::withToken($this->token())->timeout(60)->retry(2, 800, fn ($e) => ! $e instanceof \Illuminate\Http\Client\RequestException
            || $e->response->serverError() || $e->response->status() === 429, throw: false);
        $r = $args === null
            ? $req->withBody('null', 'application/json')->post(self::API . $endpoint)
            : $req->asJson()->post(self::API . $endpoint, $args);
        return $this->respuesta($r, $endpoint);
    }

    /** Endpoints de contenido: los argumentos van en el encabezado Dropbox-API-Arg (en ASCII) */
    private function contenido(string $endpoint, array $args, $cuerpo = null, bool $json = true)
    {
        $r = Http::withToken($this->token())->timeout(300)
            ->withHeaders(['Dropbox-API-Arg' => self::argumento($args)])
            ->withBody($cuerpo ?? '', 'application/octet-stream')
            ->post(self::CONTENIDO . $endpoint);
        if (! $json) {
            if (! $r->successful()) $this->respuesta($r, $endpoint);
            return $r->body();
        }
        return $this->respuesta($r, $endpoint);
    }

    private function respuesta($r, string $endpoint): array
    {
        if ($r->successful()) {
            return $r->json() ?? [];
        }
        $resumen = $r->json('error_summary') ?? Str::limit($r->body(), 200);
        // Permiso que falta en la app de Dropbox: explicar cómo arreglarlo
        if (str_contains($resumen, 'missing_scope') || str_contains($r->body(), 'required scope')) {
            preg_match("/scope '([a-z._]+)'/", $r->body(), $m);
            $permiso = $m[1] ?? ($r->json('error.required_scope') ?? 'el permiso que falta');
            throw new DropboxError("A tu app de Dropbox le falta el permiso «{$permiso}». Actívalo en dropbox.com/developers → tu app → Permissions → Submit, y luego en el panel desconecta y vuelve a conectar Dropbox.", $resumen);
        }
        throw new DropboxError("Dropbox ($endpoint): $resumen", $resumen);
    }

    /** JSON escapado a ASCII, como pide Dropbox en los encabezados */
    public static function argumento(array $args): string
    {
        return json_encode($args, JSON_UNESCAPED_SLASHES);
    }

    /* ---------------- Operaciones ---------------- */

    public function metadata(string $rutaOId): array
    {
        return $this->rpc('files/get_metadata', ['path' => $rutaOId, 'include_media_info' => true]);
    }

    public function crearCarpeta(string $ruta): void
    {
        try {
            $this->rpc('files/create_folder_v2', ['path' => $ruta, 'autorename' => false]);
        } catch (DropboxError $e) {
            if (! str_contains($e->resumen, 'conflict')) throw $e;
        }
    }

    /** @return array<int, array> entradas de la carpeta (archivos y subcarpetas) */
    public function listar(string $ruta, bool $recursivo = false): array
    {
        try {
            $r = $this->rpc('files/list_folder', ['path' => $ruta === '/' ? '' : $ruta, 'recursive' => $recursivo, 'include_media_info' => false, 'limit' => 2000]);
        } catch (DropboxError $e) {
            if (str_contains($e->resumen, 'not_found')) return [];
            throw $e;
        }
        $entradas = $r['entries'] ?? [];
        while (! empty($r['has_more'])) {
            $r = $this->rpc('files/list_folder/continue', ['cursor' => $r['cursor']]);
            $entradas = array_merge($entradas, $r['entries'] ?? []);
        }
        return $entradas;
    }

    /** Busca archivos y carpetas por nombre dentro de una ruta (máx. 100 resultados) */
    public function buscar(string $texto, string $ruta = ''): array
    {
        $r = $this->rpc('files/search_v2', [
            'query'   => $texto,
            'options' => array_filter(['path' => $ruta === '/' ? null : $ruta, 'max_results' => 100, 'file_status' => 'active', 'filename_only' => true]),
        ]);
        return array_values(array_filter(array_map(fn ($m) => $m['metadata']['metadata'] ?? null, $r['matches'] ?? [])));
    }

    /** Sube un archivo del servidor (en partes si es grande) */
    public function subirArchivo(string $rutaLocal, string $destino): array
    {
        $tam = filesize($rutaLocal);
        $commit = ['path' => $destino, 'mode' => 'add', 'autorename' => true, 'mute' => true];
        if ($tam <= self::CHUNK) {
            return $this->contenido('files/upload', $commit, file_get_contents($rutaLocal));
        }
        $f = fopen($rutaLocal, 'rb');
        $inicio = $this->contenido('files/upload_session/start', ['close' => false], fread($f, self::CHUNK));
        $sesion = $inicio['session_id'];
        $offset = self::CHUNK;
        while ($offset + self::CHUNK < $tam) {
            $this->contenido('files/upload_session/append_v2', ['cursor' => ['session_id' => $sesion, 'offset' => $offset], 'close' => false], fread($f, self::CHUNK));
            $offset += self::CHUNK;
        }
        $resto = fread($f, self::CHUNK) ?: '';
        fclose($f);
        return $this->contenido('files/upload_session/finish', ['cursor' => ['session_id' => $sesion, 'offset' => $offset], 'commit' => $commit], $resto);
    }

    /* Subida en partes a través del servidor (el navegador nunca recibe el token de Dropbox) */
    public function sesionIniciar(string $parte): string
    {
        return $this->contenido('files/upload_session/start', ['close' => false], $parte)['session_id'];
    }

    public function sesionAgregar(string $sesion, int $offset, string $parte): void
    {
        $this->contenido('files/upload_session/append_v2', ['cursor' => ['session_id' => $sesion, 'offset' => $offset], 'close' => false], $parte);
    }

    public function sesionTerminar(string $sesion, int $offset, string $parte, string $destino): array
    {
        return $this->contenido('files/upload_session/finish', [
            'cursor' => ['session_id' => $sesion, 'offset' => $offset],
            'commit' => ['path' => $destino, 'mode' => 'add', 'autorename' => true, 'mute' => false],
        ], $parte);
    }

    public function subirContenido(string $contenido, string $destino): array
    {
        return $this->contenido('files/upload', ['path' => $destino, 'mode' => 'add', 'autorename' => true, 'mute' => true], $contenido);
    }

    public function mover(string $desde, string $hacia): array
    {
        return $this->rpc('files/move_v2', ['from_path' => $desde, 'to_path' => $hacia, 'autorename' => true])['metadata'] ?? [];
    }

    /** Manda a la papelera de Dropbox (se puede recuperar unos días) */
    public function borrar(string $rutaOId): void
    {
        try {
            $this->rpc('files/delete_v2', ['path' => $rutaOId]);
        } catch (DropboxError $e) {
            if (! str_contains($e->resumen, 'not_found')) throw $e;
        }
        Cache::forget(\App\Support\Cuentas::clave('dropbox.enlace.' . md5($rutaOId)));
    }

    /** Enlace directo que dura 4 horas: lo usamos para reproducir y descargar */
    public function enlaceTemporal(string $rutaOId): string
    {
        return Cache::remember(\App\Support\Cuentas::clave('dropbox.enlace.' . md5($rutaOId)), now()->addMinutes(200), fn () =>
            $this->rpc('files/get_temporary_link', ['path' => $rutaOId])['link']);
    }

    /** Miniatura JPG generada por Dropbox (fotos). Tamaños: w256h256, w640h480, w1024h768, w2048h1536 */
    public function miniatura(string $rutaOId, string $tamano = 'w640h480'): string
    {
        return $this->contenido('files/get_thumbnail_v2', [
            'resource' => ['.tag' => 'path', 'path' => $rutaOId],
            'format'   => 'jpeg', 'size' => $tamano, 'mode' => 'fitone_bestfit',
        ], null, false);
    }

    /** Enlace compartido de una carpeta; con dl=1 Dropbox entrega un ZIP */
    public function enlaceCarpeta(string $ruta): string
    {
        try {
            $url = $this->rpc('sharing/create_shared_link_with_settings', ['path' => $ruta, 'settings' => ['requested_visibility' => 'public']])['url'];
        } catch (DropboxError $e) {
            if (! str_contains($e->resumen, 'shared_link_already_exists')) throw $e;
            $links = $this->rpc('sharing/list_shared_links', ['path' => $ruta, 'direct_only' => true])['links'] ?? [];
            $url = $links[0]['url'] ?? throw $e;
        }
        return self::conDescarga($url);
    }

    public static function conDescarga(string $url): string
    {
        $url = preg_replace('/([?&])dl=0(&|$)/', '$1', $url);
        $url = rtrim($url, '?&');
        return $url . (str_contains($url, '?') ? '&' : '?') . 'dl=1';
    }

    /* ---------------- Utilidades ---------------- */

    /** Limpia un nombre para usarlo como carpeta en Dropbox */
    public static function nombreSeguro(string $nombre): string
    {
        $n = preg_replace('/[\\\\\/<>:"|?*\x00-\x1F]+/u', '-', $nombre);
        $n = trim(preg_replace('/\s+/u', ' ', $n), " .-");
        return Str::limit($n ?: 'Sin nombre', 90, '');
    }

    public static function raiz(): string
    {
        return '/' . trim(config('vandu.dropbox.carpeta', 'Vandu'), '/');
    }
}
