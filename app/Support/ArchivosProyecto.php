<?php

namespace App\Support;

use App\Models\Proyecto;
use App\Models\ProyectoArchivo;
use App\Models\ProyectoEtapa;
use App\Support\Dropbox\Dropbox;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\MimeTypes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * Archivos de proyecto.
 *  - Con Dropbox conectado, el original vive en Dropbox (carpeta del proyecto) y en el servidor
 *    solo quedan vistas previas ligeras en storage/app/proyectos/{id}/optimizadas.
 *  - Sin Dropbox, todo se guarda en storage/app/proyectos/{id} (privado, nunca en public/).
 * Las fotos generan "vista" (1800 px) para el visor y "miniatura" (640 px) para la cuadrícula.
 */
class ArchivosProyecto
{
    private const DISCO = 'local';

    public static function guardar(Proyecto $p, UploadedFile $archivo, string $grupo, ?int $etapaId = null): ProyectoArchivo
    {
        $ext = strtolower($archivo->getClientOriginalExtension() ?: $archivo->guessExtension() ?: 'bin');
        $base = (string) Str::uuid();
        $ruta = $archivo->storeAs("{$p->carpeta}/originales", "$base.$ext", self::DISCO);
        $mime = $archivo->getMimeType() ?: $archivo->getClientMimeType();

        $datos = [
            'etapa_id' => $etapaId,
            'grupo'    => $grupo,
            'nombre'   => Str::limit($archivo->getClientOriginalName(), 250, ''),
            'ruta'     => $ruta,
            'mime'     => $mime,
            'peso'     => $archivo->getSize(),
            'orden'    => (int) $p->archivos()->where('grupo', $grupo)->max('orden') + 1,
        ];

        if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) && function_exists('imagecreatetruecolor')) {
            $datos += self::optimizar($p, Storage::disk(self::DISCO)->path($ruta), $mime, $base);
        }

        // Con Dropbox conectado, el original se va a Dropbox y aquí solo quedan las vistas previas
        if (Dropbox::conectado()) {
            $etapa = $etapaId ? $p->etapas()->find($etapaId) : null;
            $meta = Dropbox::cliente()->subirArchivo(Storage::disk(self::DISCO)->path($ruta), self::carpetaDestino($p, $grupo, $etapa) . '/' . self::nombreArchivo($datos['nombre']));
            Storage::disk(self::DISCO)->delete($ruta);
            $datos['origen'] = 'dropbox';
            $datos['dropbox_id'] = $meta['id'];
            $datos['ruta'] = $meta['path_display'];
        }

        return $p->archivos()->create($datos);
    }

    /* ======================= Dropbox ======================= */

    /** Carpeta del proyecto en Dropbox: /Vandu/Proyectos/<Cliente> - <Proyecto> · <Folio> */
    public static function carpetaProyecto(Proyecto $p): string
    {
        if ($p->dropbox_carpeta) return $p->dropbox_carpeta;
        $cliente = $p->cliente?->empresa ?: $p->cliente?->nombre ?: 'Sin cliente';
        $nombre = Dropbox::nombreSeguro("$cliente - {$p->nombre}" . ($p->presupuesto?->folio ? " · {$p->presupuesto->folio}" : " · #{$p->id}"));
        $ruta = Dropbox::raiz() . '/Proyectos/' . $nombre;
        $p->forceFill(['dropbox_carpeta' => $ruta])->saveQuietly();
        return $ruta;
    }

    /** Crea la carpeta del proyecto con sus subcarpetas (Galería, No publicado, Documentos) */
    public static function prepararCarpetas(Proyecto $p): string
    {
        $dbx = Dropbox::cliente();
        foreach (['Galería', 'No publicado', 'Documentos'] as $sub) {
            $dbx->crearCarpeta(self::carpetaProyecto($p) . '/' . $sub);
        }
        return self::carpetaProyecto($p);
    }

    public static function carpetaGaleria(Proyecto $p): string { return self::carpetaProyecto($p) . '/Galería'; }
    public static function carpetaOculta(Proyecto $p): string { return self::carpetaProyecto($p) . '/No publicado'; }

    /** Dónde va un archivo nuevo según su grupo (y etapa, para documentos). Crea la carpeta si no existe. */
    public static function carpetaDestino(Proyecto $p, string $grupo, ?ProyectoEtapa $etapa = null): string
    {
        $c = $grupo === 'galeria'
            ? self::carpetaGaleria($p)
            : self::carpetaProyecto($p) . '/Documentos' . ($etapa ? '/' . Dropbox::nombreSeguro(($etapa->orden + 1) . '. ' . $etapa->nombre) : '');
        Dropbox::cliente()->crearCarpeta($c);
        return $c;
    }

    public static function nombreArchivo(string $nombre): string
    {
        return preg_replace('#[\\\\/<>:"|?*\x00-\x1F]+#u', '-', trim($nombre)) ?: 'archivo';
    }

    public static function mimeDe(string $nombre, ?string $sugerido = null): string
    {
        $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        $porExt = MimeTypes::getDefault()->getMimeTypes($ext)[0] ?? null;
        return $porExt ?: ($sugerido ?: 'application/octet-stream');
    }

    /**
     * Da de alta un archivo que ya está en Dropbox (subido desde el navegador, importado o sincronizado)
     * y genera sus vistas previas. $poster: cuadro del video capturado en el navegador.
     */
    public static function registrarDropbox(Proyecto $p, array $meta, string $grupo, ?int $etapaId = null, ?string $poster = null, bool $visible = true): ProyectoArchivo
    {
        $existente = $p->archivos()->where('dropbox_id', $meta['id'])->first();
        if ($existente) return $existente;

        $nombre = $meta['name'] ?? basename($meta['path_display']);
        $a = $p->archivos()->create([
            'etapa_id'   => $etapaId,
            'grupo'      => $grupo,
            'origen'     => 'dropbox',
            'dropbox_id' => $meta['id'],
            'nombre'     => Str::limit($nombre, 250, ''),
            'ruta'       => $meta['path_display'],
            'mime'       => self::mimeDe($nombre),
            'peso'       => (int) ($meta['size'] ?? 0),
            'visible'    => $visible,
            'orden'      => (int) $p->archivos()->where('grupo', $grupo)->max('orden') + 1,
        ]);
        self::previas($a, $poster);
        return $a;
    }

    /** Genera (o regenera) las vistas previas locales de un archivo de Dropbox */
    public static function previas(ProyectoArchivo $a, ?string $poster = null): void
    {
        if (! function_exists('imagecreatetruecolor')) return;
        $fuente = null;
        try {
            if ($a->es_imagen) {
                $fuente = Dropbox::cliente()->miniatura($a->dropbox_id, 'w2048h1536');
            } elseif ($a->es_video && $poster) {
                $fuente = $poster;
            }
        } catch (\Throwable $e) {
            report($e);
        }
        if (! $fuente || ! ($img = @imagecreatefromstring($fuente))) return;

        $p = $a->proyecto;
        $dir = Storage::disk(self::DISCO)->path("{$p->carpeta}/optimizadas");
        if (! is_dir($dir)) mkdir($dir, 0775, true);
        $w = imagesx($img); $h = imagesy($img);
        $salida = [];
        foreach (['vista' => [1800, 82], 'miniatura' => [640, 76]] as $campo => [$max, $calidad]) {
            $esc = min(1, $max / max($w, $h));
            $lienzo = imagecreatetruecolor(max(1, (int) round($w * $esc)), max(1, (int) round($h * $esc)));
            imagecopyresampled($lienzo, $img, 0, 0, 0, 0, imagesx($lienzo), imagesy($lienzo), $w, $h);
            $nombre = "dbx-{$a->id}-$campo.jpg";
            imagejpeg($lienzo, "$dir/$nombre", $calidad);
            imagedestroy($lienzo);
            $salida[$campo] = "{$p->carpeta}/optimizadas/$nombre";
        }
        imagedestroy($img);
        $a->forceFill($salida + ['ancho' => $a->ancho ?: $w, 'alto' => $a->alto ?: $h])->saveQuietly();
    }

    /** Mostrar u ocultar: en Dropbox, la galería oculta vive en "No publicado" */
    public static function cambiarVisibilidad(ProyectoArchivo $a, bool $visible): void
    {
        if ($a->origen === 'dropbox' && $a->grupo === 'galeria' && $a->visible !== $visible) {
            $p = $a->proyecto;
            $destino = $visible ? self::carpetaGaleria($p) : self::carpetaOculta($p);
            Dropbox::cliente()->crearCarpeta($destino);
            $meta = Dropbox::cliente()->mover($a->dropbox_id, $destino . '/' . basename($a->ruta));
            $a->ruta = $meta['path_display'] ?? $a->ruta;
        }
        $a->visible = $visible;
        $a->save();
    }

    /**
     * Revisa las carpetas Galería y No publicado del proyecto en Dropbox:
     * agrega lo nuevo, actualiza lo que se movió entre ellas y quita del panel lo que ya no está.
     * @return array{nuevos: int, quitados: int}
     */
    public static function sincronizar(Proyecto $p): array
    {
        $dbx = Dropbox::cliente();
        $carpetas = [self::carpetaGaleria($p) => true, self::carpetaOculta($p) => false];
        // Si la carpeta del proyecto no existe (se renombró o borró en Dropbox), no quitamos nada del panel
        try { $dbx->metadata(self::carpetaGaleria($p)); $existe = true; } catch (\Throwable) { $existe = false; }
        $vistos = [];
        $nuevos = 0;
        foreach ($carpetas as $carpeta => $visible) {
            foreach ($dbx->listar($carpeta) as $e) {
                if (($e['.tag'] ?? '') !== 'file' || str_starts_with($e['name'], '.')) continue;
                $vistos[$e['id']] = true;
                $a = $p->archivos()->where('dropbox_id', $e['id'])->first();
                if (! $a) {
                    self::registrarDropbox($p, $e, 'galeria', null, null, $visible);
                    $nuevos++;
                } elseif ($a->visible !== $visible || $a->ruta !== $e['path_display']) {
                    $a->forceFill(['visible' => $visible, 'ruta' => $e['path_display']])->save();
                }
            }
        }
        $quitados = 0;
        if (! $existe) return ['nuevos' => $nuevos, 'quitados' => 0, 'sin_carpeta' => true];
        foreach ($p->archivos()->where('grupo', 'galeria')->where('origen', 'dropbox')->get() as $a) {
            if (! isset($vistos[$a->dropbox_id])) {
                Storage::disk(self::DISCO)->delete(array_filter([$a->vista, $a->miniatura, $a->ruta_correo]));
                $a->deleteQuietly(); // ya no está en esas carpetas: no tocamos Dropbox
                $quitados++;
            }
        }
        return ['nuevos' => $nuevos, 'quitados' => $quitados];
    }

    /** @return array{vista?: string, miniatura?: string, ancho?: int, alto?: int} */
    private static function optimizar(Proyecto $p, string $origen, string $mime, string $base): array
    {
        try {
            @ini_set('memory_limit', '512M');
            $img = match ($mime) {
                'image/jpeg' => @imagecreatefromjpeg($origen),
                'image/png'  => @imagecreatefrompng($origen),
                'image/webp' => @imagecreatefromwebp($origen),
            };
            if (! $img) return [];

            // Respeta la orientación de la cámara
            if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
                $o = @exif_read_data($origen)['Orientation'] ?? 1;
                $img = match ((int) $o) { 3 => imagerotate($img, 180, 0), 6 => imagerotate($img, -90, 0), 8 => imagerotate($img, 90, 0), default => $img };
            }

            $w = imagesx($img); $h = imagesy($img);
            $dir = Storage::disk(self::DISCO)->path("{$p->carpeta}/optimizadas");
            if (! is_dir($dir)) mkdir($dir, 0775, true);

            $salida = [];
            foreach (['vista' => [1800, 82], 'miniatura' => [640, 74]] as $campo => [$max, $calidad]) {
                $escala = min(1, $max / max($w, $h));
                $nw = max(1, (int) round($w * $escala)); $nh = max(1, (int) round($h * $escala));
                $lienzo = imagecreatetruecolor($nw, $nh);
                imagealphablending($lienzo, false); imagesavealpha($lienzo, true);
                imagecopyresampled($lienzo, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
                $nombre = "$base-$campo." . (function_exists('imagewebp') ? 'webp' : 'jpg');
                function_exists('imagewebp') ? imagewebp($lienzo, "$dir/$nombre", $calidad) : imagejpeg($lienzo, "$dir/$nombre", $calidad);
                imagedestroy($lienzo);
                $salida[$campo] = "{$p->carpeta}/optimizadas/$nombre";
            }
            imagedestroy($img);

            return $salida + ['ancho' => $w, 'alto' => $h];
        } catch (\Throwable $e) {
            report($e);
            return [];
        }
    }

    /**
     * Recorte cuadrado en JPG (480 px) para mostrar en correos: JPG porque Outlook no lee WebP.
     * Se genera la primera vez que se pide y se guarda junto a las otras versiones.
     */
    public static function cuadroCorreo(ProyectoArchivo $a): ?string
    {
        if (! $a->es_imagen || ! function_exists('imagecreatetruecolor')) return null;
        $disco = Storage::disk(self::DISCO);
        $destino = $a->ruta_correo;
        if ($disco->exists($destino)) return $destino;
        if ($a->origen === 'dropbox' && (! $a->vista || ! $disco->exists($a->vista))) self::previas($a);
        if ($a->origen === 'dropbox' && ! $a->vista) return null;

        try {
            $fuente = $disco->path($a->vista ?: $a->ruta);
            $img = match (strtolower(pathinfo($fuente, PATHINFO_EXTENSION))) {
                'webp'        => @imagecreatefromwebp($fuente),
                'png'         => @imagecreatefrompng($fuente),
                'jpg', 'jpeg' => @imagecreatefromjpeg($fuente),
                default       => false,
            };
            if (! $img) return null;
            $w = imagesx($img); $h = imagesy($img); $lado = min($w, $h);
            $lienzo = imagecreatetruecolor(480, 480);
            imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 243, 244, 246));
            imagecopyresampled($lienzo, $img, 0, 0, (int) (($w - $lado) / 2), (int) (($h - $lado) / 2), 480, 480, $lado, $lado);
            if (! is_dir(dirname($disco->path($destino)))) mkdir(dirname($disco->path($destino)), 0775, true);
            imagejpeg($lienzo, $disco->path($destino), 80);
            imagedestroy($lienzo); imagedestroy($img);
            return $destino;
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    /** Sirve el archivo: version = original | vista | miniatura | correo */
    public static function responder(ProyectoArchivo $a, string $version = 'original', bool $descargar = false): Response
    {
        if ($a->origen === 'dropbox') {
            if ($version === 'original') {
                // El original se sirve directo desde Dropbox con un enlace temporal
                return redirect()->away(Dropbox::cliente()->enlaceTemporal($a->dropbox_id))->header('Cache-Control', 'no-store');
            }
            if ($version !== 'correo' && (! $a->{$version} || ! Storage::disk(self::DISCO)->exists($a->{$version}))) {
                self::previas($a);
                $a->refresh();
            }
            if ($version !== 'correo' && ! $a->{$version}) abort(404);
        }
        $ruta = match ($version) {
            'correo'    => self::cuadroCorreo($a) ?? ($a->miniatura ?: $a->ruta),
            'vista'     => $a->vista ?: $a->ruta,
            'miniatura' => $a->miniatura ?: ($a->vista ?: $a->ruta),
            default     => $a->ruta,
        };
        $disco = Storage::disk(self::DISCO);
        abort_unless($disco->exists($ruta), 404);

        $resp = response()->file($disco->path($ruta), [
            'Cache-Control' => 'private, max-age=86400',
            'X-Robots-Tag'  => 'noindex',
        ]);
        if ($descargar || $version === 'original' && ! $a->es_imagen && ! $a->es_video && ! str_contains((string) $a->mime, 'pdf')) {
            $resp->setContentDisposition('attachment', $a->nombre, Str::ascii($a->nombre));
        }
        return $resp;
    }

    /** ZIP con los originales de la galería visibles para el cliente */
    public static function zipGaleria(Proyecto $p, bool $soloVisibles = true): Response
    {
        abort_unless(class_exists(ZipArchive::class), 501, 'El servidor no tiene la extensión zip.');

        $archivos = $p->archivos()->where('grupo', 'galeria')->when($soloVisibles, fn ($q) => $q->where('visible', true))->get();
        abort_if($archivos->isEmpty(), 404);

        // En Dropbox: el ZIP lo arma Dropbox con la carpeta Galería (solo tiene lo visible)
        if ($archivos->contains('origen', 'dropbox')) {
            if (! $p->dropbox_zip_url) {
                $p->forceFill(['dropbox_zip_url' => Dropbox::cliente()->enlaceCarpeta(self::carpetaGaleria($p))])->saveQuietly();
            }
            return redirect()->away($p->dropbox_zip_url);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'galeria');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $usados = [];
        foreach ($archivos as $a) {
            $nombre = $a->nombre;
            $i = 1;
            while (isset($usados[$nombre])) { $nombre = pathinfo($a->nombre, PATHINFO_FILENAME) . " ($i)." . pathinfo($a->nombre, PATHINFO_EXTENSION); $i++; }
            $usados[$nombre] = true;
            $zip->addFile(Storage::disk(self::DISCO)->path($a->ruta), $nombre);
            $zip->setCompressionName($nombre, ZipArchive::CM_STORE); // fotos y video ya vienen comprimidos
        }
        $zip->close();

        $nombreZip = Str::slug($p->cliente?->empresa ?: $p->cliente?->nombre ?: 'proyecto') . '-entregables.zip';

        return response()->streamDownload(function () use ($tmp) {
            readfile($tmp);
            @unlink($tmp);
        }, $nombreZip, ['Content-Type' => 'application/zip', 'Content-Length' => filesize($tmp)]);
    }
}
