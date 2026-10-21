<?php

namespace App\Support;

use App\Models\Cuenta;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Ícono de la app (panel instalado y portal del atleta) por negocio.
 * De una imagen cuadrada se generan todos los tamaños; si el negocio no sube uno, se usa el de la plataforma.
 */
class IconoApp
{
    /** archivo => [lado en px, margen para la versión "maskable" (Android recorta en círculo o gota)] */
    public const TAMANOS = [
        'icono-512.png'          => [512, 0],
        'icono-192.png'          => [192, 0],
        'apple-touch-icon.png'   => [180, 0],
        'favicon-32.png'         => [32, 0],
        'icono-maskable-512.png' => [512, .16],
    ];

    public static function tiene(?Cuenta $c): bool
    {
        return (bool) $c?->ajuste('app.icono');
    }

    /** URL del ícono: el del negocio si lo tiene (con versión, para que el navegador lo renueve), si no el de la plataforma */
    public static function url(?Cuenta $c, string $archivo): string
    {
        return self::tiene($c)
            ? route('app.icono', ['archivo' => $archivo, 'c' => $c->id, 'v' => $c->ajuste('app.icono')])
            : route('app.icono', $archivo);
    }

    public static function ruta(Cuenta $c, string $archivo): ?string
    {
        if (! self::tiene($c) || ! isset(self::TAMANOS[$archivo])) return null;
        $p = Storage::disk('local')->path("cuentas/{$c->id}/app/$archivo");
        return is_file($p) ? $p : null;
    }

    /** Genera todos los tamaños sobre el color de fondo elegido. Devuelve la versión nueva */
    public static function generar(Cuenta $c, UploadedFile $f, string $fondo = '#FFFFFF'): string
    {
        $img = @imagecreatefromstring((string) file_get_contents($f->getRealPath()));
        if (! $img) throw new RuntimeException('No se pudo leer la imagen. Súbela en PNG o JPG.');
        [$r, $g, $b] = sscanf(ltrim($fondo, '#'), '%02x%02x%02x');
        $w = imagesx($img); $h = imagesy($img);
        $dir = "cuentas/{$c->id}/app";
        Storage::disk('local')->makeDirectory($dir);

        foreach (self::TAMANOS as $archivo => [$lado, $margen]) {
            $lienzo = imagecreatetruecolor($lado, $lado);
            imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, $r ?? 255, $g ?? 255, $b ?? 255));
            imagealphablending($lienzo, true);
            // La imagen completa cabe en el cuadro (si no es cuadrada, queda centrada sobre el fondo)
            $util = (int) round($lado * (1 - 2 * $margen));
            $esc = min($util / $w, $util / $h);
            $nw = max(1, (int) round($w * $esc)); $nh = max(1, (int) round($h * $esc));
            imagecopyresampled($lienzo, $img, intdiv($lado - $nw, 2), intdiv($lado - $nh, 2), 0, 0, $nw, $nh, $w, $h);
            imagepng($lienzo, Storage::disk('local')->path("$dir/$archivo"), 9);
            imagedestroy($lienzo);
        }
        imagedestroy($img);
        return Str::lower(Str::random(6));
    }

    public static function quitar(Cuenta $c): void
    {
        Storage::disk('local')->deleteDirectory("cuentas/{$c->id}/app");
    }
}
