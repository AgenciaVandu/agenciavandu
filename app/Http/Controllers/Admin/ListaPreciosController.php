<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Support\Cuentas;
use App\Support\Documentos\ListaPrecios;
use App\Support\IA\Anthropic;
use Illuminate\Http\Request;

/** Actualizar costos del catálogo con la lista de precios de un proveedor (PDF o foto, leída con IA) */
class ListaPreciosController extends Controller
{
    private const SESION = 'lista_precios';

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            abort_unless(Cuentas::actual()?->tiene('stock'), 404);
            return $next($request);
        });
    }

    public function create()
    {
        $c = Cuentas::actual();
        return view('admin.productos.lista', [
            'proveedores' => $c->tiene('proveedores') ? Proveedor::orderBy('empresa')->get(['id', 'empresa']) : collect(),
            'ia' => $c->tiene('ia') && Anthropic::activo(),
            'conIa' => $c->tiene('ia'),
            'quedan' => max(0, Anthropic::tope($c) - Anthropic::usadasEsteMes($c)),
        ]);
    }

    public function leer(Request $request)
    {
        abort_unless(Cuentas::actual()->tiene('ia'), 404);
        $d = $request->validate([
            'archivo' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:15360',
            'proveedor_id' => 'nullable|integer',
        ], ['archivo.mimes' => 'Sube la lista en PDF o una foto. Si la tienes en Excel, usa Importar de Excel.', 'archivo.max' => 'La lista no puede pesar más de 15 MB.']);
        $f = $request->file('archivo');
        @set_time_limit(180);
        $r = ListaPrecios::leer((string) file_get_contents($f->getRealPath()), (string) $f->getMimeType(), Cuentas::actual(), $request->user());
        if (! $r['ok']) return back()->with('error', $r['mensaje'])->withInput();
        $request->session()->put(self::SESION, [
            'filas' => $r['filas'], 'con_iva' => $r['con_iva'], 'proveedor_detectado' => $r['proveedor'], 'archivo' => $f->getClientOriginalName(),
            'proveedor_id' => ! empty($d['proveedor_id']) && Proveedor::whereKey($d['proveedor_id'])->exists() ? (int) $d['proveedor_id'] : null,
        ]);
        return redirect()->route('admin.productos.lista.revisar');
    }

    public function revisar(Request $request)
    {
        $s = $request->session()->get(self::SESION);
        if (! $s) return redirect()->route('admin.productos.lista');
        return view('admin.productos.lista-revisar', [
            's' => $s,
            'renglones' => ListaPrecios::emparejar($s['filas'], $s['proveedor_id']),
            'proveedor' => $s['proveedor_id'] ? Proveedor::find($s['proveedor_id']) : null,
        ]);
    }

    public function aplicar(Request $request)
    {
        $s = $request->session()->get(self::SESION);
        if (! $s) return redirect()->route('admin.productos.lista');
        $d = $request->validate([
            'filas' => 'array',
            'filas.*.aplicar' => 'nullable|boolean',
            'filas.*.producto_id' => 'nullable|integer',
            'filas.*.costo' => 'nullable|numeric|min:0|max:99999999',
            'margen' => 'nullable|boolean',
        ]);
        $mantenerMargen = $request->boolean('margen');
        $n = 0; $subieron = 0; $bajaron = 0;
        foreach ((array) ($d['filas'] ?? []) as $f) {
            if (empty($f['aplicar']) || empty($f['producto_id']) || ! isset($f['costo'])) continue;
            $p = Producto::find($f['producto_id']);
            if (! $p) continue;
            $nuevo = round((float) $f['costo'], 2);
            $cambios = ['costo' => $nuevo];
            if ($mantenerMargen && $p->costo > 0 && $p->precio > 0) $cambios['precio'] = round($p->precio * $nuevo / $p->costo, 2);
            if ($s['proveedor_id'] && ! $p->proveedor_id) $cambios['proveedor_id'] = $s['proveedor_id'];
            if ($nuevo > (float) $p->costo) $subieron++; elseif ($nuevo < (float) $p->costo) $bajaron++;
            $p->update($cambios);
            $n++;
        }
        $request->session()->forget(self::SESION);
        return redirect()->route('admin.productos.index')->with('ok', $n
            ? "Actualizamos el costo de $n " . ($n === 1 ? 'producto' : 'productos') . " ($subieron subieron, $bajaron bajaron)" . ($mantenerMargen ? ' y su precio de venta con el mismo margen.' : '.')
            : 'No se cambió ningún costo.');
    }
}
