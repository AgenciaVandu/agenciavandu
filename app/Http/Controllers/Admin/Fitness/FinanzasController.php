<?php

namespace App\Http\Controllers\Admin\Fitness;

use App\Http\Controllers\Admin\FinanzasController as FinanzasBase;
use App\Support\Fitness\Finanzas;
use Illuminate\Http\Request;

/** Finanzas de un gimnasio: lo vendido en paquetes, lo cobrado y lo que deben */
class FinanzasController extends FitnessController
{
    public function index(Request $request)
    {
        $periodo = array_key_exists($request->query('periodo'), FinanzasBase::PERIODOS) ? $request->query('periodo') : 'mes';
        [$desde, $hasta] = FinanzasBase::rangoDe($periodo);
        return view('admin.fitness.finanzas', Finanzas::datos($desde, $hasta) + ['periodo' => $periodo, 'desde' => $desde, 'hasta' => $hasta]);
    }

    /** Paquetes vendidos del periodo en CSV (Excel o Sheets) */
    public function exportar(Request $request)
    {
        $periodo = array_key_exists($request->query('periodo'), FinanzasBase::PERIODOS) ? $request->query('periodo') : 'todo';
        [$desde, $hasta] = FinanzasBase::rangoDe($periodo);
        $d = Finanzas::datos($desde, $hasta);
        $tz = config('vandu.zona_horaria');
        $nombre = 'paquetes-vendidos-' . ($desde ?: 'inicio') . '-a-' . ($hasta ?: now($tz)->toDateString()) . '.csv';
        return response()->streamDownload(function () use ($d, $tz) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Fecha de venta', 'Atleta', 'Paquete', 'Precio', 'Clases', 'Usadas', 'Inicia', 'Vence', 'Pagado el', 'Método']);
            foreach ($d['vendidas'] as $m) {
                fputcsv($out, [$m->created_at->copy()->setTimezone($tz)->toDateString(), $m->cliente?->nombre, $m->nombre, number_format($m->precio, 2, '.', ''),
                    $m->creditos ?? 'Ilimitado', $m->usados, $m->inicia_el->toDateString(), $m->vence_el->toDateString(), $m->pagado_el?->toDateString() ?? 'Pendiente',
                    $m->metodo_pago ? (\App\Models\Membresia::METODOS[$m->metodo_pago] ?? $m->metodo_pago) : '']);
            }
            fclose($out);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
