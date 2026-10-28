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
        'ventas'        => ['texto' => 'Prospectos',            'icono' => 'bi-funnel',            'ayuda' => 'Embudo de prospectos (quien pregunta pero aún no compra) y contactos del sitio'],
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
        'vender'        => ['texto' => 'Vender y pedidos',      'icono' => 'bi-bag',               'ayuda' => 'Registrar ventas de mostrador y pedidos con envío, cobrar abonos y cambiar el estado de la entrega'],
        'proveedores'   => ['texto' => 'Proveedores',           'icono' => 'bi-truck',             'ayuda' => 'Proveedores, órdenes de compra, sus facturas y archivos'],
        'lealtad'       => ['texto' => 'Programa de lealtad',   'icono' => 'bi-gift',              'ayuda' => 'Crear campañas con su vigencia, meta de visitas y premios'],
        'canjes'        => ['texto' => 'Escanear visitas y premios', 'icono' => 'bi-qr-code-scan', 'ayuda' => 'Escanear el QR del comensal para sumar su visita, unirlo al programa y entregarle premios'],
        'comensales'    => ['texto' => 'Comensales',            'icono' => 'bi-person-hearts',     'ayuda' => 'Ver a los comensales del programa con su nombre, teléfono, correo y visitas'],
        'gastos'        => ['texto' => 'Subir gastos',          'icono' => 'bi-receipt',           'ayuda' => 'Registrar gastos con foto del ticket, XML de factura o a mano. Ve solo los suyos; ver los de todos es de Finanzas'],
        'finanzas'      => ['texto' => 'Finanzas',              'icono' => 'bi-graph-up-arrow',    'ayuda' => 'Cobros, ingresos y exportar a Excel'],
        'archivos'      => ['texto' => 'Archivos',              'icono' => 'bi-folder2-open',      'ayuda' => 'Explorar todo el Dropbox de la agencia desde el panel'],
        'configuracion' => ['texto' => 'Configuración',         'icono' => 'bi-sliders',           'ayuda' => 'Conexión con Dropbox, tipos de proyecto y plantillas de correo'],
        'usuarios'      => ['texto' => 'Usuarios y roles',      'icono' => 'bi-person-gear',       'ayuda' => 'Invitar personas, darles rol y puesto, y cambiar permisos'],
    ];

    /** Secciones que solo existen en ciertos giros: sección => módulo del giro */
    public const MODULOS = ['redes' => 'redes', 'productos' => 'stock', 'proyectos' => 'proyectos', 'unidades' => 'autos', 'cobranza' => 'autos',
                               'clases' => 'fitness', 'asistencia' => 'fitness', 'membresias' => 'fitness', 'cotizaciones' => 'cotizaciones', 'proveedores' => 'proveedores', 'archivos' => 'archivos', 'tareas' => 'tareas',
                               'clientes' => 'clientes', 'ventas' => 'ventas', 'finanzas' => 'finanzas', 'lealtad' => 'lealtad', 'canjes' => 'lealtad', 'comensales' => 'lealtad', 'gastos' => 'gastos', 'vender' => 'stock'];

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
        ['admin.resumen', '*'], // cada quien tiene su Inicio: el del negocio o "Mi día"
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
        // Restaurante: caja escanea; el gerente arma campañas y ve comensales
        ['admin.lealtad.escanear*', 'canjes'],
        ['admin.lealtad.qr', ['lealtad', 'canjes']],
        ['admin.lealtad.*', 'lealtad'],
        ['admin.comensales*', 'comensales'],
        ['admin.seguimiento*', 'ventas'],     // gimnasio: interesados y renovaciones
        // Lote de autos: quien vende consulta las unidades; venderlas y cobrar es de Cobranza
        ['admin.unidades.index', ['unidades', 'cobranza']],
        ['admin.unidades.show', ['unidades', 'cobranza']],
        ['admin.unidades.*', 'unidades'],
        ['admin.creditos.*', 'cobranza'],
        ['admin.sucursales*', 'configuracion'],
        ['admin.mi-plan*', 'configuracion'],
        ['admin.funciones*', 'configuracion'],
        ['admin.ejemplos*', 'configuracion'],
        ['admin.cobrar', ['finanzas', 'vender', 'proyectos', 'cobranza']],
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
        ['admin.torniquetes*', 'configuracion'],
        ['admin.clientes.*', 'clientes'],
        ['admin.proveedores.*', 'proveedores'],
        ['admin.ordenes.*', 'proveedores'],
        ['admin.correos.plantillas*', 'configuracion'],
        ['admin.correos.*', ['clientes', 'cotizaciones', 'proyectos']],
        ['admin.documentos.constancia', ['clientes', 'proveedores']],
        ['admin.pedidos.ajustes*', 'configuracion'],
        ['admin.pedidos*', 'vender'],
        ['admin.finanzas*', 'finanzas'],
        ['admin.gastos*', ['gastos', 'finanzas']],   // quien no ve Finanzas solo ve sus propios gastos
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
        ['admin.configuracion', '*'],
        ['admin.negocio*', 'configuracion'],
        ['admin.plataforma*', '@plataforma'],   // solo quien administra la plataforma
    ];

    /** Ajustes que solo tienen sentido con cierto módulo (un gimnasio no costea cotizaciones ni tiene tipos de proyecto) */
    public const RUTAS_MODULO = [
        ['admin.costeo*', 'cotizaciones'],
        ['admin.negocio.documento*', 'cotizaciones'],
        ['admin.tipos*', 'proyectos'],
        ['admin.tareas*', 'tareas'],
    ];

    /** La ruta es de una sección que no viene con el tipo de negocio (no es cosa del rol) */
    public static function faltaModulo(?string $nombre): bool
    {
        if (! $nombre) return false;
        $c = Cuentas::actual();
        foreach (self::RUTAS_MODULO as [$patron, $modulo]) {
            if (Str::is($patron, $nombre) && ! $c?->tiene($modulo)) return true;
        }
        $s = self::deRuta($nombre);
        return $s !== null && $s !== ['*'] && $s !== ['@plataforma'] && ! array_filter($s, fn ($x) => ! isset(self::MODULOS[$x]) || $c?->tiene(self::MODULOS[$x]));
    }

    /** Si la ruta es de una función que el negocio apagó, su nombre ("Tareas") */
    public static function apagadaDeRuta(?string $nombre): ?string
    {
        $c = Cuentas::actual();
        if (! $nombre || ! $c) return null;
        $modulos = collect(self::RUTAS_MODULO)->filter(fn ($r) => Str::is($r[0], $nombre))->pluck(1)
            ->merge(collect(self::deRuta($nombre) ?? [])->map(fn ($s) => self::MODULOS[$s] ?? null)->filter());
        foreach ($modulos as $m) if ($c->apagado($m)) return \App\Models\Cuenta::OPCIONALES[$m][0];
        return null;
    }

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
        // Lo que no viene con el tipo de negocio no se abre, ni para el super admin
        if (self::faltaModulo($nombre)) return false;
        if ($u->esSuperAdmin()) return true;
        if ($s === null) return false;
        if ($s === ['*']) return true;
        foreach ($s as $seccion) if ($u->puede($seccion)) return true;
        return false;
    }

    /** Primera página a la que puede entrar (para el inicio) */
    public static function inicio(User $u): string
    {
        foreach (['resumen' => 'admin.resumen', 'vender' => 'admin.pedidos', 'canjes' => 'admin.lealtad.escanear', 'clases' => 'admin.clases.index', 'asistencia' => 'admin.clases.index', 'proyectos' => 'admin.proyectos.index', 'unidades' => 'admin.unidades.index', 'cobranza' => 'admin.creditos.index', 'cotizaciones' => 'admin.presupuestos.index', 'redes' => 'admin.redes'] as $s => $r) {
            if ($u->puede($s)) return route($r);
        }
        return Cuentas::actual()?->tiene('tareas') === false ? route('admin.cuenta') : route('admin.tareas.index');
    }
}
