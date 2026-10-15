<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\ClienteConstancia;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ClienteController extends Controller
{
    public const VISTAS = [
        'todos'         => 'Todos',
        'activos'       => 'Con proyecto en marcha',
        'por_cobrar'    => 'Con saldo por cobrar',
        'nuevos'        => 'Nuevos del sitio',
        'sin_actividad' => 'Sin actividad en 3 meses',
    ];

    public const ORDENES = ['reciente' => 'Actividad reciente', 'valor' => 'Mayor valor', 'cobrar' => 'Más por cobrar', 'nombre' => 'Nombre (A–Z)'];

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $ver = $request->query('ver') ?? 'todos';
        $ver = array_key_exists($ver, self::VISTAS) ? $ver : 'todos';
        $orden = array_key_exists($request->query('orden'), self::ORDENES) ? $request->query('orden') : 'reciente';
        $hace3m = now()->subMonths(3);

        // Lo que vale cada cliente (aceptado, antes de IVA), lo que debe y cuándo fue lo último
        $aceptado = 'SELECT COALESCE(SUM(pc.cantidad * pc.precio), 0) FROM presupuesto_conceptos pc JOIN presupuestos p ON p.id = pc.presupuesto_id WHERE p.cliente_id = clientes.id AND p.estado = \'aceptada\'';
        $porCobrar = 'SELECT COALESCE(SUM(pg.monto), 0) FROM proyecto_pagos pg JOIN proyectos pr ON pr.id = pg.proyecto_id WHERE pr.cliente_id = clientes.id AND pg.pagado_el IS NULL';
        $ultima = 'SELECT MAX(p.updated_at) FROM presupuestos p WHERE p.cliente_id = clientes.id';

        $vista = fn (string $v) => function ($w) use ($v, $hace3m) {
            match ($v) {
                'activos'       => $w->whereHas('proyectos', fn ($p) => $p->where('estado', 'activo')),
                'por_cobrar'    => $w->whereHas('proyectos.pagos', fn ($p) => $p->whereNull('pagado_el')),
                'nuevos'        => $w->where('nuevo', true),
                'sin_actividad' => $w->where('created_at', '<', $hace3m)->whereDoesntHave('presupuestos', fn ($p) => $p->where('updated_at', '>=', $hace3m)),
                default         => $w,
            };
        };

        $clientes = Cliente::query()
            ->select('clientes.*')
            ->selectRaw("($aceptado) as valor_aceptado")
            ->selectRaw("($porCobrar) as por_cobrar")
            ->selectRaw("COALESCE(($ultima), clientes.created_at) as ultima_actividad")
            ->withCount(['presupuestos', 'proyectos as activos_count' => fn ($w) => $w->where('estado', 'activo')])
            ->with(['presupuestos' => fn ($p) => $p->select('id', 'cliente_id', 'folio', 'estado', 'vigente_hasta', 'comprobante')->with('facturas:id,presupuesto_id,enviada_at')])
            ->when($q, fn ($query) => $query->where(fn ($w) => $w
                ->where('nombre', 'like', "%$q%")
                ->orWhere('empresa', 'like', "%$q%")
                ->orWhere('email', 'like', "%$q%")
                ->orWhere('telefono', 'like', "%$q%")
                ->orWhere('rfc', 'like', "%$q%")))
            ->where($vista($ver))
            ->orderByDesc('nuevo')
            ->when($orden === 'reciente', fn ($o) => $o->orderByDesc('ultima_actividad'))
            ->when($orden === 'valor', fn ($o) => $o->orderByDesc('valor_aceptado'))
            ->when($orden === 'cobrar', fn ($o) => $o->orderByDesc('por_cobrar'))
            ->orderByRaw('COALESCE(NULLIF(empresa, \'\'), nombre)')
            ->paginate(25)
            ->withQueryString();

        $conteos = collect(self::VISTAS)->map(fn ($t, $k) => Cliente::where($vista($k))->count())->all();
        $anio = now(config('vandu.zona_horaria'))->startOfYear()->toDateString();
        $resumen = [
            'total'     => $conteos['todos'],
            'nuevosMes' => Cliente::where('created_at', '>=', now(config('vandu.zona_horaria'))->startOfMonth())->count(),
            'activos'   => $conteos['activos'],
            'porCobrar' => (float) \App\Models\ProyectoPago::whereNull('pagado_el')->whereHas('proyecto')->sum('monto'),
            'vencido'   => \App\Models\ProyectoPago::whereNull('pagado_el')->whereHas('proyecto')->get()->filter->vencido->sum('monto'),
            'anio'      => \App\Models\Presupuesto::with('conceptos')->where('estado', 'aceptada')->where('aceptada_el', '>=', $anio)->get()->sum(fn ($p) => $p->subtotal),
        ];

        return view('admin.clientes.index', compact('clientes', 'q', 'ver', 'orden', 'conteos', 'resumen'));
    }

    /** Marcar un contacto nuevo como atendido (o volver a marcarlo como nuevo) */
    public function atendido(Request $request, Cliente $cliente)
    {
        $cliente->update(['nuevo' => $request->boolean('nuevo')]);

        return back()->with('ok', $cliente->nuevo ? 'Marcado como nuevo.' : (($cliente->empresa ?: $cliente->nombre) . ' ya no aparece como nuevo.'));
    }

    public function create(Request $request)
    {
        // Prellenado desde una notificación de "nuevo mensaje del sitio"
        $cliente = new Cliente(array_filter($request->only(['nombre', 'email', 'telefono', 'notas'])));
        return view('admin.clientes.form', ['cliente' => $cliente]);
    }

    public function store(Request $request)
    {
        $cliente = Cliente::create($this->validar($request));
        $this->guardarConstancia($request, $cliente);

        if ($request->boolean('y_cotizar')) {
            return redirect()->route('admin.presupuestos.create', ['cliente' => $cliente->id]);
        }

        return redirect()->route('admin.clientes.show', $cliente)->with('ok', 'Cliente creado.');
    }

    /** Secciones de la ficha del cliente, en orden de importancia */
    public const SECCIONES = [
        'resumen'      => ['Resumen', 'bi-grid'],
        'cotizaciones' => ['Cotizaciones', 'bi-file-earmark-text'],
        'proyectos'    => ['Proyectos', 'bi-kanban'],
        'facturacion'  => ['Facturación', 'bi-receipt'],
        'correos'      => ['Correos y mensajes', 'bi-envelope'],
    ];

    public function show(Request $request, Cliente $cliente)
    {
        $cliente->load(['razones', 'presupuestos.razon', 'presupuestos.conceptos', 'presupuestos.facturas', 'presupuestos.complementos.pago', 'presupuestos.proyecto.pagos', 'proyectos.etapas', 'proyectos.pagos', 'constancias']);
        $ver = array_key_exists($request->query('ver'), self::SECCIONES) ? $request->query('ver') : 'resumen';

        return view('admin.clientes.show', compact('cliente', 'ver'));
    }

    public function edit(Cliente $cliente)
    {
        return view('admin.clientes.form', compact('cliente'));
    }

    public function update(Request $request, Cliente $cliente)
    {
        $cliente->update($this->validar($request));
        $this->guardarConstancia($request, $cliente);

        return redirect()->route('admin.clientes.show', $cliente)->with('ok', 'Cliente actualizado.');
    }

    /* ---------------- Constancias de Situación Fiscal ---------------- */

    /** Subir una constancia desde la ficha del cliente */
    public function subirConstancia(Request $request, Cliente $cliente)
    {
        $request->validate($this->reglasConstancia(true), $this->mensajesConstancia());
        $this->guardarConstancia($request, $cliente);

        return back()->with('ok', 'Constancia guardada en el expediente.');
    }

    /** Ver (PDF en el navegador) o descargar con ?descargar=1 */
    public function verConstancia(Request $request, Cliente $cliente, ClienteConstancia $constancia)
    {
        abort_unless($constancia->cliente_id === $cliente->id, 404);
        if ($constancia->origen === 'dropbox') {
            return redirect()->away(\App\Support\Dropbox\Dropbox::cliente()->enlaceTemporal($constancia->dropbox_id));
        }
        $disco = Storage::disk('local');
        abort_unless($disco->exists($constancia->ruta), 404);

        $resp = response()->file($disco->path($constancia->ruta), ['Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex']);
        $resp->setContentDisposition($request->boolean('descargar') ? 'attachment' : 'inline', $constancia->nombre, Str::ascii($constancia->nombre));
        return $resp;
    }

    public function borrarConstancia(Cliente $cliente, ClienteConstancia $constancia)
    {
        abort_unless($constancia->cliente_id === $cliente->id, 404);
        $constancia->delete();

        return back()->with('ok', 'Constancia eliminada.');
    }

    private function guardarConstancia(Request $request, Cliente $cliente): void
    {
        $archivo = $request->file('constancia');
        if (! $archivo instanceof UploadedFile || ! $archivo->isValid()) {
            return;
        }
        $nombre = $archivo->getClientOriginalName() ?: 'constancia.pdf';
        $origen = 'local';
        $dropboxId = null;
        if (\App\Support\Dropbox\Dropbox::conectado()) {
            // En Dropbox: /Vandu/Clientes/<Cliente>/Constancias/
            $dbx = \App\Support\Dropbox\Dropbox::cliente();
            $carpeta = \App\Support\Dropbox\Dropbox::raiz() . '/Clientes/' . \App\Support\Dropbox\Dropbox::nombreSeguro($cliente->empresa ?: $cliente->nombre) . '/Constancias';
            try {
                $dbx->crearCarpeta($carpeta);
                $meta = $dbx->subirArchivo($archivo->getRealPath(), $carpeta . '/' . \App\Support\ArchivosProyecto::nombreArchivo($nombre));
            } catch (\App\Support\Dropbox\DropboxError $e) {
                throw \Illuminate\Validation\ValidationException::withMessages(['constancia' => 'No se pudo guardar en Dropbox: ' . $e->getMessage()]);
            }
            [$ruta, $origen, $dropboxId] = [$meta['path_display'], 'dropbox', $meta['id']];
        } else {
            $ruta = $archivo->storeAs("clientes/{$cliente->id}/constancias", Str::uuid() . '.' . strtolower($archivo->getClientOriginalExtension() ?: 'pdf'), 'local');
        }

        $cliente->constancias()->create([
            'nombre'     => $nombre,
            'ruta'       => $ruta,
            'origen'     => $origen,
            'dropbox_id' => $dropboxId,
            'mime'       => $archivo->getMimeType(),
            'peso'       => $archivo->getSize(),
            'emitida_el' => $request->input('constancia_emitida_el') ?: null,
        ]);
    }

    private function reglasConstancia(bool $requerida = false): array
    {
        return [
            'constancia'            => [$requerida ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'constancia_emitida_el' => 'nullable|date',
        ];
    }

    private function mensajesConstancia(): array
    {
        return [
            'constancia.required' => 'Elige el PDF de la constancia.',
            'constancia.mimes'    => 'La constancia debe ser PDF, JPG o PNG.',
            'constancia.max'      => 'La constancia no puede pesar más de 10 MB.',
        ];
    }

    public function destroy(Cliente $cliente)
    {
        $cliente->delete();

        return redirect()->route('admin.clientes.index')->with('ok', 'Cliente eliminado junto con sus cotizaciones.');
    }

    /* ---------------- Razones sociales ---------------- */

    public function guardarRazon(Request $request, Cliente $cliente)
    {
        $cliente->razones()->create($this->validarRazon($request) + ['orden' => (int) $cliente->razones()->max('orden') + 1]);
        return redirect()->route('admin.clientes.show', [$cliente, 'ver' => 'facturacion'])->with('ok', 'Razón social agregada.');
    }

    public function actualizarRazon(Request $request, Cliente $cliente, \App\Models\ClienteRazon $razon)
    {
        abort_unless($razon->cliente_id === $cliente->id, 404);
        $razon->update($this->validarRazon($request));
        return redirect()->route('admin.clientes.show', [$cliente, 'ver' => 'facturacion'])->with('ok', "Se guardó {$razon->nombre}.");
    }

    public function borrarRazon(Cliente $cliente, \App\Models\ClienteRazon $razon)
    {
        abort_unless($razon->cliente_id === $cliente->id, 404);
        // Sus cotizaciones pasan a la empresa principal
        $n = \App\Models\Presupuesto::where('razon_id', $razon->id)->update(['razon_id' => null]);
        $razon->delete();
        return redirect()->route('admin.clientes.show', [$cliente, 'ver' => 'facturacion'])
            ->with('ok', "Se quitó {$razon->nombre}." . ($n ? " Sus $n " . ($n === 1 ? 'cotización pasó' : 'cotizaciones pasaron') . ' a la empresa principal.' : ''));
    }

    private function validarRazon(Request $request): array
    {
        $request->merge([
            'rfc'       => $request->filled('rfc') ? Str::upper(preg_replace('/[\s-]/', '', $request->input('rfc'))) : null,
            'cp_fiscal' => $request->filled('cp_fiscal') ? preg_replace('/\D/', '', $request->input('cp_fiscal')) : null,
        ]);
        return $request->validate([
            'nombre'         => 'required|string|max:255',
            'razon_social'   => 'nullable|string|max:255',
            'rfc'            => ['nullable', 'string', 'regex:/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/u'],
            'regimen_fiscal' => ['nullable', Rule::in(array_keys(config('vandu.sat.regimenes')))],
            'cp_fiscal'      => 'nullable|digits:5',
            'uso_cfdi'       => ['nullable', Rule::in(array_keys(config('vandu.sat.usos_cfdi')))],
            'email_factura'  => 'nullable|email|max:255',
        ], [
            'nombre.required'  => 'Escribe cómo se llama esta empresa.',
            'rfc.regex'        => 'El RFC no tiene un formato válido (12 caracteres para empresa, 13 para persona física).',
            'cp_fiscal.digits' => 'El código postal fiscal debe tener 5 dígitos.',
        ]);
    }

    private function validar(Request $request): array
    {
        $request->merge([
            'rfc'       => $request->filled('rfc') ? Str::upper(preg_replace('/[\s-]/', '', $request->input('rfc'))) : null,
            'cp_fiscal' => $request->filled('cp_fiscal') ? preg_replace('/\D/', '', $request->input('cp_fiscal')) : null,
        ]);

        $datos = $request->validate([
            'nombre'       => 'required|string|max:255',
            'empresa'      => 'nullable|string|max:255',
            'email'        => 'nullable|email|max:255',
            'telefono'     => 'nullable|string|max:30',
            'rfc'            => ['nullable', 'string', 'regex:/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/u'],
            'razon_social'   => 'nullable|string|max:255',
            'regimen_fiscal' => ['nullable', Rule::in(array_keys(config('vandu.sat.regimenes')))],
            'cp_fiscal'      => 'nullable|digits:5',
            'uso_cfdi'       => ['nullable', Rule::in(array_keys(config('vandu.sat.usos_cfdi')))],
            'metodo_pago'    => ['nullable', Rule::in(array_keys(config('vandu.metodos_pago')))],
            'dias_credito'   => 'nullable|integer|min:1|max:365',
            'email_factura'  => 'nullable|email|max:255',
            'notas'          => 'nullable|string|max:5000',
        ] + $this->reglasConstancia(), [
            'rfc.regex'        => 'El RFC no tiene un formato válido (12 caracteres para empresa, 13 para persona física).',
            'cp_fiscal.digits' => 'El código postal fiscal debe tener 5 dígitos.',
        ] + $this->mensajesConstancia());

        return collect($datos)->except(['constancia', 'constancia_emitida_el'])->all();
    }
}
