<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Presupuesto;
use App\Support\PresupuestoPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PresupuestoController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $filtro = $request->query('filtro', 'vigentes');

        $presupuestos = Presupuesto::query()
            ->with(['cliente', 'conceptos', 'proyecto'])
            ->when($q, fn ($query) => $query->where(fn ($w) => $w
                ->where('folio', 'like', "%$q%")
                ->orWhere('cliente_nombre', 'like', "%$q%")
                ->orWhere('cliente_empresa', 'like', "%$q%")))
            ->when($filtro === 'vigentes', fn ($w) => $w->where('vigente_hasta', '>', now())->whereNotIn('estado', ['aceptada', 'rechazada']))
            ->when($filtro === 'vencidas', fn ($w) => $w->where('vigente_hasta', '<=', now()))
            ->when($filtro === 'por_vencer', fn ($w) => $w->whereBetween('vigente_hasta', [now(), now()->addHours(72)])
                ->whereNotIn('estado', ['aceptada', 'rechazada']))
            ->when(array_key_exists($filtro, Presupuesto::ESTADOS), fn ($w) => $w->where('estado', $filtro))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $conteos = [
            'vigentes'   => Presupuesto::where('vigente_hasta', '>', now())->whereNotIn('estado', ['aceptada', 'rechazada'])->count(),
            'por_vencer' => Presupuesto::whereBetween('vigente_hasta', [now(), now()->addHours(72)])->whereNotIn('estado', ['aceptada', 'rechazada'])->count(),
            'vencidas'   => Presupuesto::where('vigente_hasta', '<=', now())->count(),
            'aceptada'   => Presupuesto::where('estado', 'aceptada')->count(),
            'todas'      => Presupuesto::count(),
        ];

        return view('admin.presupuestos.index', compact('presupuestos', 'q', 'filtro', 'conteos'));
    }

    public function create(Request $request)
    {
        $cliente = $request->filled('cliente') ? Cliente::findOrFail($request->query('cliente')) : null;
        $presupuesto = Presupuesto::nuevaPara($cliente);
        $presupuesto->setRelation('conceptos', collect());

        return view('admin.presupuestos.form', [
            'presupuesto' => $presupuesto,
            'clientes'    => Cliente::orderBy('nombre')->get(['id', 'nombre', 'empresa']),
        ]);
    }

    public function store(Request $request)
    {
        $presupuesto = DB::transaction(function () use ($request) {
            [$datos, $conceptos] = $this->validar($request);
            $p = Presupuesto::create($datos);
            $p->conceptos()->createMany($conceptos);
            return $p;
        });

        return redirect()->route('admin.presupuestos.edit', $presupuesto)
            ->with('ok', "Cotización {$presupuesto->folio} creada.");
    }

    public function edit(Presupuesto $presupuesto)
    {
        $presupuesto->load('conceptos', 'cliente');

        return view('admin.presupuestos.form', [
            'presupuesto' => $presupuesto,
            'clientes'    => Cliente::orderBy('nombre')->get(['id', 'nombre', 'empresa']),
        ]);
    }

    public function update(Request $request, Presupuesto $presupuesto)
    {
        DB::transaction(function () use ($request, $presupuesto) {
            [$datos, $conceptos] = $this->validar($request);
            $presupuesto->update($datos);
            $presupuesto->conceptos()->delete();
            $presupuesto->conceptos()->createMany($conceptos);
        });

        return back()->with('ok', 'Cambios guardados.');
    }

    public function destroy(Presupuesto $presupuesto)
    {
        $presupuesto->delete();

        return redirect()->route('admin.presupuestos.index')->with('ok', 'Cotización eliminada.');
    }

    public function duplicar(Presupuesto $presupuesto)
    {
        $copia = $presupuesto->load('conceptos')->duplicar();

        return redirect()->route('admin.presupuestos.edit', $copia)
            ->with('ok', "Copia creada como {$copia->folio}. Revisa fecha y vigencia.");
    }

    /** Vista previa del PDF (siempre disponible para el admin, aunque esté vencida) */
    public function pdf(Presupuesto $presupuesto)
    {
        return PresupuestoPdf::ver($presupuesto);
    }

    /** Cambios rápidos desde el listado: estado o extender vigencia */
    public function rapido(Request $request, Presupuesto $presupuesto)
    {
        $data = $request->validate([
            'estado'         => ['nullable', Rule::in(array_keys(Presupuesto::ESTADOS))],
            'extender_dias'  => 'nullable|integer|min:1|max:365',
        ]);

        if (! empty($data['estado'])) {
            $presupuesto->estado = $data['estado'];
        }
        if (! empty($data['extender_dias'])) {
            $presupuesto->vigente_hasta = Presupuesto::finDeDiaEnDias((int) $data['extender_dias']);
        }
        $presupuesto->save();

        return back()->with('ok', "{$presupuesto->folio} actualizada.");
    }

    /** @return array{0: array, 1: array} [datos del presupuesto, conceptos] */
    private function validar(Request $request): array
    {
        $v = $request->validate([
            'cliente_id'       => 'required|exists:clientes,id',
            'cliente_nombre'   => 'required|string|max:255',
            'cliente_empresa'  => 'nullable|string|max:255',
            'fecha'            => 'required|date',
            'titulo'           => 'required|string|max:255',
            'emisor_nombre'    => 'required|string|max:255',
            'emisor_telefono'  => 'nullable|string|max:255',
            'emisor_sitio'     => 'nullable|string|max:255',
            'emisor_email'     => 'nullable|string|max:255',
            'modo_iva'         => ['required', Rule::in(array_keys(Presupuesto::MODOS_IVA))],
            'iva_porcentaje'   => 'required|numeric|min:0|max:100',

            'conceptos'               => 'required|array|min:1',
            'conceptos.*.titulo'      => 'nullable|required_without:conceptos.*.descripcion|string|max:255',
            'conceptos.*.descripcion' => 'nullable|string|max:5000',
            'conceptos.*.cantidad'    => 'required|numeric|min:0',
            'conceptos.*.precio'      => 'required|numeric|min:0',

            'consideraciones'            => 'nullable|array',
            'consideraciones.*.titulo'   => 'nullable|string|max:255',
            'consideraciones.*.items'    => 'nullable|array',
            'consideraciones.*.items.*'  => 'nullable|string|max:2000',

            'mostrar_pago'     => 'nullable|boolean',
            'pago_intro'       => 'nullable|string|max:2000',
            'banco'            => 'nullable|string|max:255',
            'clabe'            => 'nullable|string|max:30',
            'beneficiario'     => 'nullable|string|max:255',
            'nota_comprobante' => 'nullable|string|max:2000',
            'nota_factura'     => 'nullable|string|max:2000',

            'vigente_hasta'    => 'required|date',
            'estado'           => ['required', Rule::in(array_keys(Presupuesto::ESTADOS))],
            'aceptada_el'      => 'nullable|date',
            'notas_internas'   => 'nullable|string|max:5000',
        ], [
            'conceptos.required' => 'Agrega al menos un concepto.',
            'conceptos.*.titulo.required_without' => 'Cada concepto necesita un título o una descripción.',
        ]);

        $conceptos = collect($v['conceptos'])->values()->map(fn ($c, $i) => [
            'titulo'      => trim((string) ($c['titulo'] ?? '')) ?: null,
            'descripcion' => (string) ($c['descripcion'] ?? ''),
            'cantidad'    => $c['cantidad'],
            'precio'      => $c['precio'],
            'orden'       => $i,
        ])->all();

        $datos = collect($v)->except('conceptos')->all();
        $datos['mostrar_pago'] = $request->boolean('mostrar_pago');
        $datos['consideraciones'] = collect($v['consideraciones'] ?? [])->map(fn ($s) => [
            'titulo' => (string) ($s['titulo'] ?? ''),
            'items'  => array_values(array_filter($s['items'] ?? [], fn ($i) => trim((string) $i) !== '')),
        ])->values()->all();

        // La vigencia se captura en hora local de Vandu
        $datos['vigente_hasta'] = Carbon::parse($v['vigente_hasta'], config('vandu.zona_horaria'))
            ->setTimezone(config('app.timezone'));

        return [$datos, $conceptos];
    }
}
