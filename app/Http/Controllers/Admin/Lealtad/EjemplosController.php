<?php

namespace App\Http\Controllers\Admin\Lealtad;

use App\Support\Lealtad\Ejemplo;

/** Restaurante de ejemplo: campañas, comensales con visitas y canjes para probar o enseñar el programa */
class EjemplosController extends LealtadController
{
    public function cargar()
    {
        Ejemplo::cargar();
        return redirect()->route('admin.resumen')->with('ok', 'Cargamos un programa de ejemplo: dos campañas con premios y comensales con visitas de las últimas semanas. Quítalo cuando arranques el tuyo.');
    }

    public function quitar()
    {
        Ejemplo::quitar();
        return redirect()->route('admin.resumen')->with('ok', 'Se quitaron los datos de ejemplo.');
    }
}
