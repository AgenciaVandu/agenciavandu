<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Cuentas;
use App\Support\Documentos\Constancia;
use Illuminate\Http\Request;

/** Leer documentos para llenar formularios (no guarda nada) */
class DocumentosController extends Controller
{
    /** Constancia de Situación Fiscal → RFC, razón social, régimen, C.P. y fecha */
    public function constancia(Request $request)
    {
        $request->validate(['archivo' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240'], [
            'archivo.mimes' => 'La constancia debe ser PDF o una foto (JPG o PNG).', 'archivo.max' => 'La constancia no puede pesar más de 10 MB.',
        ]);
        $f = $request->file('archivo');
        $r = Constancia::leer((string) file_get_contents($f->getRealPath()), (string) $f->getMimeType(), Cuentas::actual(), $request->user());
        return response()->json($r);
    }
}
