<?php

namespace App\Support;

use App\Models\OrdenCompra;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/** PDF de la orden de compra, con el mismo encabezado que las cotizaciones */
class OrdenCompraPdf
{
    public static function generar(OrdenCompra $o): \Barryvdh\DomPDF\PDF
    {
        $o->loadMissing(['conceptos', 'proveedor', 'proyecto.cliente']);

        if (! is_dir($dir = storage_path('fonts'))) {
            @mkdir($dir, 0775, true);
        }

        return Pdf::loadView('ordenes.pdf', ['o' => $o])
            ->setPaper('a4')
            ->setOption([
                'chroot'                  => [resource_path(), public_path()],
                'fontDir'                 => storage_path('fonts'),
                'fontCache'               => storage_path('fonts'),
                'defaultFont'             => 'Geist',
                'isRemoteEnabled'         => false,
                'isFontSubsettingEnabled' => true,
                'dpi'                     => 96,
            ]);
    }

    public static function descargar(OrdenCompra $o): Response
    {
        return static::generar($o)->download($o->nombre_archivo);
    }

    public static function ver(OrdenCompra $o): Response
    {
        return static::generar($o)->stream($o->nombre_archivo);
    }
}
