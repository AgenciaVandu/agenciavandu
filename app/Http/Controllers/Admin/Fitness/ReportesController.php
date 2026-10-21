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
}
