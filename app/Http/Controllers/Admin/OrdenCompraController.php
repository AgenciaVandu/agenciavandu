<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrdenCompra;
use App\Models\OrdenCompraArchivo;
use App\Models\Proveedor;
use App\Models\Proyecto;
use App\Support\ArchivosOrden;
use App\Support\OrdenCompraPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrdenCompraController extends Controller
{
    public function index(Request $request)
    {
        $ver = $request->query('ver', 'abiertas');
        $q = trim((string) $request->query('q'));

        $ordenes = OrdenCompra::with(['proveedor', 'proyecto.cliente', 'conceptos', 'archivos:id,orden_compra_id,tipo'])
            ->when($ver === 'abiertas', fn ($w) => $w->abiertas())
            ->when(isset(OrdenCompra::ESTADOS[$ver]), fn ($w) => $w->where('estado', $ver))
            ->when($q, fn ($w) => $w->where(fn ($x) => $x->where('folio', 'like', "%$q%")->orWhere('titulo', 'like', "%$q%")
                ->orWhereHas('proveedor', fn ($p) => $p->where('empresa', 'like', "%$q%")->orWhere('rubro', 'like', "%$q%"))))
            ->latest('fecha')->latest('id')
            ->paginate(30)->withQueryString();

        $cuentas = OrdenCompra::selectRaw('estado, count(*) as n')->groupBy('estado')->pluck('n', 'estado');

        return view('admin.ordenes.index', compact('ordenes', 'ver', 'q', 'cuentas'));
    }

    public function create(Request $request)
    {
        $o = new OrdenCompra([
            'proveedor_id'   => $request->integer('proveedor') ?: null,
            'proyecto_id'    => $request->integer('proyecto') ?: null,
            'con_iva'        => true,
            'iva_porcentaje' => (float) config('vandu.iva_porcentaje', 16),
            'fecha'          => now(config('vandu.zona_horaria')),
        ]);
        if ($o->proyecto_id && ($pr = Proyecto::find($o->proyecto_id))) {
            $o->titulo = $pr->nombre;
        }
        return $this->formulario($o);
    }

    public function store(Request $request)
    {
        [$d, $conceptos] = $this->validar($request);
        $o = DB::transaction(function () use ($d, $conceptos, $request) {
            $o = OrdenCompra::create($d + ['creada_por' => $request->user()?->id, 'estado' => 'borrador']);
            $o->conceptos()->createMany($conceptos);
            return $o;
        });
        $n = $this->adjuntar($request, $o);
        return redirect()->route('admin.ordenes.show', $o)->with('ok', "Orden {$o->folio} guardada" . ($n ? " con $n " . ($n === 1 ? 'archivo' : 'archivos') : '') . '. Revísala y envíasela al proveedor.');
    }

    public function show(OrdenCompra $orden)
    {
        $orden->load(['proveedor', 'proyecto.cliente', 'conceptos', 'archivos.user', 'comentarios.user', 'autor']);
        return view('admin.ordenes.show', ['o' => $orden, 'version' => OrdenCompra::firma([$orden->id])]);
    }

    public function edit(OrdenCompra $orden)
    {
        return $this->formulario($orden->load(['conceptos', 'archivos']));
    }

    public function update(Request $request, OrdenCompra $orden)
    {
        [$d, $conceptos] = $this->validar($request);
        DB::transaction(function () use ($orden, $d, $conceptos) {
            $orden->update($d);
            $orden->conceptos()->delete();
            $orden->conceptos()->createMany($conceptos);
        });
        $this->adjuntar($request, $orden);
        return redirect()->route('admin.ordenes.show', $orden)->with('ok', 'Orden actualizada.' . ($orden->estado !== 'borrador' ? ' El proveedor ya ve los cambios en su enlace.' : ''));
    }

    public function destroy(OrdenCompra $orden)
    {
        $proveedor = $orden->proveedor;
        $folio = $orden->folio;
        $orden->delete();
        return redirect()->route('admin.proveedores.show', $proveedor)->with('ok', "Orden $folio eliminada.");
    }

    public function duplicar(OrdenCompra $orden)
    {
        $copia = DB::transaction(function () use ($orden) {
            $c = $orden->replicate(['folio', 'estado', 'enviada_at', 'vista_at']);
            $c->fecha = now(config('vandu.zona_horaria'));
            $c->estado = 'borrador';
            $c->creada_por = request()->user()?->id;
            $c->save();
            $c->conceptos()->createMany($orden->conceptos->map->only(['descripcion', 'cantidad', 'unidad', 'precio', 'orden'])->all());
            return $c;
        });
        return redirect()->route('admin.ordenes.edit', $copia)->with('ok', "Copia lista como {$copia->folio}. Ajusta lo que cambie.");
    }

    /** Cambia el estado a mano (en proceso, entregada, pagada, cancelada…) */
    public function estado(Request $request, OrdenCompra $orden)
    {
        $d = $request->validate(['estado' => 'required|in:' . implode(',', array_keys(OrdenCompra::ESTADOS))]);
        $cambios = ['estado' => $d['estado']];
        if ($d['estado'] !== 'borrador' && ! $orden->enviada_at) $cambios['enviada_at'] = now();
        $orden->update($cambios);
        return back()->with('ok', 'Estado: ' . OrdenCompra::ESTADOS[$d['estado']]['texto'] . '.');
    }

    /** Marca la orden como enviada y abre WhatsApp con el enlace (o solo la marca, si se copió el enlace) */
    public function enviar(Request $request, OrdenCompra $orden)
    {
        abort_if($orden->conceptos()->doesntExist(), 422, 'Agrega al menos un concepto antes de enviarla.');
        if ($orden->estado === 'borrador') {
            $orden->update(['estado' => 'enviada', 'enviada_at' => now()]);
        }
        $p = $orden->proveedor;
        if ($request->input('por') === 'whatsapp' && $p->whatsapp) {
            $texto = 'Hola ' . $p->primer_nombre . ', te comparto la orden de compra ' . $orden->folio . ' (' . $orden->titulo . ').'
                . ($orden->fecha_entrega ? ' Entrega: ' . $orden->fecha_entrega->locale('es')->isoFormat('dddd D [de] MMMM') . '.' : '')
                . "\n\nAquí puedes descargarla en PDF, subir tu factura y archivos, y dejarnos comentarios:\n" . $orden->url_proveedor;
            return redirect()->away('https://wa.me/' . $p->whatsapp . '?text=' . rawurlencode($texto));
        }
        return back()->with('ok', "Orden {$orden->folio} marcada como enviada.");
    }

    /** ¿Cambió algo? La orden abierta lo pregunta cada 30 s */
    public function version(OrdenCompra $orden)
    {
        return response()->json(['v' => OrdenCompra::firma([$orden->id])])->header('Cache-Control', 'no-store');
    }

    public function pdf(Request $request, OrdenCompra $orden)
    {
        return $request->boolean('descargar') ? OrdenCompraPdf::descargar($orden) : OrdenCompraPdf::ver($orden);
    }

    /** Diseños o referencias que le mandamos al proveedor (o algo que nos mandó por fuera) */
    public function subir(Request $request, OrdenCompra $orden)
    {
        $request->validate(ArchivosOrden::reglas(array_keys(OrdenCompra::TIPOS_ARCHIVO)), ArchivosOrden::mensajes());
        $n = count(ArchivosOrden::guardar($orden, $request, 'agencia', $request->user()?->id));
        return back()->with('ok', $n === 1 ? 'Archivo agregado.' : "$n archivos agregados.");
    }

    public function zip(OrdenCompra $orden)
    {
        return ArchivosOrden::zipAdjuntos($orden);
    }

    public function verArchivo(Request $request, OrdenCompra $orden, OrdenCompraArchivo $archivo)
    {
        abort_unless($archivo->orden_compra_id === $orden->id, 404);
        return ArchivosOrden::respuesta($archivo, $request->boolean('descargar'));
    }

    public function borrarArchivo(OrdenCompra $orden, OrdenCompraArchivo $archivo)
    {
        abort_unless($archivo->orden_compra_id === $orden->id, 404);
        $archivo->delete();
        return back()->with('ok', 'Archivo quitado.');
    }

    public function comentar(Request $request, OrdenCompra $orden)
    {
        $d = $request->validate(['texto' => 'required|string|max:3000'], ['texto.required' => 'Escribe tu comentario.']);
        $orden->comentarios()->create(['autor' => 'agencia', 'user_id' => $request->user()?->id, 'texto' => $d['texto']]);
        return redirect(route('admin.ordenes.show', $orden) . '#comentarios')->with('ok', 'Comentario enviado. El proveedor lo ve en su enlace.');
    }

    /** Archivos elegidos en el formulario de la orden (opcional) */
    private function adjuntar(Request $request, OrdenCompra $o): int
    {
        if (! $request->hasFile('archivos')) return 0;
        $request->validate(ArchivosOrden::reglas(OrdenCompra::TIPOS_AGENCIA), ArchivosOrden::mensajes());
        return count(ArchivosOrden::guardar($o, $request, 'agencia', $request->user()?->id));
    }

    private function formulario(OrdenCompra $o)
    {
        $proveedores = Proveedor::orderBy('empresa')->get(['id', 'empresa', 'nombre', 'rubro']);
        $proyectos = Proyecto::with('cliente:id,nombre,empresa')
            ->where(fn ($w) => $w->where('estado', '!=', 'terminado')->when($o->proyecto_id, fn ($x) => $x->orWhere('id', $o->proyecto_id)))
            ->latest('id')->get(['id', 'nombre', 'cliente_id', 'estado']);
        $conceptos = $o->exists
            ? $o->conceptos->map->only(['descripcion', 'cantidad', 'unidad', 'precio'])->values()->all()
            : [['descripcion' => '', 'cantidad' => 1, 'unidad' => 'pzas', 'precio' => null]];
        return view('admin.ordenes.form', compact('o', 'proveedores', 'proyectos', 'conceptos'));
    }

    /** @return array{0: array, 1: array} datos de la orden y sus conceptos */
    private function validar(Request $request): array
    {
        if ($request->hasFile('archivos')) {
            $request->validate(ArchivosOrden::reglas(OrdenCompra::TIPOS_AGENCIA), ArchivosOrden::mensajes());
        }
        $d = $request->validate([
            'proveedor_id'            => 'required|integer|exists:proveedores,id',
            'proyecto_id'             => 'nullable|integer|exists:proyectos,id',
            'titulo'                  => 'required|string|max:160',
            'indicaciones'            => 'nullable|string|max:6000',
            'fecha'                   => 'required|date',
            'fecha_entrega'           => 'nullable|date',
            'lugar_entrega'           => 'nullable|string|max:200',
            'con_iva'                 => 'nullable|boolean',
            'iva_porcentaje'          => 'nullable|numeric|min:0|max:100',
            'conceptos'               => 'required|array|min:1|max:60',
            'conceptos.*.descripcion' => 'required|string|max:500',
            'conceptos.*.cantidad'    => 'required|numeric|min:0|max:9999999',
            'conceptos.*.unidad'      => 'nullable|string|max:30',
            'conceptos.*.precio'      => 'nullable|numeric|min:0|max:99999999',
        ], [
            'proveedor_id.required'            => 'Elige el proveedor.',
            'titulo.required'                  => 'Ponle un nombre a la orden, p. ej. "Uniformes Hotel X".',
            'conceptos.required'               => 'Agrega al menos un concepto.',
            'conceptos.*.descripcion.required' => 'Cada concepto necesita una descripción.',
            'conceptos.*.cantidad.required'    => 'Falta la cantidad.',
        ]);
        // Los ids deben ser de esta cuenta (exists no ve el scope)
        abort_unless(Proveedor::whereKey($d['proveedor_id'])->exists(), 422);
        if (! empty($d['proyecto_id'])) abort_unless(Proyecto::whereKey($d['proyecto_id'])->exists(), 422);

        $conceptos = collect($d['conceptos'])->values()->map(fn ($c, $i) => [
            'descripcion' => trim($c['descripcion']),
            'cantidad'    => (float) $c['cantidad'],
            'unidad'      => filled($c['unidad'] ?? null) ? trim($c['unidad']) : null,
            'precio'      => (float) ($c['precio'] ?? 0),
            'orden'       => $i,
        ])->all();

        unset($d['conceptos']);
        $d['con_iva'] = $request->boolean('con_iva');
        $d['iva_porcentaje'] = (float) ($d['iva_porcentaje'] ?? config('vandu.iva_porcentaje', 16));
        return [$d, $conceptos];
    }
}
