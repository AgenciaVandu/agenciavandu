<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gasto;
use App\Models\Proyecto;
use App\Models\Sucursal;
use App\Support\Cuentas;
use App\Support\EsteMes;
use App\Support\Gastos\Cfdi;
use App\Support\Gastos\LectorTicket;
use App\Support\Gastos\Reporte;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Gastos sueltos (los que no salen de una orden de compra): foto del ticket leída con IA, XML de la factura o a mano.
 * Quien tiene "Subir gastos" registra y ve los suyos; quien tiene Finanzas ve los de todos y sus números.
 */
class GastosController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            abort_unless(Cuentas::actual()?->tiene('gastos'), 404);
            return $next($request);
        });
    }

    private function todos(Request $r): bool
    {
        return $r->user()->puede('finanzas');
    }

    private function suyo(Request $r, Gasto $g): void
    {
        abort_unless($this->todos($r) || $g->user_id === $r->user()->id, 403);
    }

    public function index(Request $request)
    {
        $todos = $this->todos($request);
        $periodo = array_key_exists($request->query('periodo'), FinanzasController::PERIODOS) ? $request->query('periodo') : 'mes';
        [$desde, $hasta] = FinanzasController::rangoDe($periodo);
        // El gimnasio vende con precio final: sus gastos también se ven con IVA, igual que en su Finanzas
        $conIva = in_array($request->query('iva'), ['con', 'sin'], true) ? $request->query('iva') === 'con' : (Cuentas::actual()->tiene('fitness') || EsteMes::conIva());
        $categoria = array_key_exists((string) $request->query('categoria'), Gasto::CATEGORIAS) ? $request->query('categoria') : null;
        $q = trim((string) $request->query('q'));

        $gastos = Gasto::with(['usuario:id,name', 'proyecto:id,nombre'])->entre($desde, $hasta)
            ->when(! $todos, fn ($w) => $w->where('user_id', $request->user()->id))
            ->when($categoria, fn ($w) => $w->where('categoria', $categoria))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('concepto', 'like', "%$q%")->orWhere('proveedor', 'like', "%$q%")))
            ->orderByDesc('fecha')->orderByDesc('id')->paginate(40)->withQueryString();

        return view('admin.gastos.index', [
            'gastos' => $gastos, 'todos' => $todos, 'periodo' => $periodo, 'conIva' => $conIva, 'categoria' => $categoria, 'q' => $q,
            'rep' => Reporte::datos($desde, $hasta, $conIva, $todos ? null : $request->user()->id),
            'ia' => LectorTicket::activo(),
        ]);
    }

    public function create(Request $request)
    {
        return view('admin.gastos.form', $this->formulario(new Gasto(['fecha' => now(config('vandu.zona_horaria'))->toDateString(), 'categoria' => 'otros'])));
    }

    public function edit(Request $request, Gasto $gasto)
    {
        $this->suyo($request, $gasto);
        return view('admin.gastos.form', $this->formulario($gasto));
    }

    private function formulario(Gasto $g): array
    {
        $c = Cuentas::actual();
        return [
            'g' => $g,
            'ia' => LectorTicket::activo(),
            'quedan' => max(0, LectorTicket::tope($c) - LectorTicket::usadasEsteMes($c)),
            'proyectos' => $c->tiene('proyectos')
                ? Proyecto::whereNotIn('estado', ['cancelado'])->orWhere('id', $g->proyecto_id)->orderByDesc('id')->limit(80)->get(['id', 'nombre'])
                : collect(),
            'sucursales' => Sucursal::orderBy('nombre')->get(['id', 'nombre']),
        ];
    }

    /** Lee la foto del ticket y regresa los datos para prellenar (no guarda nada) */
    public function leer(Request $request)
    {
        $request->validate(['foto' => 'required|file|image|max:15360']);
        $jpeg = LectorTicket::preparar((string) file_get_contents($request->file('foto')->getRealPath()));
        if (! $jpeg) return response()->json(['ok' => false, 'motivo' => 'formato', 'mensaje' => 'No pudimos abrir esa imagen. Toma la foto otra vez.'], 422);
        return response()->json(LectorTicket::leer($jpeg, Cuentas::actual(), $request->user()));
    }

    /** Lee el XML de la factura (no guarda nada) */
    public function xml(Request $request)
    {
        $request->validate(['xml' => 'required|file|max:2048']);
        $d = Cfdi::leer((string) file_get_contents($request->file('xml')->getRealPath()));
        if (! $d) return response()->json(['ok' => false, 'mensaje' => 'Ese archivo no es el XML de una factura timbrada.'], 422);
        $ya = Gasto::where('uuid', $d['uuid'])->first();
        return response()->json(['ok' => true, 'datos' => $d, 'repetido' => $ya ? ['fecha' => $ya->fecha->toDateString(), 'url' => route('admin.gastos.edit', $ya)] : null]);
    }

    public function store(Request $request)
    {
        $d = $this->validar($request);
        $g = new Gasto($d + ['user_id' => $request->user()->id, 'origen' => 'manual']);
        $this->archivos($request, $g);
        $g->save();
        return redirect()->route('admin.gastos')->with('ok', 'Gasto guardado: ' . $g->concepto . ' por $' . number_format($g->total, 2) . '.');
    }

    public function update(Request $request, Gasto $gasto)
    {
        $this->suyo($request, $gasto);
        $gasto->fill($this->validar($request, $gasto));
        $this->archivos($request, $gasto);
        $gasto->save();
        return redirect()->route('admin.gastos')->with('ok', 'Gasto actualizado.');
    }

    public function destroy(Request $request, Gasto $gasto)
    {
        $this->suyo($request, $gasto);
        foreach ([$gasto->foto, $gasto->xml] as $ruta) if ($ruta) Storage::delete($ruta);
        $gasto->delete();
        return redirect()->route('admin.gastos')->with('ok', 'Gasto eliminado.');
    }

    /** La foto o el XML de un gasto (privados) */
    public function archivo(Request $request, Gasto $gasto, string $tipo)
    {
        $this->suyo($request, $gasto);
        $ruta = $tipo === 'xml' ? $gasto->xml : $gasto->foto;
        abort_unless($ruta && Storage::exists($ruta), 404);
        return $tipo === 'xml'
            ? Storage::download($ruta, 'factura-' . ($gasto->uuid ?: $gasto->id) . '.xml')
            : response()->file(Storage::path($ruta), ['Cache-Control' => 'private, max-age=86400']);
    }

    public function exportar(Request $request)
    {
        abort_unless($this->todos($request), 403);
        $periodo = array_key_exists($request->query('periodo'), FinanzasController::PERIODOS) ? $request->query('periodo') : 'mes';
        [$desde, $hasta] = FinanzasController::rangoDe($periodo);
        $filas = Gasto::with(['usuario:id,name', 'proyecto:id,nombre'])->entre($desde, $hasta)->orderBy('fecha')->get();
        return response()->streamDownload(function () use ($filas) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Fecha', 'Concepto', 'Categoría', 'Proveedor', 'RFC', 'Subtotal', 'IVA', 'Total', 'Forma de pago', 'Proyecto', 'Registró', 'Comprobante', 'Folio fiscal']);
            foreach ($filas as $g) {
                fputcsv($out, [$g->fecha->toDateString(), $g->concepto, $g->categoria_texto, $g->proveedor, $g->rfc, number_format($g->subtotal, 2, '.', ''), number_format($g->iva, 2, '.', ''),
                    number_format($g->total, 2, '.', ''), Gasto::FORMAS[$g->forma_pago] ?? '', $g->proyecto?->nombre, $g->usuario?->name,
                    $g->xml ? 'Factura XML' : ($g->foto ? 'Foto del ticket' : 'Sin comprobante'), $g->uuid]);
            }
            fclose($out);
        }, 'gastos-' . ($desde ?: 'inicio') . '-a-' . ($hasta ?: now()->toDateString()) . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function validar(Request $request, ?Gasto $actual = null): array
    {
        $d = $request->validate([
            'fecha'       => 'required|date|before_or_equal:' . now(config('vandu.zona_horaria'))->addDay()->toDateString(),
            'concepto'    => 'required|string|max:160',
            'categoria'   => ['required', Rule::in(array_keys(Gasto::CATEGORIAS))],
            'proveedor'   => 'nullable|string|max:160',
            'rfc'         => ['nullable', 'string', 'max:13', 'regex:/^[A-Za-z&Ññ]{3,4}\d{6}[A-Za-z0-9]{3}$/u'],
            'total'       => 'required|numeric|min:0.01|max:99999999',
            'iva'         => 'nullable|numeric|min:0|lt:total',
            'forma_pago'  => ['nullable', Rule::in(array_keys(Gasto::FORMAS))],
            'proyecto_id' => 'nullable|integer',
            'sucursal_id' => 'nullable|integer',
            'notas'       => 'nullable|string|max:2000',
            'foto'        => 'nullable|file|image|max:15360',
            'xml'         => 'nullable|file|max:2048',
            'lectura'     => 'nullable|string|max:4000',
        ], [
            'iva.lt' => 'El IVA tiene que ser menor que el total.',
            'rfc.regex' => 'Revisa el RFC (12 o 13 caracteres).',
            'fecha.before_or_equal' => 'La fecha no puede ser futura.',
        ]);
        $d['iva'] = round((float) ($d['iva'] ?? 0), 2);
        $d['total'] = round((float) $d['total'], 2);
        $d['subtotal'] = round($d['total'] - $d['iva'], 2);
        $d['rfc'] = isset($d['rfc']) ? mb_strtoupper($d['rfc']) : null;
        $d['proyecto_id'] = ! empty($d['proyecto_id']) && Proyecto::whereKey($d['proyecto_id'])->exists() ? (int) $d['proyecto_id'] : null;
        $d['sucursal_id'] = ! empty($d['sucursal_id']) && Sucursal::whereKey($d['sucursal_id'])->exists() ? (int) $d['sucursal_id'] : null;
        $lectura = json_decode((string) ($d['lectura'] ?? ''), true);
        $d['lectura'] = is_array($lectura) && ! $actual?->lectura ? array_intersect_key($lectura, array_flip(['fecha', 'proveedor', 'rfc', 'concepto', 'categoria', 'total', 'iva', 'forma_pago', 'dudas'])) : ($actual?->lectura);
        unset($d['foto'], $d['xml']);
        return $d;
    }

    /** Guarda la foto (achicada) o el XML; con XML, el folio fiscal no se puede repetir */
    private function archivos(Request $request, Gasto $g): void
    {
        $dir = 'gastos/' . Cuentas::id();
        if ($request->hasFile('xml')) {
            $contenido = (string) file_get_contents($request->file('xml')->getRealPath());
            $cfdi = Cfdi::leer($contenido);
            if (! $cfdi) throw ValidationException::withMessages(['xml' => 'Ese archivo no es el XML de una factura timbrada.']);
            if (Gasto::where('uuid', $cfdi['uuid'])->when($g->exists, fn ($q) => $q->whereKeyNot($g->id))->exists()) {
                throw ValidationException::withMessages(['xml' => 'Esa factura ya está registrada como gasto.']);
            }
            if ($g->xml) Storage::delete($g->xml);
            $g->xml = $dir . '/' . Str::random(24) . '.xml';
            Storage::put($g->xml, $contenido);
            $g->uuid = $cfdi['uuid'];
            $g->origen = 'xml';
        }
        if ($request->hasFile('foto')) {
            $jpeg = LectorTicket::preparar((string) file_get_contents($request->file('foto')->getRealPath()));
            if (! $jpeg) throw ValidationException::withMessages(['foto' => 'No pudimos abrir esa imagen.']);
            if ($g->foto) Storage::delete($g->foto);
            $g->foto = $dir . '/' . Str::random(24) . '.jpg';
            Storage::put($g->foto, $jpeg);
            if ($g->origen !== 'xml') $g->origen = 'foto';
        }
    }
}
