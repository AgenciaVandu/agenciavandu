<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Support\Cuentas;
use App\Support\ImportarProductos;
use App\Support\Inventario;
use App\Support\LectorHoja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Importar el catálogo desde Excel o CSV en tres pasos: subir, revisar qué columna es qué, aplicar.
 * La relación de columnas se guarda por negocio, así la siguiente actualización es subir y confirmar.
 */
class ProductoImportController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            abort_unless(Inventario::activo(), 404);
            return $next($request);
        });
    }

    public function create()
    {
        $guardado = Cuentas::actual()?->ajuste('catalogo.importacion');
        return view('admin.productos.importar', ['guardado' => $guardado, 'hay' => Producto::count()]);
    }

    /** Paso 2: lee el archivo y propone qué columna es cada dato */
    public function revisar(Request $request)
    {
        $request->validate(['archivo' => 'required|file|max:10240'], ['archivo.required' => 'Elige el archivo de tu catálogo.', 'archivo.max' => 'El archivo pesa más de 10 MB.']);
        $archivo = $request->file('archivo');

        try {
            $filas = LectorHoja::leer($archivo->getRealPath(), $archivo->getClientOriginalExtension() ?: $archivo->extension());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['archivo' => $e->getMessage()]);
        }
        if (count($filas) < 2) return back()->withErrors(['archivo' => 'El archivo no tiene productos: necesitamos una fila de títulos y al menos un producto abajo.']);

        // El encabezado es la primera fila que tiene al menos dos textos (algunos Excel traen un título arriba)
        $inicio = 0;
        foreach (array_slice($filas, 0, 10) as $i => $f) {
            if (count(array_filter($f, fn ($v) => $v !== '' && ! is_numeric($v))) >= 2) { $inicio = $i; break; }
        }
        $encabezados = array_map(fn ($h, $i) => $h !== '' ? $h : 'Columna ' . self::letra($i), $filas[$inicio], array_keys($filas[$inicio]));
        $datos = array_values(array_slice($filas, $inicio + 1));

        $token = Str::random(24);
        Storage::disk('local')->put($this->ruta($token), json_encode(['encabezados' => $encabezados, 'filas' => $datos, 'nombre' => $archivo->getClientOriginalName()], JSON_UNESCAPED_UNICODE));
        $this->limpiarViejos();

        $guardado = (array) (Cuentas::actual()?->ajuste('catalogo.importacion') ?? []);
        return view('admin.productos.importar-revisar', [
            'token' => $token, 'archivo' => $archivo->getClientOriginalName(), 'encabezados' => $encabezados,
            'muestra' => array_slice($datos, 0, 3000), 'total' => count($datos),
            'mapa' => ImportarProductos::sugerir($encabezados, $guardado['columnas'] ?? []),
            'opciones' => ['iva_incluido' => (bool) ($guardado['iva_incluido'] ?? false), 'existencias' => $guardado['existencias'] ?? 'reemplazar'],
            'claves' => Producto::whereNotNull('clave')->pluck('clave')->map(fn ($c) => mb_strtolower($c))->values(),
            'nombres' => Producto::whereNull('clave')->pluck('nombre')->map(fn ($c) => mb_strtolower($c))->values(),
            'campos' => collect(ImportarProductos::CAMPOS)->map(fn ($c) => $c[0]),
            'veCostos' => Inventario::veCostos(),
        ]);
    }

    /** Paso 3: importa con las columnas confirmadas y las recuerda para la próxima */
    public function aplicar(Request $request)
    {
        $d = $request->validate([
            'token'        => 'required|alpha_num|size:24',
            'mapa'         => 'array',
            'mapa.*'       => 'nullable|integer|min:0|max:500',
            'iva_incluido' => 'boolean',
            'existencias'  => 'required|in:reemplazar,sumar,ignorar',
        ]);
        $ruta = $this->ruta($d['token']);
        if (! Storage::disk('local')->exists($ruta)) {
            return redirect()->route('admin.productos.importar')->withErrors(['archivo' => 'Pasó mucho tiempo desde que subiste el archivo. Súbelo otra vez.']);
        }
        $archivo = json_decode(Storage::disk('local')->get($ruta), true);
        $mapa = array_intersect_key(array_map(fn ($v) => $v === null || $v === '' ? null : (int) $v, $d['mapa'] ?? []), ImportarProductos::CAMPOS);
        if (($mapa['nombre'] ?? null) === null && ($mapa['clave'] ?? null) === null) {
            return back()->withErrors(['mapa' => 'Indica qué columna trae el nombre del producto (o al menos la clave).']);
        }

        $res = ImportarProductos::aplicar($archivo['filas'], $mapa, ['iva_incluido' => $request->boolean('iva_incluido'), 'existencias' => $d['existencias']]);
        Storage::disk('local')->delete($ruta);

        // Recordar las columnas por su título, no por su posición: así sirve aunque cambie el orden
        $c = Cuentas::actual();
        $a = $c->ajustes ?? [];
        data_set($a, 'catalogo.importacion', [
            'columnas'     => array_map(fn ($i) => $i === null ? null : ($archivo['encabezados'][$i] ?? null), $mapa),
            'iva_incluido' => $request->boolean('iva_incluido'),
            'existencias'  => $d['existencias'],
            'ultima'       => now()->toIso8601String(),
            'archivo'      => $archivo['nombre'] ?? null,
        ]);
        $c->update(['ajustes' => $a]);

        $msg = 'Importación lista: ' . $res['nuevos'] . ' ' . ($res['nuevos'] === 1 ? 'producto nuevo' : 'productos nuevos') . ' y ' . $res['actualizados'] . ' ' . ($res['actualizados'] === 1 ? 'actualizado' : 'actualizados') . '.';
        if ($res['omitidas']) $msg .= ' Se saltaron ' . $res['omitidas'] . ' ' . ($res['omitidas'] === 1 ? 'fila' : 'filas') . ' sin nombre o repetidas.';
        return redirect()->route('admin.productos.index')->with('ok', $msg);
    }

    /** Un CSV de ejemplo con las columnas que reconocemos */
    public function plantilla()
    {
        $filas = [
            ['Clave', 'Nombre', 'Categoría', 'Descripción', 'Unidad', 'Precio', 'Costo', 'Existencia', 'Mínimo'],
            ['SIL-001', 'Silla ejecutiva Milán negra', 'Sillas', 'Respaldo de malla, base cromada, ajuste de altura', 'pieza', '3490', '2100', '12', '4'],
            ['ESC-120', 'Escritorio en L 120 cm', 'Escritorios', 'Cubierta de melamina 25 mm color nogal', 'pieza', '5890', '3650', '5', '2'],
            ['INST', 'Instalación y armado', 'Servicios', '', 'servicio', '450', '', '', ''],
        ];
        $csv = "\xEF\xBB\xBF" . collect($filas)->map(fn ($f) => collect($f)->map(fn ($v) => '"' . str_replace('"', '""', $v) . '"')->join(','))->join("\r\n");
        return response($csv, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="plantilla-productos.csv"']);
    }

    private function ruta(string $token): string
    {
        return 'importaciones/' . Cuentas::id() . '/' . $token . '.json';
    }

    private function limpiarViejos(): void
    {
        $disco = Storage::disk('local');
        foreach ($disco->files('importaciones/' . Cuentas::id()) as $f) {
            if ($disco->lastModified($f) < now()->subDay()->timestamp) $disco->delete($f);
        }
    }

    private static function letra(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) $s = chr(65 + ($i - 1) % 26) . $s;
        return $s;
    }
}
