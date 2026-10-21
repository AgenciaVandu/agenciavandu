<?php

namespace App\Http\Controllers\Admin\Fitness;

use App\Support\Fitness\Ajustes;
use Illuminate\Http\Request;

/** Reglas del gimnasio: cancelaciones, ventana de entrada con QR y avisos */
class AjustesController extends FitnessController
{
    public function show()
    {
        return view('admin.fitness.ajustes', ['a' => Ajustes::todas()]);
    }

    public function cargarEjemplos()
    {
        \App\Support\Fitness\Ejemplo::cargar();
        return redirect()->route('admin.resumen')->with('ok', 'Cargamos un gimnasio de ejemplo: tipos de clase HYROX, paquetes, horario de la semana, atletas y 8 semanas de historial. Quítalo cuando captures lo tuyo.');
    }

    /** Semanas pasadas de clases de ejemplo para ver Horarios y Reportes (demos y pruebas) */
    public function historialEjemplo()
    {
        $n = \App\Support\Fitness\Ejemplo::historial();
        return redirect()->route('admin.fitness-reportes.horarios')->with('ok', $n
            ? "Cargamos 8 semanas de clases de ejemplo ($n clases con sus reservaciones). Se quitan con «Quitar ejemplos»."
            : 'El historial de ejemplo ya estaba cargado.');
    }

    public function quitarEjemplos()
    {
        \App\Support\Fitness\Ejemplo::quitar();
        return redirect()->route('admin.resumen')->with('ok', 'Se quitaron los datos de ejemplo.');
    }

    public function update(Request $request)
    {
        $d = $request->validate([
            'horas_cancelar'  => 'required|integer|min:0|max:168',
            'reservar_hasta'  => 'required|integer|min:0|max:1440',
            'dias_adelante'   => 'required|integer|min:1|max:90',
            'checkin_antes'   => 'required|integer|min:0|max:240',
            'checkin_despues' => 'required|integer|min:0|max:240',
            'aviso_clase'     => 'required|integer|min:0|max:1440',
            'aviso_vence'     => 'required|integer|min:0|max:30',
            'max_por_dia'     => 'required|integer|min:0|max:10',
        ]);
        Ajustes::guardar(array_map('intval', $d) + ['atleta_reserva' => $request->boolean('atleta_reserva')]);
        return back()->with('ok', 'Reglas del gimnasio guardadas.');
    }
}
