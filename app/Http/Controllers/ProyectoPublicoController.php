<?php

namespace App\Http\Controllers;

use App\Models\Proyecto;
use App\Models\ProyectoArchivo;
use App\Support\ArchivosProyecto;
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
        $p->load(['cliente', 'etapas.archivos' => fn ($q) => $q->where('visible', true), 'pagos']);

        if (! $request->boolean('vista_previa')) {
            $p->timestamps = false;
            $p->increment('vistas', 1, ['ultima_vista_at' => now()]);
        }

        $galeria = $p->archivos()->where('grupo', 'galeria')->where('visible', true)->get();

        return response()->view('proyectos.publico', compact('p', 'galeria'))->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function archivo(Request $request, string $token, ProyectoArchivo $archivo)
    {
        $p = $this->proyecto($token);
        abort_unless($archivo->proyecto_id === $p->id && $archivo->visible, 404);
        $version = in_array($request->query('v'), ['vista', 'miniatura']) ? $request->query('v') : 'original';

        return ArchivosProyecto::responder($archivo, $version, $request->boolean('descargar'));
    }

    public function zip(string $token)
    {
        return ArchivosProyecto::zipGaleria($this->proyecto($token));
    }
}
