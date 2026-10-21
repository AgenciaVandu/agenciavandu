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
        'ventas'        => ['texto' => 'Ventas',                'icono' => 'bi-funnel',            'ayuda' => 'Prospectos, embudo de ventas y contactos del sitio'],
        'cotizaciones'  => ['texto' => 'Cotizaciones',          'icono' => 'bi-file-earmark-text', 'ayuda' => 'Crear, enviar y dar seguimiento a cotizaciones'],
        'proyectos'     => ['texto' => 'Proyectos',             'icono' => 'bi-kanban',            'ayuda' => 'Etapas, pagos y entregas de cada proyecto'],
        'tareas'        => ['texto' => 'Gestionar tareas',      'icono' => 'bi-check2-square',     'ayuda' => 'Crear y asignar tareas, elegir la carpeta de entrega y ver las de todo el equipo'],
        'redes'         => ['texto' => 'Redes sociales',        'icono' => 'bi-grid-3x3-gap',      'ayuda' => 'Calendario de contenido y aprobaciones de los clientes'],
        'clientes'      => ['texto' => 'Clientes',              'icono' => 'bi-people',            'ayuda' => 'Fichas, datos fiscales y contactos del sitio'],
        'unidades'      => ['texto' => 'Unidades',               'icono' => 'bi-car-front',         'ayuda' => 'Autos del lote: fichas, fotos, precios y su página para compartir'],
        'cobranza'      => ['texto' => 'Ventas a crédito y cobranza', 'icono' => 'bi-cash-coin',    'ayuda' => 'Vender unidades, registrar pagos, moratorios y el termómetro de clientes'],
        'clases'        => ['texto' => 'Agenda de clases',      'icono' => 'bi-calendar-week',     'ayuda' => 'Programar clases y su cupo, tipos de clase, ejercicios y entrenamientos'],
        'asistencia'    => ['texto' => 'Asistencia',            'icono' => 'bi-qr-code-scan',      'ayuda' => 'Escanear el QR en la entrada y pasar lista de las clases'],
        'membresias'    => ['texto' => 'Paquetes y membresías', 'icono' => 'bi-ticket-perforated', 'ayuda' => 'Crear paquetes de clases con su precio y asignarlos a los atletas'],
        'productos'     => ['texto' => 'Productos e inventario', 'icono' => 'bi-box-seam',         'ayuda' => 'Catálogo, existencias, costos, entradas y salidas, e importar desde Excel'],
        'proveedores'   => ['texto' => 'Proveedores',           'icono' => 'bi-truck',             'ayuda' => 'Proveedores, órdenes de compra, sus facturas y archivos'],
        'finanzas'      => ['texto' => 'Finanzas',              'icono' => 'bi-graph-up-arrow',    'ayuda' => 'Cobros, ingresos y exportar a Excel'],
        'archivos'      => ['texto' => 'Archivos',              'icono' => 'bi-folder2-open',      'ayuda' => 'Explorar todo el Dropbox de la agencia desde el panel'],
        'configuracion' => ['texto' => 'Configuración',         'icono' => 'bi-sliders',           'ayuda' => 'Conexión con Dropbox, tipos de proyecto y plantillas de correo'],
        'usuarios'      => ['texto' => 'Usuarios y roles',      'icono' => 'bi-person-gear',       'ayuda' => 'Invitar personas, darles rol y puesto, y cambiar permisos'],
    ];

    /** Secciones que solo existen en ciertos giros: sección => módulo del giro */
    public const MODULOS = ['redes' => 'redes', 'productos' => 'stock', 'proyectos' => 'proyectos', 'unidades' => 'autos', 'cobranza' => 'autos',
                               'clases' => 'fitness', 'asistencia' => 'fitness', 'membresias' => 'fitness', 'cotizaciones' => 'cotizaciones', 'proveedores' => 'proveedores', 'archivos' => 'archivos'];

    /** Secciones disponibles para la cuenta activa (para armar roles) */
    public static function disponibles(): array
    {
        $c = Cuentas::actual();
        return array_filter(self::SECCIONES, fn ($s, $k) => ! isset(self::MODULOS[$k]) || ($c && $c->tiene(self::MODULOS[$k])), ARRAY_FILTER_USE_BOTH);
    }

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
        // Quien cotiza consulta el catálogo y elige productos; administrarlo (costos, existencias, importar) es de Productos
        ['admin.productos.index', ['productos', 'cotizaciones']],
        ['admin.productos.buscar', ['productos', 'cotizaciones']],
        ['admin.productos.show', ['productos', 'cotizaciones']],
        ['admin.productos.*', 'productos'],
        ['admin.ventas.*', 'ventas'],
        // Lote de autos: quien vende consulta las unidades; venderlas y cobrar es de Cobranza
        ['admin.unidades.index', ['unidades', 'cobranza']],
        ['admin.unidades.show', ['unidades', 'cobranza']],
        ['admin.unidades.*', 'unidades'],
        ['admin.creditos.*', 'cobranza'],
        ['admin.sucursales*', 'configuracion'],
        ['admin.credito-ajustes*', 'configuracion'],
        // Gimnasio: el entrenador ve la agenda y sus clases; programarlas y cambiar cupos es de "clases"
        ['admin.clases.index', ['clases', 'asistencia']],
        ['admin.clases.show', ['clases', 'asistencia']],
        ['admin.clases.*', 'clases'],
        ['admin.clase-tipos*', 'clases'],
        ['admin.ejercicios*', 'clases'],
        ['admin.reservaciones.asistencia', ['asistencia', 'clases']],
        ['admin.reservaciones.*', ['clases', 'asistencia', 'membresias']],
        ['admin.escanear*', 'asistencia'],
        ['admin.paquetes*', 'membresias'],
        ['admin.membresias*', 'membresias'],
        ['admin.atletas.foto', ['clientes', 'membresias', 'asistencia', 'clases']],   // para reconocerlo al escanear
        ['admin.atletas.*', ['clientes', 'membresias']],
        ['admin.fitness-reportes*', ['clases', 'finanzas']],
        ['admin.fitness-ajustes*', 'configuracion'],
        ['admin.clientes.*', 'clientes'],
        ['admin.proveedores.*', 'proveedores'],
        ['admin.ordenes.*', 'proveedores'],
        ['admin.correos.plantillas*', 'configuracion'],
        ['admin.correos.*', ['clientes', 'cotizaciones', 'proyectos']],
        ['admin.finanzas*', 'finanzas'],
        ['admin.redes*', 'redes'],
        ['admin.archivos*', 'archivos'],
        ['admin.tipos*', 'configuracion'],
        ['admin.costeo*', 'configuracion'],
        ['admin.dropbox.explorar', ['proyectos', 'redes', 'archivos', 'tareas', 'configuracion']],
        ['admin.dropbox.token', ['proyectos', 'redes', 'configuracion']],
        ['admin.dropbox.abrir', ['proyectos', 'archivos', 'configuracion']],
        ['admin.dropbox*', 'configuracion'],
        ['admin.usuarios*', 'usuarios'],
        ['admin.roles*', 'usuarios'],
        ['admin.buscar', '*'],           // cada quien solo ve resultados de sus secciones
        ['admin.tareas*', '*'],          // el controlador distingue "mis tareas" de gestionar
        ['admin.notificaciones*', '*'],
        ['admin.cuenta*', '*'],
        ['admin.negocio*', 'configuracion'],
        ['admin.plataforma*', '@plataforma'],   // solo quien administra la plataforma
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
        $s = self::deRuta($nombre);
        if ($s === ['@plataforma']) return (bool) $u->plataforma;
        if ($s !== null && $s !== ['*'] && ! array_filter($s, fn ($x) => ! isset(self::MODULOS[$x]) || Cuentas::actual()?->tiene(self::MODULOS[$x]))) return false;
        if ($u->esSuperAdmin()) return true;
        if ($s === null) return false;
        if ($s === ['*']) return true;
        foreach ($s as $seccion) if ($u->puede($seccion)) return true;
        return false;
    }

    /** Primera página a la que puede entrar (para el inicio) */
    public static function inicio(User $u): string
    {
        foreach (['resumen' => 'admin.resumen', 'clases' => 'admin.clases.index', 'asistencia' => 'admin.clases.index', 'proyectos' => 'admin.proyectos.index', 'unidades' => 'admin.unidades.index', 'cobranza' => 'admin.creditos.index', 'cotizaciones' => 'admin.presupuestos.index', 'redes' => 'admin.redes'] as $s => $r) {
            if ($u->puede($s)) return route($r);
        }
        return route('admin.tareas.index');
    }
}
