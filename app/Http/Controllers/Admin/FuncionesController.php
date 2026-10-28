<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cuenta;
use App\Support\Cuentas;
use Illuminate\Http\Request;

/** Configuración → Funciones: el dueño prende o apaga partes del panel que no usa. Apagar oculta, no borra. */
class FuncionesController extends Controller
{
    /** Las que trae su tipo de negocio, con cuánto tiene guardado en cada una */
    public static function lista(Cuenta $c): array
    {
        $cuantos = [
            'ventas'       => fn () => \App\Models\Prospecto::count(),
            'cotizaciones' => fn () => \App\Models\Presupuesto::count(),
            'proyectos'    => fn () => \App\Models\Proyecto::count(),
            'tareas'       => fn () => \App\Models\Tarea::count(),
            'proveedores'  => fn () => \App\Models\Proveedor::count(),
            'archivos'     => fn () => 0,
            'redes'        => fn () => \App\Models\RedesPost::count(),
        ];
        $palabras = ['ventas' => ['prospecto', 'prospectos'], 'cotizaciones' => ['cotización', 'cotizaciones'], 'proyectos' => ['proyecto', 'proyectos'],
            'tareas' => ['tarea', 'tareas'], 'proveedores' => ['proveedor', 'proveedores'], 'redes' => ['publicación', 'publicaciones']];
        $lista = [];
        foreach (Cuenta::OPCIONALES as $k => [$titulo, $ayuda, $icono]) {
            if (! $c->incluye($k)) continue;
            $n = rescue(fn () => ($cuantos[$k])(), 0, false);
            $lista[$k] = ['titulo' => $titulo, 'ayuda' => $ayuda, 'icono' => $icono, 'prendida' => ! $c->apagado($k), 'n' => $n,
                'datos' => $n && isset($palabras[$k]) ? $n . ' ' . ($n === 1 ? $palabras[$k][0] : $palabras[$k][1]) : null];
        }
        return $lista;
    }

    public function index()
    {
        $c = Cuentas::actual();
        return view('admin.funciones', ['funciones' => self::lista($c), 'cuenta' => $c]);
    }

    public function update(Request $request)
    {
        $c = Cuentas::actual();
        $prendidas = array_keys(array_filter((array) $request->input('funciones', [])));
        $apagados = array_values(array_filter(array_keys(self::lista($c)), fn ($k) => ! in_array($k, $prendidas, true)));
        $a = $c->ajustes ?? [];
        $a['apagados'] = $apagados;
        $c->update(['ajustes' => $a]);
        return redirect()->route('admin.funciones')->with('ok', 'Listo: tu panel ya muestra solo lo que usas.');
    }
}
