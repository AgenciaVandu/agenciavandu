<?php

namespace App\Support;

use App\Mail\CorreoVandu;
use App\Models\Cuenta;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Correo propio de cada negocio (SMTP de Google Workspace, Outlook o su hosting).
 * Si lo configuró y pasó la prueba, todo lo que el panel manda en su nombre sale desde su cuenta
 * y queda en sus enviados; si no, sale desde el correo de la plataforma con su nombre y su correo para responder.
 */
class CorreoNegocio
{
    private static ?string $mailerBase = null;

    public const PROVEEDORES = [
        'google'  => ['texto' => 'Gmail / Google Workspace', 'host' => 'smtp.gmail.com', 'puerto' => 587, 'cifrado' => 'tls',
                      'ayuda' => 'Usa una contraseña de aplicación: en tu cuenta de Google → Seguridad → Verificación en dos pasos → Contraseñas de aplicaciones.'],
        'outlook' => ['texto' => 'Outlook / Microsoft 365', 'host' => 'smtp.office365.com', 'puerto' => 587, 'cifrado' => 'tls',
                      'ayuda' => 'Tu administrador de Microsoft 365 debe tener activado "SMTP autenticado" para este buzón.'],
        'hosting' => ['texto' => 'Correo de mi hosting (cPanel)', 'host' => '', 'puerto' => 465, 'cifrado' => 'ssl',
                      'ayuda' => 'Normalmente el servidor es mail.tudominio.com, puerto 465 con SSL, y el usuario es el correo completo.'],
        'otro'    => ['texto' => 'Otro', 'host' => '', 'puerto' => 587, 'cifrado' => 'tls', 'ayuda' => 'Pide a tu proveedor de correo los datos SMTP.'],
    ];

    /** Ajustes guardados (sin la contraseña) */
    public static function de(?Cuenta $c = null): array
    {
        $c ??= Cuentas::actual();
        return array_merge(['activo' => false, 'proveedor' => 'google', 'host' => '', 'puerto' => 587, 'cifrado' => 'tls', 'usuario' => '', 'desde' => '',
                            'tiene_clave' => false, 'verificado' => null, 'error' => null],
            array_diff_key((array) ($c?->ajuste('smtp') ?? []), ['clave' => 1]),
            ['tiene_clave' => (bool) $c?->ajuste('smtp.clave')]);
    }

    /** Configuración de Laravel para un SMTP */
    public static function config(array $s, string $clave): array
    {
        return [
            'transport' => 'smtp', 'host' => $s['host'], 'port' => (int) $s['puerto'],
            'encryption' => in_array($s['cifrado'], ['tls', 'ssl'], true) ? $s['cifrado'] : null,
            'username' => $s['usuario'], 'password' => $clave, 'timeout' => 20,
        ];
    }

    /** Al activar una cuenta: su correo, o el de la plataforma */
    public static function aplicar(?Cuenta $c): void
    {
        self::$mailerBase ??= (string) config('mail.default');
        $s = (array) ($c?->ajuste('smtp') ?? []);
        if (! empty($s['activo']) && ! empty($s['clave']) && ! empty($s['host'])) {
            try {
                $clave = Crypt::decryptString($s['clave']);
            } catch (Throwable) {
                $clave = null;
            }
            if ($clave !== null) {
                config(['mail.mailers.cuenta' => self::config(array_merge(self::de($c), $s), $clave), 'mail.default' => 'cuenta',
                        'vandu.correo.desde' => $s['desde'] ?: $s['usuario']]);
                self::purgar('cuenta');
                return;
            }
        }
        config(['mail.default' => self::$mailerBase]);
        self::purgar('cuenta');
    }

    /**
     * Manda un correo de prueba con esos datos. Regresa null si salió bien o el error en palabras simples.
     */
    public static function probar(array $s, string $clave, string $para): ?string
    {
        config(['mail.mailers.prueba' => self::config($s, $clave)]);
        self::purgar('prueba');
        $desde = config('vandu.correo.desde');
        config(['vandu.correo.desde' => $s['desde'] ?: $s['usuario']]);
        try {
            Mail::mailer('prueba')->to($para)->send(new CorreoVandu(
                asunto: 'Correo de prueba de tu panel',
                titulo: 'Tu correo quedó conectado',
                cuerpo: "Si lees esto, las cotizaciones, avisos e invitaciones de tu panel ya salen desde {$s['desde']}.\n\nTambién vas a encontrarlas en tus enviados.",
            ));
            return null;
        } catch (Throwable $e) {
            return self::explicar($e->getMessage());
        } finally {
            config(['vandu.correo.desde' => $desde]);
            self::purgar('prueba');
        }
    }

    public static function explicar(string $m): string
    {
        $l = mb_strtolower($m);
        return match (true) {
            str_contains($l, 'authenticat') || str_contains($l, '535') || str_contains($l, 'username and password')
                => 'El correo o la contraseña no son correctos. Si usas Gmail o Google Workspace, necesitas una contraseña de aplicación.',
            str_contains($l, 'connection could not be established') || str_contains($l, 'getaddrinfo') || str_contains($l, 'timed out') || str_contains($l, 'connection refused')
                => 'No pudimos conectar con el servidor de correo. Revisa el servidor y el puerto.',
            str_contains($l, 'ssl') || str_contains($l, 'tls') || str_contains($l, 'certificate')
                => 'Falló la conexión segura. Prueba con el otro tipo de cifrado (SSL con puerto 465, o TLS con puerto 587).',
            str_contains($l, 'sender') || str_contains($l, 'from address') || str_contains($l, '553') || str_contains($l, '550')
                => 'El servidor no te deja enviar con ese remitente. Usa el mismo correo con el que inicias sesión.',
            default => 'El servidor de correo respondió: ' . \Illuminate\Support\Str::limit($m, 180),
        };
    }

    private static function purgar(string $nombre): void
    {
        try { app('mail.manager')->purge($nombre); } catch (Throwable) {}
    }
}
