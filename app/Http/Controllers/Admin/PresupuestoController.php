<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Support\Aceptacion;
use App\Models\Presupuesto;
use App\Support\PresupuestoPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PresupuestoController extends Controller
{
    /** Vistas de la lista. "vigentes" se conserva como nombre de "Por responder" para los enlaces que ya existen */
    public const VISTAS = [
        'todas'     => 'Todas',
        'vigentes'  => 'Por responder',
        'por_vencer' => 'Por vencer',
        'borrador'  => 'Borradores',
        'aceptada'  => 'Aceptadas',
        'cerradas'  => 'Vencidas o rechazadas',
    ];

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $filtro = array_key_exists($request->query('filtro'), self::VISTAS) ? $request->query('filtro') : 'todas';

        $vista = fn (string $f) => function ($w) use ($f) {
            match ($f) {
                // Enviadas que siguen vigentes, o en negociación
                'vigentes'   => $w->where(fn ($x) => $x->where('estado', 'negociacion')
                                    ->orWhere(fn ($y) => $y->where('estado', 'enviada')->where('vigente_hasta', '>', now()))),
                'por_vencer' => $w->whereBetween('vigente_hasta', [now(), now()->addHours(72)])->whereIn('estado', ['enviada', 'negociacion', 'borrador']),
                'borrador'   => $w->where('estado', 'borrador'),
                'aceptada'   => $w->where('estado', 'aceptada'),
                'cerradas'   => $w->where(fn ($x) => $x->where('estado', 'rechazada')
                                    ->orWhere(fn ($y) => $y->where('vigente_hasta', '<=', now())->whereIn('estado', ['enviada', 'borrador']))),
                default      => $w,
            };
        };

        $presupuestos = Presupuesto::query()
            ->with(['cliente', 'prospecto', 'conceptos', 'proyecto', 'facturas'])
            ->when($q, fn ($query) => $query->where(fn ($w) => $w
                ->where('folio', 'like', "%$q%")
                ->orWhere('cliente_nombre', 'like', "%$q%")
                ->orWhere('cliente_empresa', 'like', "%$q%")
                ->orWhereHas('conceptos', fn ($c) => $c->where('titulo', 'like', "%$q%")->orWhere('descripcion', 'like', "%$q%"))))
            ->where($vista($filtro))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $conteos = collect(self::VISTAS)->map(fn ($t, $k) => Presupuesto::where($vista($k))->count())->all();

        // Tarjetas de arriba (montos antes de IVA)
        $suma = fn ($c) => $c->sum(fn ($p) => $p->subtotal);
        $porResponder = Presupuesto::with('conceptos')->where($vista('vigentes'))->get();
        $mes = now(config('vandu.zona_horaria'))->startOfMonth()->toDateString();
        $aceptadasMes = Presupuesto::with('conceptos')->where('estado', 'aceptada')->where('aceptada_el', '>=', $mes)->get();
        $resumen = [
            'porResponder' => ['n' => $porResponder->count(), 'monto' => $suma($porResponder)],
            'porVencer'    => $conteos['por_vencer'],
            'aceptadasMes' => ['n' => $aceptadasMes->count(), 'monto' => $suma($aceptadasMes)],
            'borradores'   => $conteos['borrador'],
            'sinProyecto'  => Presupuesto::where('estado', 'aceptada')->doesntHave('proyecto')->count(),
        ];

        return view('admin.presupuestos.index', compact('presupuestos', 'q', 'filtro', 'conteos', 'resumen'));
    }

    public function create(Request $request)
    {
        $prospecto = $request->filled('prospecto') ? \App\Models\Prospecto::find($request->integer('prospecto')) : null;
        $cliente = $request->filled('cliente') ? Cliente::findOrFail($request->query('cliente')) : $prospecto?->cliente;
        $presupuesto = Presupuesto::nuevaPara($cliente);
        $presupuesto->setRelation('conceptos', collect());
        // Prospecto que todavía no es cliente: la cotización va a su nombre
        if ($prospecto) $presupuesto->prospecto_id = $prospecto->id;
        if ($prospecto && ! $cliente) $presupuesto->fill(['cliente_nombre' => $prospecto->nombre, 'cliente_empresa' => $prospecto->empresa]);

        return view('admin.presupuestos.form', [
            'presupuesto' => $presupuesto,
            'clientes'    => Cliente::orderBy('nombre')->get(['id', 'nombre', 'empresa']),
            'prospectos'  => $this->prospectosSinCliente($presupuesto),
            'prospecto'   => $prospecto,
        ]);
    }

    public function store(Request $request)
    {
        $presupuesto = DB::transaction(function () use ($request) {
            [$datos, $conceptos] = $this->validar($request);
            $p = Presupuesto::create($datos);
            $p->conceptos()->createMany($conceptos);
            // Ya se le está atendiendo: deja de aparecer como contacto nuevo
            if ($p->cliente_id) Cliente::whereKey($p->cliente_id)->where('nuevo', true)->update(['nuevo' => false]);
            Aceptacion::registrar($p, 'creada', 'agencia', null, null, ['version' => 1] + Aceptacion::foto($p->fresh('conceptos')), false);
            // Viene de Ventas: la cotización queda ligada al prospecto (al aceptarse, pasa a Ganado)
            if ($p->prospecto_id && ($pr = \App\Models\Prospecto::find($p->prospecto_id))) {
                $p->load('conceptos');
                $pr->update(['presupuesto_id' => $p->id, 'cliente_id' => $pr->cliente_id ?? $p->cliente_id, 'valor' => $pr->valor ?: $p->subtotal]);
                $pr->eventos()->create(['tipo' => 'cotizacion', 'user_id' => $request->user()?->id, 'texto' => "Se le cotizó {$p->folio} por " . $p->monto($p->subtotal)]);
            }
            return $p;
        });

        return redirect()->route('admin.presupuestos.edit', $presupuesto)
            ->with('ok', "Cotización {$presupuesto->folio} creada.");
    }

    public function edit(Presupuesto $presupuesto)
    {
        $presupuesto->load('conceptos', 'cliente', 'prospecto');

        return view('admin.presupuestos.form', [
            'presupuesto' => $presupuesto,
            'clientes'    => Cliente::orderBy('nombre')->get(['id', 'nombre', 'empresa']),
            'prospectos'  => $this->prospectosSinCliente($presupuesto),
        ]);
    }

    /** Prospectos abiertos que todavía no son clientes (y el de esta cotización, aunque ya esté cerrado) */
    private function prospectosSinCliente(Presupuesto $p)
    {
        return \App\Models\Prospecto::whereNull('cliente_id')
            ->where(fn ($q) => $q->whereHas('etapa', fn ($e) => $e->where('tipo', 'abierta'))->when($p->prospecto_id, fn ($q) => $q->orWhere('id', $p->prospecto_id)))
            ->orderBy('nombre')->get(['id', 'nombre', 'empresa']);
    }

    public function update(Request $request, Presupuesto $presupuesto)
    {
        DB::transaction(function () use ($request, $presupuesto) {
            $antes = Aceptacion::foto($presupuesto);
            $estadoAntes = $presupuesto->estado;
            [$datos, $conceptos] = $this->validar($request);
            $presupuesto->update($datos);
            $presupuesto->conceptos()->delete();
            $presupuesto->conceptos()->createMany($conceptos);

            // Historial: versión nueva si cambió el contenido, y cambio de estado si lo hubo
            $despues = Aceptacion::foto($presupuesto->fresh('conceptos'));
            $cambios = Aceptacion::diferencias($antes, $despues, $presupuesto);
            if ($cambios) {
                $version = $presupuesto->eventos()->whereIn('tipo', ['creada', 'editada'])->count() + 1;
                Aceptacion::registrar($presupuesto, 'editada', 'agencia', null, implode("\n", $cambios),
                    ['version' => max(2, $version), 'antes' => $antes, 'despues' => $despues], $estadoAntes !== 'borrador');
            }
            if ($estadoAntes !== $presupuesto->estado) {
                Aceptacion::registrar($presupuesto, 'estado', 'agencia', null, 'Cambio manual desde el panel', ['de' => $estadoAntes, 'a' => $presupuesto->estado], $presupuesto->estado !== 'borrador');
            }
        });

        return back()->with('ok', 'Cambios guardados.');
    }

    public function destroy(Presupuesto $presupuesto)
    {
        // Si ya era proyecto, se va con ella: sus pagos dejan de contar como cobrado o por cobrar
        $proyecto = $presupuesto->proyecto;
        \Illuminate\Support\Facades\DB::transaction(function () use ($presupuesto, $proyecto) {
            $proyecto?->eliminarCompleto();
            $presupuesto->delete();
        });

        return redirect()->route('admin.presupuestos.index')->with('ok', $proyecto
            ? "Cotización {$presupuesto->folio} eliminada junto con su proyecto y sus pagos; ya no cuentan en tus ingresos."
            : 'Cotización eliminada.');
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

    /**
     * Enviar por WhatsApp: genera el código de verificación y abre WhatsApp con el mensaje ya escrito.
     * (Si la cotización ya no se puede responder en línea, manda solo el enlace.)
     */
    public function whatsapp(Presupuesto $presupuesto)
    {
        $p = $presupuesto->load('cliente', 'prospecto');
        $wa = $p->contacto?->whatsapp;
        abort_unless($wa, 404, 'No tiene WhatsApp registrado.');

        $nombre = \App\Support\Correos::primerNombre($p->cliente_nombre);
        $msg = "Hola {$nombre}, te comparto la cotización {$p->folio}: {$p->url_publica}";
        if (Aceptacion::puedeResponder($p)) {
            $c = Aceptacion::generarCodigo($p, 'whatsapp');
            $msg .= "\n\nDesde el enlace puedes aceptarla o pedir cambios con tu código de verificación: *{$c['formateado']}*\n(válido hasta el " . Aceptacion::vigenciaTexto($c['expira']) . ')';
            Aceptacion::registrar($p, 'codigo', 'agencia', null, 'Código enviado por WhatsApp · vence el ' . Aceptacion::vigenciaTexto($c['expira']), ['canal' => 'whatsapp'], false);
        }
        if ($p->estado === 'borrador') {
            $p->update(['estado' => 'enviada']);
        }
        Aceptacion::registrar($p, 'enviada', 'agencia', null, 'Por WhatsApp', ['canal' => 'whatsapp']);

        return redirect()->away('https://wa.me/' . $wa . '?text=' . rawurlencode($msg));
    }

    /** Generar un código a mano (por ejemplo, para dictarlo por teléfono) */
    public function codigo(Presupuesto $presupuesto)
    {
        if (! Aceptacion::puedeResponder($presupuesto)) {
            return back()->withErrors(['codigo' => 'Esta cotización ya no se puede responder en línea (está aceptada, rechazada o vencida).']);
        }
        $c = Aceptacion::generarCodigo($presupuesto, 'manual');
        Aceptacion::registrar($presupuesto, 'codigo', 'agencia', null, 'Código generado a mano · vence el ' . Aceptacion::vigenciaTexto($c['expira']), ['canal' => 'manual'], false);

        return back()->with('codigo_nuevo', ['formateado' => $c['formateado'], 'vence' => Aceptacion::vigenciaTexto($c['expira'])])
            ->with('ok', "Código {$c['formateado']} generado. Vale 24 horas.");
    }

    /** Cambios rápidos desde el listado: estado o extender vigencia */
    public function rapido(Request $request, Presupuesto $presupuesto)
    {
        $data = $request->validate([
            'estado'         => ['nullable', Rule::in(array_keys(Presupuesto::ESTADOS))],
            'extender_dias'  => 'nullable|integer|min:1|max:365',
        ]);

        $estadoAntes = $presupuesto->estado;
        if (! empty($data['estado'])) {
            $presupuesto->estado = $data['estado'];
        }
        if (! empty($data['extender_dias'])) {
            $presupuesto->vigente_hasta = Presupuesto::finDeDiaEnDias((int) $data['extender_dias']);
        }
        $presupuesto->save();

        if ($estadoAntes !== $presupuesto->estado) {
            Aceptacion::registrar($presupuesto, 'estado', 'agencia', null, 'Cambio manual desde el panel', ['de' => $estadoAntes, 'a' => $presupuesto->estado], $presupuesto->estado !== 'borrador');
        }
        if (! empty($data['extender_dias'])) {
            Aceptacion::registrar($presupuesto, 'vigencia', 'agencia', null, 'Vigente hasta el ' . Aceptacion::vigenciaTexto($presupuesto->vigente_hasta));
        }

        return back()->with('ok', "{$presupuesto->folio} actualizada.");
    }

    /** @return array{0: array, 1: array} [datos del presupuesto, conceptos] */
    private function validar(Request $request): array
    {
        $v = $request->validate([
            'cliente_id'       => ['nullable', 'required_without:prospecto_id', \Illuminate\Validation\Rule::exists('clientes', 'id')->where('cuenta_id', \App\Support\Cuentas::id())],
            'prospecto_id'     => ['nullable', \Illuminate\Validation\Rule::exists('prospectos', 'id')->where('cuenta_id', \App\Support\Cuentas::id())],
            'cliente_nombre'   => 'required|string|max:255',
            'cliente_empresa'  => 'nullable|string|max:255',
            'fecha'            => 'required|date',
            'titulo'           => 'required|string|max:255',
            'tipo'             => ['nullable', Rule::in(array_keys(config('vandu.proyectos')))],
            'emisor_nombre'    => 'required|string|max:255',
            'emisor_telefono'  => 'nullable|string|max:255',
            'emisor_sitio'     => 'nullable|string|max:255',
            'emisor_email'     => 'nullable|string|max:255',
            'modo_iva'         => ['required', Rule::in(array_keys(Presupuesto::MODOS_IVA))],
            'metodo_sat'       => 'nullable|in:PUE,PPD',
            'iva_porcentaje'   => 'required|numeric|min:0|max:100',

            'conceptos'               => 'required|array|min:1',
            'conceptos.*.titulo'      => 'nullable|required_without:conceptos.*.descripcion|string|max:255',
            'conceptos.*.descripcion' => 'nullable|string|max:5000',
            'conceptos.*.cantidad'    => 'required|numeric|min:0',
            'conceptos.*.precio'      => 'required|numeric|min:0',
            'conceptos.*.costos'          => 'nullable|array|max:30',
            'conceptos.*.costos.*.clave'  => 'nullable|string|max:40',
            'conceptos.*.costos.*.nombre' => 'nullable|string|max:80',
            'conceptos.*.costos.*.modo'   => 'nullable|in:unidad,fijo',
            'conceptos.*.costos.*.clase'  => 'nullable|in:tercero,operativo',
            'conceptos.*.costos.*.monto'  => 'nullable|numeric|min:0',
            'conceptos.*.utilidad'        => 'nullable|numeric',
            'conceptos.*.utilidad_modo'   => 'nullable|in:pct,monto',

            'observaciones'              => 'nullable|string|max:5000',
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

        $num = fn ($x) => ($x === null || $x === '') ? null : (float) $x;

        $conceptos = collect($v['conceptos'])->values()->map(function ($c, $i) use ($num) {
            $fila = [
                'titulo'          => trim((string) ($c['titulo'] ?? '')) ?: null,
                'descripcion'     => (string) ($c['descripcion'] ?? ''),
                'cantidad'        => $c['cantidad'],
                'precio'          => $c['precio'],
                'orden'           => $i,
                'costos'          => null, 'utilidad' => null, 'utilidad_modo' => 'pct',
                'costo_proveedor' => null, 'gasolina' => null,   // costeo viejo: ya va dentro de "costos"
            ];
            // Solo los costos con monto; cada uno guarda su nombre y cómo se calcula (copia del componente del negocio)
            $costos = collect($c['costos'] ?? [])->filter(fn ($x) => $num($x['monto'] ?? null) !== null && filled($x['nombre'] ?? null))
                ->map(fn ($x) => [
                    'clave'  => \Illuminate\Support\Str::limit((string) ($x['clave'] ?? \Illuminate\Support\Str::slug($x['nombre'], '_')), 40, ''),
                    'nombre' => trim($x['nombre']),
                    'modo'   => ($x['modo'] ?? 'fijo') === 'unidad' ? 'unidad' : 'fijo',
                    'clase'  => ($x['clase'] ?? 'operativo') === 'tercero' ? 'tercero' : 'operativo',
                    'monto'  => round($num($x['monto']), 2),
                ])->values()->all();
            if ($costos) {
                $fila['costos'] = $costos;
                $fila['utilidad_modo'] = ($c['utilidad_modo'] ?? 'pct') === 'monto' ? 'monto' : 'pct';
                // Manda el precio que quedó (lo escribas tú o lo sugiera el costeo); la utilidad se calcula a partir de él
                $costo = collect($costos)->sum(fn ($x) => \App\Support\Costeo::importe($x, (float) $c['cantidad']));
                $ganancia = (float) $c['cantidad'] * (float) $c['precio'] - $costo;
                $fila['utilidad'] = $fila['utilidad_modo'] === 'monto' ? round($ganancia, 2) : ($costo > 0 ? round($ganancia / $costo * 100, 2) : null);
            }
            return $fila;
        })->all();

        $datos = collect($v)->except('conceptos')->all();
        // Para un prospecto: si ya se volvió cliente, la cotización queda a nombre de su cliente
        $datos['cliente_id'] = ($datos['cliente_id'] ?? null) ?: (! empty($datos['prospecto_id']) ? \App\Models\Prospecto::find($datos['prospecto_id'])?->cliente_id : null);
        $datos['prospecto_id'] = $datos['prospecto_id'] ?? null;
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
