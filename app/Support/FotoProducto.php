<?php

namespace App\Support;

use App\Models\Producto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fotos del catálogo: se guardan reducidas (1600 px) y con una miniatura de 480 px en WebP,
 * así la página pública carga rápido aunque suban la foto directo del celular.
 */
class FotoProducto
{
    public const GRANDE = 1600;
    public const MINI = 480;

    /** Guarda la foto nueva, borra la anterior y regresa la ruta (relativa a storage/app) */
    public static function guardar(Producto $p, UploadedFile $archivo): string
    {
        $origen = @imagecreatefromstring((string) file_get_contents($archivo->getRealPath()));
        if (! $origen) throw new \RuntimeException('No pudimos leer la imagen. Prueba con JPG, PNG o WebP.');
        $origen = self::enderezar($origen, $archivo->getRealPath());

        $carpeta = 'productos/' . ($p->cuenta_id ?: Cuentas::id());
        $nombre = Str::slug(Str::limit($p->nombre ?: 'producto', 60, '')) . '-' . Str::lower(Str::random(6)) . '.webp';
        Storage::disk('local')->makeDirectory($carpeta);

        $disco = Storage::disk('local');
        self::escribir($origen, self::GRANDE, $disco->path("$carpeta/$nombre"));
        self::escribir($origen, self::MINI, $disco->path("$carpeta/min-$nombre"));
        imagedestroy($origen);

        self::borrar($p);
        return "$carpeta/$nombre";
    }

    public static function borrar(Producto $p): void
    {
        if (! $p->foto) return;
        Storage::disk('local')->delete([$p->foto, dirname($p->foto) . '/min-' . basename($p->foto)]);
    }

    /** Ruta en disco de la foto o su miniatura; null si el nombre no corresponde al producto */
    public static function ruta(Producto $p, string $archivo): ?string
    {
        if (! $p->foto) return null;
        $base = basename($p->foto);
        if ($archivo !== $base && $archivo !== 'min-' . $base) return null;
        $ruta = Storage::disk('local')->path(dirname($p->foto) . '/' . $archivo);
        return is_file($ruta) ? $ruta : null;
    }

    public static function escribir(\GdImage $img, int $max, string $destino): void
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $escala = min(1, $max / max($w, $h));
        $nw = max(1, (int) round($w * $escala));
        $nh = max(1, (int) round($h * $escala));
        $lienzo = imagecreatetruecolor($nw, $nh);
        imagealphablending($lienzo, false);
        imagesavealpha($lienzo, true);
        imagefill($lienzo, 0, 0, imagecolorallocatealpha($lienzo, 255, 255, 255, 127));
        imagecopyresampled($lienzo, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagewebp($lienzo, $destino, 82);
        imagedestroy($lienzo);
    }

    /** Las fotos del celular vienen giradas según su EXIF */
    public static function enderezar(\GdImage $img, string $ruta): \GdImage
    {
        if (! function_exists('exif_read_data')) return $img;
        $exif = @exif_read_data($ruta);
        $giro = match ((int) ($exif['Orientation'] ?? 1)) { 3 => 180, 6 => -90, 8 => 90, default => 0 };
        if (! $giro) return $img;
        $nueva = imagerotate($img, $giro, 0);
        if (! $nueva) return $img;
        imagedestroy($img);
        return $nueva;
    }
}
