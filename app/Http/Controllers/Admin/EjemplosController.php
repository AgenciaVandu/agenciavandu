<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Inicio\Arranque;

/** Cargar o borrar los datos de ejemplo del tipo de negocio, desde Inicio */
class EjemplosController extends Controller
{
    public function cargar()
    {
        $e = Arranque::ejemplos();
        abort_unless($e, 404);
        if (! ($e[1])()) ($e[2])();
        return redirect()->route('admin.resumen')->with('ok', 'Listo: cargamos ' . $e[0] . '. Pruébalo con confianza; los borras con un botón.');
    }

    public function quitar()
    {
        $e = Arranque::ejemplos();
        abort_unless($e, 404);
        ($e[3])();
        return redirect()->route('admin.resumen')->with('ok', 'Borramos los ' . $e[0] . '. Lo que tú capturaste sigue igual.');
    }
}
