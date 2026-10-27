<?php

namespace App\Support;

use App\Models\User;

/**
 * Configuración en un solo lugar: las páginas de ajustes del negocio, agrupadas por tema y solo las que aplican
 * a su tipo de negocio y al rol de quien entra. El menú lateral muestra un solo enlace "Configuración".
 */
class Configuracion
{
    /** @return array<string, array<int, array{titulo: string, ayuda: string, icono: string, url: string, activo: string, estado?: ?string}>> */
    public static function grupos(?User $u): array
    {
        $c = Cuentas::actual();
        if (! $u || ! $c) return [];
        $conf = $u->puede('configuracion');
        $gym = $c->tiene('fitness');
        $t = fn (bool $si, string $titulo, string $ayuda, string $icono, string $ruta, string $activo, ?string $estado = null) => $si
            ? ['titulo' => $titulo, 'ayuda' => $ayuda, 'icono' => $icono, 'url' => route($ruta), 'activo' => $activo, 'estado' => $estado] : null;

        $grupos = [
            'Tu negocio' => [
                $t($conf, 'Mi negocio', 'Nombre, logo, ícono de la app y datos de contacto y de pago', 'bi-shop', 'admin.negocio', 'admin.negocio'),
                $t($conf, 'Correo de envío', 'Desde qué correo salen tus cotizaciones y avisos', 'bi-envelope-at', 'admin.negocio.correo', 'admin.negocio.correo*'),
                $t($conf && ($gym || $c->tiene('autos')), 'Sucursales', 'Tus locales y su dirección', 'bi-geo-alt', 'admin.sucursales', 'admin.sucursales*'),
            ],
            'Documentos y correos' => [
                $t($conf && $c->tiene('cotizaciones'), 'Diseño de cotización', 'Plantilla, colores, letra y hoja membretada', 'bi-file-earmark-richtext', 'admin.negocio.documento', 'admin.negocio.documento*'),
                $t($conf, 'Plantillas de correo', 'Los textos de los correos que mandas a tus clientes', 'bi-envelope-paper', 'admin.correos.plantillas', 'admin.correos.plantillas*'),
            ],
            'Cómo trabajas' => [
                $t($conf && $c->tiene('stock') && $u->puede('vender'), 'Ventas y envíos', 'Tu ubicación y lo que cobras por envío', 'bi-truck', 'admin.pedidos.ajustes', 'admin.pedidos.ajustes*'),
                $t($conf && $c->tiene('proyectos'), 'Tipos de proyecto', 'Etapas y pagos de cada tipo de trabajo', 'bi-diagram-3', 'admin.tipos', 'admin.tipos*'),
                $t($conf && $c->tiene('cotizaciones'), 'Costeo', 'Costos base para calcular tus precios', 'bi-calculator', 'admin.costeo', 'admin.costeo*'),
                $t($conf && $gym, 'Reglas del gimnasio', 'Reservaciones, cancelaciones y faltas', 'bi-sliders2', 'admin.fitness-ajustes', 'admin.fitness-ajustes*'),
                $t($conf && $gym, 'Torniquetes', 'Acceso en la puerta con el QR del atleta', 'bi-door-open', 'admin.torniquetes', 'admin.torniquetes*'),
                $t($conf && $c->tiene('autos'), 'Crédito y cobranza', 'Intereses, recargos y termómetro de clientes', 'bi-percent', 'admin.credito-ajustes', 'admin.credito-ajustes*'),
            ],
            'Conexiones' => [
                $t($conf && $c->tiene('archivos'), 'Dropbox', 'Dónde se guardan los archivos de tus proyectos', 'bi-dropbox', 'admin.dropbox', 'admin.dropbox*',
                    rescue(fn () => \App\Support\Dropbox\Dropbox::conectado(), false, false) ? 'Conectado' : 'Sin conectar'),
            ],
            'Tu cuenta' => [
                $t($u->puede('usuarios'), 'Usuarios', 'Tu equipo y lo que cada quien puede ver', 'bi-person-gear', 'admin.usuarios', 'admin.usuarios*'),
                $t((bool) \App\Support\Licencias\Estado::de($c)->licencia && $conf, 'Mi plan', 'Lo que tienes contratado, tus pagos y recibos', 'bi-patch-check', 'admin.mi-plan', 'admin.mi-plan*'),
                $t(true, 'Mi perfil', 'Tu nombre, tu correo y tu contraseña', 'bi-person-circle', 'admin.cuenta', 'admin.cuenta*'),
                $t(true, 'Notificaciones', 'Qué avisos te llegan a este dispositivo', 'bi-bell', 'admin.notificaciones', 'admin.notificaciones*'),
            ],
        ];
        return array_filter(array_map(fn ($g) => array_values(array_filter($g)), $grupos));
    }

    /** Las páginas de ajustes del negocio (sin "Tu cuenta"), para saber si el enlace del menú va activo */
    public static function patrones(?User $u): array
    {
        $g = self::grupos($u);
        unset($g['Tu cuenta']);
        return array_merge(['admin.configuracion'], collect($g)->flatten(1)->pluck('activo')->all());
    }

    /** Hay al menos una página del negocio que esta persona puede configurar */
    public static function tieneAlgo(?User $u): bool
    {
        $g = self::grupos($u);
        unset($g['Tu cuenta']);
        return ! empty($g);
    }
}
