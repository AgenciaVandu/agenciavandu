<?php

namespace App\Support\Fitness;

use App\Models\Cliente;
use App\Support\FotoProducto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Foto del atleta (para reconocerlo al escanear su QR): WebP de 600 px, privada */
class Fotos
{
    public const TAM = 600;

    public static function guardar(Cliente $c, UploadedFile $archivo): void
    {
        $img = @imagecreatefromstring((string) file_get_contents($archivo->getRealPath()));
        if (! $img) throw new \RuntimeException('No pudimos leer la foto. Súbela en JPG, PNG o WebP.');
        $img = FotoProducto::enderezar($img, $archivo->getRealPath());
        $carpeta = 'atletas/' . $c->cuenta_id;
        $disco = Storage::disk('local');
        $disco->makeDirectory($carpeta);
        $ruta = "$carpeta/{$c->id}-" . Str::lower(Str::random(8)) . '.webp';
        FotoProducto::escribir($img, self::TAM, $disco->path($ruta));
        imagedestroy($img);
        if ($c->foto) $disco->delete($c->foto);
        $c->forceFill(['foto' => $ruta])->saveQuietly();
    }

    public static function quitar(Cliente $c): void
    {
        if ($c->foto) Storage::disk('local')->delete($c->foto);
        $c->forceFill(['foto' => null])->saveQuietly();
    }

    public static function responder(Cliente $c)
    {
        abort_unless($c->foto && Storage::disk('local')->exists($c->foto), 404);
        return response()->file(Storage::disk('local')->path($c->foto), ['Content-Type' => 'image/webp', 'Cache-Control' => 'private, max-age=604800']);
    }
}
