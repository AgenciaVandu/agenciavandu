<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Buscador;
use Illuminate\Http\Request;

/** Buscador de todo el panel: la ventana rápida pide JSON; "Ver todos" abre la página completa */
class BuscarController extends Controller
{
    public function index(Request $request)
    {
        $q = mb_substr(trim((string) $request->query('q')), 0, 80);
        $completa = ! $request->wantsJson();
        $grupos = Buscador::buscar($q, $request->user(), $completa ? 30 : 5);

        if (! $completa) {
            return response()->json(['q' => $q, 'grupos' => $grupos])->header('Cache-Control', 'no-store');
        }
        return view('admin.buscar', compact('q', 'grupos'));
    }
}
