<?php

namespace App\Http\Controllers;

use App\Models\OrdenCompra;
use App\Models\OrdenCompraArchivo;
use App\Models\Proveedor;
use App\Support\ArchivosOrden;
use App\Support\OrdenCompraPdf;
use App\Support\Push\Notificar;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * El espacio del proveedor: un solo enlace con todas sus órdenes de compra.
 * Ahí descarga cada orden en PDF, nos sube facturas y archivos, y deja comentarios. Sin usuario ni contraseña.
 */
class ProveedorPortalController extends Controller
{
    public function show(Request $request, string $token)
    {
        $p = $this->proveedor($token);
        $ordenes = $p->ordenes()->visibles()->with(['conceptos', 'archivos:id,orden_compra_id,tipo,autor', 'comentarios:id,orden_compra_id,autor,created_at'])->get();
        $ordenes->each->setRelation('proveedor', $p);
        $version = OrdenCompra::firma($ordenes->pluck('id'));

        if ($this->esVisita($request)) {
            $p->timestamps = false;
            $p->forceFill(['portal_visto_at' => now()])->save();
        }

        return $this->vista('proveedores.portal', compact('p', 'ordenes', 'version'));
    }

    public function orden(Request $request, string $token, int $orden)
    {
        $p = $this->proveedor($token);
        $o = $this->ordenDe($p, $orden)->load(['conceptos', 'archivos', 'comentarios', 'proyecto']);
        $o->setRelation('proveedor', $p);

        if ($this->esVisita($request)) {
            if (! $o->vista_at) {
                Notificar::evento('proveedor_actividad', "{$p->empresa} abrió la orden {$o->folio}", $o->titulo, route('admin.ordenes.show', $o), 'oc-vista-' . $o->id);
            }
            $o->timestamps = false;
            $o->forceFill(['vista_at' => now()])->save();
        }

        $version = OrdenCompra::firma([$o->id]);

        return $this->vista('proveedores.orden', compact('p', 'o', 'version'));
    }

    /** ¿Cambió algo? La página lo pregunta cada 30 s (no cuenta como visita ni manda avisos) */
    public function version(Request $request, string $token)
    {
        $p = $this->proveedor($token);
        $ids = $request->filled('orden')
            ? [$this->ordenDe($p, (int) $request->query('orden'))->id]
            : $p->ordenes()->visibles()->pluck('id');
        return response()->json(['v' => OrdenCompra::firma($ids)])->header('Cache-Control', 'no-store');
    }

    public function pdf(Request $request, string $token, int $orden)
    {
        $o = $this->ordenDe($this->proveedor($token), $orden);
        return $request->boolean('ver') ? OrdenCompraPdf::ver($o) : OrdenCompraPdf::descargar($o);
    }

    public function subir(Request $request, string $token, int $orden)
    {
        $p = $this->proveedor($token);
        $o = $this->ordenDe($p, $orden);
        abort_if($o->estado === 'cancelada', 422, 'Esta orden está cancelada.');

        $tipos = array_values(array_diff(array_keys(OrdenCompra::TIPOS_ARCHIVO), ['diseno']));
        $request->validate(ArchivosOrden::reglas($tipos), ArchivosOrden::mensajes());
        $subidos = ArchivosOrden::guardar($o, $request, 'proveedor');
        $n = count($subidos);

        $esFactura = $request->input('tipo') === 'factura';
        if ($esFactura && in_array($o->estado, ['enviada', 'en_proceso', 'entregada'], true)) {
            $o->update(['estado' => 'facturada']);
        }

        $que = $esFactura ? ($n === 1 ? 'su factura' : 'sus facturas') : ($n === 1 ? 'un archivo' : "$n archivos");
        Notificar::evento('proveedor_actividad', "{$p->empresa} subió $que", "{$o->folio} · " . collect($subidos)->pluck('nombre')->implode(', '), route('admin.ordenes.show', $o), 'oc-archivo-' . $o->id);

        return redirect(route('proveedor.orden', [$token, $o->id]) . ($esFactura ? '#facturas' : '#archivos'))
            ->with('ok', $esFactura ? '¡Gracias! Recibimos tu factura.' : ($n === 1 ? 'Archivo recibido.' : "Recibimos $n archivos."));
    }

    public function archivo(Request $request, string $token, int $orden, OrdenCompraArchivo $archivo)
    {
        $o = $this->ordenDe($this->proveedor($token), $orden);
        abort_unless($archivo->orden_compra_id === $o->id, 404);
        return ArchivosOrden::respuesta($archivo, $request->boolean('descargar'));
    }

    /** Quita algo que subió por error (solo lo suyo, y solo antes de que esté pagada) */
    public function quitar(string $token, int $orden, OrdenCompraArchivo $archivo)
    {
        $o = $this->ordenDe($this->proveedor($token), $orden);
        abort_unless($archivo->orden_compra_id === $o->id && $archivo->autor === 'proveedor', 404);
        abort_if($o->estado === 'pagada', 422, 'La orden ya está pagada; si necesitas cambiar algo, escríbenos en los comentarios.');
        $archivo->delete();
        return back()->with('ok', 'Archivo quitado.');
    }

    public function comentar(Request $request, string $token, int $orden)
    {
        $p = $this->proveedor($token);
        $o = $this->ordenDe($p, $orden);
        $d = $request->validate(['texto' => 'required|string|max:3000'], ['texto.required' => 'Escribe tu comentario.']);
        $o->comentarios()->create(['autor' => 'proveedor', 'texto' => $d['texto']]);
        Notificar::evento('proveedor_actividad', "{$p->empresa} comentó en {$o->folio}", Str::limit($d['texto'], 120), route('admin.ordenes.show', $o) . '#comentarios', 'oc-comentario-' . $o->id);
        return redirect(route('proveedor.orden', [$token, $o->id]) . '#comentarios')->with('ok', 'Comentario enviado.');
    }

    private function proveedor(string $token): Proveedor
    {
        return Proveedor::where('portal_token', $token)->firstOrFail();
    }

    private function ordenDe(Proveedor $p, int $id): OrdenCompra
    {
        return $p->ordenes()->visibles()->whereKey($id)->firstOrFail();
    }

    /** Visita del proveedor (no cuenta si es alguien del equipo revisando) */
    private function esVisita(Request $request): bool
    {
        return ! auth()->check() && ! $request->boolean('vista_previa');
    }

    private function vista(string $vista, array $datos)
    {
        return response()->view($vista, $datos)->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer');
    }
}
