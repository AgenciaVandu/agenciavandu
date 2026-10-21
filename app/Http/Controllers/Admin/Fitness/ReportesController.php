<?php

namespace App\Http\Controllers\Admin\Fitness;

use App\Http\Controllers\Admin\FinanzasController;
use App\Support\Fitness\Reportes;
use Illuminate\Http\Request;

/** Reportes del gimnasio: asistencia, ocupación, clases populares, cancelaciones y membresías por vencer */
class ReportesController extends FitnessController
{
    public function index(Request $request)
    {
        $periodo = array_key_exists($request->query('periodo'), FinanzasController::PERIODOS) ? $request->query('periodo') : 'mes';
        [$desde, $hasta] = FinanzasController::rangoDe($periodo);
        return view('admin.fitness.reportes', Reportes::datos($desde, $hasta) + ['periodo' => $periodo]);
    }

    /** Horarios: mapa de ocupación (día × hora) y hallazgos sobre qué abrir, mover o cuidar */
    public function horarios(Request $request)
    {
        $semanas = in_array((int) $request->query('semanas'), [4, 8, 12], true) ? (int) $request->query('semanas') : 8;
        $sucursales = \App\Models\Sucursal::orderBy('nombre')->get(['id', 'nombre']);
        $sucursal = $sucursales->count() > 1 ? ((int) $request->query('sucursal') ?: null) : null;
        return view('admin.fitness.horarios', \App\Support\Fitness\Horarios\Horarios::analizar($semanas, $sucursal)
            + ['semanas' => $semanas, 'sucursales' => $sucursales, 'sucursal' => $sucursal]);
    }
}
