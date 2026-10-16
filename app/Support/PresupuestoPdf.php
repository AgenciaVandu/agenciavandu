<?php

namespace App\Support;

use App\Models\Presupuesto;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class PresupuestoPdf
{
    public static function generar(Presupuesto $p): \Barryvdh\DomPDF\PDF
    {
        $p->loadMissing('conceptos');

        if (! is_dir($dir = storage_path('fonts'))) {
            @mkdir($dir, 0775, true);
        }

        $papel = DisenoDocumento::de()['papel'] === 'carta' ? 'letter' : 'a4';
        return Pdf::loadView('presupuestos.pdf', ['p' => $p])
            ->setPaper($papel)
            ->setOption([
                'chroot'               => [resource_path(), public_path(), \Illuminate\Support\Facades\Storage::disk('local')->path('membretes')],
                'fontDir'              => storage_path('fonts'),
                'fontCache'            => storage_path('fonts'),
                'defaultFont'          => 'Geist',
                'isRemoteEnabled'      => false,
                'isFontSubsettingEnabled' => true,
                'dpi'                  => 96,
            ]);
    }

    /** Nota de venta (sin factura) con los conceptos de la cotización */
    public static function nota(Presupuesto $p, bool $conIva, ?string $formaPago = null, ?string $texto = null): \Barryvdh\DomPDF\PDF
    {
        $p->loadMissing('conceptos');
        if (! is_dir($dir = storage_path('fonts'))) @mkdir($dir, 0775, true);

        return Pdf::loadView('presupuestos.nota', compact('p', 'conIva', 'formaPago', 'texto'))
            ->setPaper('a4')
            ->setOption([
                'chroot' => [resource_path(), public_path()], 'fontDir' => storage_path('fonts'), 'fontCache' => storage_path('fonts'),
                'defaultFont' => 'Geist', 'isRemoteEnabled' => false, 'isFontSubsettingEnabled' => true, 'dpi' => 96,
            ]);
    }

    public static function descargar(Presupuesto $p): Response
    {
        return static::generar($p)->download($p->nombre_archivo);
    }

    public static function ver(Presupuesto $p): Response
    {
        return static::generar($p)->stream($p->nombre_archivo);
    }
}
