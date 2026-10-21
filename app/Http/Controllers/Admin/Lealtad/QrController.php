<?php

namespace App\Http\Controllers\Admin\Lealtad;

use App\Models\LealtadCampana;
use App\Support\Cuentas;

/** Cartel para imprimir: el QR que lleva a la puerta del programa del restaurante (mesas, caja, tickets) */
class QrController extends LealtadController
{
    public function show()
    {
        $c = Cuentas::actual();
        return view('admin.lealtad.qr', [
            'cuenta' => $c,
            'url' => route('lealtad.restaurante', $c->slug),
            'campana' => LealtadCampana::vigentes()->with('premios')->orderBy('termina_el')->first(),
        ]);
    }
}
