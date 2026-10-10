<?php

namespace App\Support;

use App\Models\Cuenta;
use App\Support\Dropbox\Dropbox;
use Throwable;

/**
 * La cuenta (negocio) con la que se trabaja en esta petición.
 * Todo el panel lee config('vandu.*'); al activar una cuenta se aplican encima sus datos y su giro.
 */
class Cuentas
{
    private static ?int $id = null;
    private static ?Cuenta $cuenta = null;
    private static ?array $base = null;          // config('vandu') como la deja la cuenta principal
    private static ?int $aplicada = null;        // cuenta cuyos datos están puestos en la config
    private static ?array $fabrica = null;       // tipos de proyecto del archivo de config, sin cambios

    /** Lo que cambia de una cuenta a otra dentro de config('vandu') */
    private const PROPIOS = ['marca', 'emisor', 'whatsapp', 'pago', 'folio_prefijo', 'vigencia_dias', 'zona_horaria', 'titulo', 'giro', 'proyectos', 'consideraciones', 'correo.nombre', 'correo.pie', 'correo.responder_a', 'correo.plantillas', 'dropbox.carpeta'];

    /** Al arrancar la app: estado limpio (en pruebas la app se crea muchas veces en el mismo proceso) */
    public static function reiniciar(): void
    {
        self::$id = self::$aplicada = null;
        self::$cuenta = null;
        self::$base = config('vandu');
        self::$fabrica = config('vandu.proyectos');
    }

    /** Ajustes que cada negocio captura en "Mi negocio" y a qué parte de config('vandu') van */
    public const AJUSTES = ['marca', 'emisor', 'pago', 'whatsapp', 'folio_prefijo', 'vigencia_dias', 'zona_horaria', 'titulo', 'correo_nombre', 'correo_responder', 'dropbox_carpeta'];

    public static function principalId(): int
    {
        return (int) config('vandu.cuenta_principal', 1);
    }

    public static function id(): int
    {
        return self::$id ?? self::principalId();
    }

    public static function actual(): ?Cuenta
    {
        if (! self::$cuenta || self::$cuenta->id !== self::id()) {
            try { self::$cuenta = Cuenta::find(self::id()); } catch (Throwable) { self::$cuenta = null; }
        }
        return self::$cuenta;
    }

    public static function esPrincipal(): bool
    {
        return self::id() === self::principalId();
    }

    /** Tipos de proyecto de fábrica según el giro de la cuenta activa */
    public static function proyectosDeFabrica(?Cuenta $c = null): array
    {
        self::$fabrica ??= config('vandu.proyectos');
        $giro = ($c ?? self::actual())?->giro ?? 'agencia';
        return config("vandu.giros.$giro.proyectos") ?? self::$fabrica;
    }

    public static function activar(Cuenta|int|null $c): ?Cuenta
    {
        if (is_int($c)) $c = Cuenta::find($c);
        if (! $c) return null;
        self::$fabrica ??= config('vandu.proyectos');
        $v = config('vandu');
        if (self::$aplicada !== null && self::$aplicada !== self::principalId() && self::$base) {
            // Venimos de otra cuenta: regresa sus datos a los de la principal antes de aplicar la nueva
            foreach (self::PROPIOS as $k) data_set($v, $k, data_get(self::$base, $k));
        } else {
            self::$base = $v;
        }
        $v['proyectos'] = self::$fabrica;
        self::$id = $c->id;
        self::$cuenta = $c;
        self::$aplicada = $c->id;

        config(['vandu' => self::configDe($c, $v)]);
        TiposProyecto::aplicar();
        PlantillasCorreo::limpiar();
        Dropbox::usar(null);
        return $c;
    }

    /** Corre algo dentro de otra cuenta y regresa a la que estaba (comandos, plataforma) */
    public static function como(Cuenta $c, callable $fn)
    {
        $antes = self::$id;
        self::activar($c);
        try {
            return $fn($c);
        } finally {
            $antes ? self::activar($antes) : self::activar(self::principalId());
        }
    }

    /** Llave de caché propia de la cuenta (los Dropbox de cada negocio son distintos) */
    public static function clave(string $k): string
    {
        return 'c' . self::id() . '.' . $k;
    }

    /** config('vandu') con los datos y el giro de una cuenta */
    public static function configDe(Cuenta $c, array $v): array
    {
        $a = $c->ajustes ?? [];
        $principal = $c->id === ($v['cuenta_principal'] ?? 1);
        $giro = $v['giros'][$c->giro] ?? $v['giros']['agencia'];
        $v['proyectos'] = $giro['proyectos'] ?? self::$fabrica ?? $v['proyectos'];
        $v['giro'] = $c->giro;

        // Una cuenta nueva no hereda los datos de contacto ni bancarios de la principal
        if (! $principal) {
            $v['marca'] = ['nombre' => $c->nombre, 'ciudad' => '', 'sitio' => ''];
            $v['emisor'] = ['nombre' => $c->nombre, 'telefono' => '', 'sitio' => '', 'email' => ''];
            $v['whatsapp'] = '';
            $v['pago'] = array_merge($v['pago'], ['banco' => '', 'clabe' => '', 'beneficiario' => '', 'nota_comprobante' => 'Una vez realizado el pago, favor de enviar el comprobante para su confirmación.']);
            $v['correo']['nombre'] = $c->nombre;
            $v['correo']['pie'] = $c->nombre;
            $v['dropbox']['carpeta'] = \Illuminate\Support\Str::limit(Dropbox::nombreSeguro($c->nombre), 40, '');
        }
        foreach (['marca', 'emisor', 'pago'] as $k) {
            if (! empty($a[$k]) && is_array($a[$k])) $v[$k] = array_merge($v[$k], array_filter($a[$k], fn ($x) => $x !== null));
        }
        foreach (['whatsapp', 'folio_prefijo', 'vigencia_dias', 'zona_horaria', 'titulo'] as $k) {
            if (isset($a[$k]) && $a[$k] !== '') $v[$k] = $a[$k];
        }
        if (! empty($a['correo_nombre'])) $v['correo']['nombre'] = $a['correo_nombre'];
        if (! empty($a['correo_responder'])) $v['correo']['responder_a'] = $a['correo_responder'];
        elseif (! $principal && ! empty($v['emisor']['email'])) $v['correo']['responder_a'] = $v['emisor']['email'];
        if (! $principal) {
            $m = $v['marca'];
            $v['correo']['pie'] = trim($m['nombre'] . ($m['ciudad'] ? ' · ' . $m['ciudad'] : ''));
            if (! empty($v['emisor']['email']) && empty($a['pago']['nota_comprobante'])) {
                $v['pago']['nota_comprobante'] = 'Una vez realizado el pago, favor de enviar el comprobante a ' . $v['emisor']['email'] . ($v['emisor']['telefono'] ? ' o al ' . $v['emisor']['telefono'] : '') . ' para su confirmación.';
            }
        }
        if (! empty($a['dropbox_carpeta'])) $v['dropbox']['carpeta'] = $a['dropbox_carpeta'];
        if (! $principal) {
            // Los textos de fábrica (correos, consideraciones) hablan del negocio, no de Vandu
            $cambiar = fn ($x) => is_string($x) ? str_replace(['Agencia Vandu', 'agenciavandu.com'], [$v['marca']['nombre'], $v['marca']['sitio'] ?: $v['marca']['nombre']], $x) : $x;
            $v['correo']['plantillas'] = self::mapear($v['correo']['plantillas'] ?? [], $cambiar);
            $v['consideraciones'] = self::mapear($v['consideraciones'] ?? [], $cambiar);
        }
        return $v;
    }

    private static function mapear(array $a, callable $f): array
    {
        foreach ($a as $k => $x) $a[$k] = is_array($x) ? self::mapear($x, $f) : $f($x);
        return $a;
    }
}
