<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Secciones del panel que se pueden dar o quitar a cada rol, y qué rutas pertenecen a cada una.
 * "Mis tareas", "Notificaciones" y "Mi cuenta" las tiene todo el equipo.
 */
class Permisos
{
    public const SECCIONES = [
        'resumen'       => ['texto' => 'Resumen',               'icono' => 'bi-grid-1x2',          'ayuda' => 'Cifras del mes, cobros y pendientes del día'],
        'cotizaciones'  => ['texto' => 'Cotizaciones',          'icono' => 'bi-file-earmark-text', 'ayuda' => 'Crear, enviar y dar seguimiento a cotizaciones'],
        'proyectos'     => ['texto' => 'Proyectos',             'icono' => 'bi-kanban',            'ayuda' => 'Etapas, pagos y entregas de cada proyecto'],
        'tareas'        => ['texto' => 'Gestionar tareas',      'icono' => 'bi-check2-square',     'ayuda' => 'Crear y asignar tareas, elegir la carpeta de entrega y ver las de todo el equipo'],
        'redes'         => ['texto' => 'Redes sociales',        'icono' => 'bi-grid-3x3-gap',      'ayuda' => 'Calendario de contenido y aprobaciones de los clientes'],
        'clientes'      => ['texto' => 'Clientes',              'icono' => 'bi-people',            'ayuda' => 'Fichas, datos fiscales y contactos del sitio'],
        'finanzas'      => ['texto' => 'Finanzas',              'icono' => 'bi-graph-up-arrow',    'ayuda' => 'Cobros, ingresos y exportar a Excel'],
        'archivos'      => ['texto' => 'Archivos',              'icono' => 'bi-folder2-open',      'ayuda' => 'Explorar todo el Dropbox de la agencia desde el panel'],
        'configuracion' => ['texto' => 'Configuración',         'icono' => 'bi-sliders',           'ayuda' => 'Conexión con Dropbox, tipos de proyecto y plantillas de correo'],
        'usuarios'      => ['texto' => 'Usuarios y roles',      'icono' => 'bi-person-gear',       'ayuda' => 'Invitar personas, darles rol y puesto, y cambiar permisos'],
    ];

    /**
     * Ruta → sección(es). Basta con tener una. El primer patrón que coincide gana.
     * "*" = cualquiera del equipo con sesión.
     */
    public const RUTAS = [
        ['admin.resumen', 'resumen'],
        ['admin.proyectos.create', 'proyectos'],
        ['admin.proyectos.store', 'proyectos'],
        ['admin.proyectos.*', 'proyectos'],
        ['admin.presupuestos.*', 'cotizaciones'],
        ['admin.clientes.*', 'clientes'],
        ['admin.correos.plantillas*', 'configuracion'],
        ['admin.correos.*', ['clientes', 'cotizaciones', 'proyectos']],
        ['admin.finanzas*', 'finanzas'],
        ['admin.redes*', 'redes'],
        ['admin.archivos*', 'archivos'],
        ['admin.tipos*', 'configuracion'],
        ['admin.dropbox.explorar', ['proyectos', 'redes', 'archivos', 'tareas', 'configuracion']],
        ['admin.dropbox.token', ['proyectos', 'redes', 'configuracion']],
        ['admin.dropbox.abrir', ['proyectos', 'archivos', 'configuracion']],
        ['admin.dropbox*', 'configuracion'],
        ['admin.usuarios*', 'usuarios'],
        ['admin.roles*', 'usuarios'],
        ['admin.tareas*', '*'],          // el controlador distingue "mis tareas" de gestionar
        ['admin.notificaciones*', '*'],
        ['admin.cuenta*', '*'],
    ];

    /** Secciones que pide una ruta; null = no está mapeada (solo super admin) */
    public static function deRuta(?string $nombre): ?array
    {
        if (! $nombre) return null;
        foreach (self::RUTAS as [$patron, $secciones]) {
            if (Str::is($patron, $nombre)) return (array) $secciones;
        }
        return null;
    }

    public static function puedeRuta(User $u, ?string $nombre): bool
    {
        if ($u->esSuperAdmin()) return true;
        $s = self::deRuta($nombre);
        if ($s === null) return false;
        if ($s === ['*']) return true;
        foreach ($s as $seccion) if ($u->puede($seccion)) return true;
        return false;
    }

    /** Primera página a la que puede entrar (para el inicio) */
    public static function inicio(User $u): string
    {
        foreach (['resumen' => 'admin.resumen', 'proyectos' => 'admin.proyectos.index', 'cotizaciones' => 'admin.presupuestos.index', 'redes' => 'admin.redes'] as $s => $r) {
            if ($u->puede($s)) return route($r);
        }
        return route('admin.tareas.index');
    }
}
