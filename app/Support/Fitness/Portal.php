<?php

namespace App\Support\Fitness;

use App\Models\Cuenta;
use App\Support\Cuentas;

/** Dirección del portal del atleta: el dominio propio del negocio (/atleta) o /atleta/{slug} en la plataforma */
class Portal
{
    /** Palabras que no pueden ser el nombre corto de un negocio (son páginas del portal) */
    public const RESERVADAS = ['inicio', 'clases', 'credencial', 'historial', 'perfil', 'salir', 'entrar', 'acceso', 'olvide', 'avisos', 'app', 'sw.js', 'manifest.webmanifest', 'reservaciones', 'registro', 'qr', 'ejemplos'];   // también rutas de /lealtad

    public static function url(?Cuenta $c = null): string
    {
        $c ??= Cuentas::actual();
        if ($c?->dominio) return 'https://' . $c->dominio . '/atleta';
        return route('atleta.entrar', $c?->slug);
    }
}
