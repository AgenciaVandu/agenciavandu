<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cotizacion; // <--- Importante importar el modelo

class CotizarController extends Controller
{
    public function store(Request $request)
    {
        // 1. Validación
        $data = $request->validate([
            'name'     => 'required|string|max:100',
            'lastname' => 'required|string|max:100',
            'phone'    => 'required',
            'email'    => 'required|email',
            'service'  => 'required',
        ]);

        // 2. GUARDAR EN LA BASE DE DATOS
        Cotizacion::create($data);

        // 3. Respuesta al usuario
        return back()->with('success', '¡Datos guardados correctamente! Te contactaremos pronto.');
    }
    // Esto servirá para mostrar la lista de mensajes
    public function index()
    {
        $cotizaciones = \App\Models\Cotizacion::latest()->get(); // Trae los más nuevos primero
        return view('admin.cotizaciones', compact('cotizaciones'));
    }
}
