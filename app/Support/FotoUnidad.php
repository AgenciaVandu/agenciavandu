<?php

namespace App\Support;

use App\Models\Unidad;
use App\Models\UnidadFoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Fotos de las unidades: se guardan en WebP de 1600 px con miniatura, igual que las de productos */
class FotoUnidad
{
    public static function agregar(Unidad $u, UploadedFile $archivo): UnidadFoto
    {
        $origen = @imagecreatefromstring((string) file_get_contents($archivo->getRealPath()));
        if (! $origen) throw new \RuntimeException('No pudimos leer una de las fotos. Súbelas en JPG, PNG o WebP.');
        $origen = FotoProducto::enderezar($origen, $archivo->getRealPath());

        $carpeta = 'autos/' . $u->cuenta_id . '/' . $u->id;
        $disco = Storage::disk('local');
        $disco->makeDirectory($carpeta);
        $nombre = Str::slug(Str::limit($u->nombre, 50, '')) . '-' . Str::lower(Str::random(6)) . '.webp';
        FotoProducto::escribir($origen, FotoProducto::GRANDE, $disco->path("$carpeta/$nombre"));
        FotoProducto::escribir($origen, FotoProducto::MINI, $disco->path("$carpeta/min-$nombre"));
        imagedestroy($origen);

        return $u->fotos()->create(['archivo' => "$carpeta/$nombre", 'orden' => (int) $u->fotos()->max('orden') + 1]);
    }

    public static function quitar(UnidadFoto $f): void
    {
        Storage::disk('local')->delete([$f->archivo, dirname($f->archivo) . '/min-' . basename($f->archivo)]);
        $f->delete();
    }

    public static function ruta(Unidad $u, string $archivo): ?string
    {
        $base = preg_replace('/^min-/', '', $archivo);
        $foto = $u->fotos()->get()->first(fn ($f) => basename($f->archivo) === $base);
        if (! $foto) return null;
        $ruta = Storage::disk('local')->path(dirname($foto->archivo) . '/' . $archivo);
        return is_file($ruta) ? $ruta : null;
    }
}
