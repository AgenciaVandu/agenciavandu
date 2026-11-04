<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Ayudantes para el inicio y la barra del sitio: arman los enlaces que vienen de config/inicio.php
 * para que funcionen igual desde el inicio que desde cualquier otra página.
 */
class Inicio
{
    /** Enlace directo a WhatsApp (el sitio lo intercepta y abre el formulario que guarda al prospecto). */
    public static function whatsapp(): string
    {
        return 'https://wa.me/' . preg_replace('/\D/', '', (string) config('vandu.whatsapp', '529991460310'));
    }

    /** Una sección del inicio: "#nerby" estando en el inicio y "/#nerby" desde cualquier otra página. */
    public static function ancla(string $id): string
    {
        return request()->routeIs('home') ? '#' . $id : route('home') . '#' . $id;
    }

    /**
     * Convierte un "destino" de config/inicio.php en href y atributos extra.
     *
     * @return array{href: string, servicio: ?string}
     */
    public static function enlace(array $destino): array
    {
        return match (true) {
            isset($destino['ruta']) => ['href' => route($destino['ruta']), 'servicio' => null],
            isset($destino['ancla']) => ['href' => self::ancla($destino['ancla']), 'servicio' => null],
            isset($destino['whatsapp']) => ['href' => self::whatsapp(), 'servicio' => $destino['whatsapp']],
            default => ['href' => '#', 'servicio' => null],
        };
    }

    /** Número con dos cifras: 1 → "01". */
    public static function n2(int $i): string
    {
        return str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Lo que encuentra el buscador de la barra (tecla /): texto, href, tipo, servicio y texto para filtrar.
     *
     * @return array<int, array{texto: string, href: string, tipo: string, servicio: ?string, buscar: string}>
     */
    public static function buscable(): array
    {
        $items = [
            ['Servicios', ['ancla' => 'servicios'], 'Sección'],
            ['Nerby', ['ancla' => 'nerby'], 'Sección'],
            ['Foto, video e impresión', ['ancla' => 'foto'], 'Sección'],
            ['Cómo trabajamos', ['ancla' => 'proceso'], 'Sección'],
            ['Preguntas frecuentes', ['ancla' => 'preguntas'], 'Sección'],
        ];
        foreach (config('inicio.servicios') as $s) {
            $items[] = [$s['titulo'], $s['destino'], 'Servicio'];
        }
        $items[] = ['Cotizar mi proyecto', ['ancla' => 'contacto'], 'Contacto'];
        $items[] = ['Escribir por WhatsApp', ['whatsapp' => ''], 'WhatsApp'];
        $items[] = ['Aviso de privacidad', ['ruta' => 'privacidad'], 'Página'];

        return array_map(function ($i) {
            [$texto, $destino, $tipo] = $i;
            $e = self::enlace($destino);

            return [
                'texto' => $texto,
                'href' => $e['href'],
                'tipo' => $tipo,
                'servicio' => $e['servicio'] ?: null,
                'buscar' => Str::ascii(mb_strtolower($texto . ' ' . $tipo)),
            ];
        }, $items);
    }
}
