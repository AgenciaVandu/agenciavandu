<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Proyecto;
use App\Models\ProyectoSolicitud;
use Illuminate\Http\Request;

/** Solicitudes de archivos al cliente (él los sube desde la página de su proyecto) */
class SolicitudesController extends Controller
{
    private function validar(Request $request): array
    {
        return $request->validate([
            'titulo'        => 'required|string|max:160',
            'instrucciones' => 'nullable|string|max:2000',
            'fecha_limite'  => 'nullable|date',
        ], ['titulo.required' => 'Escribe qué archivos necesitas.']);
    }

    private function volver(Proyecto $p, string $msg)
    {
        return redirect()->to(route('admin.proyectos.show', $p) . '#archivos-cliente')->with('ok', $msg);
    }

    private function delProyecto(Proyecto $p, ProyectoSolicitud $s): void
    {
        abort_unless($s->proyecto_id === $p->id, 404);
    }

    public function store(Request $request, Proyecto $proyecto)
    {
        $proyecto->solicitudes()->create($this->validar($request));
        return $this->volver($proyecto, 'Solicitud creada. Mándale el enlace del proyecto para que suba los archivos.');
    }

    public function update(Request $request, Proyecto $proyecto, ProyectoSolicitud $solicitud)
    {
        $this->delProyecto($proyecto, $solicitud);
        $solicitud->update($this->validar($request));
        return $this->volver($proyecto, 'Solicitud actualizada.');
    }

    public function estado(Proyecto $proyecto, ProyectoSolicitud $solicitud)
    {
        $this->delProyecto($proyecto, $solicitud);
        $solicitud->update(['estado' => $solicitud->abierta ? 'cerrada' : 'abierta']);
        return $this->volver($proyecto, $solicitud->abierta ? 'La solicitud está abierta otra vez.' : 'Listo, ya tienes esos archivos. El cliente ya no verá la solicitud.');
    }

    public function destroy(Proyecto $proyecto, ProyectoSolicitud $solicitud)
    {
        $this->delProyecto($proyecto, $solicitud);
        $solicitud->delete();   // los archivos recibidos se quedan en el proyecto
        return $this->volver($proyecto, 'Solicitud eliminada. Los archivos que ya envió se quedan en el proyecto.');
    }
}
