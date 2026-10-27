<?php

namespace App\Http\Controllers;

use App\Models\Proyecto;
use App\Models\ProyectoArchivo;
use App\Support\ArchivosProyecto;
use App\Support\SubidaCliente;
use Illuminate\Http\Request;

/** Vista del proyecto para el cliente: /proyecto/{token} */
class ProyectoPublicoController extends Controller
{
    private function proyecto(string $token): Proyecto
    {
        return Proyecto::where('token', $token)->firstOrFail();
    }

    public function show(Request $request, string $token)
    {
        $p = $this->proyecto($token);
        $p->load(['cliente', 'etapas.archivos' => fn ($q) => $q->where('visible', true), 'pagos', 'solicitudes.archivos:id,solicitud_id,nombre,peso,created_at']);
        $enviados = $p->archivos()->where('grupo', 'cliente')->whereNull('solicitud_id')->latest('id')->take(30)->get(['id', 'nombre', 'peso', 'created_at']);

        if (! $request->boolean('vista_previa')) {
            $this->avisarVisita($p, 'avance');
            $p->timestamps = false;
            $p->increment('vistas', 1, ['ultima_vista_at' => now()]);
        }

        $grupos = \App\Support\Galeria::agrupada($p, true);
        $galeria = $grupos->flatMap(fn ($g) => $g['archivos'])->values();

        return response()->view('proyectos.publico', compact('p', 'galeria', 'grupos', 'enviados'))->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /** El cliente manda la dirección de entrega o instalación (productos y servicios) */
    public function guardarEntrega(Request $request, string $token)
    {
        $p = $this->proyecto($token);
        abort_unless(\App\Support\Proyectos\Entrega::abierta($p), 410, 'Este proyecto ya no recibe cambios.');
        $d = $request->validate(\App\Support\Proyectos\Entrega::REGLAS, \App\Support\Proyectos\Entrega::MENSAJES);
        \App\Support\Proyectos\Entrega::guardar($p, $d, 'cliente');
        \App\Support\Push\Notificar::evento('proyecto_datos', "{$p->nombre}: {$d['nombre']} mandó la dirección de entrega", $d['direccion'], route('admin.proyectos.show', $p), 'proyecto-entrega-' . $p->id);
        return redirect()->to(route('proyecto.publico', $token) . '#entrega')->with('ok_entrega', '¡Listo! Recibimos tus datos de entrega.');
    }

    /** Página de entrega: solo los entregables, pensada para enviarse por correo */
    public function entrega(Request $request, string $token)
    {
        $p = $this->proyecto($token);
        $p->load(['cliente', 'presupuesto', 'etapas.archivos' => fn ($q) => $q->where('visible', true)]);

        if (! $request->boolean('vista_previa')) {
            $this->avisarVisita($p, 'entrega');
            $p->timestamps = false;
            $p->increment('vistas', 1, ['ultima_vista_at' => now()]);
        }

        $grupos = \App\Support\Galeria::agrupada($p, true);
        $galeria = $grupos->flatMap(fn ($g) => $g['archivos'])->values();
        $documentos = $p->etapas->flatMap->archivos->values();

        return response()->view('proyectos.entrega', compact('p', 'galeria', 'grupos', 'documentos'))->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function archivo(Request $request, string $token, ProyectoArchivo $archivo)
    {
        $p = $this->proyecto($token);
        abort_unless($archivo->proyecto_id === $p->id && $archivo->visible, 404);
        $version = in_array($request->query('v'), ['vista', 'miniatura', 'correo']) ? $request->query('v') : 'original';

        return ArchivosProyecto::responder($archivo, $version, $request->boolean('descargar'));
    }

    /** El cliente sube archivos por partes (ver App\Support\SubidaCliente) */
    public function subir(Request $request, string $token)
    {
        $p = $this->proyecto($token);
        if ($p->estado === 'cancelado') return response()->json(['message' => 'Este proyecto se canceló; ya no recibe archivos.'], 422);
        // Solo se reciben archivos cuando el equipo los pidió
        if (! $p->solicitudes()->where('estado', 'abierta')->exists()) return response()->json(['message' => 'Por ahora no te pedimos archivos para este proyecto.'], 422);
        $max = intdiv(SubidaCliente::PARTE, 1024) + 64;
        $d = $request->validate([
            'accion'    => 'required|in:iniciar,agregar,terminar',
            'sesion'    => 'nullable|required_unless:accion,iniciar|string|max:200',
            'offset'    => 'nullable|required_unless:accion,iniciar|integer|min:0',
            'nombre'    => 'required|string|max:200',
            'tamano'    => 'nullable|integer|min:0',
            'solicitud' => 'nullable|integer',
            'parte'     => 'required|file|max:' . $max,
        ], [
            'parte.uploaded' => 'El servidor no aceptó esta parte del archivo. Intenta de nuevo.',
            'parte.max'      => 'La parte es demasiado grande para el servidor.',
        ]);

        if (! SubidaCliente::permitido($d['nombre'])) {
            return response()->json(['message' => 'Por seguridad no aceptamos este tipo de archivo (programas o scripts). Si lo necesitas, comprímelo en ZIP.'], 422);
        }
        if (($d['tamano'] ?? 0) > SubidaCliente::maxBytes()) {
            return response()->json(['message' => 'El archivo pesa más de ' . round(SubidaCliente::maxBytes() / 1073741824, 1) . ' GB. Compártenos un enlace de descarga o divídelo.'], 413);
        }
        $s = ! empty($d['solicitud']) ? $p->solicitudes()->where('id', $d['solicitud'])->where('estado', 'abierta')->first() : null;
        $parte = file_get_contents($request->file('parte')->getRealPath());

        try {
            if ($d['accion'] === 'iniciar') {
                // Si el archivo cabe en una parte se guarda de una vez
                $r = SubidaCliente::iniciar($p, $parte, $request->boolean('unico') ? $d['nombre'] : null, $s);
                return response()->json(isset($r['archivo']) ? ['ok' => true, 'archivo' => $this->datos($r['archivo'])] : $r);
            }
            if ($d['accion'] === 'agregar') {
                SubidaCliente::agregar($p, $d['sesion'], (int) $d['offset'], $parte);
                return response()->json(['ok' => true]);
            }
            $a = SubidaCliente::terminar($p, $d['sesion'], (int) $d['offset'], $parte, $d['nombre'], $s);
        } catch (\App\Support\Dropbox\DropboxError $e) {
            report($e);
            return response()->json(['message' => 'No pudimos guardar el archivo. Intenta de nuevo en un momento.'], 502);
        }
        return response()->json(['ok' => true, 'archivo' => $this->datos($a)]);
    }

    private function datos(ProyectoArchivo $a): array
    {
        return ['id' => $a->id, 'nombre' => $a->nombre, 'peso' => $a->peso_texto];
    }

    public function zip(Request $request, string $token)
    {
        $p = $this->proyecto($token);
        $s = $request->filled('seccion') ? $p->secciones()->findOrFail($request->integer('seccion')) : null;
        return ArchivosProyecto::zipGaleria($p, true, $s);
    }

    private function avisarVisita(Proyecto $p, string $que): void
    {
        if (auth()->check() || ! \App\Support\Push\Notificar::visitaNueva($p->ultima_vista_at)) return;
        $quien = $p->cliente?->empresa ?: $p->cliente?->nombre ?: 'Tu cliente';
        \App\Support\Push\Notificar::evento('proyecto_visto',
            $que === 'entrega' ? "$quien abrió su entrega" : "$quien revisó el avance",
            $p->nombre . ($que === 'entrega' ? ' · ya está viendo sus archivos' : ' · ' . $p->progreso . '% de avance'),
            route('admin.proyectos.show', $p), 'proyecto-' . $p->id);
    }
}
