<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cuenta;
use App\Models\Giro;
use App\Support\Cuentas;
use App\Support\Giros;
use App\Support\Permisos;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Tipos de empresa (giros) que se crean desde Plataforma: con qué módulos, roles y tipos de proyecto
 * arranca cada negocio nuevo. Los de fábrica (config/vandu.php) no se editan aquí.
 */
class GirosController extends Controller
{
    public function create()
    {
        return view('admin.plataforma.giro', ['giro' => null, 'datos' => [
            'nombre' => '', 'icono' => 'bi-briefcase', 'descripcion' => '', 'modulos' => [], 'origen' => 'blanco',
            'roles' => [
                ['nombre' => 'Gerente', 'descripcion' => 'Ve todo el negocio menos usuarios y permisos.', 'permisos' => ['resumen', 'ventas', 'cotizaciones', 'proyectos', 'tareas', 'clientes', 'productos', 'proveedores', 'finanzas', 'archivos']],
                ['nombre' => 'Vendedor', 'descripcion' => 'Atiende prospectos y clientes y hace cotizaciones.', 'permisos' => ['ventas', 'cotizaciones', 'clientes']],
                ['nombre' => 'Administración', 'descripcion' => 'Cobros, finanzas y datos de los clientes.', 'permisos' => ['finanzas', 'clientes', 'cotizaciones']],
            ],
        ]] + $this->comunes());
    }

    public function store(Request $request)
    {
        $d = $this->validar($request);
        $usadas = array_keys(config('vandu.giros'));
        $base = Str::limit(Str::slug($d['nombre'], '_') ?: 'giro', 34, '');
        $clave = $base;
        for ($i = 2; in_array($clave, $usadas, true) || Giro::where('clave', $clave)->exists(); $i++) $clave = $base . '_' . $i;

        Giro::create($d + [
            'clave'     => $clave,
            'proyectos' => $this->proyectosDe($d['origen']),
            'costeo'    => $d['origen'] !== 'blanco' && config("vandu.giros.{$d['origen']}.costeo")
                ? config("vandu.giros.{$d['origen']}.costeo") : Giros::costeoBase(in_array('stock', $d['modulos'], true)),
        ]);
        Giros::cargar();
        return redirect()->route('admin.plataforma', ['#giros'])->with('ok', "Tipo de empresa “{$d['nombre']}” creado. Ya lo puedes elegir al dar de alta un negocio.");
    }

    public function edit(Giro $giro)
    {
        return view('admin.plataforma.giro', ['giro' => $giro, 'datos' => [
            'nombre' => $giro->nombre, 'icono' => $giro->icono, 'descripcion' => (string) $giro->descripcion,
            'modulos' => $giro->modulos ?? [], 'origen' => 'conservar', 'roles' => $giro->roles ?? [],
        ]] + $this->comunes() + ['cuentas' => Cuenta::where('giro', $giro->clave)->count()]);
    }

    public function update(Request $request, Giro $giro)
    {
        $d = $this->validar($request, true);
        $costeo = $giro->costeo ?: Giros::costeoBase(false);
        // Si se prende el inventario, el costeo trae el costo del producto
        if (in_array('stock', $d['modulos'], true) && ! collect($costeo['componentes'] ?? [])->contains('clave', 'producto')) {
            array_unshift($costeo['componentes'], ['clave' => 'producto', 'nombre' => 'Costo del producto', 'modo' => 'unidad', 'clase' => 'tercero']);
        }
        $giro->update($d + ['costeo' => $costeo] + ($d['origen'] === 'conservar' ? [] : ['proyectos' => $this->proyectosDe($d['origen'])]));
        Giros::cargar();
        return redirect()->route('admin.plataforma', ['#giros'])->with('ok', "Tipo de empresa “{$giro->nombre}” guardado. Los negocios nuevos arrancan así; los que ya existen conservan sus roles y procesos.");
    }

    public function destroy(Giro $giro)
    {
        if ($n = Cuenta::where('giro', $giro->clave)->count()) {
            return back()->withErrors(['giro' => "No se puede eliminar: lo usan $n " . ($n === 1 ? 'negocio' : 'negocios') . '. Cámbiales el tipo primero.']);
        }
        $giro->delete();
        Giros::cargar();
        return redirect()->route('admin.plataforma', ['#giros'])->with('ok', 'Tipo de empresa eliminado.');
    }

    private function comunes(): array
    {
        $giros = collect(config('vandu.giros'))->map(fn ($g, $k) => $g['nombre'] . ' (' . count($this->proyectosDe($k)) . ' tipos)');
        return [
            'secciones' => collect(Permisos::SECCIONES)->except(['usuarios'])->all(),
            'modulosSeccion' => Permisos::MODULOS,
            'modulos' => Giros::MODULOS,
            'iconos' => Giros::ICONOS,
            'origenes' => $giros,
        ];
    }

    /** Tipos de proyecto: uno general para que cada negocio arme los suyos, o copia de otro tipo de empresa */
    private function proyectosDe(string $origen): array
    {
        if ($origen === 'blanco' || ! isset(config('vandu.giros')[$origen])) return Giros::proyectosEnBlanco();
        return config("vandu.giros.$origen.proyectos") ?? Cuentas::proyectosDeFabrica(new Cuenta(['giro' => $origen]));
    }

    private function validar(Request $request, bool $editando = false): array
    {
        $secciones = array_keys(Permisos::SECCIONES);
        if ($request->input('origen') === 'copiar') $request->merge(['origen' => (string) $request->input('origen_copia')]);
        $d = $request->validate([
            'nombre'               => 'required|string|max:80',
            'icono'                => ['required', Rule::in(Giros::ICONOS)],
            'descripcion'          => 'nullable|string|max:255',
            'modulos'              => 'array',
            'modulos.*'            => [Rule::in(array_keys(Giros::MODULOS))],
            'origen'               => ['required', Rule::in(array_merge(['blanco'], $editando ? ['conservar'] : [], array_keys(config('vandu.giros'))))],
            'roles'                => 'array|max:12',
            'roles.*.nombre'       => 'required|string|max:60',
            'roles.*.descripcion'  => 'nullable|string|max:200',
            'roles.*.permisos'     => 'array',
            'roles.*.permisos.*'   => [Rule::in($secciones)],
        ], [
            'nombre.required'         => 'Ponle nombre al tipo de empresa.',
            'roles.*.nombre.required' => 'Cada rol necesita un nombre.',
        ]);
        $modulos = array_values(array_unique($d['modulos'] ?? []));
        // Un rol no puede tener secciones de módulos que este tipo de empresa no tiene
        $roles = collect($d['roles'] ?? [])->map(fn ($r) => [
            'nombre' => trim($r['nombre']),
            'descripcion' => trim((string) ($r['descripcion'] ?? '')),
            'permisos' => array_values(array_filter(array_unique($r['permisos'] ?? []), fn ($s) => in_array($s, ['proyectos', 'cotizaciones', 'proveedores', 'archivos'], true) ? ! in_array('sin_' . $s, $modulos, true) : (! isset(Permisos::MODULOS[$s]) || in_array(Permisos::MODULOS[$s], $modulos, true)))),
        ])->filter(fn ($r) => mb_strtolower($r['nombre']) !== 'super admin')->values()->all();

        return ['nombre' => trim($d['nombre']), 'icono' => $d['icono'], 'descripcion' => $d['descripcion'] ?? null,
                'modulos' => $modulos, 'roles' => $roles, 'origen' => $d['origen']];
    }
}
