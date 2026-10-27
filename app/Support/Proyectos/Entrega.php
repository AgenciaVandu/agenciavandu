<?php

namespace App\Support\Proyectos;

use App\Models\Proyecto;
use App\Support\Cuentas;

/**
 * Dirección de entrega o instalación de un proyecto (solo negocios de productos y servicios).
 * El cliente la llena desde el enlace de su proyecto, con su ubicación en el mapa; el equipo, desde el panel.
 */
class Entrega
{
    /** Solo aplica a negocios de productos y servicios (los que tienen inventario) */
    public static function aplica(): bool
    {
        return (bool) Cuentas::actual()?->tiene('stock');
    }

    /** El cliente todavía la puede llenar o corregir */
    public static function abierta(Proyecto $p): bool
    {
        return self::aplica() && ! in_array($p->estado, ['terminado', 'cancelado'], true);
    }

    public const REGLAS = [
        'nombre' => 'required|string|max:160', 'telefono' => 'required|string|max:30',
        'direccion' => 'required|string|max:300', 'referencias' => 'nullable|string|max:300', 'notas' => 'nullable|string|max:1000',
        'lat' => 'nullable|numeric|between:-90,90', 'lng' => 'nullable|numeric|between:-180,180',
    ];

    public const MENSAJES = ['nombre.required' => 'Escribe quién recibe.', 'telefono.required' => 'Escribe un teléfono.', 'direccion.required' => 'Escribe la dirección.'];

    public static function guardar(Proyecto $p, array $d, string $por): void
    {
        $p->forceFill([
            'entrega_nombre' => trim($d['nombre']), 'entrega_telefono' => trim($d['telefono']), 'entrega_direccion' => trim($d['direccion']),
            'entrega_referencias' => ($d['referencias'] ?? null) ?: null, 'entrega_notas' => ($d['notas'] ?? null) ?: null,
            'entrega_lat' => isset($d['lat']) && $d['lat'] !== '' ? round((float) $d['lat'], 7) : null,
            'entrega_lng' => isset($d['lng']) && $d['lng'] !== '' ? round((float) $d['lng'], 7) : null,
            'entrega_datos_at' => now(), 'entrega_datos_por' => $por,
        ])->save();
    }

    /** Mensaje de WhatsApp para pedirle los datos al cliente */
    public static function mensaje(Proyecto $p): string
    {
        $nombre = \App\Support\Correos::primerNombre((string) $p->cliente?->nombre);
        return "Hola {$nombre}, para la entrega de {$p->nombre} ayúdanos con la dirección y la ubicación en el mapa aquí: {$p->url_publica}#entrega";
    }
}
