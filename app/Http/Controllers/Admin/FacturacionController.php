<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Facturas\Timbrado;
use Illuminate\Http\Request;

/** Configuración › Facturación: conectar Facturapi para generar (timbrar) facturas desde Nerby */
class FacturacionController extends Controller
{
    public function index()
    {
        $a = Timbrado::ajustes();
        return view('admin.facturacion', ['activo' => Timbrado::activo(), 'a' => $a]);
    }

    public function guardar(Request $request)
    {
        $d = $request->validate([
            'key' => ['nullable', 'string', 'max:200', 'regex:/^sk_(live|test)_[A-Za-z0-9]+$/'],
            'clave_prod' => 'required|digits:8', 'clave_unidad' => 'required|string|max:3', 'clave_servicio' => 'required|string|max:3',
            'serie' => 'nullable|string|max:10|alpha_num', 'quitar' => 'nullable|boolean',
        ], ['key.regex' => 'La clave secreta de Facturapi empieza con sk_live_ o sk_test_.', 'clave_prod.digits' => 'La clave del SAT tiene 8 números.']);
        Timbrado::guardarAjustes($d);
        if (! empty($d['key'])) {
            [$ok, $msg] = Timbrado::probar();
            return back()->with($ok ? 'ok' : 'error', $msg);
        }
        return back()->with('ok', ! empty($d['quitar']) ? 'Desconectamos Facturapi.' : 'Ajustes de facturación guardados.');
    }
}
