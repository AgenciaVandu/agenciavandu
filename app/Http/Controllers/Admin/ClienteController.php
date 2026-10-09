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
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));

        $clientes = Cliente::query()
            ->withCount(['presupuestos', 'presupuestos as vigentes_count' => fn ($w) => $w->where('vigente_hasta', '>', now())])
            ->withMax('presupuestos', 'fecha')
            ->when($q, fn ($query) => $query->where(fn ($w) => $w
                ->where('nombre', 'like', "%$q%")
                ->orWhere('empresa', 'like', "%$q%")
                ->orWhere('email', 'like', "%$q%")
                ->orWhere('telefono', 'like', "%$q%")))
            ->orderBy('nombre')
            ->paginate(25)
            ->withQueryString();

        return view('admin.clientes.index', compact('clientes', 'q'));
    }

    public function create()
    {
        return view('admin.clientes.form', ['cliente' => new Cliente()]);
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

    public function show(Cliente $cliente)
    {
        $cliente->load(['presupuestos.conceptos', 'proyectos.etapas', 'proyectos.pagos', 'constancias']);

        return view('admin.clientes.show', compact('cliente'));
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
