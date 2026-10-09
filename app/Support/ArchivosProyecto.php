<?php

namespace App\Support;

use App\Models\Proyecto;
use App\Models\ProyectoArchivo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * Guarda archivos de proyecto en storage/app/proyectos/{id} (privado, nunca en public/).
 * Las fotos generan dos versiones WebP: "vista" (1800 px) para el visor y "miniatura" (640 px) para la cuadrícula.
 * El original se conserva intacto para la descarga.
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

        return $p->archivos()->create($datos);
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

    /** Sirve el archivo: version = original | vista | miniatura */
    public static function responder(ProyectoArchivo $a, string $version = 'original', bool $descargar = false): BinaryFileResponse
    {
        $ruta = match ($version) {
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
    public static function zipGaleria(Proyecto $p, bool $soloVisibles = true): StreamedResponse
    {
        abort_unless(class_exists(ZipArchive::class), 501, 'El servidor no tiene la extensión zip.');

        $archivos = $p->archivos()->where('grupo', 'galeria')->when($soloVisibles, fn ($q) => $q->where('visible', true))->get();
        abort_if($archivos->isEmpty(), 404);

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
