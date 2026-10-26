<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Licencias\Numeros;

/** Plataforma > Números: la plataforma vista como negocio (ingreso recurrente, cobrado, vencidos y lo que viene) */
class NerbyNumerosController extends Controller
{
    public function index()
    {
        return view('admin.plataforma.numeros', ['n' => Numeros::datos()]);
    }
}
