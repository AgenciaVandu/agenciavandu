<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Proyecto;
use App\Models\ProyectoTipo;
use App\Support\TiposProyecto;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** La agencia define sus tipos de proyecto: etapas, duración estimada y pagos */
class TiposProyectoController extends Controller
{
    public function index(Request $request)
    {
        $tipos = config('vandu.proyectos');
        $clave = isset($tipos[$request->query('t')]) ? $request->query('t') : array_key_first($tipos);

        return view('admin.tipos-proyecto', [
            'tipos'   => $tipos,
            'clave'   => $clave,
            'tipo'    => $tipos[$clave],
            'fabrica' => array_key_exists($clave, TiposProyecto::fabrica()),
            'usos'    => Proyecto::where('tipo', $clave)->count(),
            'iconos'  => TiposProyecto::ICONOS,
        ]);
    }

    public function store()
    {
        $usadas = array_keys(config('vandu.proyectos'));
        $clave = TiposProyecto::clave('servicio-' . Str::random(4), $usadas);
        ProyectoTipo::create([
            'clave' => $clave, 'propio' => true, 'nombre' => 'Servicio nuevo', 'icono' => 'bi-kanban',
            'etapas' => [['clave' => 'inicio', 'nombre' => 'Inicio', 'dias' => 3, 'descripcion' => ''], ['clave' => 'entrega', 'nombre' => 'Entrega', 'dias' => 1, 'fecha' => true, 'descripcion' => '']],
            'pagos'  => [['clave' => 'anticipo', 'concepto' => 'Anticipo', 'porcentaje' => 50, 'antes_de' => 'inicio'], ['clave' => 'saldo', 'concepto' => 'Saldo', 'porcentaje' => 50, 'antes_de' => 'entrega']],
            'opciones' => [],
        ]);
        TiposProyecto::aplicar();
        return redirect()->route('admin.tipos', ['t' => $clave])->with('ok', 'Tipo creado. Ponle nombre y arma sus etapas.');
    }

    public function update(Request $request, string $clave)
    {
        abort_unless(isset(config('vandu.proyectos')[$clave]), 404);
        $esFabrica = array_key_exists($clave, TiposProyecto::fabrica());
        $d = json_decode((string) $request->input('datos'), true);
        if (! is_array($d)) throw ValidationException::withMessages(['datos' => 'No se recibieron los datos.']);

        $nombre = trim((string) ($d['nombre'] ?? ''));
        $errores = [];
        if ($nombre === '') $errores['nombre'] = 'Ponle un nombre al tipo.';
        $icono = in_array($d['icono'] ?? '', TiposProyecto::ICONOS, true) ? $d['icono'] : 'bi-kanban';

        // Etapas: nombre obligatorio, días 1–365, clave única (las nuevas se generan del nombre)
        $etapas = [];
        $usadas = [];
        $mapa = []; // clave temporal → clave final (para las condiciones de pago)
        foreach (array_values($d['etapas'] ?? []) as $i => $e) {
            $n = trim((string) ($e['nombre'] ?? ''));
            if ($n === '') { $errores["etapas.$i"] = 'Cada etapa necesita un nombre.'; continue; }
            $k = preg_match('/^[a-z0-9-]{1,40}$/', (string) ($e['clave'] ?? '')) && ! in_array($e['clave'], $usadas, true) ? $e['clave'] : TiposProyecto::clave($n, $usadas);
            $usadas[] = $k;
            $mapa[(string) ($e['ref'] ?? '') ?: ((string) ($e['clave'] ?? '') ?: 'nueva-' . $i)] = $k;
            $etapas[] = array_filter([
                'clave' => $k, 'nombre' => Str::limit($n, 120, ''), 'dias' => max(1, min(365, (int) ($e['dias'] ?? 1))),
                'descripcion' => Str::limit(trim((string) ($e['descripcion'] ?? '')), 300, ''), 'fecha' => ! empty($e['fecha']) ?: null,
            ], fn ($v) => $v !== null && $v !== '');
        }
        if (! $etapas) $errores['etapas'] = 'El tipo necesita al menos una etapa.';

        // Pagos: porcentajes que sumen 100 y que se pidan antes de una etapa existente (o sin condición)
        $pagos = [];
        $usadasP = [];
        foreach (array_values($d['pagos'] ?? []) as $i => $p) {
            $c = trim((string) ($p['concepto'] ?? ''));
            if ($c === '') { $errores["pagos.$i"] = 'Cada pago necesita un concepto.'; continue; }
            $k = TiposProyecto::clave($c, $usadasP);
            $usadasP[] = $k;
            $antes = $p['antes_de'] ?? null;
            $antes = $antes !== null && $antes !== '' ? ($mapa[$antes] ?? (in_array($antes, $usadas, true) ? $antes : null)) : null;
            $pagos[] = ['clave' => $k, 'concepto' => Str::limit($c, 80, ''), 'porcentaje' => round((float) ($p['porcentaje'] ?? 0), 2), 'antes_de' => $antes];
        }
        $suma = round(array_sum(array_column($pagos, 'porcentaje')), 2);
        if ($pagos && abs($suma - 100) > 0.01) $errores['pagos'] = "Los pagos suman {$suma}%; deben sumar 100%.";
        if (! $pagos) $errores['pagos'] = 'Agrega al menos un pago (por ejemplo, 100% al inicio).';
        if ($errores) throw ValidationException::withMessages($errores);

        $opciones = $esFabrica ? null : array_filter([
            'galeria' => ! empty($d['galeria']) ?: null,
            'costeo'  => ! empty($d['costeo']) ?: null,
        ]);
        ProyectoTipo::updateOrCreate(['clave' => $clave], [
            'propio' => ! $esFabrica, 'nombre' => Str::limit($nombre, 80, ''), 'icono' => $icono,
            'etapas' => $etapas, 'pagos' => $pagos, 'opciones' => $opciones,
        ]);
        TiposProyecto::aplicar();

        return redirect()->route('admin.tipos', ['t' => $clave])->with('ok', "“{$nombre}” guardado. Se usa en los proyectos nuevos.");
    }

    public function restaurar(string $clave)
    {
        abort_unless(array_key_exists($clave, TiposProyecto::fabrica()), 404);
        ProyectoTipo::where('clave', $clave)->delete();
        TiposProyecto::aplicar();
        return redirect()->route('admin.tipos', ['t' => $clave])->with('ok', 'Se restauraron las etapas originales.');
    }

    public function destroy(string $clave)
    {
        $t = ProyectoTipo::where('clave', $clave)->where('propio', true)->firstOrFail();
        if ($n = Proyecto::where('tipo', $clave)->count()) {
            return back()->withErrors(['tipo' => "No se puede eliminar: hay $n " . ($n === 1 ? 'proyecto' : 'proyectos') . ' de este tipo.']);
        }
        $t->delete();
        TiposProyecto::aplicar();
        return redirect()->route('admin.tipos')->with('ok', "Se eliminó “{$t->nombre}”.");
    }
}
