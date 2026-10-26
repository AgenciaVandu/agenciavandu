<?php

namespace App\Support\Licencias;

use App\Models\LicenciaPago;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/** Recibo en PDF de un pago de licencia (no es factura) */
class Recibo
{
    public static function pdf(LicenciaPago $p): Response
    {
        $p->loadMissing('licencia.plan', 'cuenta');
        if (! is_dir($dir = storage_path('fonts'))) @mkdir($dir, 0775, true);
        // Lo emite la plataforma: sus datos de contacto, no los del negocio que lo descarga
        $principal = \App\Models\Cuenta::find(\App\Support\Cuentas::principalId());
        $bytes = \App\Support\Cuentas::como($principal, fn () => Pdf::loadView('licencias.recibo', ['p' => $p, 'e' => config('vandu.emisor')])
            ->setPaper('a4')
            ->setOption(['chroot' => [resource_path(), public_path()], 'fontDir' => storage_path('fonts'), 'fontCache' => storage_path('fonts'),
                'defaultFont' => 'Geist', 'isRemoteEnabled' => false, 'isFontSubsettingEnabled' => true, 'dpi' => 96])
            ->output());
        return response($bytes, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="recibo-' . $p->folio . '.pdf"']);
    }
}
