<?php

namespace App\Support;

use App\Models\Cuenta;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Cómo se ven las cotizaciones de cada negocio: su hoja membretada de fondo, márgenes, papel, color y letra.
 * Se aplica al PDF y a la vista que abre el cliente (ahí van la franja de arriba y la de abajo del membrete).
 */
class DisenoDocumento
{
    public const PAPELES = ['a4' => ['A4', 210, 297], 'carta' => ['Carta', 215.9, 279.4]];
    public const LETRAS = [
        'geist'     => ['texto' => 'Moderna', 'pdf' => 'Geist', 'web' => "'Geist', system-ui, sans-serif"],
        'helvetica' => ['texto' => 'Clásica sin remates', 'pdf' => 'Helvetica', 'web' => "'Helvetica Neue', Helvetica, Arial, sans-serif"],
        'times'     => ['texto' => 'Elegante con remates', 'pdf' => 'Times', 'web' => "Georgia, 'Times New Roman', serif"],
    ];
    /** Diseños listos para quien no tiene (o no le gusta) su formato */
    public const PLANTILLAS = [
        'clasica'  => ['texto' => 'Clásica',  'ayuda' => 'Encabezado de color con esquinas redondeadas.'],
        'moderna'  => ['texto' => 'Moderna',  'ayuda' => 'Bloques de color sólidos y tabla con encabezado de color.'],
        'minimal'  => ['texto' => 'Minimal',  'ayuda' => 'Fondo blanco, una línea de color y mucho aire.'],
        'elegante' => ['texto' => 'Elegante', 'ayuda' => 'Logo al centro, líneas finas y títulos en versalitas.'],
    ];

    public const DEFECTO = [
        'plantilla' => 'clasica', 'membrete' => null, 'franja_sup' => null, 'franja_inf' => null, 'en_todas' => true, 'con_encabezado' => false,
        'sup' => 45, 'inf' => 25, 'izq' => 18, 'der' => 18, 'papel' => 'a4', 'color' => '#242424', 'letra' => 'geist',
    ];

    /** Ajustes del negocio activo con sus valores por defecto */
    public static function de(?Cuenta $c = null): array
    {
        $c ??= Cuentas::actual();
        $d = array_merge(self::DEFECTO, (array) ($c?->ajuste('documento') ?? []));
        if (! isset(self::PAPELES[$d['papel']])) $d['papel'] = 'a4';
        if (! isset(self::LETRAS[$d['letra']])) $d['letra'] = 'geist';
        if (! isset(self::PLANTILLAS[$d['plantilla']])) $d['plantilla'] = 'clasica';
        if (! preg_match('/^#[0-9a-f]{6}$/i', (string) $d['color'])) $d['color'] = '#242424';
        if ($d['membrete'] && ! Storage::disk('local')->exists($d['membrete'])) $d['membrete'] = $d['franja_sup'] = $d['franja_inf'] = null;
        return $d;
    }

    /** ¿Usa algo distinto al diseño de fábrica? */
    public static function personalizado(array $d): bool
    {
        return $d['membrete'] || $d['plantilla'] !== 'clasica' || $d['color'] !== '#242424' || $d['letra'] !== 'geist' || $d['papel'] !== 'a4';
    }

    /**
     * Guarda la hoja membretada (imagen, o PDF si el servidor puede convertirlo) como JPG del tamaño de la hoja.
     * Regresa la ruta relativa a storage/app.
     */
    public static function guardarMembrete(Cuenta $c, UploadedFile $archivo): string
    {
        $ruta = $archivo->getRealPath();
        $esPdf = strtolower($archivo->getClientOriginalExtension()) === 'pdf' || $archivo->getMimeType() === 'application/pdf';
        $img = $esPdf ? self::pdfAImagen($ruta) : @imagecreatefromstring((string) file_get_contents($ruta));
        if (! $img) throw new \RuntimeException($esPdf
            ? 'No pudimos leer el PDF en este servidor. Expórtalo como imagen (PNG o JPG) y súbelo de nuevo.'
            : 'No pudimos leer la imagen. Súbela en PNG o JPG.');

        // Máximo 1800 px de ancho (≈ 215 dpi en A4): nítido en el PDF y ligero
        $w = imagesx($img); $h = imagesy($img);
        $escala = min(1, 1800 / $w);
        $nw = (int) round($w * $escala); $nh = (int) round($h * $escala);
        $lienzo = imagecreatetruecolor($nw, $nh);
        imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 255, 255, 255));   // las transparencias quedan en blanco
        imagecopyresampled($lienzo, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);

        $dir = 'membretes/' . $c->id;
        $disco = Storage::disk('local');
        $disco->makeDirectory($dir);
        $nombre = 'membrete-' . Str::lower(Str::random(8)) . '.jpg';
        imagejpeg($lienzo, $disco->path("$dir/$nombre"), 88);
        imagedestroy($lienzo);
        return "$dir/$nombre";
    }

    /** Franjas de arriba y de abajo del membrete (según los márgenes) para la vista web */
    public static function recortarFranjas(array $d): array
    {
        $disco = Storage::disk('local');
        foreach (['franja_sup', 'franja_inf'] as $k) if ($d[$k]) $disco->delete($d[$k]);
        $d['franja_sup'] = $d['franja_inf'] = null;
        if (! $d['membrete'] || ! $disco->exists($d['membrete'])) return $d;

        $img = @imagecreatefromjpeg($disco->path($d['membrete']));
        if (! $img) return $d;
        $w = imagesx($img); $h = imagesy($img);
        $altoHoja = self::PAPELES[$d['papel']][2];
        $base = dirname($d['membrete']) . '/' . pathinfo($d['membrete'], PATHINFO_FILENAME);
        foreach (['franja_sup' => [0, $d['sup']], 'franja_inf' => [$altoHoja - $d['inf'], $d['inf']]] as $k => [$desdeMm, $altoMm]) {
            $y = (int) round($desdeMm / $altoHoja * $h);
            $alto = (int) round($altoMm / $altoHoja * $h);
            if ($alto < 4 || self::vacia($img, $y, $alto)) continue;
            $franja = imagecreatetruecolor($w, $alto);
            imagecopy($franja, $img, 0, 0, 0, $y, $w, $alto);
            $ruta = "$base-$k.jpg";
            imagejpeg($franja, $disco->path($ruta), 85);
            imagedestroy($franja);
            $d[$k] = $ruta;
        }
        imagedestroy($img);
        return $d;
    }

    /** Una franja toda en blanco no se muestra en la web */
    private static function vacia(\GdImage $img, int $y, int $alto): bool
    {
        $w = imagesx($img);
        for ($i = 0; $i < 400; $i++) {
            $c = imagecolorat($img, random_int(0, $w - 1), $y + random_int(0, max(0, $alto - 1)));
            if ((($c >> 16) & 255) < 235 || (($c >> 8) & 255) < 235 || ($c & 255) < 235) return false;
        }
        return true;
    }

    public static function borrarArchivos(array $d): void
    {
        Storage::disk('local')->delete(array_filter([$d['membrete'] ?? null, $d['franja_sup'] ?? null, $d['franja_inf'] ?? null]));
    }

    /** Logo para fondo oscuro o claro (en la cuenta principal, el de Vandu) */
    public static function logo(bool $fondoOscuro): ?string
    {
        if (! Cuentas::esPrincipal()) return Cuentas::actual()?->logo($fondoOscuro);
        $f = resource_path('pdf/logo-vandu-' . ($fondoOscuro ? 'blanco' : 'negro') . '.png');
        return 'data:image/png;base64,' . base64_encode((string) file_get_contents($f));
    }

    /** ¿El encabezado de esta plantilla va sobre color? */
    public static function encabezadoOscuro(array $d): bool
    {
        return in_array($d['plantilla'], ['clasica', 'moderna'], true) && self::tintaSobre($d['color']) === '#ffffff';
    }

    /** data URI para incrustar en la vista web */
    public static function dataUri(?string $ruta): ?string
    {
        if (! $ruta || ! Storage::disk('local')->exists($ruta)) return null;
        return 'data:image/jpeg;base64,' . base64_encode(Storage::disk('local')->get($ruta));
    }

    /** Color mezclado con blanco: 0.9 = casi blanco */
    public static function tinte(string $hex, float $blanco): string
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        $m = fn ($v) => (int) round($v + (255 - $v) * $blanco);
        return sprintf('#%02x%02x%02x', $m($r), $m($g), $m($b));
    }

    /** Texto blanco o negro según qué se lea mejor sobre el color */
    public static function tintaSobre(string $hex): string
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 160 ? '#13161D' : '#ffffff';
    }

    private static function pdfAImagen(string $ruta): ?\GdImage
    {
        if (extension_loaded('imagick')) {
            try {
                $im = new \Imagick();
                $im->setResolution(200, 200);
                $im->readImage($ruta . '[0]');
                $im->setImageBackgroundColor('white');
                $im = $im->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
                $im->setImageFormat('png');
                return @imagecreatefromstring($im->getImageBlob()) ?: null;
            } catch (\Throwable) {
            }
        }
        if (function_exists('exec') && ($bin = trim((string) @shell_exec('command -v pdftoppm 2>/dev/null')))) {
            $salida = tempnam(sys_get_temp_dir(), 'mbr');
            @exec(escapeshellarg($bin) . ' -png -r 200 -f 1 -l 1 -singlefile ' . escapeshellarg($ruta) . ' ' . escapeshellarg($salida));
            $png = $salida . '.png';
            $img = is_file($png) ? @imagecreatefrompng($png) : null;
            @unlink($png); @unlink($salida);
            return $img ?: null;
        }
        return null;
    }
}
