<?php

namespace App\Support\Facturas;

use App\Models\Cuenta;
use App\Support\Cuentas;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Facturación por negocio con la cuenta de Facturapi de Nerby (multiempresa).
 * Con la clave de usuario (FACTURAPI_USER_KEY, solo en el .env) Nerby crea la organización de cada negocio,
 * le pone sus datos fiscales, su logo y su color, y le sube su sello digital (CSD).
 * El .cer, el .key y la contraseña se mandan directo a Facturapi: Nerby no los guarda ni en disco ni en la base.
 * De cada organización solo se guardan (cifradas) sus claves para timbrar.
 */
class Organizacion
{
    public static function disponible(): bool
    {
        return filled(config('services.facturapi.user_key'));
    }

    private static function http()
    {
        return Http::withToken((string) config('services.facturapi.user_key'))->acceptJson()->timeout(40);
    }

    private static function error($r, string $que): OrganizacionError
    {
        $m = $r->json('message') ?: ('respondió ' . $r->status());
        return new OrganizacionError("$que: $m");
    }

    /** Las claves llegan como texto ("sk_live_…") o, según la versión, dentro de un objeto */
    private static function clave($r): ?string
    {
        $j = $r->json();
        $k = is_string($j) ? $j : ($j['key'] ?? $j['api_key'] ?? null);
        if (! $k) $k = trim((string) $r->body(), "\" \n");
        return str_starts_with((string) $k, 'sk_') ? $k : null;
    }

    /**
     * Paso 1: datos fiscales del negocio. Crea la organización la primera vez y obtiene la clave de pruebas.
     * @param array $d razon_social, regimen, cp, direccion (opcional)
     */
    public static function guardarEmisor(array $d): void
    {
        $c = Cuentas::actual();
        $a = Timbrado::ajustes();
        $org = $a['org_id'];
        if (! $org) {
            $r = self::http()->post(Timbrado::API . '/organizations', ['name' => Str::limit($c->nombre, 80, '')]);
            if (! $r->successful() || ! $r->json('id')) throw self::error($r, 'No se pudo crear tu cuenta de facturación');
            $org = (string) $r->json('id');
            Timbrado::guardarAjustes(['org_id' => $org, 'pruebas' => true]);
        }

        $r = self::http()->put(Timbrado::API . "/organizations/$org/legal", array_filter([
            'name' => Str::limit($c->nombre, 80, ''),
            'legal_name' => mb_strtoupper(trim($d['razon_social'])),
            'tax_system' => (string) $d['regimen'],
            'address' => array_filter(['zip' => (string) $d['cp'], 'street' => $d['direccion'] ?? null, 'country' => 'MEX']),
            'phone' => $c->ajuste('emisor.telefono') ?: null,
            'support_email' => $c->ajuste('emisor.email') ?: null,
            'website' => $c->ajuste('marca.sitio') ?: null,
        ]));
        if (! $r->successful()) throw self::error($r, 'Facturapi no aceptó tus datos fiscales');

        self::imagen($org, $c);
        $test = $a['test_key'] ?: self::clave(self::http()->get(Timbrado::API . "/organizations/$org/apikeys/test"));
        Timbrado::guardarAjustes(['test_key' => $test, 'emisor' => ['razon_social' => mb_strtoupper(trim($d['razon_social'])), 'regimen' => (string) $d['regimen'],
            'cp' => (string) $d['cp'], 'direccion' => $d['direccion'] ?? null]]);
    }

    /** Logo (PNG o JPG de Mi negocio) y color de la marca en el PDF de la factura. Si falla no detiene nada */
    public static function imagen(string $org, Cuenta $c): void
    {
        try {
            $ruta = collect(['logo', 'logo_oscuro'])->map(fn ($k) => $c->ajuste("marca.$k"))
                ->first(fn ($r) => $r && preg_match('/\.(png|jpe?g)$/i', $r) && is_file(storage_path('app/' . $r)));
            if ($ruta) {
                self::http()->attach('file', file_get_contents(storage_path('app/' . $ruta)), basename($ruta))
                    ->put(Timbrado::API . "/organizations/$org/logo");
            }
            $color = ltrim((string) ($c->ajuste('documento.color') ?: $c->ajuste('icono_fondo') ?: '#5B3DF5'), '#');
            if (preg_match('/^[0-9A-Fa-f]{6}$/', $color)) self::http()->put(Timbrado::API . "/organizations/$org/customization", ['color' => $color]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Paso 2: sello digital. Se manda a Facturapi (que lo valida con el SAT) y no se guarda en Nerby.
     * La primera vez también se genera la clave para timbrar de verdad.
     */
    public static function subirSello(UploadedFile $cer, UploadedFile $key, string $password): array
    {
        $a = Timbrado::ajustes();
        if (! $a['org_id']) throw new OrganizacionError('Primero guarda tus datos fiscales.');
        $r = self::http()
            ->attach('cer', file_get_contents($cer->getRealPath()), 'cer.cer')
            ->attach('key', file_get_contents($key->getRealPath()), 'key.key')
            ->put(Timbrado::API . "/organizations/{$a['org_id']}/certificate", ['password' => $password]);
        if (! $r->successful()) throw self::error($r, 'El SAT o Facturapi no aceptaron el sello');

        $cert = [
            'tiene' => (bool) ($r->json('certificate.has_certificate') ?? true),
            'vence' => $r->json('certificate.expires_at') ? substr((string) $r->json('certificate.expires_at'), 0, 10) : null,
            'serie' => $r->json('certificate.serial_number'),
            'rfc' => $r->json('legal.tax_id'),
            'subido' => now()->toDateString(),
        ];
        $live = $a['live_key'];
        if (! $live) {
            $live = self::clave(self::http()->put(Timbrado::API . "/organizations/{$a['org_id']}/apikeys/live"));
            if (! $live) throw new OrganizacionError('El sello quedó guardado, pero no se pudo activar la facturación real. Intenta de nuevo.');
        }
        Timbrado::guardarAjustes(['certificado' => $cert, 'live_key' => $live, 'pruebas' => false]);
        return $cert;
    }
}
