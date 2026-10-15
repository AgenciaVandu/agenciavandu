<?php

namespace App\Support;

use App\Models\Proyecto;
use App\Models\ProyectoArchivo;
use App\Models\ProyectoSolicitud;
use App\Support\Dropbox\Dropbox;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Archivos que sube el cliente desde la página de su proyecto, por partes (para que pasen
 * archivos grandes: ZIP, videos, editables…).
 *  - Con Dropbox conectado, cada parte va directo a una sesión de subida de Dropbox y el archivo
 *    termina en  <carpeta del proyecto>/Del cliente/<solicitud>/.
 *  - Sin Dropbox, las partes se juntan en storage/app/proyectos/{id}/cliente.
 * Cada sesión queda ligada al proyecto que la abrió (no se puede continuar desde otro enlace).
 */
class SubidaCliente
{
    public const PARTE = 4 * 1024 * 1024;

    /** Lo que nunca se acepta: programas y scripts que podrían ejecutarse */
    public const BLOQUEADAS = ['php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'pht', 'phps', 'cgi', 'pl', 'py', 'asp', 'aspx', 'jsp',
        'exe', 'msi', 'bat', 'cmd', 'com', 'scr', 'pif', 'cpl', 'sh', 'bash', 'ps1', 'psm1', 'vbs', 'vbe', 'wsf', 'jar', 'htaccess', 'htpasswd'];

    /** Tamaño de cada parte: 4 MB, o menos si el servidor acepta menos por petición */
    public static function parte(): int
    {
        $ini = fn (string $k) => (function ($v) {
            $v = trim((string) $v);
            if ($v === '' || $v === '0' || $v === '-1') return PHP_INT_MAX;
            $n = (int) $v;
            return match (strtolower(substr($v, -1))) { 'g' => $n * 1073741824, 'm' => $n * 1048576, 'k' => $n * 1024, default => $n };
        })(ini_get($k));
        $limite = min($ini('upload_max_filesize'), $ini('post_max_size') - 65536);
        return max(256 * 1024, min(self::PARTE, $limite - 1024));
    }

    public static function maxBytes(): int
    {
        return (int) config('vandu.max_subida_cliente_mb', 5120) * 1024 * 1024;
    }

    public static function permitido(string $nombre): bool
    {
        $partes = array_map('strtolower', explode('.', basename($nombre)));
        array_shift($partes);
        // Revisa todas las extensiones ("foto.php.jpg" tampoco pasa)
        return ! array_intersect($partes, self::BLOQUEADAS) && ! str_starts_with(basename($nombre), '.');
    }

    private static function clave(string $sesion): string
    {
        return 'subida-cliente.' . $sesion;
    }

    /** Primera parte. Si es la única, el archivo queda guardado de una vez. */
    public static function iniciar(Proyecto $p, string $parte, ?string $nombre, ?ProyectoSolicitud $s): array
    {
        if ($nombre !== null) {
            return ['archivo' => self::guardarCompleto($p, $parte, $nombre, $s)];
        }
        if (Dropbox::conectado()) {
            $sesion = Dropbox::cliente()->sesionIniciar($parte);
        } else {
            $sesion = Str::random(32);
            Storage::disk('local')->put(self::temporal($p, $sesion), $parte);
        }
        Cache::put(self::clave($sesion), ['proyecto' => $p->id], now()->addDay());
        return ['sesion' => $sesion];
    }

    public static function agregar(Proyecto $p, string $sesion, int $offset, string $parte): void
    {
        self::validarSesion($p, $sesion, $offset, strlen($parte));
        if (Dropbox::conectado()) {
            Dropbox::cliente()->sesionAgregar($sesion, $offset, $parte);
            return;
        }
        $ruta = Storage::disk('local')->path(self::temporal($p, $sesion));
        abort_unless(is_file($ruta) && filesize($ruta) === $offset, 409, 'La subida se desfasó. Intenta de nuevo.');
        file_put_contents($ruta, $parte, FILE_APPEND);
    }

    public static function terminar(Proyecto $p, string $sesion, int $offset, string $parte, string $nombre, ?ProyectoSolicitud $s): ProyectoArchivo
    {
        self::validarSesion($p, $sesion, $offset, strlen($parte));
        $nombre = ArchivosProyecto::nombreArchivo($nombre);

        if (Dropbox::conectado()) {
            $meta = Dropbox::cliente()->sesionTerminar($sesion, $offset, $parte, self::carpetaDropbox($p, $s) . '/' . $nombre);
            Cache::forget(self::clave($sesion));
            return self::registrar($p, $s, ['origen' => 'dropbox', 'dropbox_id' => $meta['id'] ?? null, 'ruta' => $meta['path_display'], 'nombre' => $meta['name'] ?? $nombre, 'peso' => (int) ($meta['size'] ?? 0)]);
        }

        self::agregar($p, $sesion, $offset, $parte);
        Cache::forget(self::clave($sesion));
        $final = self::rutaLocal($p, $nombre);
        Storage::disk('local')->move(self::temporal($p, $sesion), $final);
        return self::registrar($p, $s, ['origen' => 'local', 'ruta' => $final, 'nombre' => $nombre, 'peso' => Storage::disk('local')->size($final)]);
    }

    private static function guardarCompleto(Proyecto $p, string $contenido, string $nombre, ?ProyectoSolicitud $s): ProyectoArchivo
    {
        $nombre = ArchivosProyecto::nombreArchivo($nombre);
        if (Dropbox::conectado()) {
            $meta = Dropbox::cliente()->subirContenido($contenido, self::carpetaDropbox($p, $s) . '/' . $nombre);
            return self::registrar($p, $s, ['origen' => 'dropbox', 'dropbox_id' => $meta['id'] ?? null, 'ruta' => $meta['path_display'], 'nombre' => $meta['name'] ?? $nombre, 'peso' => strlen($contenido)]);
        }
        $final = self::rutaLocal($p, $nombre);
        Storage::disk('local')->put($final, $contenido);
        return self::registrar($p, $s, ['origen' => 'local', 'ruta' => $final, 'nombre' => $nombre, 'peso' => strlen($contenido)]);
    }

    private static function validarSesion(Proyecto $p, string $sesion, int $offset, int $largo): void
    {
        abort_unless((Cache::get(self::clave($sesion))['proyecto'] ?? null) === $p->id, 404, 'Esta subida ya no existe. Vuelve a intentarlo.');
        abort_if($offset + $largo > self::maxBytes(), 413, 'El archivo es demasiado grande.');
    }

    private static function temporal(Proyecto $p, string $sesion): string
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{32}$/', $sesion), 422);
        return "{$p->carpeta}/cliente/.partes/$sesion";
    }

    private static function rutaLocal(Proyecto $p, string $nombre): string
    {
        $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        return "{$p->carpeta}/cliente/" . Str::uuid() . ($ext && preg_match('/^[a-z0-9]{1,10}$/', $ext) ? ".$ext" : '');
    }

    public static function carpetaDropbox(Proyecto $p, ?ProyectoSolicitud $s): string
    {
        return ArchivosProyecto::carpetaProyecto($p) . '/Del cliente' . ($s ? '/' . Dropbox::nombreSeguro($s->titulo) : '');
    }

    private static function registrar(Proyecto $p, ?ProyectoSolicitud $s, array $datos): ProyectoArchivo
    {
        $a = $p->archivos()->create($datos + [
            'grupo' => 'cliente', 'visible' => false, 'solicitud_id' => $s?->id,
            'mime' => ArchivosProyecto::mimeDe($datos['nombre']),
            'orden' => (int) $p->archivos()->where('grupo', 'cliente')->max('orden') + 1,
        ]);
        $s?->forceFill(['recibida_at' => now()])->save();

        // Un aviso por tanda (no uno por archivo)
        if (Cache::add('aviso.archivos-cliente.' . $p->id, 1, now()->addMinutes(10))) {
            $quien = $p->cliente?->empresa ?: $p->cliente?->nombre ?: 'Tu cliente';
            \App\Support\Push\Notificar::evento('archivos_cliente', "$quien te envió archivos",
                $p->nombre . ($s ? ' · ' . $s->titulo : '') . ' · ' . $a->nombre,
                route('admin.proyectos.show', $p) . '#archivos-cliente', 'archivos-' . $p->id);
        }
        return $a;
    }
}
