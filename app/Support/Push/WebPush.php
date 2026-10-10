<?php

namespace App\Support\Push;

use App\Models\Integracion;
use App\Models\PushSuscripcion;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Notificaciones push estándar (Web Push) sin librerías externas: solo OpenSSL.
 * - Firma VAPID (JWT ES256) para identificar al panel ante Apple, Google y Mozilla.
 * - Cifrado del mensaje "aes128gcm" (RFC 8188 / RFC 8291): solo el dispositivo puede leerlo.
 * Las claves VAPID se crean solas la primera vez y se guardan cifradas en la base de datos.
 */
class WebPush
{
    private const SPKI_P256 = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    /** @return array{publica:string, privada:string} en base64url (formato estándar) */
    public static function claves(): array
    {
        if (config('vandu.push.publica') && config('vandu.push.privada')) {
            return ['publica' => config('vandu.push.publica'), 'privada' => config('vandu.push.privada')];
        }
        // Las claves son de la plataforma (una sola app instalable), se guardan en la cuenta principal
        $i = Integracion::sinCuenta()->where('cuenta_id', \App\Support\Cuentas::principalId())->where('proveedor', 'push')->first();
        if ($i && ! empty($i->datos['publica'])) {
            return $i->datos;
        }
        $nuevas = static::generarClaves();
        Integracion::sinCuenta()->updateOrCreate(['cuenta_id' => \App\Support\Cuentas::principalId(), 'proveedor' => 'push'], ['cuenta' => 'VAPID', 'datos' => $nuevas]);
        return $nuevas;
    }

    public static function generarClaves(): array
    {
        [$priv, $pub] = static::parEc();
        return ['publica' => static::b64($pub), 'privada' => static::b64($priv)];
    }

    /**
     * Envía un mensaje a una suscripción.
     * @return int código HTTP del servicio push (201 = entregado; 404/410 = la suscripción ya no existe)
     */
    public static function enviar(PushSuscripcion $s, array $mensaje, int $ttl = 86400, string $urgencia = 'normal'): int
    {
        $claves = static::claves();
        $cuerpo = static::cifrar(json_encode($mensaje, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $s->p256dh, $s->auth);

        $u = parse_url($s->endpoint);
        $jwt = static::jwtVapid($u['scheme'] . '://' . $u['host'] . (isset($u['port']) ? ':' . $u['port'] : ''), $claves);

        $r = Http::timeout(10)
            ->withHeaders([
                'Authorization'    => 'vapid t=' . $jwt . ', k=' . $claves['publica'],
                'Content-Encoding' => 'aes128gcm',
                'TTL'              => (string) $ttl,
                'Urgency'          => $urgencia,
            ])
            ->withBody($cuerpo, 'application/octet-stream')
            ->post($s->endpoint);

        return $r->status();
    }

    /** Cifra el texto para el dispositivo (p256dh y auth vienen del navegador, en base64url) */
    public static function cifrar(string $texto, string $p256dh, string $auth): string
    {
        $uaPublica = static::deB64($p256dh);
        $secreto = static::deB64($auth);
        if (strlen($uaPublica) !== 65 || strlen($secreto) !== 16) {
            throw new RuntimeException('La suscripción push tiene claves inválidas.');
        }

        // Clave efímera del servidor y secreto compartido (ECDH)
        $efimera = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $d = openssl_pkey_get_details($efimera)['ec'];
        $asPublica = "\x04" . static::pad32($d['x']) . static::pad32($d['y']);
        $compartido = openssl_pkey_derive(static::clavePublicaPem($uaPublica), $efimera, 32);
        if ($compartido === false) {
            throw new RuntimeException('No se pudo calcular la clave del mensaje push.');
        }

        // RFC 8291: IKM a partir del secreto de autenticación
        $ikm = hash_hkdf('sha256', $compartido, 32, "WebPush: info\0" . $uaPublica . $asPublica, $secreto);

        // RFC 8188: clave y nonce del contenido
        $sal = random_bytes(16);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $sal);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $sal);

        $cifrado = openssl_encrypt($texto . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);

        return $sal . pack('N', 4096) . chr(65) . $asPublica . $cifrado . $tag;
    }

    public static function jwtVapid(string $audiencia, array $claves): string
    {
        $cab = static::b64(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $datos = static::b64(json_encode([
            'aud' => $audiencia,
            'exp' => time() + 12 * 3600,
            'sub' => 'mailto:' . config('vandu.push.contacto'),
        ], JSON_UNESCAPED_SLASHES));

        $privada = static::deB64($claves['privada']);
        $publica = static::deB64($claves['publica']);
        $pem = "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode(
            hex2bin('30770201010420') . $privada . hex2bin('a00a06082a8648ce3d030107a144034200') . $publica
        ), 64, "\n") . "-----END EC PRIVATE KEY-----\n";

        if (! openssl_sign($cab . '.' . $datos, $der, $pem, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('No se pudo firmar la notificación push.');
        }

        return $cab . '.' . $datos . '.' . static::b64(static::derARaw($der));
    }

    // ---------- utilidades ----------

    /** @return array{0:string,1:string} [privada 32 bytes, pública 65 bytes] */
    private static function parEc(): array
    {
        $k = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $d = openssl_pkey_get_details($k)['ec'];
        return [static::pad32($d['d']), "\x04" . static::pad32($d['x']) . static::pad32($d['y'])];
    }

    private static function clavePublicaPem(string $punto)
    {
        $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode(hex2bin(self::SPKI_P256) . $punto), 64, "\n") . "-----END PUBLIC KEY-----\n";
        $k = openssl_pkey_get_public($pem);
        if ($k === false) {
            throw new RuntimeException('La clave pública del dispositivo no es válida.');
        }
        return $k;
    }

    /** Firma ECDSA en DER (SEQUENCE{r,s}) → r||s de 32 bytes cada uno */
    private static function derARaw(string $der): string
    {
        $pos = 2;
        if (ord($der[1]) & 0x80) $pos += ord($der[1]) & 0x7f;
        $leer = function () use ($der, &$pos) {
            $pos++; // 0x02
            $len = ord($der[$pos++]);
            $v = substr($der, $pos, $len);
            $pos += $len;
            return str_pad(ltrim($v, "\x00"), 32, "\x00", STR_PAD_LEFT);
        };
        return $leer() . $leer();
    }

    private static function pad32(string $v): string
    {
        return str_pad(substr($v, -32), 32, "\x00", STR_PAD_LEFT);
    }

    public static function b64(string $v): string
    {
        return rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
    }

    public static function deB64(string $v): string
    {
        return (string) base64_decode(strtr($v, '-_', '+/') . str_repeat('=', (4 - strlen($v) % 4) % 4));
    }
}
